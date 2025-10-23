import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { Notify } from 'quasar'

// 创建axios实例
export const api = axios.create({
  // 开发环境优先走 Vite 代理（避免跨域）；生产可通过 VITE_API_BASE_URL 指定完整地址
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// 请求拦截器
api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()
    
    // 更新用户活动时间
    if (authStore.updateActivity) {
      authStore.updateActivity()
    }
    
    // 如果有token，添加到请求头
    if (authStore.accessToken) {
      config.headers.Authorization = `Bearer ${authStore.accessToken}`
    }
    
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// 响应拦截器
api.interceptors.response.use(
  (response) => {
    return response
  },
  async (error) => {
    const authStore = useAuthStore()
    const originalRequest = error.config
    
    // 如果是401错误且没有重试过
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true
      
      // 尝试刷新token
      const refreshed = await authStore.refreshAccessToken()
      
      if (refreshed) {
        // 重新发送原请求
        return api(originalRequest)
      } else {
        // 刷新失败，跳转到登录页
        authStore.logout()
        window.location.href = '/login'
        return Promise.reject(error)
      }
    }
    
    // 其他错误处理
    if (error.response?.status >= 500) {
      Notify.create({
        type: 'negative',
        message: '服务器错误，请稍后重试',
        position: 'top'
      })
    } else if (error.response?.status === 403) {
      Notify.create({
        type: 'negative',
        message: '无权限访问',
        position: 'top'
      })
    } else if (!error.response) {
      Notify.create({
        type: 'negative',
        message: '网络连接错误',
        position: 'top'
      })
    }
    
    return Promise.reject(error)
  }
)

export default api
