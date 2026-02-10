import { createApp } from 'vue'
import { Quasar, Dialog, Notify, Loading } from 'quasar'
import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'

// 启用 vConsole 调试工具（通过 URL 参数 ?debug=1 或 localStorage）
const urlParams = new URLSearchParams(window.location.search)
if (urlParams.get('debug') === '1' || localStorage.getItem('enableVConsole') === 'true') {
  localStorage.setItem('enableVConsole', 'true')
  import('vconsole').then(({ default: VConsole }) => {
    new VConsole()
  })
}

// Import icon libraries (FontAwesome via extras; Material Icons 由 index.html CDN 引入)
import '@quasar/extras/fontawesome-v6/fontawesome-v6.css'

// Import Quasar css
import 'quasar/src/css/index.sass'

// Import common notes UI optimization styles
import './styles/common-notes-variables.css'
import './styles/common-notes.css'

// Assumes your root component is App.vue
// and placed in same folder as main.js
import App from './App.vue'
import router from './router'
import { useAuthStore } from '@/stores/auth'

// PWA注册
import { registerSW } from 'virtual:pwa-register'

const updateSW = registerSW({
  onNeedRefresh() {
    // 显示更新提示
    console.log('App需要更新')
  },
  onOfflineReady() {
    console.log('App已可离线使用')
  },
})

const pinia = createPinia()
pinia.use(piniaPluginPersistedstate)

const myApp = createApp(App)

myApp.use(Quasar, {
  plugins: {
    Dialog,
    Notify,
    Loading
  },
  config: {
    notify: {
      position: 'top',
      timeout: 3000
    }
  }
})

myApp.use(pinia)

// 在pinia初始化后、router使用前，初始化认证状态
// 这样可以确保从localStorage恢复的token能正确设置authState
const authStore = useAuthStore()
authStore.initializeAuth()

myApp.use(router)

// 全局错误处理器
myApp.config.errorHandler = (err, instance, info) => {
  console.error('[Global Error Handler]', err, info)
  
  // 对于关键错误显示通知
  if (err.message && !err.message.includes('Navigation')) {
    Notify.create({
      type: 'negative',
      message: '应用发生错误，请刷新页面重试',
      position: 'top',
      timeout: 5000,
      actions: [
        {
          label: '刷新',
          color: 'white',
          handler: () => {
            window.location.reload()
          }
        }
      ]
    })
  }
}

// 监听未捕获的Promise rejection
window.addEventListener('unhandledrejection', (event) => {
  console.error('[Unhandled Promise Rejection]', event.reason)
  
  // 如果是网络错误或认证错误，不重复提示（已在拦截器中处理）
  if (event.reason?.message?.includes('401') || 
      event.reason?.message?.includes('Token') ||
      event.reason?.message?.includes('refresh')) {
    return
  }
  
  // 其他未处理的错误显示提示
  Notify.create({
    type: 'negative',
    message: '操作失败，请重试',
    position: 'top'
  })
})

// 监听网络状态变化
window.addEventListener('online', () => {
  console.log('[Network] 网络已连接')
  Notify.create({
    type: 'positive',
    message: '网络已恢复连接',
    position: 'top'
  })
})

window.addEventListener('offline', () => {
  console.log('[Network] 网络已断开')
  Notify.create({
    type: 'warning',
    message: '网络连接已断开，请检查网络设置',
    position: 'top',
    timeout: 0,
    actions: [
      {
        label: '知道了',
        color: 'white'
      }
    ]
  })
})

myApp.mount('#app')
