// Service Worker for PWA
const CACHE_NAME = 'currency-exchange-v1'
const API_CACHE_NAME = 'currency-exchange-api-v1'

// 需要缓存的静态资源
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/manifest.json',
  '/icon-192x192.png',
  '/icon-512x512.png'
]

// API缓存策略配置
const API_CACHE_CONFIG = {
  // 缓存时间（毫秒）
  maxAge: 24 * 60 * 60 * 1000, // 24小时
  // 最大缓存条目数
  maxEntries: 100
}

// 安装事件
self.addEventListener('install', (event) => {
  console.log('[SW] Installing...')
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[SW] Caching static assets')
        return cache.addAll(STATIC_ASSETS)
      })
      .then(() => {
        console.log('[SW] Static assets cached')
        return self.skipWaiting()
      })
  )
})

// 激活事件
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating...')
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== CACHE_NAME && cacheName !== API_CACHE_NAME) {
              console.log('[SW] Deleting old cache:', cacheName)
              return caches.delete(cacheName)
            }
          })
        )
      })
      .then(() => {
        console.log('[SW] Claiming clients')
        return self.clients.claim()
      })
  )
})

// 请求拦截
self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)
  
  // 跳过非GET请求和Chrome扩展请求
  if (request.method !== 'GET' || url.protocol === 'chrome-extension:') {
    return
  }
  
  // API请求处理
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(handleApiRequest(request))
    return
  }
  
  // 静态资源处理
  event.respondWith(handleStaticRequest(request))
})

// 处理API请求 (Network First策略)
async function handleApiRequest(request) {
  const cache = await caches.open(API_CACHE_NAME)
  
  try {
    // 尝试网络请求
    const networkResponse = await fetch(request)
    
    if (networkResponse.ok) {
      // 缓存成功的响应
      cache.put(request, networkResponse.clone())
    }
    
    return networkResponse
  } catch (error) {
    console.log('[SW] Network failed, trying cache:', error)
    
    // 网络失败，尝试缓存
    const cachedResponse = await cache.match(request)
    
    if (cachedResponse) {
      console.log('[SW] Serving from cache:', request.url)
      return cachedResponse
    }
    
    // 返回离线页面或错误响应
    return new Response(
      JSON.stringify({
        message: '网络连接异常，请稍后重试',
        offline: true
      }),
      {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'application/json' }
      }
    )
  }
}

// 处理静态资源请求 (Cache First策略)
async function handleStaticRequest(request) {
  const cache = await caches.open(CACHE_NAME)
  
  // 先检查缓存
  const cachedResponse = await cache.match(request)
  
  if (cachedResponse) {
    return cachedResponse
  }
  
  try {
    // 缓存中没有，尝试网络请求
    const networkResponse = await fetch(request)
    
    if (networkResponse.ok) {
      // 缓存响应
      cache.put(request, networkResponse.clone())
    }
    
    return networkResponse
  } catch (error) {
    console.log('[SW] Failed to fetch:', request.url, error)
    
    // 对于导航请求，返回缓存的index.html
    if (request.mode === 'navigate') {
      const indexResponse = await cache.match('/index.html')
      if (indexResponse) {
        return indexResponse
      }
    }
    
    // 返回错误响应
    return new Response('离线状态，无法加载资源', {
      status: 503,
      statusText: 'Service Unavailable'
    })
  }
}

// 后台同步事件
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync:', event.tag)
  
  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync())
  }
})

// 执行后台同步
async function doBackgroundSync() {
  try {
    // 通知客户端执行同步
    const clients = await self.clients.matchAll()
    
    clients.forEach((client) => {
      client.postMessage({
        type: 'BACKGROUND_SYNC',
        payload: 'sync-offline-data'
      })
    })
    
    console.log('[SW] Background sync completed')
  } catch (error) {
    console.error('[SW] Background sync failed:', error)
  }
}

// 推送通知事件
self.addEventListener('push', (event) => {
  console.log('[SW] Push received:', event)
  
  const options = {
    body: event.data ? event.data.text() : '您有新的通知',
    icon: '/icon-192x192.png',
    badge: '/icon-192x192.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: '查看详情',
        icon: '/icon-192x192.png'
      },
      {
        action: 'close',
        title: '关闭',
        icon: '/icon-192x192.png'
      }
    ]
  }
  
  event.waitUntil(
    self.registration.showNotification('财务管理系统', options)
  )
})

// 通知点击事件
self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification click:', event)
  
  event.notification.close()
  
  if (event.action === 'explore') {
    // 打开应用
    event.waitUntil(
      clients.openWindow('/')
    )
  }
})

// 消息事件
self.addEventListener('message', (event) => {
  console.log('[SW] Message received:', event.data)
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting()
  }
})
