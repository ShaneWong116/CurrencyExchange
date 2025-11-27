import { defineStore } from 'pinia'
import { api } from '@/utils/api'
import { authApi } from '@/utils/authApi'
import { Notify } from 'quasar'

// 导入router用于强制跳转
let router = null
export const setAuthRouter = (r) => {
  router = r
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    accessToken: null,
    refreshToken: null,
    isLoading: false,
    lastActivity: Date.now(),
    // 认证状态机: 'authenticated' | 'refreshing' | 'unauthenticated'
    authState: 'unauthenticated',
    // 保存正在进行的刷新Promise，避免并发刷新
    refreshPromise: null
  }),

  getters: {
    isAuthenticated: (state) => state.authState === 'authenticated' && !!state.accessToken && !!state.user,
    userName: (state) => state.user?.name || state.user?.username || '',
    userId: (state) => state.user?.id,
    isRefreshing: (state) => state.authState === 'refreshing'
  },

  actions: {
    /**
     * 初始化认证状态 - 在应用启动时调用
     * 用于从localStorage恢复后，根据token状态设置authState
     */
    initializeAuth() {
      console.log('[Auth] 初始化认证状态...')
      
      // 尝试手动从localStorage读取，确保数据已加载
      if (!this.accessToken || !this.user) {
        try {
          const stored = localStorage.getItem('auth')
          if (stored) {
            const parsed = JSON.parse(stored)
            // 检查是否包含必要字段
            if (parsed.accessToken && parsed.user) {
              console.log('[Auth] 手动恢复localStorage数据')
              this.accessToken = parsed.accessToken
              this.user = parsed.user
              this.refreshToken = parsed.refreshToken || null
              this.lastActivity = parsed.lastActivity || Date.now()
            }
          }
        } catch (e) {
          console.error('[Auth] 手动读取localStorage失败:', e)
        }
      }
      
      // 如果有accessToken和user，说明是从localStorage恢复的
      if (this.accessToken && this.user) {
        console.log('[Auth] 检测到已保存的认证信息，恢复认证状态')
        this.authState = 'authenticated'
        
        // 设置API默认headers
        api.defaults.headers.common['Authorization'] = `Bearer ${this.accessToken}`
        
        // 启动自动登出检查
        this.startAutoLogoutCheck()
        
        // 更新最后活动时间
        this.lastActivity = Date.now()
      } else {
        console.log('[Auth] 无有效认证信息')
        this.authState = 'unauthenticated'
      }
    },

    async login(credentials) {
      this.isLoading = true
      try {
        // 使用独立的authApi进行登录请求
        const response = await authApi.post('/auth/login', credentials)
        const { user, access_token, refresh_token } = response.data
        
        this.user = user
        this.accessToken = access_token
        this.refreshToken = refresh_token
        this.lastActivity = Date.now()
        this.authState = 'authenticated'
        
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
        this.authState = 'unauthenticated'
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
      // 如果没有refreshToken，直接返回失败
      if (!this.refreshToken) {
        console.warn('[Auth] 没有refresh token，无法刷新')
        return false
      }
      
      // 如果已经在刷新中，返回现有的Promise，避免并发刷新
      if (this.refreshPromise) {
        console.log('[Auth] 已有刷新请求进行中，等待结果...')
        return this.refreshPromise
      }
      
      // 设置状态为刷新中
      this.authState = 'refreshing'
      console.log('[Auth] 开始刷新access token...')
      
      // 创建刷新Promise
      this.refreshPromise = (async () => {
        try {
          // 使用独立的authApi，不走业务拦截器，避免死循环
          const response = await authApi.post('/auth/refresh', {}, {
            headers: { Authorization: `Bearer ${this.refreshToken}` },
            timeout: 10000 // 10秒超时
          })
          
          // 刷新成功，更新token
          this.accessToken = response.data.access_token
          api.defaults.headers.common['Authorization'] = `Bearer ${this.accessToken}`
          this.authState = 'authenticated'
          this.lastActivity = Date.now()
          
          console.log('[Auth] Token刷新成功')
          return true
        } catch (error) {
          console.error('[Auth] Token刷新失败:', error.response?.status, error.message)
          
          // 刷新失败，强制登出
          this.authState = 'unauthenticated'
          await this.forceLogout('登录已过期，请重新登录')
          
          return false
        } finally {
          // 清除refreshPromise，允许下次刷新
          this.refreshPromise = null
        }
      })()
      
      return this.refreshPromise
    },

    async logout() {
      console.log('[Auth] 用户主动登出')
      
      if (this.accessToken) {
        try {
          // 使用authApi发送登出请求，避免触发拦截器
          await authApi.post('/auth/logout', {}, {
            headers: { Authorization: `Bearer ${this.accessToken}` }
          })
        } catch (error) {
          console.error('[Auth] 登出请求失败:', error)
        }
      }
      
      this.clearAuthState()
      
      Notify.create({
        type: 'info',
        message: '已退出登录',
        position: 'top'
      })
      
      // 使用router跳转到登录页
      if (router) {
        router.push('/login').catch(err => {
          console.error('[Auth] 路由跳转失败:', err)
        })
      }
    },

    /**
     * 强制登出 - 用于token失效等异常情况
     * @param {string} message - 提示消息
     */
    async forceLogout(message = '登录已过期，请重新登录') {
      console.log('[Auth] 强制登出:', message)
      
      // 立即清除所有认证状态
      this.clearAuthState()
      
      // 显示提示
      Notify.create({
        type: 'warning',
        message,
        position: 'top',
        timeout: 5000
      })
      
      // 使用router跳转到登录页
      if (router) {
        await router.push('/login').catch(err => {
          console.error('[Auth] 路由跳转失败，使用location跳转:', err)
          // 如果router跳转失败，使用window.location作为后备
          window.location.href = '/login'
        })
      } else {
        // 如果没有router，使用window.location
        console.warn('[Auth] Router未初始化，使用location跳转')
        window.location.href = '/login'
      }
    },

    /**
     * 清除所有认证状态
     */
    clearAuthState() {
      console.log('[Auth] 清除所有认证状态')
      
      this.user = null
      this.accessToken = null
      this.refreshToken = null
      this.authState = 'unauthenticated'
      this.refreshPromise = null
      
      // 清除API默认header
      delete api.defaults.headers.common['Authorization']
      
      // 清除自动登出定时器
      if (this.logoutTimer) {
        clearTimeout(this.logoutTimer)
        this.logoutTimer = null
      }
      
      // 清除localStorage中的持久化数据
      try {
        localStorage.removeItem('auth')
      } catch (error) {
        console.error('[Auth] 清除localStorage失败:', error)
      }
    },

    updateActivity() {
      this.lastActivity = Date.now()
    },

    startAutoLogoutCheck() {
      const checkInterval = 60000 // 每分钟检查一次
      const autoLogoutTime = 15 * 60 * 1000 // 15分钟
      
      const check = () => {
        // 如果已经不是认证状态，停止检查
        if (this.authState !== 'authenticated') {
          return
        }
        
        if (Date.now() - this.lastActivity > autoLogoutTime) {
          console.log('[Auth] 自动登出：超时未活动')
          this.forceLogout('长时间未操作，已自动退出登录')
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
    // 持久化用户信息和token，但不持久化authState和refreshPromise
    paths: ['user', 'accessToken', 'refreshToken', 'lastActivity']
  }
})
