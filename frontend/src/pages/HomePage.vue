<template>
  <q-page class="home-page">
    <!-- 顶部用户信息 -->
    <header class="page-header">
      <div class="header-content">
        <div class="user-info">
          <div class="user-name">{{ authStore.userName }}</div>
          <div class="user-time">{{ currentTime }}</div>
        </div>
        <q-btn
          flat
          round
          icon="person"
          @click="$router.push('/profile')"
          class="profile-btn"
          size="md"
        />
      </div>
    </header>

    <!-- 功能入口卡片 -->
    <div class="content-section">
      <div class="cards-grid">
        <!-- 交易录入 -->
        <div class="card-wrapper">
          <div class="feature-card primary-card" @click="$router.push('/transaction')">
            <div class="card-content">
              <div class="card-icon-wrapper primary-icon">
                <q-icon name="add_circle" size="40px" class="card-icon" />
              </div>
              <div class="card-text">
                <div class="card-title">交易录入</div>
                <div class="card-subtitle">入账 · 出账 · 即时买断</div>
              </div>
            </div>
            <div class="card-arrow">
              <q-icon name="arrow_forward" size="20px" />
            </div>
          </div>
        </div>

        <!-- 草稿管理 -->
        <div class="card-wrapper">
          <div class="feature-card orange-card" @click="$router.push('/drafts')">
            <div class="card-content">
              <div class="card-icon-wrapper orange-icon">
                <q-icon name="edit_note" size="40px" class="card-icon" />
              </div>
              <div class="card-text">
                <div class="card-title">草稿箱</div>
                <div class="card-subtitle">
                  {{ draftStore.allDrafts.length }} 个草稿
                </div>
              </div>
            </div>
            <q-badge 
              v-if="draftStore.pendingCount > 0" 
              color="red" 
              floating
              class="draft-badge"
            >
              {{ draftStore.pendingCount }}
            </q-badge>
            <div class="card-arrow">
              <q-icon name="arrow_forward" size="20px" />
            </div>
          </div>
        </div>

        <!-- 同步状态 -->
        <div class="card-wrapper">
          <div class="feature-card sync-card" :class="isOnline ? 'online-card' : 'offline-card'">
            <div class="card-content">
              <div class="card-icon-wrapper" :class="isOnline ? 'success-icon' : 'error-icon'">
                <q-icon 
                  :name="isOnline ? 'cloud_done' : 'cloud_off'" 
                  size="40px" 
                  class="card-icon"
                />
              </div>
              <div class="card-text">
                <div class="card-title">
                  {{ isOnline ? '在线' : '离线' }}
                </div>
                <div class="card-subtitle">
                  {{ isOnline ? '数据已同步' : '等待网络连接' }}
                </div>
              </div>
            </div>
            
            <q-btn
              v-if="!isOnline && (draftStore.localDrafts.length > 0 || draftStore.pendingCount > 0)"
              flat
              label="重试同步"
              @click="handleSync"
              :loading="isSync"
              class="sync-btn"
              size="sm"
            />
          </div>
        </div>
      </div>
    </div>
    <BottomNavigation />
  </q-page>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDraftStore } from '@/stores/draft'
import { useTransactionStore } from '@/stores/transaction'
import BottomNavigation from '@/components/BottomNavigation.vue'

const authStore = useAuthStore()
const draftStore = useDraftStore()
const transactionStore = useTransactionStore()

const isOnline = ref(navigator.onLine)
const isSync = ref(false)
const currentTime = ref('')

const updateTime = () => {
  const now = new Date()
  currentTime.value = now.toLocaleString('zh-CN')
}

const handleOnline = () => {
  isOnline.value = true
  handleSync()
}

const handleOffline = () => {
  isOnline.value = false
}

const handleSync = async () => {
  if (!isOnline.value) return
  
  isSync.value = true
  try {
    await draftStore.syncDrafts()
    await draftStore.syncPendingQueue()
    await draftStore.fetchDrafts()
    await transactionStore.fetchTransactions()
  } finally {
    isSync.value = false
  }
}

onMounted(() => {
  updateTime()
  const timer = setInterval(updateTime, 1000)
  
  window.addEventListener('online', handleOnline)
  window.addEventListener('offline', handleOffline)
  
  draftStore.fetchDrafts()
  transactionStore.fetchChannels()
  transactionStore.fetchTransactions()
  
  onUnmounted(() => {
    clearInterval(timer)
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
  })
})
</script>

