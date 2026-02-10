/**
 * 常用备注UI优化 - 主题管理组合式API
 * 
 * 提供主题切换、CSS变量管理和响应式主题功能
 * 支持系统主题跟随和用户偏好设置
 */

import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import type { ThemeConfig } from '../types/common-notes'
import { 
  lightTheme, 
  darkTheme, 
  themes,
  getCSSVariables,
  getResponsiveCSSVariables,
  applyTheme,
  getSystemThemePreference,
  watchSystemThemeChange
} from '../config/design-tokens'
import { useBreakpoint } from '../utils/responsive'

// 主题存储键
const THEME_STORAGE_KEY = 'common-notes-theme'
const THEME_MODE_STORAGE_KEY = 'common-notes-theme-mode'

// 主题模式类型
export type ThemeMode = 'light' | 'dark' | 'system'

// 主题状态管理
const currentThemeMode = ref<ThemeMode>('system')
const currentTheme = ref<ThemeConfig>(lightTheme)
const systemTheme = ref<'light' | 'dark'>(getSystemThemePreference())

// 主题管理组合式API
export const useTheme = () => {
  const { currentBreakpoint } = useBreakpoint()
  
  // 计算当前实际主题
  const actualTheme = computed(() => {
    if (currentThemeMode.value === 'system') {
      return themes[systemTheme.value]
    }
    return currentTheme.value
  })
  
  // 计算当前主题模式
  const isDark = computed(() => actualTheme.value.mode === 'dark')
  const isLight = computed(() => actualTheme.value.mode === 'light')
  
  // 获取响应式CSS变量
  const cssVariables = computed(() => {
    return getResponsiveCSSVariables(actualTheme.value, currentBreakpoint.value)
  })
  
  // 设置主题模式
  const setThemeMode = (mode: ThemeMode) => {
    currentThemeMode.value = mode
    
    if (mode !== 'system') {
      currentTheme.value = themes[mode as 'light' | 'dark']
    }
    
    // 保存到本地存储
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(THEME_MODE_STORAGE_KEY, mode)
      if (mode !== 'system') {
        localStorage.setItem(THEME_STORAGE_KEY, JSON.stringify(currentTheme.value))
      }
    }
  }
  
  // 切换主题
  const toggleTheme = () => {
    if (currentThemeMode.value === 'system') {
      setThemeMode(systemTheme.value === 'dark' ? 'light' : 'dark')
    } else {
      setThemeMode(currentTheme.value.mode === 'dark' ? 'light' : 'dark')
    }
  }
  
  // 设置自定义主题
  const setCustomTheme = (theme: ThemeConfig) => {
    currentTheme.value = theme
    currentThemeMode.value = theme.mode
    
    // 保存到本地存储
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(THEME_STORAGE_KEY, JSON.stringify(theme))
      localStorage.setItem(THEME_MODE_STORAGE_KEY, theme.mode)
    }
  }
  
  // 重置为系统主题
  const resetToSystemTheme = () => {
    setThemeMode('system')
  }
  
  // 应用主题到DOM
  const applyThemeToDOM = (element?: HTMLElement) => {
    applyTheme(actualTheme.value, element)
  }
  
  // 获取主题CSS类名
  const getThemeClasses = () => {
    return {
      'theme-light': isLight.value,
      'theme-dark': isDark.value,
      'theme-system': currentThemeMode.value === 'system'
    }
  }
  
  // 从本地存储加载主题
  const loadThemeFromStorage = () => {
    if (typeof localStorage === 'undefined') {
      return
    }
    
    try {
      const savedMode = localStorage.getItem(THEME_MODE_STORAGE_KEY) as ThemeMode
      const savedTheme = localStorage.getItem(THEME_STORAGE_KEY)
      
      if (savedMode) {
        currentThemeMode.value = savedMode
        
        if (savedMode !== 'system' && savedTheme) {
          const parsedTheme = JSON.parse(savedTheme) as ThemeConfig
          currentTheme.value = parsedTheme
        }
      }
    } catch (error) {
      console.warn('Failed to load theme from storage:', error)
    }
  }
  
  // 监听系统主题变化
  let systemThemeCleanup: (() => void) | null = null
  
  const startSystemThemeWatch = () => {
    systemThemeCleanup = watchSystemThemeChange((newSystemTheme) => {
      systemTheme.value = newSystemTheme
    })
  }
  
  const stopSystemThemeWatch = () => {
    if (systemThemeCleanup) {
      systemThemeCleanup()
      systemThemeCleanup = null
    }
  }
  
  // 监听主题变化并应用到DOM
  watch(
    actualTheme,
    (newTheme) => {
      applyThemeToDOM()
    },
    { immediate: true }
  )
  
  // 监听断点变化并更新CSS变量
  watch(
    currentBreakpoint,
    () => {
      applyThemeToDOM()
    }
  )
  
  return {
    // 状态
    currentThemeMode: readonly(currentThemeMode),
    currentTheme: readonly(actualTheme),
    systemTheme: readonly(systemTheme),
    isDark: readonly(isDark),
    isLight: readonly(isLight),
    cssVariables: readonly(cssVariables),
    
    // 方法
    setThemeMode,
    toggleTheme,
    setCustomTheme,
    resetToSystemTheme,
    applyThemeToDOM,
    getThemeClasses,
    loadThemeFromStorage,
    startSystemThemeWatch,
    stopSystemThemeWatch
  }
}

