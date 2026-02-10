<!--
  常用备注UI优化 - 列表容器组件
  
  管理备注列表的显示和交互，支持长备注处理
  需求: 2.1, 2.3, 3.1, 3.2, 3.3
-->
<template>
  <div 
    class="notes-list-container"
    :class="containerClasses"
  >
    <!-- 空状态 -->
    <EmptyState
      v-if="notes.length === 0"
      message="暂无常用备注"
      icon="note_add"
      action-text="添加备注"
      :theme="theme"
      @action="$emit('add-note')"
    />
    
    <!-- 备注列表 -->
    <div 
      v-else 
      class="notes-list"
      :class="listClasses"
      role="list"
      :aria-label="`共${notes.length}条备注`"
    >
      <NoteItem
        v-for="note in notes"
        :key="note.id"
        :note="note"
        :is-expanded="expandedNotes.has(String(note.id))"
        :max-preview-length="maxPreviewLength"
        :theme="theme"
        role="listitem"
        @expand="handleNoteExpand(note.id, $event)"
        @select="handleNoteSelect(note)"
        @delete="handleNoteDelete(note.id)"
      />
    </div>
    
    <!-- 加载更多按钮（如果需要分页） -->
    <div 
      v-if="showLoadMore"
      class="load-more-section"
    >
      <q-btn
        flat
        label="加载更多"
        icon="expand_more"
        :loading="isLoadingMore"
        :style="loadMoreButtonStyles"
        @click="$emit('load-more')"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import type { Note, NotesListContainerProps } from '../types/common-notes'
import { useBreakpoint } from '../utils/responsive'
import { designTokens } from '../config/design-tokens'
import NoteItem from './NoteItem.vue'
import EmptyState from './EmptyState.vue'

// Props定义
interface Props {
  notes: Note[]
  expandedNotes: Set<string>
  maxPreviewLength: number
  theme?: any
  showLoadMore?: boolean
  isLoadingMore?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  notes: () => [],
  expandedNotes: () => new Set(),
  maxPreviewLength: 100,
  showLoadMore: false,
  isLoadingMore: false
})

// Emits定义
const emit = defineEmits<{
  'note-select': [note: Note]
  'note-expand': [noteId: string, expanded: boolean]
  'note-delete': [noteId: string]
  'add-note': []
  'load-more': []
}>()

// 组合式API
const { isMobile, isTablet, isDesktop } = useBreakpoint()

// 添加调试日志
watch(() => props.notes, (newNotes) => {
  console.log('[NotesListContainer] notes变化:', newNotes)
  console.log('[NotesListContainer] notes长度:', newNotes.length)
}, { immediate: true, deep: true })

// 计算属性
const containerClasses = computed(() => ({
  'notes-list-container--mobile': isMobile.value,
  'notes-list-container--tablet': isTablet.value,
  'notes-list-container--desktop': isDesktop.value,
  'notes-list-container--empty': props.notes.length === 0,
  'notes-list-container--has-notes': props.notes.length > 0
}))

const listClasses = computed(() => ({
  'notes-list--mobile': isMobile.value,
  'notes-list--desktop': isDesktop.value,
  'notes-list--scrollable': props.notes.length > 5
}))

const loadMoreButtonStyles = computed(() => ({
  minHeight: designTokens.touchTarget.minSize,
  fontSize: isMobile.value 
    ? designTokens.fontSize.base.mobile 
    : designTokens.fontSize.base.desktop,
  padding: designTokens.spacing.button
}))

// 事件处理
const handleNoteSelect = (note: Note) => {
  emit('note-select', note)
}

const handleNoteExpand = (noteId: string | number, expanded: boolean) => {
  emit('note-expand', String(noteId), expanded)
}

const handleNoteDelete = (noteId: string | number) => {
  emit('note-delete', String(noteId))
}
</script>

<style scoped>
.notes-list-container {
  /* 基础布局 */
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0; /* 允许flex子项收缩 */
  background-color: var(--color-background);
  
  /* 内边距 */
  padding: var(--spacing-content-padding);
}

.notes-list-container--mobile {
  padding: 12px;
}

.notes-list-container--desktop {
  padding: var(--spacing-md);
}

/* 备注列表样式 */
.notes-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-list-item);
  flex: 1;
  min-height: 0;
}

.notes-list--scrollable {
  max-height: 400px;
  overflow-y: auto;
  
  /* 自定义滚动条 */
  scrollbar-width: thin;
  scrollbar-color: var(--color-border) transparent;
}

.notes-list--scrollable::-webkit-scrollbar {
  width: 6px;
}

.notes-list--scrollable::-webkit-scrollbar-track {
  background: transparent;
}

.notes-list--scrollable::-webkit-scrollbar-thumb {
  background-color: var(--color-border);
  border-radius: var(--border-radius-sm);
}

.notes-list--scrollable::-webkit-scrollbar-thumb:hover {
  background-color: var(--color-text-tertiary);
}

/* 移动端列表优化 */
.notes-list--mobile {
  gap: 8px;
}

.notes-list--mobile.notes-list--scrollable {
  max-height: 350px;
}

/* 桌面端列表优化 */
.notes-list--desktop {
  gap: var(--spacing-sm);
}

.notes-list--desktop.notes-list--scrollable {
  max-height: 350px;
}

/* 加载更多区域 */
.load-more-section {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: var(--spacing-md) 0;
  margin-top: var(--spacing-sm);
  border-top: var(--border-width-thin) solid var(--color-border);
}

/* 空状态容器 */
.notes-list-container--empty {
  justify-content: center;
  align-items: center;
  min-height: 120px;
}

/* 有内容时的容器 */
.notes-list-container--has-notes {
  justify-content: flex-start;
}

/* 主题适配 */
.theme-dark .notes-list-container {
  background-color: var(--color-background);
}

.theme-dark .load-more-section {
  border-top-color: var(--color-border);
}

.theme-dark .notes-list--scrollable {
  scrollbar-color: var(--color-border) transparent;
}

.theme-dark .notes-list--scrollable::-webkit-scrollbar-thumb {
  background-color: var(--color-border);
}

.theme-dark .notes-list--scrollable::-webkit-scrollbar-thumb:hover {
  background-color: var(--color-text-secondary);
}

/* 高对比度模式 */
@media (prefers-contrast: high) {
  .load-more-section {
    border-top-width: var(--border-width-medium);
  }
  
  .notes-list--scrollable::-webkit-scrollbar-thumb {
    border: var(--border-width-thin) solid var(--color-text-primary);
  }
}

/* 减少动画偏好 */
@media (prefers-reduced-motion: reduce) {
  .notes-list--scrollable {
    scroll-behavior: auto;
  }
}

/* 打印样式 */
@media print {
  .notes-list-container {
    background-color: transparent;
    padding: var(--spacing-sm);
  }
  
  .notes-list--scrollable {
    max-height: none;
    overflow: visible;
  }
  
  .load-more-section {
    display: none;
  }
}

/* 无障碍优化 */
@media (prefers-reduced-motion: reduce) {
  .notes-list {
    scroll-behavior: auto;
  }
}

/* 触控设备优化 */
@media (hover: none) and (pointer: coarse) {
  .notes-list--scrollable {
    scrollbar-width: auto;
  }
  
  .notes-list--scrollable::-webkit-scrollbar {
    width: 12px;
  }
  
  .notes-list--scrollable::-webkit-scrollbar-thumb {
    background-color: var(--color-text-tertiary);
    border-radius: var(--border-radius-md);
  }
}
</style>