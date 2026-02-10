<!--
  常用备注UI优化 - 展开/收起控制组件
  
  提供长备注的展开/收起功能，支持键盘导航和动画效果
  需求: 3.1, 3.4, 3.5
-->
<template>
  <button
    class="expand-toggle"
    :class="toggleClasses"
    :aria-expanded="isExpanded"
    :aria-label="ariaLabel"
    @click="handleToggle"
    @keydown.enter="handleToggle"
    @keydown.space.prevent="handleToggle"
  >
    <!-- 图标 -->
    <q-icon 
      :name="iconName"
      class="toggle-icon"
      :class="iconClasses"
    />
    
    <!-- 文字标签 -->
    <span class="toggle-text">
      {{ toggleText }}
    </span>
    
    <!-- 动画指示器 -->
    <div 
      class="animation-indicator"
      :class="indicatorClasses"
      :aria-hidden="true"
    />
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useBreakpoint } from '../utils/responsive'

// Props定义
interface Props {
  isExpanded: boolean
  hasLongContent: boolean
  expandText?: string
  collapseText?: string
  theme?: any
}

const props = withDefaults(defineProps<Props>(), {
  expandText: '展开',
  collapseText: '收起'
})

// Emits定义
const emit = defineEmits<{
  'toggle': [expanded: boolean]
}>()

// 组合式API
const { isMobile, isDesktop } = useBreakpoint()

// 计算属性
const toggleClasses = computed(() => ({
  'expand-toggle--mobile': isMobile.value,
  'expand-toggle--desktop': isDesktop.value,
  'expand-toggle--expanded': props.isExpanded,
  'expand-toggle--collapsed': !props.isExpanded,
  'expand-toggle--has-long-content': props.hasLongContent
}))

const iconClasses = computed(() => ({
  'icon--expanded': props.isExpanded,
  'icon--collapsed': !props.isExpanded
}))

const indicatorClasses = computed(() => ({
  'indicator--expanded': props.isExpanded,
  'indicator--collapsed': !props.isExpanded
}))

const iconName = computed(() => {
  return props.isExpanded ? 'expand_less' : 'expand_more'
})

const toggleText = computed(() => {
  return props.isExpanded ? props.collapseText : props.expandText
})

const ariaLabel = computed(() => {
  const action = props.isExpanded ? '收起' : '展开'
  return `${action}备注内容`
})

// 事件处理
const handleToggle = () => {
  if (!props.hasLongContent) {
    return
  }
  
  emit('toggle', !props.isExpanded)
}
</script>

<style scoped>
.expand-toggle {
  /* 重置按钮样式 */
  all: unset;
  
  /* 基础布局 */
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-xs);
  
  /* 样式 */
  padding: var(--spacing-xs) var(--spacing-sm);
  border-radius: var(--border-radius-sm);
  background-color: transparent;
  border: var(--border-width-thin) solid var(--color-border);
  
  /* 交互 */
  cursor: pointer;
  transition: var(--transition-all);
  
  /* 触控目标 */
  min-height: var(--touch-target-min-size);
  min-width: var(--touch-target-min-size);
  
  /* 无障碍 */
  outline: none;
  position: relative;
}

.expand-toggle:hover {
  background-color: var(--color-surface);
  border-color: var(--color-primary);
}

.expand-toggle:focus-visible {
  outline: var(--border-width-medium) solid var(--color-primary);
  outline-offset: 2px;
}

.expand-toggle:active {
  background-color: var(--color-border-light);
  transform: scale(0.95);
}

/* 禁用状态 */
.expand-toggle:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

/* 图标样式 */
.toggle-icon {
  flex-shrink: 0;
  font-size: 16px;
  color: var(--color-text-secondary);
  transition: var(--transition-transform);
}

.icon--expanded .toggle-icon {
  transform: rotate(180deg);
}

.icon--collapsed .toggle-icon {
  transform: rotate(0deg);
}

