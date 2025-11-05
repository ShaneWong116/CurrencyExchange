import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore, setAuthRouter } from '@/stores/auth'
import { Notify } from 'quasar'

const routes = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/pages/LoginPage.vue'),
    meta: { requiresGuest: true }
  },
  {
    path: '/home',
    name: 'Home',
    component: () => import('@/pages/HomePage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/transaction',
    name: 'Transaction',
    component: () => import('@/pages/TransactionPage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/transaction/entry/:type',
    name: 'TransactionEntry',
    component: () => import('@/pages/TransactionEntryPage.vue'),
    meta: { requiresAuth: true },
    props: true
  },
  {
    path: '/drafts',
    name: 'Drafts',
    component: () => import('@/pages/DraftsPage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/drafts/:id/edit',
    name: 'DraftEdit',
    component: () => import('@/pages/TransactionEntryPage.vue'),
    meta: { requiresAuth: true },
    props: true
  },
  {
    path: '/history',
    name: 'History',
    component: () => import('@/pages/HistoryPage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/records',
    name: 'Records',
    component: () => import('@/pages/RecordsPage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/profile',
    name: 'Profile',
    component: () => import('@/pages/ProfilePage.vue'),
    meta: { requiresAuth: true }
  },
  // 结余管理
  {
    path: '/settlements',
    name: 'SettlementList',
    component: () => import('@/pages/SettlementListPage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settlement/preview',
    name: 'SettlementPreview',
    component: () => import('@/pages/SettlementPreviewPage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settlements/:id',
    name: 'SettlementDetail',
    component: () => import('@/pages/SettlementDetailPage.vue'),
    meta: { requiresAuth: true },
    props: true
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/pages/NotFoundPage.vue')
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 将router实例注入到authStore，用于强制跳转
setAuthRouter(router)

// 路由守卫
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  
  console.log('[Router] 导航:', from.path, '->', to.path, '| authState:', authStore.authState)
  
  // 如果正在刷新token，显示提示并等待
  if (authStore.isRefreshing && to.meta.requiresAuth) {
    console.log('[Router] 正在刷新token，等待...')
    // 可以显示一个loading提示
    // 这里我们允许导航继续，让拦截器处理
  }
  
  // 检查是否需要登录
  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated) {
      console.log('[Router] 需要认证，重定向到登录页')
      Notify.create({
        type: 'warning',
        message: '请先登录',
        position: 'top'
      })
      next('/login')
      return
    }
  }
  
  // 已登录用户访问登录页，重定向到首页
  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    console.log('[Router] 已登录用户访问登录页，重定向到首页')
    next('/home')
    return
  }
  
  next()
})

// 全局导航错误处理
router.onError((error) => {
  console.error('[Router] 导航错误:', error)
  Notify.create({
    type: 'negative',
    message: '页面跳转失败，请重试',
    position: 'top'
  })
})

export default router
