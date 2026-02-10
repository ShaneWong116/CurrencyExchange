<!--
  常用备注UI优化 - 标题区域组件
  
  提供标题显示和展开/收起控制功能
  需求: 1.1, 1.2, 4.2, 4.3
-->
<template>
  <div 
    class="notes-header"
    :class="headerClasses"
  >
    <button
      class="header-toggle-btn"
      :class="toggleButtonClasses"
      :aria-expanded="expanded"
      :aria-label="expanded ? '收起常用备注' : '展开常用备注'"
      @click="handleToggle"
    >
      <!-- 图标 -->
      <q-icon 
        :name="expanded ? 'expand_less' : 'expand_more'"
        class="toggle-icon"
        :class="iconClasses"
      />
      
      <!-- 标题文字 -->
      <span class="header-title">{{ title }}</span>
      
      <!-- 状态指示器 -->
      <div 
        class="status-indicator"
        :class="statusClasses"
        :aria-hidden="true"
      />
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { NotesHeaderProps } from '../types/common-notes'
import { useBreakpoint } from '../utils/responsive'

// Props定义
interface Props {
  title: string
  expanded: boolean
  theme?: any
}

const props = withDefaults(defineProps<Props>(), {
  title: '常用备注'
})

// Emits定义
const emit = defineEmits<{
  'toggle': [expanded: boolean]
}>()

// 组合式API
const { isMobile, isTablet, isDesktop } = useBreakpoint()

// 计算属性
const headerClasses = computed(() => ({
  'notes-header--mobile': isMobile.value,
  'notes-header--tablet': isTablet.value,
  'notes-header--desktop': isDesktop.value,
  'notes-header--expanded': props.expanded,
  'notes-header--collapsed': !props.expanded
}))

const toggleButtonClasses = computed(() => ({
  'toggle-btn--expanded': props.expanded,
  'toggle-btn--collapsed': !props.expanded,
  'toggle-btn--mobile': isMobile.value,
  'toggle-btn--desktop': isDesktop.value
}))

const iconClasses = computed(() => ({
  'icon--expanded': props.expanded,
  'icon--collapsed': !props.expanded
}))

const statusClasses = computed(() => ({
  'status--expanded': props.expanded,
  'status--collapsed': !props.expanded
}))

// 事件处理
const handleToggle = () => {
  emit('toggle', !props.expanded)
}
</script>

<style scoped>
.notes-header {
  /* 基础布局 */
  display: flex;
  align-items: center;
  width: 100%;
  background-color: var(--color-surface);
  border-bottom: var(--border-width-thin) solid var(--color-border);
  
  /* 过渡动画 */
  transition: var(--transition-all);
}

.header-toggle-btn {
  /* 重置按钮样式 */
  all: unset;
  
  /* 布局 */
  display: flex;
  align-items: center;
  width: 100%;
  padding: var(--spacing-md);
  gap: var(--spacing-sm);
  
  /* 触控目标 */
  min-height: var(--touch-target-min-size);
  
  /* 交互样式 */
  cursor: pointer;
  background-color: transparent;
  border-radius: var(--border-radius-sm);
  
  /* 过渡动画 */
  transition: var(--transition-all);
  
  /* 无障碍 */
  outline: none;
  position: relative;
}

.header-toggle-btn:hover {
  background-color: var(--color-surface-elevated, rgba(0, 0, 0, 0.04));
}

.header-toggle-btn:focus-visible {
  outline: var(--border-width-medium) solid var(--color-primary);
  outline-offset: 2px;
}

.header-toggle-btn:active {
  background-color: var(--color-border-light, rgba(0, 0, 0, 0.08));
  transform: scale(0.98);
}

/* 图标样式 */
.toggle-icon {
  flex-shrink: 0;
  font-size: 20px;
  color: var(--color-text-secondary);
  transition: var(--transition-transform);
}

.icon--expanded .toggle-icon {
  transform: rotate(0deg);
}

.icon--collapsed .toggle-icon {
  transform: rotate(-90deg);
}

/* 标题样式 */
.header-title {
  flex: 1;
  font-size: var(--font-size-title);
  font-weight: var(--font-weight-medium);
  color: var(--color-text-primary);
  line-height: var(--line-height-title);
  text-align: left;
  
  /* 文本溢出处理 */
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* 状态指示器 */
.status-indicator {
  flex-shrink: 0;
  width: 8px;
  height: 8px;
  border-radius: var(--border-radius-round);
  background-color: var(--color-border);
  transition: var(--transition-all);
}

.status--expanded {
  background-color: var(--color-primary);
  box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.2);
}

.status--collapsed {
  background-color: var(--color-border);
}

/* 响应式适配 */
.notes-header--mobile .header-toggle-btn {
  padding: 8px 12px;
  gap: 8px;
}

.notes-header--mobile .header-title {
  font-size: 15px;
}

.notes-header--desktop .header-toggle-btn {
  padding: var(--spacing-sm) var(--spacing-md);
  gap: var(--spacing-sm);
}

.notes-header--desktop .header-title {
  font-size: var(--font-size-title-desktop);
}

/* 移动端触控优化 */
.toggle-btn--mobile {
  min-height: 36px;
  padding: 8px 12px;
}

.toggle-btn--desktop {
  min-height: 36px;
}

/* 展开/收起状态样式 */
.notes-header--expanded {
  background-color: var(--color-surface);
}

.notes-header--collapsed {
  background-color: var(--color-background);
}

.toggle-btn--expanded:hover {
  background-color: rgba(0, 122, 255, 0.08);
}

.toggle-btn--collapsed:hover {
  background-color: var(--color-surface);
}

/* 主题适配 */
.theme-dark .notes-header {
  background-color: var(--color-surface);
  border-bottom-color: var(--color-border);
}

.theme-dark .header-toggle-btn:hover {
  background-color: rgba(255, 255, 255, 0.08);
}

.theme-dark .toggle-btn--expanded:hover {
  background-color: rgba(0, 122, 255, 0.15);
}

/* 高对比度模式 */
@media (prefers-contrast: high) {
  .notes-header {
    border-bottom-width: var(--border-width-medium);
  }
  
  .header-toggle-btn:focus-visible {
    outline-width: 3px;
  }
  
  .status-indicator {
    border: var(--border-width-thin) solid var(--color-text-primary);
  }
}

/* 减少动画偏好 */
@media (prefers-reduced-motion: reduce) {
  .header-toggle-btn,
  .toggle-icon,
  .status-indicator {
    transition: none;
  }
  
  .header-toggle-btn:active {
    transform: none;
  }
  
  .icon--collapsed .toggle-icon {
    transform: none;
  }
}

/* 打印样式 */
@media print {
  .notes-header {
    background-color: transparent;
    border-bottom: var(--border-width-thin) solid #000000;
  }
  
  .status-indicator {
    display: none;
  }
}
</style>