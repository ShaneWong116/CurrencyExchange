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
            <div class="detail-label">人民币余额</div>
            <div class="detail-value">¥{{ formatAmount(balanceOverview.total_rmb) }}</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">港币余额</div>
            <div class="detail-value">HK${{ formatAmount(balanceOverview.total_hkd) }}</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">交易数</div>
            <div class="detail-value">{{ getCurrentCount() }}笔</div>
          </div>
        </div>
        <!-- 筛选汇总（仅入账/出账时显示） -->
        <div class="filter-summary" v-if="filterType === 'income' || filterType === 'outcome'">
          <div class="filter-summary-content" :class="filterType">
            <span class="filter-type-label">{{ filterType === 'income' ? '入账' : '出账' }}汇总</span>
            <span class="filter-amount-cny">￥{{ formatInteger(filterType === 'income' ? stats.income.cny : stats.outcome.cny) }}</span>
            <span class="filter-amount-hkd">HK${{ formatInteger(filterType === 'income' ? stats.income.hkd : stats.outcome.hkd) }}</span>
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
          @click="changeFilter('all')"
        >
          全部
        </div>
        <div 
          class="tab-item" 
          :class="{ active: filterType === 'income' }"
          @click="changeFilter('income')"
        >
          入账
        </div>
        <div 
          class="tab-item" 
          :class="{ active: filterType === 'outcome' }"
          @click="changeFilter('outcome')"
        >
          出账
        </div>
        <div 
          class="tab-item" 
          :class="{ active: filterType === 'instant_buyout' }"
          @click="changeFilter('instant_buyout')"
        >
          即时买断
        </div>
      </div>
    </section>

    <!-- Transaction List -->
    <section class="transaction-section">
      <div class="section-header">
        <h6 class="section-title">交易明细</h6>
        <div class="header-actions">
          <!-- 结算按钮 -->
          <button 
            class="settlement-btn-compact" 
            @click="openSettlementDialog"
          >
            <q-icon name="account_balance" size="16px" />
            <span>结算</span>
          </button>
          <q-btn flat dense icon="refresh" size="sm" @click="refreshTransactions" :loading="loading" />
        </div>
      </div>

      <q-infinite-scroll 
        class="transaction-list"
        @load="loadMore" 
        :offset="250"
        :disable="!hasMore"
        ref="infiniteScroll"
        :scroll-target="scrollTarget"
      >
        <div v-for="t in filteredTransactions" :key="t.id" class="transaction-item" @click="openEditDialog(t)">
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
            
            <!-- 即时买断汇率 -->
            <div v-if="t.type === 'instant_buyout' && t.instant_rate" class="instant-rate-row">
              <q-icon name="bolt" size="14px" class="rate-icon" />
              <span class="rate-label">即时买断汇率:</span>
              <span class="rate-value">{{ Number(t.instant_rate).toFixed(3) }}</span>
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

        <template v-slot:loading>
          <div class="loading-more">
            <q-spinner color="primary" size="20px" />
            <span class="loading-more-text">加载更多...</span>
          </div>
        </template>
      </q-infinite-scroll>

      <!-- 手动加载更多按钮 -->
      <div class="load-more-section" v-if="hasMore && transactions.length > 0">
        <q-btn 
          flat 
          color="primary" 
          :loading="loading"
          @click="manualLoadMore"
          class="load-more-btn"
        >
          <q-icon name="expand_more" class="q-mr-xs" />
          加载更多 ({{ transactions.length }}/{{ totalCount }})
        </q-btn>
      </div>

      <!-- 已加载全部 -->
      <div class="load-more-section" v-if="!hasMore && transactions.length > 0">
        <span class="all-loaded-text">已加载全部 {{ transactions.length }} 条记录</span>
      </div>
    </section>

    <!-- 回到顶部按钮 -->
    <transition name="fade">
      <q-btn
        v-show="showBackToTop"
        fab
        icon="keyboard_arrow_up"
        color="primary"
        class="back-to-top-btn"
        @click="scrollToTop"
      />
    </transition>

    <!-- 结算对话框 -->
    <q-dialog v-model="showSettlementDialog" persistent maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="settlement-dialog">
        <!-- 对话框头部 -->
        <q-card-section class="dialog-header">
          <div class="dialog-title">
            <q-icon name="account_balance" size="28px" />
            <span>每日结算</span>
          </div>
          <q-btn flat round dense icon="close" @click="closeSettlementDialog" color="white" />
        </q-card-section>

        <!-- 步骤1: 结算预览 -->
        <q-card-section v-if="settlementStep === 'preview'" class="settlement-content">
          <div v-if="loadingPreview" class="loading-state">
            <q-spinner color="primary" size="50px" />
            <div class="loading-text">加载结算数据...</div>
          </div>

          <div v-else-if="previewData" class="preview-container">
            <!-- 当前状态卡片 -->
            <div class="preview-card current-state">
              <div class="card-header">
                <q-icon name="info" size="20px" />
                <span>当前状态</span>
              </div>
              <div class="data-row">
                <span class="label">原本金</span>
                <span class="value primary">HK$ {{ formatAmount(previewData.previous_capital) }}</span>
              </div>
              <div class="data-row">
                <span class="label">港币余额</span>
                <span class="value">HK$ {{ formatAmount(previewData.current_hkd_balance) }}</span>
              </div>
              <div class="data-row">
                <span class="label">人民币结余</span>
                <span class="value">¥ {{ formatAmount(previewData.rmb_balance_total) }}</span>
              </div>
              <div class="data-row">
                <span class="label">结算汇率</span>
                <span class="value rate">{{ formatRate(previewData.settlement_rate) }}</span>
              </div>
            </div>

            <!-- 利润明细卡片 -->
            <div class="preview-card profit-detail">
              <div class="card-header">
                <q-icon name="trending_up" size="20px" />
                <span>利润明细</span>
              </div>
              <div class="data-row highlight">
                <span class="label">出账利润</span>
                <span class="value profit">+HK$ {{ formatInteger(previewData.outgoing_profit) }}</span>
              </div>
              <div class="data-row highlight">
                <span class="label">即时买断利润</span>
                <span class="value profit">+HK$ {{ formatInteger(previewData.instant_profit) }}</span>
              </div>
              <div class="divider"></div>
              <div class="data-row total">
                <span class="label">总利润</span>
                <span class="value profit-total">+HK$ {{ formatInteger(previewData.profit) }}</span>
              </div>
            </div>

            <!-- 交易统计卡片 -->
            <div class="preview-card transaction-stats">
              <div class="card-header">
                <q-icon name="receipt" size="20px" />
                <span>交易统计</span>
              </div>
              <div class="stats-grid">
                <div class="stat-item">
                  <div class="stat-value income">{{ previewData.unsettled_income_count || 0 }}</div>
                  <div class="stat-label">入账笔数</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value outcome">{{ previewData.unsettled_outcome_count || 0 }}</div>
                  <div class="stat-label">出账笔数</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value instant">{{ previewData.unsettled_instant_count || 0 }}</div>
                  <div class="stat-label">即时买断</div>
                </div>
              </div>
            </div>

            <!-- 结算后状态卡片 -->
            <div class="preview-card result-state">
              <div class="card-header">
                <q-icon name="check_circle" size="20px" />
                <span>结算后状态</span>
              </div>
              <div class="data-row result">
                <span class="label">新本金</span>
                <span class="value new-capital">HK$ {{ formatAmount(previewData.expected_new_capital) }}</span>
              </div>
              <div class="data-row result">
                <span class="label">新港币余额</span>
                <span class="value">HK$ {{ formatAmount(previewData.expected_new_hkd_balance) }}</span>
              </div>
            </div>

            <!-- 警告提示 -->
            <div class="warning-box">
              <q-icon name="warning" size="20px" />
              <span>结算后今日将无法再次结算，请确认数据无误后继续</span>
            </div>
          </div>
        </q-card-section>

        <!-- 步骤2: 密码输入 -->
        <q-card-section v-if="settlementStep === 'password'" class="settlement-content">
          <div class="password-container">
            <div class="password-header">
              <q-icon name="lock" size="48px" color="primary" />
              <h6>请输入结算密码</h6>
              <p>为确保操作安全，请输入结算确认密码</p>
            </div>

            <q-input
              v-model="settlementPassword"
              :type="showPassword ? 'text' : 'password'"
              label="结算密码"
              outlined
              class="password-input"
              @keyup.enter="confirmSettlement"
            >
              <template v-slot:prepend>
                <q-icon name="lock" />
              </template>
              <template v-slot:append>
                <q-icon
                  :name="showPassword ? 'visibility_off' : 'visibility'"
                  class="cursor-pointer"
                  @click="showPassword = !showPassword"
                />
              </template>
            </q-input>

            <div v-if="passwordError" class="error-message">
              <q-icon name="error" size="16px" />
              <span>{{ passwordError }}</span>
            </div>
          </div>
        </q-card-section>

        <!-- 底部操作按钮 -->
        <q-card-actions class="dialog-actions">
          <q-btn
            v-if="settlementStep === 'preview'"
            flat
            label="取消"
            color="grey-7"
            @click="closeSettlementDialog"
            class="action-btn"
          />
          <q-btn
            v-if="settlementStep === 'password'"
            flat
            label="返回"
            color="grey-7"
            @click="settlementStep = 'preview'"
            class="action-btn"
          />
          <q-btn
            v-if="settlementStep === 'preview'"
            unelevated
            label="继续"
            color="primary"
            @click="goToPasswordStep"
            :disable="!previewData || !previewData.can_settle"
            class="action-btn primary-btn"
          />
          <q-btn
            v-if="settlementStep === 'password'"
            unelevated
            label="确认结算"
            color="orange"
            @click="confirmSettlement"
            :loading="isSubmittingSettlement"
            :disable="!settlementPassword"
            class="action-btn confirm-btn"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- 编辑交易对话框 -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card style="min-width: 350px; max-width: 450px; width: 100%;">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">
            <q-icon name="edit" class="q-mr-sm" />
            修改交易
          </div>
          <div class="text-caption">{{ typeLabel(editingTransaction?.type) }} - {{ editingTransaction?.channel?.name }}</div>
        </q-card-section>

        <q-card-section class="q-pt-md">
          <q-form @submit.prevent="submitEdit" class="q-gutter-md">
            <!-- 渠道选择 -->
            <q-select
              v-model="editForm.channel_id"
              :options="channelOptions"
              option-value="id"
              option-label="name"
              emit-value
              map-options
              label="渠道"
              outlined
              dense
            />

            <!-- 人民币金额 -->
            <q-input
              v-model.number="editForm.rmb_amount"
              type="number"
              label="人民币金额"
              outlined
              dense
              prefix="¥"
              :rules="[val => val > 0 || '金额必须大于0']"
              @update:model-value="calculateExchangeRate"
            />

            <!-- 港币金额 -->
            <q-input
              v-model.number="editForm.hkd_amount"
              type="number"
              label="港币金额"
              outlined
              dense
              prefix="HK$"
              :rules="[val => val > 0 || '金额必须大于0']"
              @update:model-value="calculateExchangeRate"
            />

            <!-- 汇率（自动计算，只读） -->
            <q-input
              v-model.number="editForm.exchange_rate"
              type="number"
              step="0.001"
              label="汇率（自动计算）"
              outlined
              dense
              readonly
              bg-color="grey-2"
              :rules="[val => val > 0 || '汇率必须大于0']"
            />

            <!-- 即时买断汇率（仅即时买断类型显示） -->
            <q-input
              v-if="editingTransaction?.type === 'instant_buyout'"
              v-model.number="editForm.instant_rate"
              type="number"
              step="0.001"
              label="即时买断汇率"
              outlined
              dense
            />

            <!-- 备注 -->
            <q-input
              v-model="editForm.notes"
              type="textarea"
              label="备注"
              outlined
              dense
              rows="2"
            />
          </q-form>
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat label="取消" color="grey-7" @click="closeEditDialog" />
          <q-btn 
            flat 
            label="删除" 
            color="negative" 
            @click="confirmDelete"
            :loading="isDeleting"
          />
          <q-btn 
            unelevated 
            label="保存" 
            color="primary" 
            @click="submitEdit"
            :loading="isSubmittingEdit"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <BottomNavigation />
  </q-page>