/* 文字标签 */
.toggle-text {
  font-size: var(--font-size-caption);
  color: var(--color-text-secondary);
  font-weight: var(--font-weight-medium);
  white-space: nowrap;
  transition: var(--transition-all);
}

.expand-toggle:hover .toggle-text {
  color: var(--color-primary);
}

/* 动画指示器 */
.animation-indicator {
  flex-shrink: 0;
  width: 2px;
  height: 12px;
  border-radius: var(--border-radius-sm);
  background-color: var(--color-border);
  transition: var(--transition-all);
}

.indicator--expanded {
  background-color: var(--color-primary);
  height: 16px;
}

.indicator--collapsed {
  background-color: var(--color-border);
  height: 8px;
}

.expand-toggle:hover .animation-indicator {
  background-color: var(--color-primary);
}

/* 响应式适配 */
.expand-toggle--mobile {
  padding: var(--spacing-sm) var(--spacing-md);
  gap: var(--spacing-sm);
  border-radius: var(--border-radius-md);
  min-height: var(--touch-target-min-size);
}

.expand-toggle--mobile .toggle-icon {
  font-size: 18px;
}

.expand-toggle--mobile .toggle-text {
  font-size: var(--font-size-base);
}

.expand-toggle--desktop {
  padding: var(--spacing-xs) var(--spacing-sm);
  gap: var(--spacing-xs);
  min-height: 32px;
}

.expand-toggle--desktop .toggle-icon {
  font-size: 14px;
}

.expand-toggle--desktop .toggle-text {
  font-size: var(--font-size-caption-desktop);
}

/* 展开/收起状态样式 */
.expand-toggle--expanded {
  background-color: rgba(0, 122, 255, 0.1);
  border-color: var(--color-primary);
}

.expand-toggle--expanded .toggle-icon,
.expand-toggle--expanded .toggle-text {
  color: var(--color-primary);
}

.expand-toggle--collapsed:hover {
  background-color: var(--color-surface);
}

/* 长内容状态 */
.expand-toggle--has-long-content {
  opacity: 1;
  pointer-events: auto;
}

.expand-toggle--has-long-content:not(.expand-toggle--expanded):hover {
  border-color: var(--color-accent);
}

.expand-toggle--has-long-content:not(.expand-toggle--expanded):hover .toggle-text {
  color: var(--color-accent);
}

/* 主题适配 */
.theme-dark .expand-toggle {
  border-color: var(--color-border);
  background-color: transparent;
}

.theme-dark .expand-toggle:hover {
  background-color: var(--color-surface-elevated);
  border-color: var(--color-primary);
}

.theme-dark .expand-toggle--expanded {
  background-color: rgba(0, 122, 255, 0.2);
}

.theme-dark .animation-indicator {
  background-color: var(--color-border);
}

/* 高对比度模式 */
@media (prefers-contrast: high) {
  .expand-toggle {
    border-width: var(--border-width-medium);
  }
  
  .expand-toggle:focus-visible {
    outline-width: 3px;
  }
  
  .animation-indicator {
    border: var(--border-width-thin) solid var(--color-text-primary);
  }
}

/* 减少动画偏好 */
@media (prefers-reduced-motion: reduce) {
  .expand-toggle,
  .toggle-icon,
  .toggle-text,
  .animation-indicator {
    transition: none;
  }
  
  .expand-toggle:active {
    transform: none;
  }
  
  .icon--expanded .toggle-icon {
    transform: none;
  }
}

/* 打印样式 */
@media print {
  .expand-toggle {
    display: none;
  }
}

/* 触控设备优化 */
@media (hover: none) and (pointer: coarse) {
  .expand-toggle {
    min-height: var(--touch-target-min-size);
    min-width: var(--touch-target-min-size);
  }
  
  .expand-toggle--mobile {
    padding: var(--spacing-md);
  }
}

/* 键盘导航优化 */
.expand-toggle:focus-visible {
  z-index: 1;
}

/* 无障碍优化 */
@media (prefers-reduced-motion: reduce) {
  .expand-toggle {
    scroll-behavior: auto;
  }
}
</style>