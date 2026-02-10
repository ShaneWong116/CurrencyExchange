/**
 * 常用备注UI优化 - TypeScript接口定义
 * 
 * 定义了常用备注组件的数据结构和接口规范
 * 支持响应式设计和主题切换功能
 */

// 基础备注数据结构
export interface Note {
  id: string | number
  content: string
  created_at: string
  updated_at: string
  user_id?: number
  user_type?: string
  category?: string
  priority?: 'low' | 'medium' | 'high'
}

// UI状态管理接口
export interface NotesUIState {
  expandedNotes: Set<string>
  selectedNote: string | null
  viewMode: 'compact' | 'comfortable'
  fontSize: 'small' | 'medium' | 'large'
}

// 主题配置接口
export interface ThemeConfig {
  mode: 'light' | 'dark'
  primaryColor: string
  backgroundColor: string
  textColor: string
  borderColor: string
  accentColor: string
}

// 响应式断点配置
export interface BreakpointConfig {
  mobile: number    // 320px
  tablet: number    // 768px
  desktop: number   // 1024px
}

// 设计令牌接口
export interface DesignTokens {
  // 字体大小配置
  fontSize: {
    base: {
      mobile: string
      desktop: string
    }
    title: {
      mobile: string
      desktop: string
    }
    caption: {
      mobile: string
      desktop: string
    }
  }
  
  // 间距配置
  spacing: {
    component: {
      mobile: string
      desktop: string
    }
    content: string
    listItem: string
    button: string
  }
  
  // 触控目标配置
  touchTarget: {
    minSize: string
    padding: string
  }
  
  // 长备注配置
  longNote: {
    previewLength: {
      mobile: number
      desktop: number
    }
    animation: {
      duration: string
      easing: string
    }
  }
  
  // 颜色配置
  colors: {
    primary: string
    secondary: string
    background: string
    surface: string
    text: {
      primary: string
      secondary: string
    }
    border: string
    accent: string
  }
  
  // 边框和圆角
  border: {
    radius: string
    width: string
  }
}

// CommonNotesUI 主组件接口
export interface CommonNotesUIProps {
  notes: Note[]
  onNoteSelect: (noteId: string) => void
  onNoteExpand: (noteId: string, expanded: boolean) => void
  maxPreviewLength: number
  theme: ThemeConfig
  designTokens?: DesignTokens
}

// NoteItem 组件接口
export interface NoteItemProps {
  note: Note
  isExpanded: boolean
  maxPreviewLength: number
  onExpand: (expanded: boolean) => void
  onSelect: () => void
  theme: ThemeConfig
}

// ExpandToggle 组件接口
export interface ExpandToggleProps {
  isExpanded: boolean
  hasLongContent: boolean
  onToggle: (expanded: boolean) => void
  expandText: string
  collapseText: string
  theme: ThemeConfig
}

// NotesHeader 组件接口
export interface NotesHeaderProps {
  title: string
  expanded: boolean
  onToggle: (expanded: boolean) => void
  theme: ThemeConfig
}

// NotesListContainer 组件接口
export interface NotesListContainerProps {
  notes: Note[]
  expandedNotes: Set<string>
  onNoteSelect: (note: Note) => void
  onNoteExpand: (noteId: string, expanded: boolean) => void
  maxPreviewLength: number
  theme: ThemeConfig
}

// EmptyState 组件接口
export interface EmptyStateProps {
  message: string
  icon?: string
  actionText?: string
  onAction?: () => void
  theme: ThemeConfig
}

// NotesFooter 组件接口
export interface NotesFooterProps {
  onAddNote: () => void
  theme: ThemeConfig
}

// 事件处理接口
export interface NotesEventHandlers {
  onNoteSelect: (note: Note) => void
  onNoteAdd: (content: string) => Promise<void>
  onNoteDelete: (noteId: string) => Promise<void>
  onNoteUpdate: (noteId: string, content: string) => Promise<void>
  onExpandToggle: (noteId: string, expanded: boolean) => void
}

// API响应接口
export interface NotesApiResponse<T = any> {
  success: boolean
  data: T
  message?: string
  error?: string
}

// 创建备注请求接口
export interface CreateNoteRequest {
  content: string
  category?: string
  priority?: 'low' | 'medium' | 'high'
}

// 更新备注请求接口
export interface UpdateNoteRequest {
  content?: string
  category?: string
  priority?: 'low' | 'medium' | 'high'
}

// 组件配置接口
export interface ComponentConfig {
  enableVirtualScroll: boolean
  enableLazyLoading: boolean
  enableAnimation: boolean
  debounceDelay: number
  maxNotesPerPage: number
}

// 无障碍配置接口
export interface AccessibilityConfig {
  enableScreenReader: boolean
  enableKeyboardNavigation: boolean
  enableHighContrast: boolean
  focusIndicatorColor: string
}

// 性能配置接口
export interface PerformanceConfig {
  enableVirtualization: boolean
  chunkSize: number
  renderThreshold: number
  animationOptimization: boolean
}

// 完整的配置接口
export interface CommonNotesConfig {
  theme: ThemeConfig
  designTokens: DesignTokens
  breakpoints: BreakpointConfig
  component: ComponentConfig
  accessibility: AccessibilityConfig
  performance: PerformanceConfig
}