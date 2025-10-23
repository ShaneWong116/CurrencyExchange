<template>
  <q-page>
    <!-- 顶部导航 -->
    <q-header class="bg-primary">
      <q-toolbar>
        <q-btn flat round icon="arrow_back" @click="$router.back()" />
        <q-toolbar-title>交易历史</q-toolbar-title>
        <q-btn flat round icon="filter_list" @click="showFilters = !showFilters" />
      </q-toolbar>
    </q-header>

    <div class="q-pa-md">
      <!-- 筛选器 -->
      <q-slide-transition>
        <q-card v-show="showFilters" class="q-mb-md">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md">筛选条件</div>
            
            <div class="row q-gutter-md">
              <!-- 交易类型 -->
              <div class="col-12 col-sm-6">
                <q-select
                  v-model="filters.type"
                  :options="typeOptions"
                  label="交易类型"
                  outlined
                  clearable
                  emit-value
                  map-options
                />
              </div>

              <!-- 支付渠道 -->
              <div class="col-12 col-sm-6">
                <q-select
                  v-model="filters.channel_id"
                  :options="channelOptions"
                  label="支付渠道"
                  outlined
                  clearable
                  emit-value
                  map-options
                />
              </div>

              <!-- 地点 -->
              <div class="col-12 col-sm-6">
                <q-select
                  v-model="filters.location_id"
                  :options="locationOptions"
                  label="地点"
                  outlined
                  clearable
                  emit-value
                  map-options
                />
              </div>

              <!-- 日期范围 -->
              <div class="col-12">
                <q-input
                  v-model="filters.dateRange"
                  label="日期范围"
                  outlined
                  readonly
                  @click="showDatePicker = true"
                >
                  <template v-slot:append>
                    <q-icon name="event" class="cursor-pointer" @click="showDatePicker = true" />
                  </template>
                </q-input>
              </div>
            </div>

            <div class="row q-gutter-md q-mt-md">
              <div class="col">
                <q-btn label="重置" flat @click="resetFilters" />
              </div>
              <div class="col">
                <q-btn label="应用" color="primary" @click="applyFilters" />
              </div>
            </div>
          </q-card-section>
        </q-card>
      </q-slide-transition>

      <!-- 统计卡片 -->
      <div class="row q-gutter-md q-mb-md">
        <div class="col">
          <q-card class="text-center" style="border-radius: 10px;">
            <q-card-section class="q-pa-sm">
              <div class="text-h6 text-positive">{{ stats.income }}</div>
              <div class="text-caption">入账</div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col">
          <q-card class="text-center" style="border-radius: 10px;">
            <q-card-section class="q-pa-sm">
              <div class="text-h6 text-negative">{{ stats.outcome }}</div>
              <div class="text-caption">出账</div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col">
          <q-card class="text-center" style="border-radius: 10px;">
            <q-card-section class="q-pa-sm">
              <div class="text-h6 text-info">{{ stats.exchange }}</div>
              <div class="text-caption">兑换</div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- 交易列表 -->
      <div v-if="transactionStore.isLoading" class="text-center q-pa-xl">
        <q-spinner size="xl" color="primary" />
      </div>

      <div v-else-if="transactions.length === 0" class="text-center q-pa-xl">
        <q-icon name="receipt_long" size="64px" color="grey-5" />
        <div class="text-h6 text-grey-7 q-mt-sm">暂无交易记录</div>
        <div class="text-caption text-grey-6">完成的交易会在这里显示</div>
      </div>

      <div v-else class="q-gutter-md">
        <q-card
          v-for="transaction in transactions"
          :key="transaction.id"
          class="list-item cursor-pointer"
          @click="viewTransaction(transaction)"
        >
          <q-card-section>
            <div class="row items-center">
              <div class="col">
                <div class="row items-center q-gutter-sm">
                  <q-icon
                    :name="getTypeIcon(transaction.type)"
                    :color="getTypeColor(transaction.type)"
                    size="sm"
                  />
                  <span class="text-subtitle1 text-weight-medium">
                    {{ getTypeLabel(transaction.type) }}
                  </span>
                  <q-badge
                    :color="getStatusColor(transaction.status)"
                    :label="getStatusLabel(transaction.status)"
                  />
                </div>
                
                <div class="text-body1 q-mt-xs">
                  <span class="text-weight-medium">￥{{ transaction.rmb_amount }}</span>
                  <span class="text-grey-6"> / </span>
                  <span class="text-weight-medium">HK${{ transaction.hkd_amount }}</span>
                </div>
                
                <div class="text-grey-6 text-caption q-mt-xs">
                  <div>{{ transaction.channel?.name }}</div>
                  <div>汇率: {{ transaction.exchange_rate }}</div>
                  <div>{{ formatTime(transaction.created_at) }}</div>
                </div>
              </div>
              
              <div class="col-auto">
                <q-icon name="chevron_right" color="grey-5" />
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- 加载更多 -->
      <div v-if="hasMore" class="text-center q-mt-md">
        <q-btn
          label="加载更多"
          flat
          color="primary"
          @click="loadMore"
          :loading="isLoadingMore"
        />
      </div>
    </div>

    <!-- 日期选择器 -->
    <q-dialog v-model="showDatePicker">
      <q-card style="min-width: 300px">
        <q-card-section>
          <div class="text-h6">选择日期范围</div>
        </q-card-section>
        
        <q-card-section class="q-pt-none">
          <q-date
            v-model="tempDateRange"
            range
            :options="dateOptions"
          />
        </q-card-section>
        
        <q-card-actions align="right">
          <q-btn flat label="取消" @click="showDatePicker = false" />
          <q-btn flat label="确定" color="primary" @click="confirmDateRange" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useTransactionStore } from '@/stores/transaction'
