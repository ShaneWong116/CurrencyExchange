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

// 请求队列管理
let isRefreshingToken = false
let failedRequestsQueue = []

/**
 * 处理请求队列
 * @param {Error|null} error - 错误对象，如果为null表示刷新成功
 * @param {string|null} token - 新的access token
 */
const processQueue = (error, token = null) => {
  failedRequestsQueue.forEach(promise => {
    if (error) {
      promise.reject(error)
    } else {
      promise.resolve(token)
    }
  })
  
  failedRequestsQueue = []
}

// 请求拦截器
api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()
    
    // 更新用户活动时间
    if (authStore.updateActivity && authStore.authState === 'authenticated') {
      authStore.updateActivity()
    }
    
    // 如果有token，添加到请求头
    if (authStore.accessToken) {
      config.headers.Authorization = `Bearer ${authStore.accessToken}`
    }
    
    return config
  },
  (error) => {
    console.error('[API] 请求拦截器错误:', error)
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
    
    // 处理401错误（未授权）
    if (error.response?.status === 401) {
      console.log('[API] 收到401错误:', originalRequest.url)
      
      // 如果请求已经重试过，不再重试，直接强制登出
      if (originalRequest._retry) {
        console.error('[API] 请求已重试过，强制登出')
        await authStore.forceLogout('身份验证失败，请重新登录')
        return Promise.reject(error)
      }
      
      // 标记请求已重试
      originalRequest._retry = true
      
      // 如果当前正在刷新token，将请求加入队列等待
      if (isRefreshingToken) {
        console.log('[API] 正在刷新token，请求加入队列:', originalRequest.url)
        
        return new Promise((resolve, reject) => {
          failedRequestsQueue.push({ resolve, reject })
        })
          .then(token => {
            // token刷新成功，更新请求头并重试
            originalRequest.headers.Authorization = `Bearer ${token}`
            return api(originalRequest)
          })
          .catch(err => {
            // token刷新失败，拒绝请求
            return Promise.reject(err)
          })
      }
      
      // 开始刷新token流程
      isRefreshingToken = true
      console.log('[API] 开始刷新token流程')
      
      try {
        // 调用authStore的刷新方法（该方法使用独立的authApi，不会触发此拦截器）
        const refreshed = await authStore.refreshAccessToken()
        
        if (refreshed) {
          // 刷新成功
          console.log('[API] Token刷新成功，处理队列中的请求')
          const newToken = authStore.accessToken
          
          // 处理队列中的请求
          processQueue(null, newToken)
          
          // 更新当前请求的token并重试
          originalRequest.headers.Authorization = `Bearer ${newToken}`
          return api(originalRequest)
        } else {
          // 刷新失败
          console.error('[API] Token刷新失败')
          const refreshError = new Error('Token刷新失败')
          processQueue(refreshError, null)
          return Promise.reject(refreshError)
        }
      } catch (err) {
        console.error('[API] Token刷新异常:', err)
        processQueue(err, null)
        return Promise.reject(err)
      } finally {
        isRefreshingToken = false
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
    } else if (error.response?.status === 400) {
      const message = error.response?.data?.message || '请求参数错误'
      Notify.create({
        type: 'negative',
        message,
        position: 'top'
      })
    } else if (!error.response) {
      // 网络错误
      console.error('[API] 网络错误:', error.message)
      Notify.create({
        type: 'negative',
        message: '网络连接错误，请检查网络设置',
        position: 'top'
      })
    }
    
    return Promise.reject(error)
  }
)

export default api
