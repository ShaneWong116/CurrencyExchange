<!--
  常用备注UI优化 - 底部操作区组件
  
  提供添加备注等主要操作功能
  需求: 4.2, 4.3, 2.2
-->
<template>
  <div 
    class="notes-footer"
    :class="footerClasses"
  >
    <!-- 主要操作按钮 -->
    <div class="primary-actions">
      <q-btn
        :label="addButtonText"
        color="primary"
        icon="add"
        :style="addButtonStyles"
        :class="addButtonClasses"
        @click="handleAddNote"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useQuasar } from 'quasar'
import type { NotesFooterProps } from '../types/common-notes'
import { useBreakpoint } from '../utils/responsive'
import { designTokens } from '../config/design-tokens'

// Props定义
interface Props {
  theme?: any
}

const props = withDefaults(defineProps<Props>(), {})

// Emits定义
const emit = defineEmits<{
  'add-note': []
}>()

// 组合式API
const $q = useQuasar()
const { isMobile, isDesktop } = useBreakpoint()

// 计算属性
const footerClasses = computed(() => ({
  'notes-footer--mobile': isMobile.value,
  'notes-footer--desktop': isDesktop.value
}))

const addButtonClasses = computed(() => ({
  'add-btn--mobile': isMobile.value,
  'add-btn--desktop': isDesktop.value
}))

const addButtonText = computed(() => {
  return isMobile.value ? '添加备注' : '添加'
})

const addButtonStyles = computed(() => ({
  minHeight: '36px',
  fontSize: '14px',
  padding: '8px 16px',
  borderRadius: isMobile.value 
    ? designTokens.border.radius 
    : '6px'
}))

// 事件处理
const handleAddNote = () => {
  emit('add-note')
}
</script>

<style scoped>
.notes-footer {
  /* 基础布局 */
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-sm);
  
  /* 样式 */
  padding: var(--spacing-md);
  background-color: var(--color-surface);
  border-top: var(--border-width-thin) solid var(--color-border);
  
  /* 确保在底部 */
  margin-top: auto;
}

/* 主要操作区域 */
.primary-actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

/* 添加按钮样式 */
.add-btn--mobile {
  min-width: 200px;
}

.add-btn--desktop {
  min-width: 120px;
}

/* 响应式适配 */
.notes-footer--mobile {
  padding: 12px;
}

.notes-footer--desktop {
  padding: var(--spacing-sm) var(--spacing-md);
}

/* 主题适配 */
.theme-dark .notes-footer {
  background-color: var(--color-surface);
  border-top-color: var(--color-border);
}

/* 高对比度模式 */
@media (prefers-contrast: high) {
  .notes-footer {
    border-top-width: var(--border-width-medium);
  }
}

/* 打印样式 */
@media print {
  .notes-footer {
    display: none;
  }
}

/* 触控设备优化 */
@media (hover: none) and (pointer: coarse) {
  .notes-footer--mobile {
    padding: var(--spacing-lg);
  }
  
  .notes-footer--mobile .primary-actions .q-btn {
    min-height: var(--touch-target-min-size);
  }
}

/* 安全区域适配（用于移动设备） */
@supports (padding-bottom: env(safe-area-inset-bottom)) {
  .notes-footer--mobile {
    padding-bottom: calc(var(--spacing-lg) + env(safe-area-inset-bottom));
  }
}
</style>