import { Dialog } from 'quasar'

const transactionStore = useTransactionStore()

const showFilters = ref(false)
const showDatePicker = ref(false)
const isLoadingMore = ref(false)
const tempDateRange = ref(null)

// 筛选条件
const filters = ref({
  type: null,
  channel_id: null,
  location_id: null,
  dateRange: null,
  start_date: null,
  end_date: null
})

// 选项
const typeOptions = [
  { label: '入账', value: 'income' },
  { label: '出账', value: 'outcome' },
  { label: '兑换', value: 'exchange' }
]

const channelOptions = computed(() => {
  return transactionStore.channels.map(channel => ({
    label: channel.name,
    value: channel.id
  }))
})

const locationOptions = computed(() => {
  return (transactionStore.locations || []).map(l => ({
    label: l.name,
    value: l.id
  }))
})

// 数据
const transactions = computed(() => transactionStore.transactions)
const hasMore = computed(() => {
  const pagination = transactionStore.pagination
  return pagination.page * pagination.rowsPerPage < pagination.rowsNumber
})

// 统计
const stats = computed(() => {
  return {
    income: transactions.value.filter(t => t.type === 'income').length,
    outcome: transactions.value.filter(t => t.type === 'outcome').length,
    exchange: transactions.value.filter(t => t.type === 'exchange').length
  }
})

// 日期选项（限制最近一年）
const dateOptions = (date) => {
  const oneYearAgo = new Date()
  oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1)
  return new Date(date) >= oneYearAgo && new Date(date) <= new Date()
}

// 工具函数
const getTypeIcon = (type) => {
  const icons = {
    income: 'add_circle',
    outcome: 'remove_circle',
    exchange: 'swap_horiz'
  }
  return icons[type] || 'help'
}

const getTypeColor = (type) => {
  const colors = {
    income: 'positive',
    outcome: 'negative',
    exchange: 'info'
  }
  return colors[type] || 'grey'
}

const getTypeLabel = (type) => {
  const labels = {
    income: '入账',
    outcome: '出账',
    exchange: '兑换'
  }
  return labels[type] || '未知'
}

const getStatusColor = (status) => {
  const colors = {
    success: 'positive',
    pending: 'warning',
    failed: 'negative'
  }
  return colors[status] || 'grey'
}

const getStatusLabel = (status) => {
  const labels = {
    success: '成功',
    pending: '处理中',
    failed: '失败'
  }
  return labels[status] || '未知'
}

const formatTime = (timeString) => {
  const date = new Date(timeString)
  return date.toLocaleString('zh-CN')
}

// 筛选操作
const resetFilters = () => {
  filters.value = {
    type: null,
    channel_id: null,
    location_id: null,
    dateRange: null,
    start_date: null,
    end_date: null
  }
  applyFilters()
}

const applyFilters = () => {
  showFilters.value = false
  loadTransactions()
}

const confirmDateRange = () => {
  if (tempDateRange.value) {
    if (typeof tempDateRange.value === 'string') {
      // 单个日期
      filters.value.start_date = tempDateRange.value
      filters.value.end_date = tempDateRange.value
      filters.value.dateRange = tempDateRange.value
    } else {
      // 日期范围
      filters.value.start_date = tempDateRange.value.from
      filters.value.end_date = tempDateRange.value.to
      filters.value.dateRange = `${tempDateRange.value.from} ~ ${tempDateRange.value.to}`
    }
  }
  showDatePicker.value = false
}

// 数据加载
const loadTransactions = () => {
  const params = {}
  
  if (filters.value.type) params.type = filters.value.type
  if (filters.value.channel_id) params.channel_id = filters.value.channel_id
  if (filters.value.location_id) params.location_id = filters.value.location_id
  if (filters.value.start_date) params.start_date = filters.value.start_date
  if (filters.value.end_date) params.end_date = filters.value.end_date
  
  transactionStore.fetchTransactions(params)
}

const loadMore = async () => {
  isLoadingMore.value = true
  try {
    const params = {
      page: transactionStore.pagination.page + 1
    }
    
    if (filters.value.type) params.type = filters.value.type
    if (filters.value.channel_id) params.channel_id = filters.value.channel_id
    if (filters.value.location_id) params.location_id = filters.value.location_id
    if (filters.value.start_date) params.start_date = filters.value.start_date
    if (filters.value.end_date) params.end_date = filters.value.end_date
    
    await transactionStore.fetchTransactions(params)
  } finally {
    isLoadingMore.value = false
  }
}

// 查看交易详情
const viewTransaction = (transaction) => {
  Dialog.create({
    title: '交易详情',
    message: `
      <div class="q-gutter-sm">
        <div><strong>类型:</strong> ${getTypeLabel(transaction.type)}</div>
        <div><strong>金额:</strong> ￥${transaction.rmb_amount} / HK$${transaction.hkd_amount}</div>
        <div><strong>汇率:</strong> ${transaction.exchange_rate}</div>
        <div><strong>渠道:</strong> ${transaction.channel?.name}</div>
        <div><strong>地点:</strong> ${transaction.location || '无'}</div>
        <div><strong>时间:</strong> ${formatTime(transaction.created_at)}</div>
        <div><strong>状态:</strong> ${getStatusLabel(transaction.status)}</div>
        ${transaction.notes ? `<div><strong>备注:</strong> ${transaction.notes}</div>` : ''}
      </div>
    `,
    html: true
  })
}

onMounted(() => {
  transactionStore.fetchChannels()
  transactionStore.fetchLocations()
  loadTransactions()
})
</script>

<style scoped>
.list-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>
