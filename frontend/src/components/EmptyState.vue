<!--
  常用备注UI优化 - 空状态组件
  
  显示空状态提示和操作引导
  需求: 4.1, 4.2, 4.4
-->
<template>
  <div 
    class="empty-state"
    :class="emptyStateClasses"
    role="status"
    :aria-label="message"
  >
    <!-- 图标 -->
    <div class="empty-icon-wrapper">
      <q-icon 
        :name="icon"
        class="empty-icon"
        :class="iconClasses"
      />
    </div>
    
    <!-- 消息文本 -->
    <div class="empty-message">
      <h3 class="message-title">{{ message }}</h3>
      <p 
        v-if="description"
        class="message-description"
      >
        {{ description }}
      </p>
    </div>
    
    <!-- 操作按钮 -->
    <div 
      v-if="actionText && onAction"
      class="empty-actions"
    >
      <q-btn
        :label="actionText"
        color="primary"
        :icon="actionIcon"
        :style="actionButtonStyles"
        @click="handleAction"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { EmptyStateProps } from '../types/common-notes'
import { useBreakpoint } from '../utils/responsive'
import { designTokens } from '../config/design-tokens'

// Props定义
interface Props {
  message: string
  description?: string
  icon?: string
  actionText?: string
  actionIcon?: string
  theme?: any
}

const props = withDefaults(defineProps<Props>(), {
  icon: 'note_add',
  actionIcon: 'add'
})

// Emits定义
const emit = defineEmits<{
  'action': []
}>()

// 组合式API
const { isMobile, isDesktop } = useBreakpoint()

// 计算属性
const emptyStateClasses = computed(() => ({
  'empty-state--mobile': isMobile.value,
  'empty-state--desktop': isDesktop.value,
  'empty-state--with-action': props.actionText,
  'empty-state--simple': !props.actionText
}))

const iconClasses = computed(() => ({
  'icon--mobile': isMobile.value,
  'icon--desktop': isDesktop.value
}))

const actionButtonStyles = computed(() => ({
  minHeight: designTokens.touchTarget.minSize,
  fontSize: isMobile.value 
    ? designTokens.fontSize.base.mobile 
    : designTokens.fontSize.base.desktop,
  padding: designTokens.spacing.button
}))

// 事件处理
const handleAction = () => {
  emit('action')
}
</script>

<style scoped>
.empty-state {
  /* 基础布局 */
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: var(--spacing-lg);
  
  /* 样式 */
  padding: var(--spacing-xl);
  background-color: var(--color-surface);
  border-radius: var(--border-radius-md);
  
  /* 尺寸 */
  min-height: 200px;
  width: 100%;
}

/* 图标包装器 */
.empty-icon-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 80px;
  height: 80px;
  border-radius: var(--border-radius-round);
  background-color: var(--color-background);
  border: var(--border-width-thin) solid var(--color-border);
}

.empty-icon {
  font-size: 32px;
  color: var(--color-text-tertiary);
}

.icon--mobile .empty-icon {
  font-size: 36px;
}

.icon--desktop .empty-icon {
  font-size: 28px;
}

/* 消息区域 */
.empty-message {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
  max-width: 300px;
}

.message-title {
  font-size: var(--font-size-title);
  font-weight: var(--font-weight-medium);
  color: var(--color-text-primary);
  margin: 0;
  line-height: var(--line-height-title);
}

.message-description {
  font-size: var(--font-size-base);
  color: var(--color-text-secondary);
  margin: 0;
  line-height: var(--line-height-base);
}

/* 操作区域 */
.empty-actions {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
  align-items: center;
}

/* 响应式适配 */
.empty-state--mobile {
  padding: var(--spacing-xl) var(--spacing-lg);
  gap: var(--spacing-xl);
  min-height: 250px;
}

.empty-state--mobile .empty-icon-wrapper {
  width: 100px;
  height: 100px;
}

.empty-state--mobile .message-title {
  font-size: var(--font-size-title);
}

.empty-state--mobile .message-description {
  font-size: var(--font-size-base);
}

.empty-state--desktop {
  padding: var(--spacing-lg);
  gap: var(--spacing-lg);
  min-height: 180px;
}

.empty-state--desktop .empty-icon-wrapper {
  width: 64px;
  height: 64px;
}

.empty-state--desktop .message-title {
  font-size: var(--font-size-title-desktop);
}

.empty-state--desktop .message-description {
  font-size: var(--font-size-base-desktop);
}

/* 有操作按钮的状态 */
.empty-state--with-action {
  /* 有操作按钮时的特殊样式 */
}

.empty-state--with-action .empty-icon-wrapper {
  background-color: rgba(0, 122, 255, 0.1);
  border-color: var(--color-primary);
}

.empty-state--with-action .empty-icon {
  color: var(--color-primary);
}

/* 简单状态（无操作按钮） */
.empty-state--simple {
  /* 无操作按钮时的样式 */
}

.empty-state--simple .empty-icon-wrapper {
  background-color: var(--color-background);
  border-color: var(--color-border-light);
}

/* 主题适配 */
.theme-dark .empty-state {
  background-color: var(--color-surface);
}

.theme-dark .empty-icon-wrapper {
  background-color: var(--color-surface-elevated);
  border-color: var(--color-border);
}

.theme-dark .empty-state--with-action .empty-icon-wrapper {
  background-color: rgba(0, 122, 255, 0.2);
}

/* 高对比度模式 */
@media (prefers-contrast: high) {
  .empty-icon-wrapper {
    border-width: var(--border-width-medium);
  }
  
  .empty-icon {
    color: var(--color-text-primary);
  }
  
  .message-title {
    color: var(--color-text-primary);
  }
}

/* 减少动画偏好 */
@media (prefers-reduced-motion: reduce) {
  .empty-state {
    /* 移除可能的动画 */
  }
}

/* 打印样式 */
@media print {
  .empty-state {
    background-color: transparent;
    border: var(--border-width-thin) solid #000000;
  }
  
  .empty-actions {
    display: none;
  }
  
  .empty-icon-wrapper {
    background-color: transparent;
    border-color: #000000;
  }
}

/* 无障碍优化 */
.empty-state[role="status"] {
  /* 确保屏幕阅读器能够正确识别 */
}

/* 触控设备优化 */
@media (hover: none) and (pointer: coarse) {
  .empty-state--mobile {
    padding: var(--spacing-xl);
  }
  
  .empty-state--mobile .empty-actions .q-btn {
    min-width: 200px;
  }
}
</style>