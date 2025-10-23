import { defineStore } from 'pinia'
import { api } from '@/utils/api'
import { Notify } from 'quasar'
import { v4 as uuidv4 } from 'uuid'

export const useDraftStore = defineStore('draft', {
  state: () => ({
    drafts: [],
    localDrafts: [], // 本地离线草稿
    pendingQueue: [], // 待同步队列
    isLoading: false
  }),

  getters: {
    allDrafts: (state) => [...state.drafts, ...state.localDrafts],
    pendingCount: (state) => state.pendingQueue.length
  },

  actions: {
    async fetchDrafts() {
      this.isLoading = true
      try {
        const response = await api.get('/drafts')
        this.drafts = response.data.data
      } catch (error) {
        console.error('获取草稿列表失败:', error)
        // 离线时使用本地数据
        if (!navigator.onLine) {
          console.log('离线模式，使用本地草稿')
        }
      } finally {
        this.isLoading = false
      }
    },

    async saveDraft(draftData) {
      const draft = {
        ...draftData,
        uuid: draftData.uuid || uuidv4(),
        last_modified: new Date().toISOString()
      }

      try {
        if (navigator.onLine) {
          // 在线保存到服务器
          const response = await api.post('/drafts', draft)
          
          // 更新本地状态
          const existingIndex = this.drafts.findIndex(d => d.uuid === draft.uuid)
          if (existingIndex >= 0) {
            this.drafts[existingIndex] = response.data.draft
          } else {
            this.drafts.unshift(response.data.draft)
          }
          
          Notify.create({
            type: 'positive',
            message: '草稿保存成功',
            position: 'top'
          })
        } else {
          // 离线保存到本地
          this.saveLocalDraft(draft)
          
          Notify.create({
            type: 'info',
            message: '离线保存成功，将在联网后同步',
            position: 'top'
          })
        }
        
        return { success: true, draft }
      } catch (error) {
        // 在线保存失败，降级到离线保存
        this.saveLocalDraft(draft)
        
        Notify.create({
          type: 'warning',
          message: '网络异常，已保存到本地',
          position: 'top'
        })
        
        return { success: true, draft }
      }
    },

    saveLocalDraft(draft) {
      const existingIndex = this.localDrafts.findIndex(d => d.uuid === draft.uuid)
      if (existingIndex >= 0) {
        this.localDrafts[existingIndex] = draft
      } else {
        this.localDrafts.unshift(draft)
      }
    },

    async updateDraft(draftId, draftData) {
      const draft = {
        ...draftData,
        last_modified: new Date().toISOString()
      }

      try {
        if (navigator.onLine) {
          const response = await api.put(`/drafts/${draftId}`, draft)
          
          // 更新本地状态
          const index = this.drafts.findIndex(d => d.id === draftId)
          if (index >= 0) {
            this.drafts[index] = response.data.draft
          }
          
          Notify.create({
            type: 'positive',
            message: '草稿更新成功',
            position: 'top'
          })
        } else {
          // 离线更新本地草稿
          const localIndex = this.localDrafts.findIndex(d => d.id === draftId || d.uuid === draft.uuid)
          if (localIndex >= 0) {
            this.localDrafts[localIndex] = { ...this.localDrafts[localIndex], ...draft }
          }
          
          Notify.create({
            type: 'info',
            message: '离线更新成功，将在联网后同步',
            position: 'top'
          })
        }
        
        return { success: true }
      } catch (error) {
        const message = error.response?.data?.message || '更新草稿失败'
        Notify.create({
          type: 'negative',
          message,
          position: 'top'
        })
        return { success: false, message }
      }
    },

    async deleteDraft(draftId) {
      try {
        if (navigator.onLine) {
          await api.delete(`/drafts/${draftId}`)
          
          // 从本地列表移除
          this.drafts = this.drafts.filter(d => d.id !== draftId)
        } else {
          // 离线删除本地草稿
          this.localDrafts = this.localDrafts.filter(d => d.id !== draftId)
        }
        
        Notify.create({
          type: 'positive',
          message: '草稿删除成功',
          position: 'top'
        })
        
        return { success: true }
      } catch (error) {
        const message = error.response?.data?.message || '删除草稿失败'
        Notify.create({
          type: 'negative',
          message,
          position: 'top'
        })
        return { success: false, message }
      }
    },

    async submitDraft(draftId) {
      try {
        const response = await api.post(`/drafts/${draftId}/submit`)
        
        // 从草稿列表移除
        this.drafts = this.drafts.filter(d => d.id !== draftId)
        
        Notify.create({
          type: 'positive',
          message: '草稿提交成功',
          position: 'top'
        })
        
        return { success: true, transaction: response.data.transaction }
      } catch (error) {
        const message = error.response?.data?.message || '提交草稿失败'
        Notify.create({
          type: 'negative',
          message,
          position: 'top'
        })
        return { success: false, message }
      }
    },

    async syncDrafts() {
      if (!navigator.onLine || this.localDrafts.length === 0) return
      
      try {
        const normalizedDrafts = this.localDrafts.map(d => {
          const normalizedType = d.type === 'instant-buyout' ? 'exchange' : d.type
          return {
            uuid: d.uuid,
            type: normalizedType,
            rmb_amount: d.rmb_amount != null ? Number(d.rmb_amount) : null,
            hkd_amount: d.hkd_amount != null ? Number(d.hkd_amount) : null,
            exchange_rate: d.exchange_rate != null ? Number(d.exchange_rate) : null,
            instant_rate: d.instant_rate != null ? Number(d.instant_rate) : null,
            channel_id: d.channel_id ?? null,
            location_id: d.location_id ?? null,
            location: d.location ?? null,
            notes: d.notes ?? '',
            last_modified: d.last_modified || new Date().toISOString()
          }
        })
        const response = await api.post('/drafts/batch-sync', {
          drafts: normalizedDrafts
        })
        
        // 清空本地草稿
        this.localDrafts = []
        
        // 重新获取服务器草稿
        await this.fetchDrafts()
        
        Notify.create({
          type: 'positive',
          message: '草稿同步完成',
          position: 'top'
        })
        
        return { success: true }
      } catch (error) {
        console.error('草稿同步失败:', error)
        return { success: false }
      }
    },

    // 添加到待提交队列
    addToPendingQueue(transaction) {
      const existingIndex = this.pendingQueue.findIndex(t => t.uuid === transaction.uuid)
      if (existingIndex >= 0) {
        this.pendingQueue[existingIndex] = transaction
      } else {
        this.pendingQueue.push(transaction)
      }
    },

    // 批量同步待提交队列
    async syncPendingQueue() {
      if (!navigator.onLine || this.pendingQueue.length === 0) return
      
      const transactionStore = useTransactionStore()
      const result = await transactionStore.batchSubmitTransactions(this.pendingQueue)
      
      if (result.success) {
        // 清空成功提交的记录
        const successUuids = result.results
          .filter(r => r.status === 'success')
          .map(r => r.uuid)
        
        this.pendingQueue = this.pendingQueue.filter(t => !successUuids.includes(t.uuid))
      }
      
      return result
    }
  },

  persist: {
    key: 'draft',
    storage: localStorage,
    paths: ['localDrafts', 'pendingQueue']
  }
})
