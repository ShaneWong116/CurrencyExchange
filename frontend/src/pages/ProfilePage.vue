<template>
  <q-page>

    <div class="q-pa-md">
      <!-- 用户信息卡片 -->
      <q-card class="info-card q-mb-md">
        <q-card-section>
          <div class="row items-center">
            <div class="col-auto q-mr-md">
              <q-avatar size="64px" color="primary" text-color="white">
                <q-icon name="person" size="32px" />
              </q-avatar>
            </div>
            <div class="col">
              <div class="text-h6">{{ authStore.user?.name }}</div>
              <div class="text-grey-6">{{ authStore.user?.username }}</div>
              <div class="text-caption text-grey-5">
                最后登录: {{ formatTime(authStore.user?.last_login_at) }}
              </div>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- 统计信息 -->
      <q-card class="info-card q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 q-mb-md">数据统计</div>
          <div class="row text-center">
            <div class="col">
              <div class="text-h5 text-primary">{{ userStats.totalTransactions }}</div>
              <div class="text-grey-6">总交易数</div>
            </div>
            <div class="col">
              <div class="text-h5 text-orange">{{ userStats.totalDrafts }}</div>
              <div class="text-grey-6">草稿数</div>
            </div>
            <div class="col">
              <div class="text-h5 text-red">{{ userStats.pendingCount }}</div>
              <div class="text-grey-6">待同步</div>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- 同步状态 -->
      <q-card class="info-card q-mb-md">
        <q-card-section>
          <div class="row items-center">
            <div class="col">
              <div class="text-subtitle1">同步状态</div>
              <div class="text-grey-6 q-mt-xs">
                <div>网络状态: {{ isOnline ? '在线' : '离线' }}</div>
                <div>最后同步: {{ lastSyncTime }}</div>
              </div>
            </div>
            <div class="col-auto">
              <q-btn
                round
                :icon="isOnline ? 'cloud_done' : 'cloud_off'"
                :color="isOnline ? 'positive' : 'negative'"
                @click="handleSync"
                :loading="isSyncing"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- 应用信息 -->
      <q-card class="info-card q-mb-md" style="border-radius: 12px;">
        <q-card-section>
          <div class="text-subtitle1 q-mb-md">应用信息</div>
          <div class="q-gutter-sm">
            <div class="row">
              <div class="col-4 text-grey-6">版本:</div>
              <div class="col">1.0.0</div>
            </div>
            <div class="row">
              <div class="col-4 text-grey-6">构建:</div>
              <div class="col">{{ buildTime }}</div>
            </div>
            <div class="row">
              <div class="col-4 text-grey-6">浏览器:</div>
              <div class="col">{{ browserInfo }}</div>
            </div>
            <div class="row">
              <div class="col-4 text-grey-6">存储:</div>
              <div class="col">{{ storageInfo }}</div>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- 操作按钮 -->
      <div class="q-gutter-md">
        <q-btn
          label="清除本地数据"
          color="orange"
          outline
          class="full-width"
          @click="clearLocalData"
        />
        
        <q-btn
          label="退出登录"
          color="negative"
          outline
          class="full-width"
          @click="logout"
        />
      </div>

      
    </div>
    <BottomNavigation />
  </q-page>
</template>

<script setup>
import BottomNavigation from '@/components/BottomNavigation.vue'
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDraftStore } from '@/stores/draft'
import { useTransactionStore } from '@/stores/transaction'
import { Dialog, Notify } from 'quasar'

const router = useRouter()
const authStore = useAuthStore()
const draftStore = useDraftStore()
const transactionStore = useTransactionStore()

const isOnline = ref(navigator.onLine)
const isSyncing = ref(false)
const lastSyncTime = ref('未同步')


// 用户统计
const userStats = computed(() => ({
  totalTransactions: transactionStore.transactions.length,
  totalDrafts: draftStore.allDrafts.length,
  pendingCount: draftStore.pendingCount
}))

// 构建时间
const buildTime = computed(() => {
  return new Date().toLocaleString('zh-CN')
})

// 浏览器信息
const browserInfo = computed(() => {
  const ua = navigator.userAgent
  if (ua.indexOf('Chrome') > -1) return 'Chrome'
  if (ua.indexOf('Firefox') > -1) return 'Firefox'
  if (ua.indexOf('Safari') > -1) return 'Safari'
  if (ua.indexOf('Edge') > -1) return 'Edge'
  return '未知'
})

// 存储信息
const storageInfo = computed(() => {
  if (navigator.storage && navigator.storage.estimate) {
    return '支持 Storage API'
  }
  return '基础存储'
})


// 格式化时间
const formatTime = (timeString) => {
  if (!timeString) return '无'
  const date = new Date(timeString)
  return date.toLocaleString('zh-CN')
}

// 同步数据
const handleSync = async () => {
  if (!isOnline.value) {
    Notify.create({
      type: 'warning',
      message: '网络连接异常，无法同步',
      position: 'top'
    })
    return
  }

  isSyncing.value = true
  try {
    // 同步草稿
    await draftStore.syncDrafts()
    // 同步待提交队列
    await draftStore.syncPendingQueue()
    // 刷新数据
    await Promise.all([
      draftStore.fetchDrafts(),
      transactionStore.fetchTransactions(),
      authStore.fetchUserInfo()
    ])
    
    lastSyncTime.value = new Date().toLocaleString('zh-CN')
    
    Notify.create({
      type: 'positive',
      message: '同步完成',
      position: 'top'
    })
  } catch (error) {
    Notify.create({
      type: 'negative',
      message: '同步失败',
      position: 'top'
    })
  } finally {
    isSyncing.value = false
  }
}

// 清除本地数据
const clearLocalData = () => {
  Dialog.create({
    title: '确认清除',
    message: '这将清除所有本地数据（草稿、待同步队列等），但不会影响已提交的数据。确定要继续吗？',
    cancel: true,
    persistent: true
  }).onOk(() => {
    // 清除本地草稿和待同步队列
    draftStore.localDrafts = []
    draftStore.pendingQueue = []
    
    // 清除其他缓存数据
    const keysToRemove = []
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key.startsWith('draft') || key.startsWith('transaction')) {
        keysToRemove.push(key)
      }
    }
    
    keysToRemove.forEach(key => localStorage.removeItem(key))
    
    Notify.create({
      type: 'positive',
      message: '本地数据已清除',
      position: 'top'
    })
  })
}

// 退出登录
const logout = () => {
  Dialog.create({
    title: '确认退出',
    message: '确定要退出登录吗？本地数据将保留。',
    cancel: true,
    persistent: true
  }).onOk(async () => {
    await authStore.logout()
    router.push('/login')
  })
}

// 网络状态监听
const handleOnline = () => {
  isOnline.value = true
  Notify.create({
    type: 'positive',
    message: '网络已连接',
    position: 'top'
  })
}

const handleOffline = () => {
  isOnline.value = false
  Notify.create({
    type: 'warning',
    message: '网络已断开',
    position: 'top'
  })
}

onMounted(() => {
  // 监听网络状态
  window.addEventListener('online', handleOnline)
  window.addEventListener('offline', handleOffline)
  
  // 设置最后同步时间
  const lastSync = localStorage.getItem('lastSyncTime')
  if (lastSync) {
    lastSyncTime.value = new Date(lastSync).toLocaleString('zh-CN')
  }
  
  // 清理函数
  return () => {
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
  }
})
</script>
