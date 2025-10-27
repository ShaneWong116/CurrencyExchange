<template>
  <q-page class="records-page">
    <!-- Header -->
    <header class="page-header">
      <div class="header-content">
        <h5 class="header-title">交易记录</h5>
        <div class="header-user">
          <span class="user-name">{{ userName }}</span>
          <q-btn flat dense round icon="logout" size="sm" @click="logout" class="q-ml-xs" />
        </div>
      </div>
    </header>

    <!-- Summary Section -->
    <section class="summary-section">
      <div class="summary-card">
        <div class="summary-details">
          <div class="detail-row">
            <div class="detail-label">人民币</div>
            <div class="detail-value">¥{{ formatAmount(getCurrentCNY()) }}</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">港币</div>
            <div class="detail-value">HK${{ formatAmount(getCurrentHKD()) }}</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">交易数</div>
            <div class="detail-value">{{ getCurrentCount() }}笔</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Filter Tabs -->
    <section class="filter-section">
      <div class="filter-tabs">
        <div 
          class="tab-item" 
          :class="{ active: filterType === 'all' }"
          @click="filterType = 'all'"
        >
          全部
        </div>
        <div 
          class="tab-item" 
          :class="{ active: filterType === 'income' }"
          @click="filterType = 'income'"
        >
          入账
        </div>
        <div 
          class="tab-item" 
          :class="{ active: filterType === 'outcome' }"
          @click="filterType = 'outcome'"
        >
          出账
        </div>
        <div 
          class="tab-item" 
          :class="{ active: filterType === 'instant_buy' }"
          @click="filterType = 'instant_buy'"
        >
          即时买断
        </div>
      </div>
    </section>

    <!-- Transaction List -->
    <section class="transaction-section">
      <div class="section-header">
        <h6 class="section-title">交易明细</h6>
        <q-btn flat dense icon="refresh" size="sm" @click="refreshTransactions" :loading="loading" />
      </div>

      <div class="transaction-list">
        <div v-for="t in filteredTransactions" :key="t.id" class="transaction-item">
          <div class="transaction-icon" :class="getIconClass(t.type)">
            <q-icon :name="getTypeIcon(t.type)" size="20px" color="white" />
          </div>
          <div class="transaction-main">
            <div class="transaction-header-row">
              <div class="transaction-title-row">
                <span class="transaction-type">{{ typeLabel(t.type) }}</span>
                <span class="transaction-divider">－</span>
                <span class="transaction-channel">{{ t.channel?.name || t.channel }}</span>
              </div>
              <q-badge 
                :color="getStatusColor(t.status)" 
                :label="statusLabel(t.status)"
                class="status-badge-small"
              />
            </div>
            
            <div class="transaction-amounts-row">
              <span class="amount-cny-highlight">¥{{ formatAmount(t.rmb_amount) }}</span>
              <q-icon name="arrow_forward" size="14px" class="arrow-icon" />
              <span class="amount-hkd-highlight">HK${{ formatAmount(t.hkd_amount) }}</span>
            </div>
            
            <div class="transaction-footer-row">
              <span class="transaction-time">{{ formatFullTime(t.created_at) }}</span>
              <span class="transaction-id">编号 {{ (t.uuid || t.transaction_id).substring(0, 8) }}</span>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading && transactions.length === 0" class="loading-container">
          <q-spinner color="primary" size="40px" />
          <div class="loading-text">加载中...</div>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && transactions.length === 0" class="empty-state">
          <q-icon name="receipt_long" size="64px" color="grey-4" />
          <div class="empty-text">暂无交易记录</div>
        </div>

        <!-- Infinite Scroll -->
        <q-infinite-scroll 
          v-if="hasMore && transactions.length > 0" 
          @load="loadMore" 
          :offset="100"
        >
          <template v-slot:loading>
            <div class="loading-more">
              <q-spinner color="primary" size="20px" />
              <span class="loading-more-text">加载更多...</span>
            </div>
          </template>
        </q-infinite-scroll>
      </div>
    </section>

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
      stats: {
        income: { cny: 0, hkd: 0, count: 0 },
        outcome: { cny: 0, hkd: 0, count: 0 },
        instant: { cny: 0, hkd: 0, count: 0 }
      },
      transactions: [],
      page: 1,
      hasMore: true,
      loading: false,
      filterType: 'all',
      accountBalance: 0  // 账户人民币结余
    }
  },
  computed: {
    filteredTransactions() {
      if (this.filterType === 'all') {
        return this.transactions
      }
      return this.transactions.filter(t => t.type === this.filterType)
    }
  },
  async created() {
    const auth = useAuthStore()
    this.userName = auth.user?.name || ''

    await this.fetchStats()
    await this.fetchTransactions()
  },
  methods: {
    getCurrentCNY() {
      if (this.filterType === 'all') {
        return this.stats.income.cny + this.stats.outcome.cny + this.stats.instant.cny
      }
      const key = this.filterType === 'instant_buy' ? 'instant' : this.filterType
      return this.stats[key]?.cny || 0
    },
    getCurrentHKD() {
      if (this.filterType === 'all') {
        return this.stats.income.hkd + this.stats.outcome.hkd + this.stats.instant.hkd
      }
      const key = this.filterType === 'instant_buy' ? 'instant' : this.filterType
      return this.stats[key]?.hkd || 0
    },
    getCurrentCount() {
      if (this.filterType === 'all') {
        return this.stats.income.count + this.stats.outcome.count + this.stats.instant.count
      }
      const key = this.filterType === 'instant_buy' ? 'instant' : this.filterType
      return this.stats[key]?.count || 0
    },
    typeLabel(type) {
      const labels = {
        'income': '入账',
        'outcome': '出账',
        'instant_buy': '即时买断',
        'exchange': '兑换'
      }
      return labels[type] || type
    },
    statusLabel(status) {
      const labels = {
        'pending': '处理中',
        'success': '成功',
        'completed': '成功',
        'failed': '失败',
        'cancelled': '已取消'
      }
      return labels[status] || status
    },
    formatAmount(v) {
      const n = Number(v || 0)
      return n.toFixed(2)
    },
    formatTime(datetime) {
      if (!datetime) return ''
      // Format: 2025-09-18 11:16:27 -> 09-18 11:16
      const match = datetime.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/)
      if (match) {
        return `${match[2]}-${match[3]} ${match[4]}:${match[5]}`
      }
      return datetime
    },
    formatFullTime(datetime) {
      if (!datetime) return ''
      // Format: 2025-10-23T05:30:18.000000Z -> 2025-10-23 05:30:18
      // Or: 2025-10-23 05:30:18 -> 2025-10-23 05:30:18
      const isoMatch = datetime.match(/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/)
      if (isoMatch) {
        return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]} ${isoMatch[4]}:${isoMatch[5]}:${isoMatch[6]}`
      }
      const normalMatch = datetime.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/)
      if (normalMatch) {
        return datetime.substring(0, 19)
      }
      return datetime
    },
    getTypeColor(type) {
      const colors = {
        'income': 'green',
        'outcome': 'orange',
        'instant_buy': 'purple',
        'exchange': 'blue'
      }
      return colors[type] || 'grey'
    },
    getStatusColor(status) {
      const colors = {
        'pending': 'warning',
        'success': 'positive',
        'completed': 'positive',
        'failed': 'negative',
        'cancelled': 'grey'
      }
      return colors[status] || 'grey'
    },
    async fetchStats() {
      try {
        const res = await api.get('/transactions/statistics')
        
        if (res.data?.success) {
          const data = res.data.data
          
          // 初始化统计数据
          this.stats = {
            income: { cny: 0, hkd: 0, count: 0 },
            outcome: { cny: 0, hkd: 0, count: 0 },
            instant: { cny: 0, hkd: 0, count: 0 }
          }

          // 处理 by_type 数据
          if (data.by_type) {
            Object.keys(data.by_type).forEach(type => {
              const stat = data.by_type[type]
              if (type === 'income') {
                this.stats.income = {
                  cny: Number(stat.rmb_amount) || 0,
                  hkd: Number(stat.hkd_amount) || 0,
                  count: Number(stat.count) || 0
                }
              } else if (type === 'outcome') {
                this.stats.outcome = {
                  cny: Number(stat.rmb_amount) || 0,
                  hkd: Number(stat.hkd_amount) || 0,
                  count: Number(stat.count) || 0
                }
              } else if (type === 'instant_buy') {
                this.stats.instant = {
                  cny: Number(stat.rmb_amount) || 0,
                  hkd: Number(stat.hkd_amount) || 0,
                  count: Number(stat.count) || 0
                }
              }
            })
          }
        }
      } catch (error) {
        console.error('Failed to fetch statistics:', error)
        this.$q.notify({
          type: 'negative',
          message: '获取统计数据失败'
        })
      }
    },
    async fetchTransactions(reset = true) {
      const params = { 
        per_page: 20, 
        page: this.page,
        settlement_status: 'unsettled' // 只查询未结余的记录
      }
      this.loading = true
      try {
        const res = await api.get('/transactions', { params })
        const data = res.data?.data || res.data
        const list = (data?.data || data || [])
        this.hasMore = !!data?.next_page_url
        this.transactions = reset ? list : [...this.transactions, ...list]
      } catch (error) {
        console.error('Failed to fetch transactions:', error)
        this.$q.notify({
          type: 'negative',
          message: '获取交易记录失败'
        })
      } finally {
        this.loading = false
      }
    },
    async refreshTransactions() {
      this.page = 1
      this.hasMore = true
      await Promise.all([
        this.fetchStats(),
        this.fetchTransactions(true)
      ])
      this.$q.notify({
        type: 'positive',
        message: '刷新成功',
        timeout: 1000
      })
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
    },
    getTypeIcon(type) {
      const icons = {
        'income': 'account_balance_wallet',
        'outcome': 'account_balance_wallet',
        'instant_buy': 'swap_horiz',
        'exchange': 'compare_arrows'
      }
      return icons[type] || 'account_balance_wallet'
    },
    getTypeColorIcon(type) {
      const colors = {
        'income': 'positive',
        'outcome': 'negative',
        'instant_buy': 'purple',
        'exchange': 'blue'
      }
      return colors[type] || 'grey'
    },
    getAmountClass(type) {
      return type === 'income' ? 'amount-positive' : 'amount-negative'
    },
    getAmountSign(type) {
      return type === 'income' ? '+' : '-'
    },
    getIconClass(type) {
      const classes = {
        'income': 'icon-income',
        'outcome': 'icon-outcome',
        'instant_buy': 'icon-instant',
        'exchange': 'icon-exchange'
      }
      return classes[type] || 'icon-default'
    }
  }
}
</script>

<style scoped>
/* Page Layout */
.records-page {
  background: #f5f5f5;
  min-height: 100vh;
  padding-bottom: 80px;
}

/* Header */
.page-header {
  background: linear-gradient(135deg, #1976D2 0%, #1565C0 50%, #0D47A1 100%);
  padding: 16px 16px 24px;
  box-shadow: 0 4px 20px rgba(25, 118, 210, 0.3);
  position: relative;
  overflow: hidden;
}

.page-header::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -20%;
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  border-radius: 50%;
}

.page-header::after {
  content: '';
  position: absolute;
  bottom: -30%;
  left: -10%;
  width: 150px;
  height: 150px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
  border-radius: 50%;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 100%;
  position: relative;
  z-index: 1;
}

.header-title {
  color: white;
  font-size: 20px;
  font-weight: 700;
  margin: 0;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  letter-spacing: 0.5px;
}

.header-user {
  display: flex;
  align-items: center;
  gap: 4px;
}

.user-name {
  color: white;
  font-size: 14px;
  opacity: 0.95;
}

.header-user :deep(.q-btn) {
  color: white;
}

/* Summary Section */
.summary-section {
  padding: 16px;
  background: #f5f5f5;
}

.summary-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.summary-details {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  border-radius: 10px;
  transition: all 0.3s ease;
  border: 1px solid #f0f0f0;
}

.detail-row:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(25, 118, 210, 0.12);
  border-color: #e3f2fd;
}

.detail-label {
  font-size: 14px;
  color: #666;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.detail-label::before {
  content: '';
  width: 4px;
  height: 16px;
  border-radius: 2px;
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
}

.detail-row:nth-child(1) .detail-label::before {
  background: linear-gradient(135deg, #f44336 0%, #ef5350 100%);
}

.detail-row:nth-child(2) .detail-label::before {
  background: linear-gradient(135deg, #2196F3 0%, #42A5F5 100%);
}

.detail-row:nth-child(3) .detail-label::before {
  background: linear-gradient(135deg, #4CAF50 0%, #66BB6A 100%);
}

.detail-value {
  font-size: 17px;
  font-weight: 700;
  color: #1a1a1a;
  white-space: nowrap;
  letter-spacing: 0.3px;
}

/* Filter Section */
.filter-section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  background: #f5f5f5;
  margin-bottom: 8px;
}

.filter-tabs {
  display: flex;
  gap: 8px;
  background: #fff;
  padding: 4px;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.tab-item {
  font-size: 13px;
  color: #666;
  cursor: pointer;
  padding: 8px 16px;
  transition: all 0.3s ease;
  position: relative;
  border-radius: 8px;
  font-weight: 500;
}

.tab-item.active {
  color: #fff;
  font-weight: 600;
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
  box-shadow: 0 2px 8px rgba(25, 118, 210, 0.3);
}

.tab-item:not(.active):hover {
  background: #f5f5f5;
  color: #1976D2;
}

.refresh-btn {
  color: #666;
}



/* Transaction Section */
.transaction-section {
  background: #f5f5f5;
  padding: 0 16px 16px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: #fff;
  border-radius: 12px 12px 0 0;
  border-bottom: 2px solid #f5f5f5;
}

.section-title {
  font-size: 15px;
  font-weight: 700;
  color: #333;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-title::before {
  content: '';
  width: 3px;
  height: 18px;
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
  border-radius: 2px;
}

/* Transaction List */
.transaction-list {
  background: #fff;
  border-radius: 0 0 12px 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.transaction-item {
  display: flex;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #f8f8f8;
  gap: 14px;
  transition: all 0.2s ease;
  position: relative;
}

.transaction-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3px;
  background: transparent;
  transition: all 0.3s ease;
}

.transaction-item:hover {
  background: #fafafa;
}

.transaction-item:hover::before {
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
}

.transaction-item:last-child {
  border-bottom: none;
}

.transaction-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.transaction-item:hover .transaction-icon {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.icon-income {
  background: linear-gradient(135deg, #52c41a 0%, #73d13d 100%);
}

.icon-outcome {
  background: linear-gradient(135deg, #ff4d4f 0%, #ff7875 100%);
}

.icon-instant {
  background: linear-gradient(135deg, #722ed1 0%, #9254de 100%);
}

.icon-exchange {
  background: linear-gradient(135deg, #1890ff 0%, #40a9ff 100%);
}

.icon-default {
  background: linear-gradient(135deg, #bfbfbf 0%, #d9d9d9 100%);
}

.transaction-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

/* Header Row: Type + Channel + Status */
.transaction-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.transaction-title-row {
  font-size: 15px;
  font-weight: 500;
  color: #1a1a1a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
  margin-right: 8px;
}

.transaction-type {
  font-weight: 600;
  color: #1a1a1a;
}

.transaction-divider {
  color: #d9d9d9;
  margin: 0 4px;
}

.transaction-channel {
  color: #595959;
}

.status-badge-small {
  font-size: 10px;
  padding: 4px 10px;
  flex-shrink: 0;
  border-radius: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}

/* Amounts Row: Highlighted CNY and HKD */
.transaction-amounts-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  border-radius: 8px;
  margin: 4px 0;
}

.amount-cny-highlight {
  font-size: 18px;
  font-weight: 700;
  color: #d32f2f;
  letter-spacing: 0.3px;
}

.amount-hkd-highlight {
  font-size: 18px;
  font-weight: 700;
  color: #1976d2;
  letter-spacing: 0.3px;
}

.arrow-icon {
  color: #90a4ae;
  opacity: 0.7;
}

/* Footer Row: Time + ID */
.transaction-footer-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: #999;
}

.transaction-time {
  flex-shrink: 0;
  color: #757575;
}

.transaction-id {
  color: #bdbdbd;
  font-size: 11px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-left: 8px;
}


/* Loading States */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 16px;
  gap: 12px;
}

.loading-text {
  font-size: 14px;
  color: #757575;
}

.loading-more {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px;
}

.loading-more-text {
  font-size: 13px;
  color: #757575;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px 16px;
  gap: 12px;
}

.empty-text {
  font-size: 14px;
  color: #9e9e9e;
}

/* Responsive Adjustments */
@media (max-width: 360px) {
  .transaction-amounts {
    flex-direction: column;
    gap: 8px;
  }
  
  .amount-separator {
    transform: rotate(90deg);
  }
  
  .stat-value,
  .stat-count {
    font-size: 16px;
  }
}

/* Safe area for iPhone notch */
.safe-area-bottom {
  padding-bottom: constant(safe-area-inset-bottom);
  padding-bottom: env(safe-area-inset-bottom);
}
</style>
