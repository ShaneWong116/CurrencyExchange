<template>
  <div id="q-app">
    <q-layout view="lHh Lpr lFf">
      <q-page-container>
        <router-view />
      </q-page-container>
    </q-layout>
    
    <!-- 全局刷新Token Loading -->
    <q-dialog v-model="showRefreshingDialog" persistent>
      <q-card style="min-width: 300px">
        <q-card-section class="row items-center">
          <q-spinner color="primary" size="40px" class="q-mr-md" />
          <span class="q-ml-sm">正在刷新登录状态...</span>
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
import { defineComponent, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'

export default defineComponent({
  name: 'App',
  
  setup() {
    const authStore = useAuthStore()
    const { authState } = storeToRefs(authStore)
    const showRefreshingDialog = ref(false)
    
    // 监听认证状态，显示/隐藏刷新loading
    watch(authState, (newState) => {
      console.log('[App] authState changed:', newState)
      showRefreshingDialog.value = newState === 'refreshing'
    })
    
    return {
      showRefreshingDialog
    }
  }
})
</script>

<style>
/* 全局样式 */
.q-page {
  padding: 16px;
}

/* 移动端适配 */
@media (max-width: 600px) {
  .q-page {
    padding: 12px;
  }
}

/* 自定义样式 */
.full-width {
  width: 100%;
}

.text-center {
  text-align: center;
}

.q-mb-md {
  margin-bottom: 16px;
}

.q-mt-md {
  margin-top: 16px;
}

/* 表单输入框样式 */
.q-field {
  margin-bottom: 16px;
}

/* 按钮样式 */
.action-button {
  width: 100%;
  height: 48px;
  font-size: 16px;
  font-weight: 500;
}

/* 卡片样式 */
.info-card {
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* 列表项样式 */
.list-item {
  border-radius: 8px;
  margin-bottom: 8px;
  background: white;
}

/* 安全区域适配 */
.safe-area-bottom {
  padding-bottom: env(safe-area-inset-bottom);
}
</style>