// 全局主题管理器
class ThemeManager {
  private themeComposable = useTheme()
  private initialized = false
  
  // 初始化主题管理器
  initialize() {
    if (this.initialized) {
      return
    }
    
    this.themeComposable.loadThemeFromStorage()
    this.themeComposable.startSystemThemeWatch()
    this.themeComposable.applyThemeToDOM()
    
    this.initialized = true
  }
  
  // 清理资源
  cleanup() {
    this.themeComposable.stopSystemThemeWatch()
    this.initialized = false
  }
  
  // 获取主题组合式API
  getThemeComposable() {
    return this.themeComposable
  }
}

// 全局主题管理器实例
export const themeManager = new ThemeManager()

// 主题提供者组合式API（用于根组件）
export const useThemeProvider = () => {
  onMounted(() => {
    themeManager.initialize()
  })
  
  onUnmounted(() => {
    themeManager.cleanup()
  })
  
  return themeManager.getThemeComposable()
}

// 主题消费者组合式API（用于子组件）
export const useThemeConsumer = () => {
  return themeManager.getThemeComposable()
}

// 主题工具函数
export const themeUtils = {
  // 创建主题变体
  createThemeVariant: (baseTheme: ThemeConfig, overrides: Partial<ThemeConfig>): ThemeConfig => {
    return { ...baseTheme, ...overrides }
  },
  
  // 检查主题对比度
  checkContrast: (backgroundColor: string, textColor: string): number => {
    // 简化的对比度计算，实际项目中可以使用更精确的算法
    const bgLuminance = getLuminance(backgroundColor)
    const textLuminance = getLuminance(textColor)
    
    const lighter = Math.max(bgLuminance, textLuminance)
    const darker = Math.min(bgLuminance, textLuminance)
    
    return (lighter + 0.05) / (darker + 0.05)
  },
  
  // 生成主题调色板
  generatePalette: (primaryColor: string): Record<string, string> => {
    // 简化的调色板生成，实际项目中可以使用更复杂的算法
    return {
      primary: primaryColor,
      primaryLight: lightenColor(primaryColor, 0.2),
      primaryDark: darkenColor(primaryColor, 0.2),
      secondary: adjustHue(primaryColor, 30),
      accent: adjustHue(primaryColor, 180)
    }
  }
}

// 辅助函数
function getLuminance(color: string): number {
  // 简化的亮度计算
  const hex = color.replace('#', '')
  const r = parseInt(hex.substr(0, 2), 16) / 255
  const g = parseInt(hex.substr(2, 2), 16) / 255
  const b = parseInt(hex.substr(4, 2), 16) / 255
  
  return 0.299 * r + 0.587 * g + 0.114 * b
}

function lightenColor(color: string, amount: number): string {
  // 简化的颜色变亮函数
  return color // 实际实现需要颜色处理库
}

function darkenColor(color: string, amount: number): string {
  // 简化的颜色变暗函数
  return color // 实际实现需要颜色处理库
}

function adjustHue(color: string, degrees: number): string {
  // 简化的色相调整函数
  return color // 实际实现需要颜色处理库
}

// 导出只读的ref工具函数
function readonly<T>(ref: any): T {
  return ref as T
}