<style scoped>
/* Page Layout */
.home-page {
  background: #f5f5f5;
  min-height: 100vh;
  padding-bottom: 80px;
}

/* Header */
.page-header {
  background: linear-gradient(135deg, #1976D2 0%, #1565C0 50%, #0D47A1 100%);
  padding: 20px 20px 28px;
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
  position: relative;
  z-index: 1;
}

.user-info {
  flex: 1;
}

.user-name {
  font-size: 22px;
  font-weight: 700;
  color: white;
  margin-bottom: 4px;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  letter-spacing: 0.5px;
}

.user-time {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.85);
  font-weight: 400;
}

.profile-btn {
  color: white !important;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  transition: all 0.3s ease;
}

.profile-btn:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: scale(1.05);
}

/* Content Section */
.content-section {
  padding: 20px 16px;
}

.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}

/* Feature Cards */
.card-wrapper {
  position: relative;
}

.feature-card {
  background: white;
  border-radius: 16px;
  padding: 24px 20px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  position: relative;
  overflow: hidden;
  min-height: 110px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.feature-card::before {
  content: '';
  position: absolute;
  top: -20px;
  right: -20px;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  opacity: 0.06;
  transition: all 0.3s ease;
}

.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.feature-card:active {
  transform: translateY(-2px);
}

/* Card Content - 横向布局 */
.card-content {
  display: flex;
  align-items: center;
  gap: 16px;
}

/* Card Icon */
.card-icon-wrapper {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.feature-card:hover .card-icon-wrapper {
  transform: scale(1.05) rotate(-5deg);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.card-icon {
  color: white;
}

/* Card Text */
.card-text {
  flex: 1;
  min-width: 0;
}

.primary-icon {
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
}

.primary-card::before {
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
}

.orange-icon {
  background: linear-gradient(135deg, #FF9800 0%, #FFB74D 100%);
}

.orange-card::before {
  background: linear-gradient(135deg, #FF9800 0%, #FFB74D 100%);
}

.success-icon {
  background: linear-gradient(135deg, #4CAF50 0%, #66BB6A 100%);
}

.online-card::before {
  background: linear-gradient(135deg, #4CAF50 0%, #66BB6A 100%);
}

.error-icon {
  background: linear-gradient(135deg, #f44336 0%, #ef5350 100%);
}

.offline-card::before {
  background: linear-gradient(135deg, #f44336 0%, #ef5350 100%);
}

/* Card Title & Subtitle */
.card-title {
  font-size: 18px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 4px;
  letter-spacing: 0.3px;
  line-height: 1.3;
}

.card-subtitle {
  font-size: 13px;
  color: #757575;
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Card Arrow */
.card-arrow {
  position: absolute;
  bottom: 20px;
  right: 20px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transform: translateX(-10px);
  transition: all 0.3s ease;
}

.feature-card:hover .card-arrow {
  opacity: 1;
  transform: translateX(0);
}

.card-arrow .q-icon {
  color: #666;
}

/* Draft Badge */
.draft-badge {
  top: 20px !important;
  right: 20px !important;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 10px;
}

/* Sync Button */
.sync-btn {
  margin-top: 8px;
  align-self: flex-start;
  margin-left: 80px;
  background: rgba(25, 118, 210, 0.1);
  color: #1976D2;
  border-radius: 8px;
  font-weight: 600;
  font-size: 12px;
  padding: 4px 12px;
}

.sync-btn:hover {
  background: rgba(25, 118, 210, 0.15);
}

/* Responsive */
@media (max-width: 600px) {
  .cards-grid {
    grid-template-columns: 1fr;
  }
  
  .user-name {
    font-size: 20px;
  }
  
  .card-icon-wrapper {
    width: 56px;
    height: 56px;
  }
  
  .card-icon-wrapper .q-icon {
    font-size: 32px;
  }
  
  .card-title {
    font-size: 16px;
  }
  
  .card-subtitle {
    font-size: 12px;
  }
}

@media (max-width: 360px) {
  .card-content {
    gap: 12px;
  }
  
  .card-icon-wrapper {
    width: 52px;
    height: 52px;
  }
  
  .sync-btn {
    margin-left: 68px;
  }
}
</style>
