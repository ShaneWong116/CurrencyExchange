/**
 * 独立的认证API实例
 * 不包含业务拦截器，专门用于认证相关请求（login, refresh, logout）
 * 避免在刷新token时触发401拦截器导致死循环
 */
import axios from 'axios'

export const authApi = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// 仅添加基础的响应错误处理，不涉及token刷新逻辑
authApi.interceptors.response.use(
  (response) => response,
  (error) => {
    // 对于认证API，直接抛出错误，由调用方处理
    return Promise.reject(error)
  }
)

export default authApi

