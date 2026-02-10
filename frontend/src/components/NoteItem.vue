<!--
  常用备注UI优化 - 备注项组件
  
  单个备注项的显示和交互，支持展开/收起和长备注处理
  需求: 2.2, 3.1, 3.2, 3.3, 3.4, 3.5, 4.2, 4.3
-->
<template>
  <div 
    class="note-item"
    :class="itemClasses"
    :aria-expanded="isExpanded"
    :aria-selected="false"
    :tabindex="0"
    role="article"
    @click="handleSelect"
    @keydown.enter="handleSelect"
    @keydown.space.prevent="handleSelect"
  >
    <!-- 主要内容区域 -->
    <div 
      class="note-content-wrapper"
      :style="touchTargetStyles"
    >
      <!-- 备注内容 -->
      <div class="note-content">
        <!-- 预览内容 -->
        <div 
          v-if="!isExpanded && hasLongContent"
          class="content-preview"
          :title="note.content"
        >
          {{ previewContent }}
          <span class="ellipsis-indicator">...</span>
        </div>
        
        <!-- 完整内容 -->
        <div 
          v-else
          class="content-full"
        >
          {{ note.content }}
        </div>
        
        <!-- 元数据 -->
        <div class="note-metadata">
          <span class="note-date">
            {{ formatDate(note.updated_at || note.created_at) }}
          </span>
          <span 
            v-if="note.category"
            class="note-category"
          >
            {{ note.category }}
          </span>
          <span 
            v-if="note.priority"
            class="note-priority"
            :class="`priority--${note.priority}`"
          >
            {{ getPriorityLabel(note.priority) }}
          </span>
        </div>
      </div>
      
      <!-- 选择指示器 -->
      <div 
        class="select-indicator"
        :aria-hidden="true"
      />
    </div>
    
    <!-- 操作按钮区域 -->
    <div class="note-actions">
      <!-- 展开/收起按钮 -->
      <button
        v-if="hasLongContent"
        class="expand-toggle"
        :class="expandToggleClasses"
        :aria-expanded="isExpanded"
        :aria-label="isExpanded ? '收起备注内容' : '展开备注内容'"
        @click.stop="handleExpand(!isExpanded)"
        @keydown.enter.stop="handleExpand(!isExpanded)"
        @keydown.space.prevent.stop="handleExpand(!isExpanded)"
      >
        <q-icon 
          :name="isExpanded ? 'expand_less' : 'expand_more'"
          class="toggle-icon"
        />
        <span class="toggle-text">
          {{ isExpanded ? collapseText : expandText }}
        </span>
      </button>
      
      <!-- 删除按钮 -->
      <q-btn
        flat
        dense
        round
        icon="delete_outline"
        :aria-label="`删除备注: ${note.content.substring(0, 20)}...`"
        class="delete-btn"
        :style="deleteButtonStyles"
        @click.stop="handleDelete"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useQuasar } from 'quasar'
import type { Note } from '../types/common-notes'
import { useBreakpoint } from '../utils/responsive'
import { designTokens } from '../config/design-tokens'

// Props定义
interface Props {
  note: Note
  isExpanded: boolean
  maxPreviewLength: number
  theme?: any
}

const props = withDefaults(defineProps<Props>(), {
  maxPreviewLength: 100
})

// Emits定义
const emit = defineEmits<{
  'expand': [expanded: boolean]
  'select': []
  'delete': []
}>()

// 组合式API
const $q = useQuasar()
const { isMobile, isDesktop } = useBreakpoint()

// 计算属性
const itemClasses = computed(() => ({
  'note-item--mobile': isMobile.value,
  'note-item--desktop': isDesktop.value,
  'note-item--expanded': props.isExpanded,
  'note-item--collapsed': !props.isExpanded,
  'note-item--long-content': hasLongContent.value,
  'note-item--short-content': !hasLongContent.value,
  [`note-item--priority-${props.note.priority}`]: props.note.priority
}))

const hasLongContent = computed(() => {
  return props.note.content.length > props.maxPreviewLength
})

const previewContent = computed(() => {
  if (!hasLongContent.value) {
    return props.note.content
  }
  
  // 智能截断：尽量在句号、感叹号、问号处截断
  const content = props.note.content
  const maxLength = props.maxPreviewLength
  
  if (content.length <= maxLength) {
    return content
  }
  
  // 查找最近的句子结束符
  const sentenceEnders = /[。！？.!?]/g
  let match: RegExpExecArray | null
  let lastGoodBreak = 0
  
  while ((match = sentenceEnders.exec(content)) !== null) {
    if (match.index < maxLength - 10) { // 留一些余量
      lastGoodBreak = match.index + 1
    } else {
      break
    }
  }
  
  // 如果找到了合适的断点，使用它；否则直接截断
  if (lastGoodBreak > maxLength * 0.6) { // 至少要有60%的内容
    return content.substring(0, lastGoodBreak).trim()
  } else {
    return content.substring(0, maxLength).trim()
  }
})

