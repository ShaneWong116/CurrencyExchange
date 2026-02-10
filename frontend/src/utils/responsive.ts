/**
 * 常用备注UI优化 - 响应式设计工具
 * 
 * 提供响应式设计相关的工具函数和组合式API
 * 支持断点检测、媒体查询和响应式样式计算
 */

import { ref, computed, onMounted, onUnmounted, type Ref } from 'vue'
import { breakpoints } from '../config/design-tokens'

// 断点类型
export type Breakpoint = 'mobile' | 'tablet' | 'desktop'

// 媒体查询工具类
export class MediaQueryManager {
  private queries: Map<string, MediaQueryList> = new Map()
  private listeners: Map<string, Set<(matches: boolean) => void>> = new Map()

  // 创建媒体查询
  createQuery(name: string, query: string): MediaQueryList {
    if (typeof window === 'undefined') {
      // SSR环境下的模拟对象
      return {
        matches: false,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
        onchange: null,
        dispatchEvent: () => false
      } as MediaQueryList
    }

    const mediaQuery = window.matchMedia(query)
    this.queries.set(name, mediaQuery)
    this.listeners.set(name, new Set())

    // 监听变化
    const handleChange = (e: MediaQueryListEvent) => {
      const callbacks = this.listeners.get(name)
      if (callbacks) {
        callbacks.forEach(callback => callback(e.matches))
      }
    }

    mediaQuery.addEventListener('change', handleChange)
    return mediaQuery
  }

  // 添加监听器
  addListener(name: string, callback: (matches: boolean) => void): void {
    const callbacks = this.listeners.get(name)
    if (callbacks) {
      callbacks.add(callback)
    }
  }

  // 移除监听器
  removeListener(name: string, callback: (matches: boolean) => void): void {
    const callbacks = this.listeners.get(name)
    if (callbacks) {
      callbacks.delete(callback)
    }
  }

  // 获取查询结果
  matches(name: string): boolean {
    const query = this.queries.get(name)
    return query ? query.matches : false
  }

  // 清理所有查询
  cleanup(): void {
    this.queries.clear()
    this.listeners.clear()
  }
}

// 全局媒体查询管理器
export const mediaQueryManager = new MediaQueryManager()

// 初始化标准断点查询
export const initializeBreakpointQueries = (): void => {
  mediaQueryManager.createQuery('mobile', `(max-width: ${breakpoints.tablet - 1}px)`)
  mediaQueryManager.createQuery('tablet', `(min-width: ${breakpoints.tablet}px) and (max-width: ${breakpoints.desktop - 1}px)`)
  mediaQueryManager.createQuery('desktop', `(min-width: ${breakpoints.desktop}px)`)
  mediaQueryManager.createQuery('mobile-up', `(min-width: ${breakpoints.mobile}px)`)
  mediaQueryManager.createQuery('tablet-up', `(min-width: ${breakpoints.tablet}px)`)
  mediaQueryManager.createQuery('desktop-up', `(min-width: ${breakpoints.desktop}px)`)
}

// 获取当前断点
export const getCurrentBreakpoint = (): Breakpoint => {
  if (typeof window === 'undefined') {
    return 'mobile'
  }

  const width = window.innerWidth

  if (width >= breakpoints.desktop) {
    return 'desktop'
  } else if (width >= breakpoints.tablet) {
    return 'tablet'
  } else {
    return 'mobile'
  }
}

// 检查是否为移动设备
export const isMobile = (): boolean => {
  return getCurrentBreakpoint() === 'mobile'
}

// 检查是否为平板设备
export const isTablet = (): boolean => {
  return getCurrentBreakpoint() === 'tablet'
}

// 检查是否为桌面设备
export const isDesktop = (): boolean => {
  return getCurrentBreakpoint() === 'desktop'
}

// 响应式断点组合式API
export const useBreakpoint = () => {
  const currentBreakpoint = ref<Breakpoint>(getCurrentBreakpoint())
  const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 0)
  const windowHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 0)

  // 计算属性
  const isMobile = computed(() => currentBreakpoint.value === 'mobile')
  const isTablet = computed(() => currentBreakpoint.value === 'tablet')
  const isDesktop = computed(() => currentBreakpoint.value === 'desktop')
  const isMobileOrTablet = computed(() => currentBreakpoint.value !== 'desktop')
  const isTabletOrDesktop = computed(() => currentBreakpoint.value !== 'mobile')

  // 更新断点
  const updateBreakpoint = () => {
    if (typeof window !== 'undefined') {
      windowWidth.value = window.innerWidth
      windowHeight.value = window.innerHeight
      currentBreakpoint.value = getCurrentBreakpoint()
    }
  }

  // 防抖处理
  let resizeTimer: number | null = null
  const handleResize = () => {
    if (resizeTimer) {
      clearTimeout(resizeTimer)
    }
    resizeTimer = window.setTimeout(updateBreakpoint, 100)
  }

  onMounted(() => {
    if (typeof window !== 'undefined') {
      updateBreakpoint()
      window.addEventListener('resize', handleResize)
    }
  })

  onUnmounted(() => {
    if (typeof window !== 'undefined') {
      window.removeEventListener('resize', handleResize)
    }
    if (resizeTimer) {
      clearTimeout(resizeTimer)
    }
  })

  return {
    currentBreakpoint: readonly(currentBreakpoint),
    windowWidth: readonly(windowWidth),
    windowHeight: readonly(windowHeight),
    isMobile: readonly(isMobile),
    isTablet: readonly(isTablet),
    isDesktop: readonly(isDesktop),
    isMobileOrTablet: readonly(isMobileOrTablet),
    isTabletOrDesktop: readonly(isTabletOrDesktop)
  }
}

