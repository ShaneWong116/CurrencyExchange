import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

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

// 路由守卫
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  
  // 检查是否需要登录
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
    return
  }
  
  // 已登录用户访问登录页，重定向到首页
  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    next('/home')
    return
  }
  
  next()
})

export default router
