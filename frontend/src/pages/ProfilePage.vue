<template>
  <q-page class="profile-page">
    <!-- 顶部装饰 -->
    <div class="page-header">
      <div class="header-decoration"></div>
    </div>

    <div class="profile-content">
      <!-- 用户信息卡片 -->
      <div class="user-card">
        <div class="user-avatar-wrapper">
          <div class="avatar-container">
            <q-icon name="person" size="48px" class="avatar-icon" />
          </div>
        </div>
        <div class="user-details">
          <div class="user-name">{{ authStore.user?.name }}</div>
          <div class="user-username">@{{ authStore.user?.username }}</div>
        </div>
      </div>

      <!-- 统计信息 -->
      <div class="stats-card">
        <div class="stats-title">数据统计</div>
        <div class="stats-grid">
          <div class="stat-item primary-stat">
            <div class="stat-icon-wrapper primary-bg">
              <q-icon name="receipt_long" size="24px" class="stat-icon" />
            </div>
            <div class="stat-value">{{ userStats.totalTransactions }}</div>
            <div class="stat-label">总交易数</div>
          </div>
          <div class="stat-item orange-stat">
            <div class="stat-icon-wrapper orange-bg">
              <q-icon name="edit_note" size="24px" class="stat-icon" />
            </div>
            <div class="stat-value">{{ userStats.totalDrafts }}</div>
            <div class="stat-label">草稿数</div>
          </div>
          <div class="stat-item red-stat">
            <div class="stat-icon-wrapper red-bg">
              <q-icon name="sync" size="24px" class="stat-icon" />
            </div>
            <div class="stat-value">{{ userStats.pendingCount }}</div>
            <div class="stat-label">待同步</div>
          </div>
        </div>
      </div>

      <!-- 操作按钮 -->
      <div class="action-buttons">
        <button class="action-btn logout-btn logout-btn-full" @click="logout">
          <q-icon name="logout" size="20px" />
          <span>退出登录</span>
        </button>
      </div>
    </div>
    
    <BottomNavigation />
  </q-page>
</template>

<script setup>
import BottomNavigation from '@/components/BottomNavigation.vue'
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDraftStore } from '@/stores/draft'
import { useTransactionStore } from '@/stores/transaction'
import { Dialog } from 'quasar'

const router = useRouter()
const authStore = useAuthStore()
const draftStore = useDraftStore()
const transactionStore = useTransactionStore()

// 用户统计
const userStats = computed(() => ({
  totalTransactions: transactionStore.unsettledCount, // 改为未结余交易数
  totalDrafts: draftStore.allDrafts.length,
  pendingCount: draftStore.pendingCount
}))

// 页面加载时刷新数据
onMounted(async () => {
  // 刷新未结余交易数据
  await transactionStore.fetchTransactions({ settlement_status: 'unsettled' })
  // 刷新草稿数据
  await draftStore.fetchDrafts()
})

// 格式化时间
const formatTime = (timeString) => {
  if (!timeString) return '无'
  const date = new Date(timeString)
  return date.toLocaleString('zh-CN')
}

// 退出登录
const logout = () => {
  Dialog.create({
    title: '确认退出',
    message: '确定要退出登录吗？',
    cancel: true,
    persistent: true
  }).onOk(async () => {
    await authStore.logout()
    router.push('/login')
  })
}
</script>

<style scoped>
/* Page Layout */
.profile-page {
  background: #f5f5f5;
  min-height: 100vh;
  padding-bottom: 80px;
}

/* Header Decoration */
.page-header {
  height: 120px;
  background: linear-gradient(135deg, #1976D2 0%, #1565C0 50%, #0D47A1 100%);
  position: relative;
  overflow: hidden;
}

.header-decoration::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -20%;
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  border-radius: 50%;
}

.header-decoration::after {
  content: '';
  position: absolute;
  bottom: -30%;
  left: -10%;
  width: 150px;
  height: 150px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
  border-radius: 50%;
}

/* Content */
.profile-content {
  padding: 0 16px 20px;
  margin-top: -60px;
  position: relative;
  z-index: 1;
}

/* User Card */
.user-card {
  background: white;
  border-radius: 16px;
  padding: 24px 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.user-avatar-wrapper {
  margin-bottom: 16px;
}

.avatar-container {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 24px rgba(25, 118, 210, 0.3);
  position: relative;
}

.avatar-container::before {
  content: '';
  position: absolute;
  inset: -4px;
  border-radius: 50%;
  padding: 4px;
  background: linear-gradient(135deg, #42A5F5, #1976D2);
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  mask-composite: exclude;
  opacity: 0.3;
}

.avatar-icon {
  color: white;
}

.user-name {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 4px;
  letter-spacing: 0.5px;
}

.user-username {
  font-size: 15px;
  color: #1976D2;
  font-weight: 600;
  margin-bottom: 8px;
}

.user-login-time {
  font-size: 12px;
  color: #999;
  font-weight: 400;
}

/* Stats Card */
.stats-card {
  background: white;
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.stats-title {
  font-size: 16px;
  font-weight: 700;
  color: #333;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.stats-title::before {
  content: '';
  width: 3px;
  height: 18px;
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
  border-radius: 2px;
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
  border-radius: 12px;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  transition: all 0.3s ease;
}

.stat-item:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  color: white;
}

.primary-bg {
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
}

.orange-bg {
  background: linear-gradient(135deg, #FF9800 0%, #FFB74D 100%);
}

.red-bg {
  background: linear-gradient(135deg, #f44336 0%, #ef5350 100%);
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 12px;
  color: #757575;
  font-weight: 500;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 20px;
}

.action-btn {
  width: 100%;
  padding: 16px 24px;
  border-radius: 12px;
  border: none;
  font-size: 15px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.action-btn:active {
  transform: scale(0.98);
}

.logout-btn {
  background: linear-gradient(135deg, #f44336 0%, #ef5350 100%);
  color: white;
}

.logout-btn:hover {
  box-shadow: 0 4px 16px rgba(244, 67, 54, 0.4);
  transform: translateY(-2px);
}

.logout-btn-full {
  padding: 18px 24px;
  font-size: 16px;
}

/* Responsive */
@media (max-width: 400px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .stat-item {
    flex-direction: row;
    justify-content: flex-start;
    gap: 12px;
  }
  
  .stat-icon-wrapper {
    margin-bottom: 0;
  }
}
</style>