// 媒体查询组合式API
export const useMediaQuery = (query: string) => {
  const matches = ref(false)

  onMounted(() => {
    if (typeof window !== 'undefined') {
      const mediaQuery = window.matchMedia(query)
      matches.value = mediaQuery.matches

      const handleChange = (e: MediaQueryListEvent) => {
        matches.value = e.matches
      }

      mediaQuery.addEventListener('change', handleChange)

      onUnmounted(() => {
        mediaQuery.removeEventListener('change', handleChange)
      })
    }
  })

  return readonly(matches)
}

// 响应式值工具
export const useResponsiveValue = <T>(values: {
  mobile: T
  tablet?: T
  desktop?: T
}): Ref<T> => {
  const { currentBreakpoint } = useBreakpoint()
  
  return computed(() => {
    switch (currentBreakpoint.value) {
      case 'desktop':
        return values.desktop ?? values.tablet ?? values.mobile
      case 'tablet':
        return values.tablet ?? values.mobile
      case 'mobile':
      default:
        return values.mobile
    }
  })
}

// 响应式字体大小
export const useResponsiveFontSize = (baseSizes: {
  mobile: string
  desktop: string
}) => {
  const { isDesktop } = useBreakpoint()
  
  return computed(() => {
    return isDesktop.value ? baseSizes.desktop : baseSizes.mobile
  })
}

// 响应式间距
export const useResponsiveSpacing = (spacings: {
  mobile: string
  desktop: string
}) => {
  const { isDesktop } = useBreakpoint()
  
  return computed(() => {
    return isDesktop.value ? spacings.desktop : spacings.mobile
  })
}

// 触控设备检测
export const isTouchDevice = (): boolean => {
  if (typeof window === 'undefined') {
    return false
  }
  
  return 'ontouchstart' in window || 
         navigator.maxTouchPoints > 0 || 
         (navigator as any).msMaxTouchPoints > 0
}

// 设备像素比检测
export const getDevicePixelRatio = (): number => {
  if (typeof window === 'undefined') {
    return 1
  }
  
  return window.devicePixelRatio || 1
}

// 视口尺寸工具
export const getViewportSize = () => {
  if (typeof window === 'undefined') {
    return { width: 0, height: 0 }
  }
  
  return {
    width: window.innerWidth,
    height: window.innerHeight
  }
}

// 安全区域检测（用于处理刘海屏等）
export const getSafeAreaInsets = () => {
  if (typeof window === 'undefined' || !CSS.supports('padding-top: env(safe-area-inset-top)')) {
    return { top: 0, right: 0, bottom: 0, left: 0 }
  }
  
  const computedStyle = getComputedStyle(document.documentElement)
  
  return {
    top: parseInt(computedStyle.getPropertyValue('env(safe-area-inset-top)')) || 0,
    right: parseInt(computedStyle.getPropertyValue('env(safe-area-inset-right)')) || 0,
    bottom: parseInt(computedStyle.getPropertyValue('env(safe-area-inset-bottom)')) || 0,
    left: parseInt(computedStyle.getPropertyValue('env(safe-area-inset-left)')) || 0
  }
}

// 响应式类名生成器
export const generateResponsiveClasses = (
  baseClass: string,
  breakpointModifiers?: Partial<Record<Breakpoint, string>>
): string => {
  const { currentBreakpoint } = useBreakpoint()
  const classes = [baseClass]
  
  if (breakpointModifiers) {
    const modifier = breakpointModifiers[currentBreakpoint.value]
    if (modifier) {
      classes.push(`${baseClass}--${modifier}`)
    }
  }
  
  classes.push(`${baseClass}--${currentBreakpoint.value}`)
  
  return classes.join(' ')
}

// 导出只读的ref工具函数
function readonly<T>(ref: Ref<T>): Readonly<Ref<T>> {
  return ref as Readonly<Ref<T>>
}