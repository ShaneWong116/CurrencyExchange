import { defineStore } from 'pinia'
import { api } from '@/utils/api'
import { Notify } from 'quasar'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    accessToken: null,
    refreshToken: null,
    isLoading: false,
    lastActivity: Date.now()
  }),

  getters: {
    isAuthenticated: (state) => !!state.accessToken && !!state.user,
    userName: (state) => state.user?.name || state.user?.username || '',
    userId: (state) => state.user?.id
  },

  actions: {
    async login(credentials) {
      this.isLoading = true
      try {
        const response = await api.post('/auth/login', credentials)
        const { user, access_token, refresh_token } = response.data
        
        this.user = user
        this.accessToken = access_token
        this.refreshToken = refresh_token
        this.lastActivity = Date.now()
        
        // 设置API默认headers
        api.defaults.headers.common['Authorization'] = `Bearer ${access_token}`
        
        // 启动自动登出检查
        this.startAutoLogoutCheck()
        
        Notify.create({
          type: 'positive',
          message: '登录成功',
          position: 'top'
        })
        
        return { success: true }
      } catch (error) {
        const message = error.response?.data?.message || '登录失败'
        Notify.create({
          type: 'negative',
          message,
          position: 'top'
        })
        return { success: false, message }
      } finally {
        this.isLoading = false
      }
    },

    async refreshAccessToken() {
      if (!this.refreshToken) return false
      
      try {
        const response = await api.post('/auth/refresh', {}, {
          headers: { Authorization: `Bearer ${this.refreshToken}` }
        })
        
        this.accessToken = response.data.access_token
        api.defaults.headers.common['Authorization'] = `Bearer ${this.accessToken}`
        
        return true
      } catch (error) {
        this.logout()
        return false
      }
    },

    async logout() {
      if (this.accessToken) {
        try {
          await api.post('/auth/logout')
        } catch (error) {
          console.error('登出请求失败:', error)
        }
      }
      
      this.user = null
      this.accessToken = null
      this.refreshToken = null
      delete api.defaults.headers.common['Authorization']
      
      // 清除自动登出定时器
      if (this.logoutTimer) {
        clearTimeout(this.logoutTimer)
        this.logoutTimer = null
      }
      
      Notify.create({
        type: 'info',
        message: '已退出登录',
        position: 'top'
      })
    },

    updateActivity() {
      this.lastActivity = Date.now()
    },

    startAutoLogoutCheck() {
      const checkInterval = 60000 // 每分钟检查一次
      const autoLogoutTime = 15 * 60 * 1000 // 15分钟
      
      const check = () => {
        if (Date.now() - this.lastActivity > autoLogoutTime) {
          this.logout()
          return
        }
        
        // 在最后1分钟显示提醒
        if (Date.now() - this.lastActivity > (autoLogoutTime - 60000)) {
          Notify.create({
            type: 'warning',
            message: '即将自动登出，请点击任意位置保持登录',
            timeout: 10000,
            actions: [
              {
                label: '保持登录',
                color: 'white',
                handler: () => {
                  this.updateActivity()
                }
              }
            ]
          })
        }
        
        this.logoutTimer = setTimeout(check, checkInterval)
      }
      
      this.logoutTimer = setTimeout(check, checkInterval)
    },

    async fetchUserInfo() {
      if (!this.accessToken) return
      
      try {
        const response = await api.get('/auth/me')
        this.user = response.data
      } catch (error) {
        console.error('获取用户信息失败:', error)
      }
    }
  },

  persist: {
    key: 'auth',
    storage: localStorage,
    paths: ['user', 'accessToken', 'refreshToken']
  }
})