const expandText = computed(() => {
  return isMobile.value ? '展开' : '查看更多'
})

const collapseText = computed(() => {
  return isMobile.value ? '收起' : '收起'
})

const expandToggleClasses = computed(() => ({
  'expand-toggle--mobile': isMobile.value,
  'expand-toggle--desktop': isDesktop.value,
  'expand-toggle--expanded': props.isExpanded,
  'expand-toggle--collapsed': !props.isExpanded
}))

const deleteButtonStyles = computed(() => ({
  minWidth: designTokens.touchTarget.minSize,
  minHeight: designTokens.touchTarget.minSize,
  fontSize: isMobile.value ? '18px' : '16px',
  padding: isMobile.value ? '12px' : '8px'
}))

// 触控目标样式
const touchTargetStyles = computed(() => ({
  minHeight: designTokens.touchTarget.minSize,
  padding: isMobile.value ? '16px' : '12px'
}))

// 工具函数
const formatDate = (dateString: string): string => {
  if (!dateString) return ''
  
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))
  
  if (diffDays === 0) {
    return '今天'
  } else if (diffDays === 1) {
    return '昨天'
  } else if (diffDays < 7) {
    return `${diffDays}天前`
  } else if (diffDays < 30) {
    const weeks = Math.floor(diffDays / 7)
    return `${weeks}周前`
  } else {
    return date.toLocaleDateString('zh-CN', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  }
}

const getPriorityLabel = (priority: string): string => {
  const labels = {
    low: '低',
    medium: '中',
    high: '高'
  }
  return labels[priority as keyof typeof labels] || priority
}

// 事件处理
const handleSelect = () => {
  emit('select')
}

const handleExpand = (expanded: boolean) => {
  emit('expand', expanded)
}

const handleDelete = () => {
  $q.dialog({
    title: '确认删除',
    message: `确定要删除这条备注吗？\n\n"${props.note.content.substring(0, 50)}${props.note.content.length > 50 ? '...' : ''}"`,
    cancel: {
      label: '取消',
      flat: true
    },
    ok: {
      label: '删除',
      color: 'negative'
    },
    persistent: true
  }).onOk(() => {
    emit('delete')
  })
}
</script>

<style scoped>
.note-item {
  background-color: var(--color-background);
  border-radius: var(--border-radius-md);
  border: var(--border-width-thin) solid var(--color-border);
  transition: var(--transition-all);
  cursor: pointer;
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-sm);
  overflow: hidden;
}

.note-item:hover {
  background-color: var(--color-surface);
  border-color: var(--color-primary);
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.note-item:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.note-item:active {
  transform: translateY(0);
  box-shadow: none;
}

.note-item--expanded {
  background-color: var(--color-surface);
}

/* 内容包装器 */
.note-content-wrapper {
  flex: 1;
  min-height: 44px;
  padding: 12px;
  display: flex;
  align-items: center;
}

.note-content {
  flex: 1;
  font-size: 14px;
  line-height: 1.5;
  color: var(--color-text-primary);
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.content-preview {
  position: relative;
}

.ellipsis-indicator {
  color: var(--color-text-tertiary);
  font-weight: 500;
}

.note-metadata {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
  font-size: 12px;
  color: var(--color-text-secondary);
}

.note-date {
  font-size: 12px;
}

.note-category {
  padding: 2px 6px;
  background-color: var(--color-surface);
  border-radius: 4px;
  font-size: 12px;
}

.select-indicator {
  width: 4px;
  height: 20px;
  background-color: transparent;
  border-radius: 2px;
  transition: var(--transition-all);
}

.note-item:hover .select-indicator {
  background-color: var(--color-primary);
}

/* 操作按钮区域 */
.note-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px;
}

.expand-toggle {
  min-height: 36px;
  min-width: 36px;
  padding: 6px 12px;
  background: transparent;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  color: var(--color-primary);
  font-size: 12px;
  cursor: pointer;
  transition: var(--transition-all);
  display: flex;
  align-items: center;
  gap: 4px;
}

.expand-toggle:hover {
  background-color: var(--color-primary);
  color: white;
}

.expand-toggle:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.expand-toggle--expanded .toggle-icon {
  transform: rotate(180deg);
}

.toggle-text {
  font-size: 12px;
  white-space: nowrap;
}

.delete-btn {
  min-height: 36px;
  min-width: 36px;
  color: var(--color-text-tertiary);
  transition: var(--transition-all);
}

.delete-btn:hover {
  color: var(--color-error);
  background-color: rgba(220, 53, 69, 0.1);
}

/* 移动端优化 */
.note-item--mobile {
  padding: 0;
}

.note-item--mobile .note-content-wrapper {
  padding: 12px;
}

.note-item--mobile .note-actions {
  padding: 8px;
}

.note-item--mobile .expand-toggle {
  min-height: 44px;
  min-width: 44px;
}

.note-item--mobile .delete-btn {
  min-height: 44px;
  min-width: 44px;
}
</style>