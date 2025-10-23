import { createApp } from 'vue'
import { Quasar, Dialog, Notify, Loading } from 'quasar'
import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'

// Import icon libraries (FontAwesome via extras; Material Icons 由 index.html CDN 引入)
import '@quasar/extras/fontawesome-v6/fontawesome-v6.css'

// Import Quasar css
import 'quasar/src/css/index.sass'

// Assumes your root component is App.vue
// and placed in same folder as main.js
import App from './App.vue'
import router from './router'

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
myApp.use(router)

myApp.mount('#app')
