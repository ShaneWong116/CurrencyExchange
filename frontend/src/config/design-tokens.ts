/**
 * 常用备注UI优化 - 设计令牌配置
 * 
 * 定义了设计系统的核心令牌，包括颜色、字体、间距等
 * 支持响应式设计和主题切换
 */

import type { DesignTokens, ThemeConfig, BreakpointConfig } from '../types/common-notes'

// 响应式断点配置
export const breakpoints: BreakpointConfig = {
  mobile: 320,
  tablet: 768,
  desktop: 1024
}

// 基础设计令牌
export const designTokens: DesignTokens = {
  // 字体大小配置
  fontSize: {
    base: {
      mobile: '14px',
      desktop: '14px'
    },
    title: {
      mobile: '15px',
      desktop: '16px'
    },
    caption: {
      mobile: '12px',
      desktop: '12px'
    }
  },
  
  // 间距配置
  spacing: {
    component: {
      mobile: '8px',
      desktop: '12px'
    },
    content: '12px',
    listItem: '8px',
    button: '8px 16px'
  },
  
  // 触控目标配置
  touchTarget: {
    minSize: '36px',
    padding: '8px'
  },
  
  // 长备注配置
  longNote: {
    previewLength: {
      mobile: 100,
      desktop: 150
    },
    animation: {
      duration: '300ms',
      easing: 'ease-in-out'
    }
  },
  
  // 颜色配置
  colors: {
    primary: '#007AFF',
    secondary: '#5856D6',
    background: '#FFFFFF',
    surface: '#F8F9FA',
    text: {
      primary: '#333333',
      secondary: '#666666'
    },
    border: '#E5E5E5',
    accent: '#FF6B35'
  },
  
  // 边框和圆角
  border: {
    radius: '8px',
    width: '1px'
  }
}

// 亮色主题配置
export const lightTheme: ThemeConfig = {
  mode: 'light',
  primaryColor: '#007AFF',
  backgroundColor: '#FFFFFF',
  textColor: '#333333',
  borderColor: '#E5E5E5',
  accentColor: '#FF6B35'
}

// 暗色主题配置
export const darkTheme: ThemeConfig = {
  mode: 'dark',
  primaryColor: '#007AFF',
  backgroundColor: '#1A1A1A',
  textColor: '#FFFFFF',
  borderColor: '#404040',
  accentColor: '#FF6B35'
}

// 主题映射
export const themes = {
  light: lightTheme,
  dark: darkTheme
}

// CSS变量映射函数
export const getCSSVariables = (theme: ThemeConfig): Record<string, string> => {
  return {
    '--color-primary': theme.primaryColor,
    '--color-background': theme.backgroundColor,
    '--color-text-primary': theme.textColor,
    '--color-border': theme.borderColor,
    '--color-accent': theme.accentColor,
    
    // 字体大小
    '--font-size-base': designTokens.fontSize.base.mobile,
    '--font-size-title': designTokens.fontSize.title.mobile,
    '--font-size-caption': designTokens.fontSize.caption.mobile,
    
    // 间距
    '--spacing-component': designTokens.spacing.component.mobile,
    '--spacing-content-padding': designTokens.spacing.content,
    '--spacing-list-item': designTokens.spacing.listItem,
    
    // 触控目标
    '--touch-target-min-size': designTokens.touchTarget.minSize,
    '--touch-target-padding': designTokens.touchTarget.padding,
    
    // 长备注
    '--long-note-animation-duration': designTokens.longNote.animation.duration,
    '--long-note-animation-easing': designTokens.longNote.animation.easing,
    
    // 边框
    '--border-radius-md': designTokens.border.radius,
    '--border-width-thin': designTokens.border.width
  }
}

// 响应式CSS变量映射函数
export const getResponsiveCSSVariables = (
  theme: ThemeConfig,
  breakpoint: 'mobile' | 'tablet' | 'desktop'
): Record<string, string> => {
  const baseVariables = getCSSVariables(theme)
  
  if (breakpoint === 'desktop') {
    return {
      ...baseVariables,
      '--font-size-base': designTokens.fontSize.base.desktop,
      '--font-size-title': designTokens.fontSize.title.desktop,
      '--font-size-caption': designTokens.fontSize.caption.desktop,
      '--spacing-component': designTokens.spacing.component.desktop
    }
  }
  
  return baseVariables
}

// 主题切换工具函数
export const applyTheme = (theme: ThemeConfig, element: HTMLElement = document.documentElement): void => {
  const variables = getCSSVariables(theme)
  
  // 设置data-theme属性
  element.setAttribute('data-theme', theme.mode)
  
  // 应用CSS变量
  Object.entries(variables).forEach(([property, value]) => {
    element.style.setProperty(property, value)
  })
}

// 检测系统主题偏好
export const getSystemThemePreference = (): 'light' | 'dark' => {
  if (typeof window !== 'undefined' && window.matchMedia) {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }
  return 'light'
}

// 监听系统主题变化
export const watchSystemThemeChange = (callback: (theme: 'light' | 'dark') => void): (() => void) => {
  if (typeof window === 'undefined' || !window.matchMedia) {
    return () => {}
  }
  
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  
  const handleChange = (e: MediaQueryListEvent) => {
    callback(e.matches ? 'dark' : 'light')
  }
  
  mediaQuery.addEventListener('change', handleChange)
  
  // 返回清理函数
  return () => {
    mediaQuery.removeEventListener('change', handleChange)
  }
}

// 响应式断点检测工具
export const getCurrentBreakpoint = (): 'mobile' | 'tablet' | 'desktop' => {
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

// 监听断点变化
export const watchBreakpointChange = (callback: (breakpoint: 'mobile' | 'tablet' | 'desktop') => void): (() => void) => {
  if (typeof window === 'undefined') {
    return () => {}
  }
  
  let currentBreakpoint = getCurrentBreakpoint()
  
  const handleResize = () => {
    const newBreakpoint = getCurrentBreakpoint()
    if (newBreakpoint !== currentBreakpoint) {
      currentBreakpoint = newBreakpoint
      callback(newBreakpoint)
    }
  }
  
  window.addEventListener('resize', handleResize)
  
  // 返回清理函数
  return () => {
    window.removeEventListener('resize', handleResize)
  }
}

// 预设配置导出
export const presetConfigs = {
  // 移动端优化配置
  mobile: {
    theme: lightTheme,
    designTokens: {
      ...designTokens,
      fontSize: {
        ...designTokens.fontSize,
        base: { mobile: '18px', desktop: '16px' }, // 移动端字体更大
        title: { mobile: '20px', desktop: '18px' },
        caption: { mobile: '16px', desktop: '14px' }
      },
      spacing: {
        ...designTokens.spacing,
        component: { mobile: '20px', desktop: '16px' }, // 移动端间距更大
        content: '20px'
      }
    }
  },
  
  // 桌面端优化配置
  desktop: {
    theme: lightTheme,
    designTokens: {
      ...designTokens,
      spacing: {
        ...designTokens.spacing,
        component: { mobile: '16px', desktop: '12px' }, // 桌面端更紧凑
        content: '12px'
      }
    }
  },
  
  // 高对比度配置
  highContrast: {
    theme: {
      ...lightTheme,
      textColor: '#000000',
      backgroundColor: '#FFFFFF',
      borderColor: '#000000'
    },
    designTokens
  }
}

// 默认配置
export const defaultConfig = {
  theme: lightTheme,
  designTokens,
  breakpoints
}