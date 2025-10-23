<template>
  <q-page>
    <!-- 顶部用户信息 -->
    <div class="bg-primary text-white q-pa-md">
      <div class="row items-center">
        <div class="col">
          <div class="text-h6">{{ authStore.userName }}</div>
          <div class="text-caption opacity-80">{{ currentTime }}</div>
        </div>
        <div class="col-auto">
          <q-btn
            flat
            round
            icon="person"
            @click="$router.push('/profile')"
            class="text-white"
          />
        </div>
      </div>
    </div>

    <!-- 功能入口卡片 -->
    <div class="q-pa-md">
      <div class="row q-gutter-md">
        <!-- 交易录入 -->
        <div class="col-12 col-sm-6">
          <q-card class="info-card cursor-pointer" @click="$router.push('/transaction')">
            <q-card-section class="text-center">
              <q-icon name="add_circle" size="3rem" color="primary" />
              <div class="text-h6 q-mt-sm">交易录入</div>
              <div class="text-grey-7">入账 · 出账 · 即时买断</div>
            </q-card-section>
          </q-card>
        </div>

        <!-- 草稿管理 -->
        <div class="col-12 col-sm-6">
          <q-card class="info-card cursor-pointer" @click="$router.push('/drafts')">
            <q-card-section class="text-center">
              <q-icon name="edit_note" size="3rem" color="orange" />
              <div class="text-h6 q-mt-sm">草稿箱</div>
              <div class="text-grey-7">
                {{ draftStore.allDrafts.length }} 个草稿
                <q-badge v-if="draftStore.pendingCount > 0" color="red" floating>
                  {{ draftStore.pendingCount }}
                </q-badge>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- 同步状态 -->
        <div class="col-12 col-sm-6">
          <q-card class="info-card">
            <q-card-section class="text-center">
              <q-icon 
                :name="isOnline ? 'cloud_done' : 'cloud_off'" 
                size="3rem" 
                :color="isOnline ? 'positive' : 'negative'" 
              />
              <div class="text-h6 q-mt-sm">
                {{ isOnline ? '在线' : '离线' }}
              </div>
              <div class="text-grey-6">
                {{ isOnline ? '数据已同步' : '等待网络连接' }}
              </div>
              
              <q-btn
                v-if="!isOnline && (draftStore.localDrafts.length > 0 || draftStore.pendingCount > 0)"
                flat
                color="primary"
                label="重试同步"
                @click="handleSync"
                :loading="isSync"
                class="q-mt-sm"
              />
            </q-card-section>
          </q-card>
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
.cursor-pointer {
  cursor: pointer;
  transition: transform 0.2s;
}

.cursor-pointer:hover {
  transform: translateY(-2px);
}

.cursor-pointer:active {
  transform: translateY(0);
}
</style>

<style scoped>
.cursor-pointer {
  cursor: pointer;
  transition: transform 0.2s;
}

.cursor-pointer:hover {
  transform: translateY(-2px);
}

.cursor-pointer:active {
  transform: translateY(0);
}
</style>
