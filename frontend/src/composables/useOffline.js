// 离线功能组合式API
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useDraftStore } from '@/stores/draft'
import { useTransactionStore } from '@/stores/transaction'
import { Notify } from 'quasar'
import offlineManager from '@/utils/offline'

export function useOffline() {
  const isOnline = ref(navigator.onLine)
  const isSyncing = ref(false)
  const lastSyncTime = ref(null)
  
  const draftStore = useDraftStore()
  const transactionStore = useTransactionStore()

  // 网络状态
  const networkStatus = computed(() => ({
    isOnline: isOnline.value,
    isOffline: !isOnline.value,
    canSync: isOnline.value && !isSyncing.value
  }))

  // 离线数据统计
  const offlineStats = computed(() => ({
    localDrafts: draftStore.localDrafts.length,
    pendingTransactions: draftStore.pendingQueue.length,
    totalOfflineItems: draftStore.localDrafts.length + draftStore.pendingQueue.length
  }))

  // 网络状态变化处理
  const handleOnline = async () => {
    isOnline.value = true
    
    Notify.create({
      type: 'positive',
      message: '网络已连接，正在同步数据...',
      position: 'top',
      timeout: 2000
    })

    // 自动同步
    await syncOfflineData()
  }

  const handleOffline = () => {
    isOnline.value = false
    
    Notify.create({
      type: 'warning',
      message: '网络已断开，已切换到离线模式',
      position: 'top',
      timeout: 3000
    })
  }

  // 同步离线数据
  const syncOfflineData = async () => {
    if (!isOnline.value || isSyncing.value) return
    
    isSyncing.value = true
    let successCount = 0
    let errorCount = 0
    
    try {
      // 同步草稿
      if (draftStore.localDrafts.length > 0) {
        const result = await draftStore.syncDrafts()
        if (result.success) {
          successCount += draftStore.localDrafts.length
          draftStore.localDrafts = []
        }
      }

      // 同步待提交交易
      if (draftStore.pendingQueue.length > 0) {
        const result = await draftStore.syncPendingQueue()
        if (result.success) {
          successCount += result.results.filter(r => r.status === 'success').length
          errorCount += result.results.filter(r => r.status === 'error').length
        }
      }

      // 获取最新数据
      await Promise.all([
        draftStore.fetchDrafts(),
        transactionStore.fetchTransactions()
      ])

      lastSyncTime.value = new Date()
      localStorage.setItem('lastSyncTime', lastSyncTime.value.toISOString())

      if (successCount > 0) {
        Notify.create({
          type: 'positive',
          message: `同步完成，成功 ${successCount} 条${errorCount > 0 ? `，失败 ${errorCount} 条` : ''}`,
          position: 'top'
        })
      }

    } catch (error) {
      console.error('同步失败:', error)
      Notify.create({
        type: 'negative',
        message: '同步失败，请稍后重试',
        position: 'top'
      })
    } finally {
      isSyncing.value = false
    }
  }

  // 手动触发同步
  const manualSync = async () => {
    if (!isOnline.value) {
      Notify.create({
        type: 'warning',
        message: '网络连接异常，无法同步',
        position: 'top'
      })
      return
    }

    await syncOfflineData()
  }

  // 保存到离线存储
  const saveToOffline = async (data, type = 'draft') => {
    try {
      if (type === 'draft') {
        return await offlineManager.saveDraft(data)
      } else if (type === 'transaction') {
        return await offlineManager.addToPendingQueue(data)
      }
    } catch (error) {
      console.error('离线保存失败:', error)
      throw error
    }
  }

  // 从离线存储读取
  const loadFromOffline = async (userId, type = 'draft') => {
    try {
      if (type === 'draft') {
        return await offlineManager.getDrafts(userId)
      } else if (type === 'transaction') {
        return await offlineManager.getPendingTransactions(userId)
      }
    } catch (error) {
      console.error('离线读取失败:', error)
      return []
    }
  }

  // 清理离线数据
  const cleanupOfflineData = async () => {
    try {
      await offlineManager.cleanup()
      Notify.create({
        type: 'positive',
        message: '离线数据清理完成',
        position: 'top'
      })
    } catch (error) {
      console.error('清理失败:', error)
    }
  }

  // 获取存储使用情况
  const getStorageUsage = async () => {
    try {
      return await offlineManager.getStorageUsage()
    } catch (error) {
      console.error('获取存储信息失败:', error)
      return null
    }
  }

  // 初始化
  const init = () => {
    // 设置网络状态监听
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)

    // 加载最后同步时间
    const lastSync = localStorage.getItem('lastSyncTime')
    if (lastSync) {
      lastSyncTime.value = new Date(lastSync)
    }

    // 如果在线且有离线数据，自动同步
    if (isOnline.value && offlineStats.value.totalOfflineItems > 0) {
      setTimeout(syncOfflineData, 1000)
    }
  }

  // 清理
  const cleanup = () => {
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
  }

  // 生命周期钩子
  onMounted(init)
  onUnmounted(cleanup)

  return {
    // 状态
    isOnline: computed(() => isOnline.value),
    isOffline: computed(() => !isOnline.value),
    isSyncing: computed(() => isSyncing.value),
    lastSyncTime: computed(() => lastSyncTime.value),
    networkStatus,
    offlineStats,
    
    // 方法
    syncOfflineData,
    manualSync,
    saveToOffline,
    loadFromOffline,
    cleanupOfflineData,
    getStorageUsage
  }
}
