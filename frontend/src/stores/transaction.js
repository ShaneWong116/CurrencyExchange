import { defineStore } from 'pinia'
import { api } from '@/utils/api'
import { Notify } from 'quasar'
import { v4 as uuidv4 } from 'uuid'

export const useTransactionStore = defineStore('transaction', {
  state: () => ({
    transactions: [],
    channels: [],
    locations: [],
    isLoading: false,
    pagination: {
      page: 1,
      rowsPerPage: 15,
      rowsNumber: 0
    }
  }),

  getters: {
    activeChannels: (state) => Array.isArray(state.channels) ? state.channels.filter(channel => channel.status === 'active') : [],
    todayTransactions: (state) => {
      const today = new Date().toISOString().split('T')[0]
      return Array.isArray(state.transactions) ? state.transactions.filter(t => t.created_at && t.created_at.startsWith(today)) : []
    },
    // 未结余的交易数量（使用分页返回的 total）
    unsettledCount: (state) => {
      return state.pagination.rowsNumber || 0
    }
  },

  actions: {
    async fetchChannels() {
      try {
        const response = await api.get('/channels')
        this.channels = Array.isArray(response.data) ? response.data : []
      } catch (error) {
        console.error('获取渠道列表失败:', error)
        this.channels = []
      }
    },

    async fetchLocations() {
      try {
        const response = await api.get('/locations')
        this.locations = Array.isArray(response.data) ? response.data : []
      } catch (error) {
        console.error('获取地点列表失败:', error)
        this.locations = []
      }
    },

    async fetchTransactions(params = {}) {
      this.isLoading = true
      try {
        const response = await api.get('/transactions', { params })
        this.transactions = Array.isArray(response.data.data) ? response.data.data : []
        this.pagination.rowsNumber = response.data.total || 0
        this.pagination.page = response.data.current_page || 1
      } catch (error) {
        console.error('获取交易记录失败:', error)
        this.transactions = []
      } finally {
        this.isLoading = false
      }
    },

    async createTransaction(transactionData) {
      try {
        const response = await api.post('/transactions', transactionData)
        
        // 添加到本地列表
        this.transactions.unshift(response.data.transaction)
        
        Notify.create({
          type: 'positive',
          message: '交易记录创建成功',
          position: 'top'
        })
        
        return { success: true, data: response.data.transaction }
      } catch (error) {
        const message = error.response?.data?.message || '创建交易记录失败'
        Notify.create({
          type: 'negative',
          message,
          position: 'top'
        })
        return { success: false, message }
      }
    },

    async batchSubmitTransactions(transactions) {
      try {
        const response = await api.post('/transactions/batch', { transactions })
        
        Notify.create({
          type: 'positive',
          message: `批量同步完成，成功 ${response.data.results.filter(r => r.status === 'success').length} 条`,
          position: 'top'
        })
        
        return { success: true, results: response.data.results }
      } catch (error) {
        const message = error.response?.data?.message || '批量同步失败'
        Notify.create({
          type: 'negative',
          message,
          position: 'top'
        })
        return { success: false, message }
      }
    },

    async submitInstantBuyout(data) {
      try {
        // 即时买断只创建一条 instant_buyout 类型的交易
        const instantBuyoutRecord = {
          type: 'instant_buyout',
          rmb_amount: data.rmb_amount,
          hkd_amount: data.hkd_amount,
          exchange_rate: data.exchange_rate,
          instant_rate: data.instant_rate,
          channel_id: data.channel_id,
          notes: data.notes,
          uuid: uuidv4()
        }

        const response = await api.post('/transactions', instantBuyoutRecord)
        
        // 添加到本地列表
        this.transactions.unshift(response.data.transaction)

        return { success: true, data: response.data.transaction }
      } catch (error) {
        const message = error.response?.data?.message || '即时买断提交失败'
        return { success: false, message }
      }
    }
  },

  persist: {
    key: 'transaction',
    storage: localStorage,
    paths: ['channels']
  }
})
