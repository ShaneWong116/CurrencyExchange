<template>
  <q-page class="records-page safe-area-bottom">
    <header class="page-header q-mb-md">
      <div class="logo">兑换系统</div>
      <div class="user">{{ userName }} <a href="#" @click.prevent="logout">退出</a></div>
    </header>

    <section class="stats-card q-card q-pa-md q-mb-md">
      <div class="row q-col-gutter-md">
        <div class="col-3">总笔数：{{ todayStats.total_count }}</div>
        <div class="col-3">入账：${{ todayStats.total_income.toFixed(2) }}</div>
        <div class="col-3">出账：${{ todayStats.total_expense.toFixed(2) }}</div>
        <div class="col-3">净额：${{ todayStats.net_amount.toFixed(2) }}</div>
      </div>
      <div class="row q-col-gutter-lg q-mt-md">
        <div class="col">
          <h6>按货币 Top3</h6>
          <div v-for="item in currencyTop3" :key="item.currency">{{ item.currency }} ${{ Number(item.amount).toFixed(2) }}</div>
        </div>
        <div class="col">
          <h6>按渠道 Top3</h6>
          <div v-for="item in channelTop3" :key="item.channel">{{ item.channel }} ${{ Number(item.amount).toFixed(2) }}</div>
        </div>
      </div>
    </section>

    

    <div class="txn-list q-mb-xl">
      <q-list bordered separator>
        <q-item v-for="t in transactions" :key="t.id" class="q-py-md">
          <q-item-section>
            <div class="row items-center justify-between">
              <div class="text-body1">{{ typeLabel(t.type) }} · {{ t.channel?.name || t.channel }}</div>
              <q-badge v-if="t.transaction_label" color="orange" outline>{{ t.transaction_label }}</q-badge>
            </div>
            <div class="row q-col-gutter-sm q-mt-xs">
              <div class="col-auto">¥{{ formatAmount(t.rmb_amount) }}</div>
              <div class="col-auto">HK${{ formatAmount(t.hkd_amount) }}</div>
            </div>
            <div class="text-grey-7 q-mt-xs">{{ t.created_at }} 编号 {{ t.uuid || t.transaction_id }}</div>
            <div v-if="t.notes" class="q-mt-xs">备注：{{ t.notes }}</div>
            <div>状态：{{ statusLabel(t.status) }}</div>
          </q-item-section>
        </q-item>
        <q-inner-loading :showing="loading">
          <q-spinner color="primary" />
        </q-inner-loading>
        <div v-if="!loading && transactions.length === 0" class="q-pa-md text-grey-6">暂无记录</div>
        <q-infinite-scroll @load="loadMore" :offset="100" v-if="hasMore" />
      </q-list>
    </div>

    <BottomNavigation />
  </q-page>
</template>

<script>
import BottomNavigation from '@/components/BottomNavigation.vue'
import { api } from '@/utils/api'
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'RecordsPage',
  components: { BottomNavigation },
  data() {
    return {
      userName: '',
      todayStats: { total_count: 0, total_income: 0, total_expense: 0, net_amount: 0 },
      currencyTop3: [],
      channelTop3: [],
      transactions: [],
      page: 1,
      hasMore: true,
      loading: false,
    }
  },
  async created() {
    const auth = useAuthStore()
    this.userName = auth.user?.name || ''

    await this.fetchStats()
    await this.fetchTransactions()
  },
  methods: {
    typeLabel(type) {
      return type === 'income' ? '入账' : type === 'outcome' ? '出账' : '兑换'
    },
    statusLabel(status) {
      return status === 'pending' ? '处理中' : status === 'success' ? '成功' : status === 'failed' ? '失败' : status
    },
    formatAmount(v) {
      const n = Number(v || 0)
      return n.toFixed(2)
    },
    async fetchStats() {
      const res = await api.get('/transactions/statistics')
      if (res.data?.success) {
        this.todayStats = res.data.data.today_stats
        this.currencyTop3 = res.data.data.currency_top3
        this.channelTop3 = res.data.data.channel_top3
      }
    },
    async fetchTransactions(reset = true) {
      const params = { per_page: 20, page: this.page }
      this.loading = true
      const res = await api.get('/transactions', { params }).finally(() => (this.loading = false))
      const data = res.data?.data || res.data
      const list = (data?.data || data || [])
      this.hasMore = !!data?.next_page_url
      this.transactions = reset ? list : [...this.transactions, ...list]
    },
    async loadMore(index, done) {
      if (!this.hasMore) return done()
      this.page += 1
      await this.fetchTransactions(false)
      done()
    },
    logout() {
      const auth = useAuthStore()
      auth.logout()
      this.$router.push('/login')
    }
  }
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; }
.txn-list { padding-bottom: 72px; }
</style>