</template>

<script>
import BottomNavigation from '@/components/BottomNavigation.vue'
import { api } from '@/utils/api'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime, formatShortDateTime } from '@/utils/dateFormat'

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
      balanceOverview: {
        total_rmb: 0,
        total_hkd: 0
      },
      transactions: [],
      page: 1,
      hasMore: false,  // 初始禁用，防止无限滚动自动触发
      totalCount: 0,
      loading: false,
      filterType: 'all',
      // 结算相关
      showSettlementDialog: false,
      settlementStep: 'preview', // 'preview' | 'password'
      loadingPreview: false,
      previewData: null,
      settlementPassword: '',
      showPassword: false,
      passwordError: '',
      isSubmittingSettlement: false,
      // 编辑交易相关
      showEditDialog: false,
      editingTransaction: null,
      editForm: {
        channel_id: null,
        rmb_amount: 0,
        hkd_amount: 0,
        exchange_rate: 0,
        instant_rate: null,
        notes: ''
      },
      channelOptions: [],
      isSubmittingEdit: false,
      isDeleting: false,
      // 回到顶部
      showBackToTop: false,
      // 无限滚动目标
      scrollTarget: null
    }
  },
  computed: {
    // 不再需要前端过滤，直接返回后端数据
    filteredTransactions() {
      return this.transactions
    }
  },
  mounted() {
    // 监听滚动事件
    window.addEventListener('scroll', this.handleScroll)
    // 设置无限滚动的目标为页面滚动容器
    this.$nextTick(() => {
      this.scrollTarget = document.querySelector('.q-page-container') || document.body
    })
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.handleScroll)
  },
  async created() {
    const auth = useAuthStore()
    this.userName = auth.user?.name || ''

    await Promise.all([
      this.fetchBalanceOverview(),
      this.fetchStats(),
      this.fetchTransactions(),
      this.fetchChannels()
    ])
  },
  methods: {
    getCurrentCount() {
      if (this.filterType === 'all') {
        return this.stats.income.count + this.stats.outcome.count + this.stats.instant.count
      }
      const key = this.filterType === 'instant_buyout' ? 'instant' : this.filterType
      return this.stats[key]?.count || 0
    },
    async fetchBalanceOverview() {
      try {
        const res = await api.get('/transactions/balance-overview')
        if (res.data?.success) {
          this.balanceOverview = res.data.data
        }
      } catch (error) {
        console.error('Failed to fetch balance overview:', error)
        this.$q.notify({
          type: 'negative',
          message: '获取余额总览失败'
        })
      }
    },
    typeLabel(type) {
      const labels = {
        'income': '入账',
        'outcome': '出账',
        'instant_buyout': '即时买断',
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
      // 使用工具函数进行时区转换
      return formatShortDateTime(datetime)
    },
    formatFullTime(datetime) {
      // 使用工具函数进行时区转换(包含秒)
      return formatDateTime(datetime, true)
    },
    getTypeColor(type) {
      const colors = {
        'income': 'green',
        'outcome': 'orange',
        'instant_buyout': 'purple',
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
              } else if (type === 'instant_buyout') {
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
      // 如果选择了特定类型，添加 type 参数
      if (this.filterType !== 'all') {
        params.type = this.filterType
      }
      this.loading = true
      try {
        const res = await api.get('/transactions', { params })
        // API 返回结构: res.data = { current_page, data: [...], next_page_url, total, ... }
        // 或者 res.data = { data: { current_page, data: [...], ... } }
        const pagination = res.data?.current_page !== undefined ? res.data : res.data?.data
        const list = pagination?.data || []
        this.hasMore = !!pagination?.next_page_url
        this.totalCount = pagination?.total || 0
        this.transactions = reset ? list : [...this.transactions, ...list]
        console.log('[fetchTransactions] page:', this.page, 'loaded:', list.length, 'total:', this.totalCount, 'hasMore:', this.hasMore)
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
    // 切换筛选类型
    async changeFilter(type) {
      if (this.filterType === type) return
      this.filterType = type
      this.page = 1
      this.hasMore = false  // 先禁用，防止 loadMore 被触发
      this.transactions = []
      await this.fetchTransactions(true)
      // 数据加载完成后，根据实际情况恢复 hasMore
      // hasMore 已在 fetchTransactions 中正确设置
    },
    async refreshTransactions() {
      this.page = 1
      this.hasMore = false  // 先禁用，防止 loadMore 被触发
      await Promise.all([
        this.fetchBalanceOverview(),
        this.fetchStats(),
        this.fetchTransactions(true)
      ])
      // hasMore 已在 fetchTransactions 中正确设置
      this.$q.notify({
        type: 'positive',
        message: '刷新成功',
        timeout: 1000
      })
    },
    async loadMore(index, done) {
      console.log('[InfiniteScroll] loadMore called, page:', this.page, 'hasMore:', this.hasMore)
      if (!this.hasMore) {
        return done(true) // true 表示停止
      }
      this.page += 1
      await this.fetchTransactions(false)
      done(!this.hasMore) // 如果没有更多数据，传 true 停止
    },
    // 手动加载更多
    async manualLoadMore() {
      if (!this.hasMore || this.loading) return
      this.page += 1
      await this.fetchTransactions(false)
    },
    // 滚动事件处理
    handleScroll() {
      this.showBackToTop = window.scrollY > 300
    },
    // 回到顶部
    scrollToTop() {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      })
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
        'instant_buyout': 'swap_horiz',
        'exchange': 'compare_arrows'
      }
      return icons[type] || 'account_balance_wallet'
    },
    getTypeColorIcon(type) {
      const colors = {
        'income': 'positive',
        'outcome': 'negative',
        'instant_buyout': 'purple',
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
        'instant_buyout': 'icon-instant',
        'exchange': 'icon-exchange'
      }
      return classes[type] || 'icon-default'
    },
    formatInteger(value) {
      return Math.round(parseFloat(value || 0)).toString()
    },
    formatRate(value) {
      return parseFloat(value || 0).toFixed(3)
    },
    // 结算相关方法
    async openSettlementDialog() {
      // 直接跳转到结算预览页面
      this.$router.push('/settlement/preview')
      
    },
    async loadSettlementPreview() {
      this.loadingPreview = true
      try {
        const res = await api.get('/settlements/preview')
        if (res.data?.success) {
          this.previewData = res.data.data
          
          if (!this.previewData.can_settle) {
            this.$q.notify({
              type: 'warning',
              message: '当前没有未结算的交易，无法执行结算',
              position: 'top'
            })
          }
        }
      } catch (error) {
        console.error('获取结算预览失败:', error)
        this.$q.notify({
          type: 'negative',
          message: '获取结算数据失败',
          caption: error.response?.data?.message || error.message,
          position: 'top'
        })
      } finally {
        this.loadingPreview = false
      }
    },
    goToPasswordStep() {
      if (!this.previewData || !this.previewData.can_settle) {
        return
      }
      this.settlementStep = 'password'
      this.passwordError = ''
    },
    async confirmSettlement() {
      if (!this.settlementPassword) {
        this.passwordError = '请输入结算密码'
        return
      }
      
      this.isSubmittingSettlement = true
      this.passwordError = ''
      
      try {
        const res = await api.post('/settlements', {
          password: this.settlementPassword,
          expenses: [],
          notes: `外勤端结算 - ${this.userName}`
        })
        
        if (res.data?.success) {
          this.$q.notify({
            type: 'positive',
            message: '结算成功',
            caption: '今日结算已完成',
            position: 'top'
          })
          
          this.closeSettlementDialog()
          this.hasSettledToday = true
          
          // 刷新页面数据
          await Promise.all([
            this.fetchBalanceOverview(),
            this.fetchStats(),
            this.fetchTransactions(true)
          ])
        }
      } catch (error) {
        console.error('结算失败:', error)
        const errorMsg = error.response?.data?.message || error.message
        
        if (errorMsg.includes('密码')) {
          this.passwordError = '密码错误，请重新输入'
        } else {
          this.passwordError = errorMsg
        }
        
        this.$q.notify({
          type: 'negative',
          message: '结算失败',
          caption: errorMsg,
          position: 'top'
        })
      } finally {
        this.isSubmittingSettlement = false
      }
    },
    closeSettlementDialog() {
      this.showSettlementDialog = false
      this.settlementStep = 'preview'
      this.settlementPassword = ''
      this.passwordError = ''
      this.previewData = null
    },
    // 获取渠道列表
    async fetchChannels() {
      try {
        const res = await api.get('/channels')
        if (res.data) {
          this.channelOptions = res.data.data || res.data
        }
      } catch (error) {
        console.error('Failed to fetch channels:', error)
      }
    },
    // 打开编辑对话框
    openEditDialog(transaction) {
      this.editingTransaction = transaction
      this.editForm = {
        channel_id: transaction.channel_id,
        rmb_amount: Number(transaction.rmb_amount),
        hkd_amount: Number(transaction.hkd_amount),
        exchange_rate: Number(transaction.exchange_rate),
        instant_rate: transaction.instant_rate ? Number(transaction.instant_rate) : null,
        notes: transaction.notes || ''
      }
      this.showEditDialog = true
    },
    // 计算汇率：人民币 / 港币
    calculateExchangeRate() {
      if (this.editForm.rmb_amount > 0 && this.editForm.hkd_amount > 0) {
        this.editForm.exchange_rate = Number((this.editForm.rmb_amount / this.editForm.hkd_amount).toFixed(5))
      }
    },
    // 关闭编辑对话框
    closeEditDialog() {
      this.showEditDialog = false
      this.editingTransaction = null
      this.editForm = {
        channel_id: null,
        rmb_amount: 0,
        hkd_amount: 0,
        exchange_rate: 0,
        instant_rate: null,
        notes: ''
      }
    },
    // 提交编辑
    async submitEdit() {
      if (!this.editingTransaction) return
      
      this.isSubmittingEdit = true
      try {
        const res = await api.put(`/transactions/${this.editingTransaction.id}`, this.editForm)
        
        if (res.data?.success) {
          this.$q.notify({
            type: 'positive',
            message: '交易更新成功',
            timeout: 1500
          })
          
          this.closeEditDialog()
          
          // 刷新数据
          await Promise.all([
            this.fetchBalanceOverview(),
            this.fetchStats(),
            this.fetchTransactions(true)
          ])
        }
      } catch (error) {
        console.error('Update transaction failed:', error)
        this.$q.notify({
          type: 'negative',
          message: error.response?.data?.message || '更新失败'
        })
      } finally {
        this.isSubmittingEdit = false
      }
    },
    // 确认删除
    confirmDelete() {
      this.$q.dialog({
        title: '确认删除',
        message: '确定要删除这笔交易吗？此操作不可恢复。',
        cancel: {
          label: '取消',
          flat: true
        },
        ok: {
          label: '删除',
          color: 'negative'
        },
        persistent: true
      }).onOk(() => {
        this.deleteTransaction()
      })
    },
    // 删除交易
    async deleteTransaction() {
      if (!this.editingTransaction) return
      
      this.isDeleting = true
      try {
        const res = await api.delete(`/transactions/${this.editingTransaction.id}`)
        
        if (res.data?.success) {
          this.$q.notify({
            type: 'positive',
            message: '交易删除成功',
            timeout: 1500
          })
          
          this.closeEditDialog()
          
          // 刷新数据
          await Promise.all([
            this.fetchBalanceOverview(),
            this.fetchStats(),
            this.fetchTransactions(true)
          ])
        }
      } catch (error) {
        console.error('Delete transaction failed:', error)
        this.$q.notify({
          type: 'negative',
          message: error.response?.data?.message || '删除失败'
        })
      } finally {
        this.isDeleting = false
      }
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

/* 筛选汇总 */
.filter-summary {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px dashed #e0e0e0;
}

.filter-summary-content {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 8px 0;
}

.filter-type-label {
  font-size: 13px;
  font-weight: 600;
  color: #666;
}

.filter-amount-cny {
  font-size: 16px;
  font-weight: 700;
  color: #e65100;
}

.filter-amount-hkd {
  font-size: 15px;
  font-weight: 600;
  color: #1565c0;
}

/* 加载更多按钮 */
.load-more-section {
  display: flex;
  justify-content: center;
  padding: 16px;
}

.load-more-btn {
  font-size: 14px;
}

.all-loaded-text {
  font-size: 13px;
  color: #999;
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

.header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* 紧凑型结算按钮 */
.settlement-btn-compact {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border: none;
  border-radius: 8px;
  background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
  color: white;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 6px rgba(255, 152, 0, 0.3);
  white-space: nowrap;
}

.settlement-btn-compact:not(:disabled):active {
  transform: scale(0.95);
}

.settlement-btn-compact:not(:disabled):hover {
  box-shadow: 0 3px 10px rgba(255, 152, 0, 0.4);
}

.settlement-btn-compact.settlement-btn-disabled {
  background: linear-gradient(135deg, #bdbdbd 0%, #9e9e9e 100%);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
  cursor: not-allowed;
  opacity: 0.7;
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

/* Instant Rate Row */
.instant-rate-row {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: linear-gradient(135deg, #f3e5f5 0%, #ffffff 100%);
  border-radius: 6px;
  border-left: 3px solid #9c27b0;
}

.rate-icon {
  color: #9c27b0;
}

.rate-label {
  font-size: 12px;
  color: #666;
  font-weight: 500;
}

.rate-value {
  font-size: 13px;
  font-weight: 700;
  color: #9c27b0;
  margin-left: auto;
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

/* 结算对话框样式 */
.settlement-dialog {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: #f8f9fa;
}

.dialog-header {
  background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
  color: white;
  padding: 20px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
}

.dialog-title {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 20px;
  font-weight: 700;
}

.settlement-content {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  -webkit-overflow-scrolling: touch;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  gap: 16px;
}

.loading-text {
  font-size: 14px;
  color: #757575;
}

.preview-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.preview-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 700;
  color: #333;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid #f0f0f0;
}

.card-header .q-icon {
  color: #ff9800;
}

.data-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f5f5f5;
}

.data-row:last-child {
  border-bottom: none;
}

.data-row .label {
  font-size: 14px;
  color: #666;
  font-weight: 500;
}

.data-row .value {
  font-size: 16px;
  font-weight: 700;
  color: #1a1a1a;
}

.data-row .value.primary {
  color: #1976d2;
  font-size: 18px;
}

.data-row .value.rate {
  color: #7b1fa2;
  font-family: 'Courier New', monospace;
}

.data-row.highlight {
  background: linear-gradient(135deg, #fff3e0 0%, #ffffff 100%);
  padding: 12px 14px;
  margin: 4px -14px;
  border-radius: 8px;
  border-bottom: none;
}

.data-row .value.profit {
  color: #4caf50;
  font-size: 17px;
}

.divider {
  height: 1px;
  background: linear-gradient(90deg, transparent 0%, #e0e0e0 50%, transparent 100%);
  margin: 12px 0;
}

.data-row.total {
  background: linear-gradient(135deg, #e8f5e9 0%, #ffffff 100%);
  padding: 14px;
  margin: 8px -14px -14px;
  border-radius: 0 0 8px 8px;
  border-bottom: none;
}

.data-row .value.profit-total {
  color: #2e7d32;
  font-size: 20px;
  font-weight: 800;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px 8px;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  border-radius: 10px;
  border: 1px solid #f0f0f0;
}

.stat-value {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 6px;
}

.stat-value.income {
  color: #4caf50;
}

.stat-value.outcome {
  color: #f44336;
}

.stat-value.instant {
  color: #9c27b0;
}

.stat-label {
  font-size: 12px;
  color: #757575;
  font-weight: 500;
}

.data-row.result {
  background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);
  padding: 14px;
  margin: 4px -14px;
  border-radius: 8px;
  border-bottom: none;
}

.data-row .value.new-capital {
  color: #1565c0;
  font-size: 20px;
  font-weight: 800;
}

.warning-box {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  background: linear-gradient(135deg, #fff8e1 0%, #fffdf7 100%);
  border-left: 4px solid #ffa726;
  border-radius: 8px;
  margin-top: 8px;
}

.warning-box .q-icon {
  color: #ff9800;
  flex-shrink: 0;
  margin-top: 2px;
}

.warning-box span {
  font-size: 13px;
  color: #e65100;
  line-height: 1.5;
  font-weight: 500;
}

.password-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 20px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.password-header {
  text-align: center;
  margin-bottom: 32px;
}

.password-header .q-icon {
  margin-bottom: 16px;
}

.password-header h6 {
  margin: 0 0 8px 0;
  font-size: 20px;
  font-weight: 700;
  color: #333;
}

.password-header p {
  margin: 0;
  font-size: 14px;
  color: #757575;
  line-height: 1.5;
}

.password-input {
  width: 100%;
  max-width: 400px;
}

.password-input :deep(.q-field__control) {
  min-height: 56px;
}

.error-message {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 16px;
  padding: 12px 16px;
  background: #ffebee;
  border-radius: 8px;
  color: #c62828;
  font-size: 14px;
  font-weight: 500;
}

.error-message .q-icon {
  color: #ef5350;
}

.dialog-actions {
  padding: 16px;
  background: white;
  border-top: 1px solid #e0e0e0;
  display: flex;
  gap: 12px;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.05);
}

.action-btn {
  flex: 1;
  height: 48px;
  font-size: 15px;
  font-weight: 600;
  border-radius: 10px;
  text-transform: none;
  letter-spacing: 0.3px;
}

.primary-btn {
  background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%);
  box-shadow: 0 2px 8px rgba(25, 118, 210, 0.3);
}

.confirm-btn {
  background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
  box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
}

/* 响应式调整 */
@media (max-width: 400px) {
  .tab-item {
    font-size: 12px;
    padding: 7px 12px;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .stat-item {
    flex-direction: row;
    justify-content: space-between;
  }
  
  .settlement-btn-compact span {
    display: none;
  }
  
  .settlement-btn-compact {
    padding: 6px 8px;
  }
}

/* 回到顶部按钮 */
.back-to-top-btn {
  position: fixed;
  bottom: 80px;
  right: 20px;
  z-index: 100;
  box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
}

/* 淡入淡出动画 */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
