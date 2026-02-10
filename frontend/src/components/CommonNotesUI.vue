<!--
  常用备注UI优化 - 主容器组件
  
  实现整体布局管理和状态协调，集成响应式设计系统
  需求: 1.1, 1.2, 1.4, 2.1
-->
<template>
  <div 
    class="common-notes-ui"
    :class="themeClasses"
    :style="cssVariables"
  >
    <!-- 标题区域 -->
    <NotesHeader
      :title="headerTitle"
      :expanded="isExpanded"
      :theme="currentTheme"
      @toggle="handleHeaderToggle"
    />
    
    <!-- 主要内容区域 -->
    <q-slide-transition>
      <div v-show="isExpanded" class="notes-content">
        <!-- 列表容器 -->
        <NotesListContainer
          :notes="internalNotes"
          :expanded-notes="expandedNotes"
          :max-preview-length="responsivePreviewLength"
          :theme="currentTheme"
          @note-select="handleNoteSelect"
          @note-expand="handleNoteExpand"
        />
        
        <!-- 底部操作区 -->
        <NotesFooter
          :theme="currentTheme"
          @add-note="handleAddNote"
        />
      </div>
    </q-slide-transition>
    
    <!-- 添加备注对话框 -->
    <q-dialog v-model="showAddDialog" persistent>
      <q-card class="add-note-dialog" :style="dialogStyles">
        <q-card-section class="dialog-header">
          <div class="text-h6">添加常用备注</div>
        </q-card-section>
        
        <q-card-section class="dialog-content">
          <q-input
            v-model="newNoteContent"
            type="textarea"
            label="备注内容"
            :rows="isMobile ? 4 : 3"
            maxlength="500"
            counter
            autofocus
            outlined
            :style="inputStyles"
            @keyup.ctrl.enter="confirmAddNote"
          />
        </q-card-section>
        
        <q-card-actions align="right" class="dialog-actions">
          <q-btn 
            flat 
            label="取消" 
            :style="buttonStyles.secondary"
            @click="cancelAddNote"
          />
          <q-btn
            label="添加"
            color="primary"
            :loading="isAdding"
            :style="buttonStyles.primary"
            @click="confirmAddNote"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useQuasar } from 'quasar'
import type { 
  CommonNotesUIProps, 
  Note, 
  NotesEventHandlers,
  ThemeConfig 
} from '../types/common-notes'
import { useThemeConsumer } from '../composables/useTheme'
import { useBreakpoint } from '../utils/responsive'
import { designTokens } from '../config/design-tokens'
import NotesHeader from './NotesHeader.vue'
import NotesListContainer from './NotesListContainer.vue'
import NotesFooter from './NotesFooter.vue'
import api from '../utils/api'

// Props定义
interface Props {
  notes?: Note[]
  maxPreviewLength?: number
  theme?: ThemeConfig
  headerTitle?: string
  initialExpanded?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  notes: () => [],
  maxPreviewLength: 100,
  headerTitle: '常用备注',
  initialExpanded: false
})

// Emits定义
const emit = defineEmits<{
  'note-select': [note: Note]
  'note-add': [note: Note]
  'note-delete': [noteId: string]
  'note-update': [note: Note]
  'expand-change': [expanded: boolean]
}>()

// 组合式API
const $q = useQuasar()
const { 
  currentTheme, 
  isDark, 
  cssVariables: themeCssVariables,
  getThemeClasses 
} = useThemeConsumer()
const { 
  currentBreakpoint, 
  isMobile, 
  isTablet, 
  isDesktop 
} = useBreakpoint()

// 响应式状态
const isExpanded = ref(props.initialExpanded)
const expandedNotes = ref<Set<string>>(new Set())
const internalNotes = ref<Note[]>([...props.notes])
const showAddDialog = ref(false)
const newNoteContent = ref('')
const isAdding = ref(false)

// 计算属性
const themeClasses = computed(() => ({
  ...getThemeClasses(),
  'common-notes-ui--mobile': isMobile.value,
  'common-notes-ui--tablet': isTablet.value,
  'common-notes-ui--desktop': isDesktop.value,
  'common-notes-ui--expanded': isExpanded.value,
  'common-notes-ui--collapsed': !isExpanded.value
}))

const cssVariables = computed(() => {
  const themeVars = themeCssVariables.value as Record<string, string> | undefined
  return {
    ...(themeVars || {}),
    '--notes-ui-font-size': isMobile.value 
      ? designTokens.fontSize.base.mobile 
      : designTokens.fontSize.base.desktop,
    '--notes-ui-spacing': isMobile.value 
      ? designTokens.spacing.component.mobile 
      : designTokens.spacing.component.desktop,
    '--notes-ui-touch-target': designTokens.touchTarget.minSize
  }
})

const responsivePreviewLength = computed(() => {
  return isMobile.value 
    ? designTokens.longNote.previewLength.mobile
    : designTokens.longNote.previewLength.desktop
})

const dialogStyles = computed(() => ({
  minWidth: isMobile.value ? '90vw' : '400px',
  maxWidth: isMobile.value ? '95vw' : '500px'
}))

const inputStyles = computed(() => ({
  fontSize: 'var(--notes-ui-font-size)',
  minHeight: 'var(--notes-ui-touch-target)'
}))

const buttonStyles = computed(() => ({
  primary: {
    minHeight: 'var(--notes-ui-touch-target)',
    fontSize: 'var(--notes-ui-font-size)',
    padding: designTokens.spacing.button
  },
  secondary: {
    minHeight: 'var(--notes-ui-touch-target)',
    fontSize: 'var(--notes-ui-font-size)',
    padding: designTokens.spacing.button
  }
}))

// 事件处理器
const handleHeaderToggle = (expanded: boolean) => {
  isExpanded.value = expanded
  emit('expand-change', expanded)
}

const handleNoteSelect = (note: Note) => {
  emit('note-select', note)
  
  // 显示用户反馈
  $q.notify({
    type: 'positive',
    message: '已选择备注',
    caption: note.content.length > 30 
      ? `${note.content.substring(0, 30)}...` 
      : note.content,
    timeout: 2000,
    position: isMobile.value ? 'bottom' : 'top-right'
  })
}

const handleNoteExpand = (noteId: string, expanded: boolean) => {
  if (expanded) {
    expandedNotes.value.add(noteId)
  } else {
    expandedNotes.value.delete(noteId)
  }
}

const handleAddNote = () => {
  showAddDialog.value = true
  newNoteContent.value = ''
}

const cancelAddNote = () => {
  showAddDialog.value = false
  newNoteContent.value = ''
}

const confirmAddNote = async () => {
  const content = newNoteContent.value.trim()
  
  if (!content) {
    $q.notify({
      type: 'warning',
      message: '请输入备注内容',
      position: isMobile.value ? 'bottom' : 'top-right'
    })
    return
  }
  
  if (content.length > 500) {
    $q.notify({
      type: 'warning',
      message: '备注内容不能超过500个字符',
      position: isMobile.value ? 'bottom' : 'top-right'
    })
    return
  }
  
  isAdding.value = true
  
  try {
    const response = await api.post('/common-notes', {
      content: content
    })
    
    if (response.data.success) {
      const newNote: Note = response.data.data
      internalNotes.value.unshift(newNote)
      emit('note-add', newNote)
      
      showAddDialog.value = false
      newNoteContent.value = ''
      
      $q.notify({
        type: 'positive',
        message: '添加成功',
        timeout: 2000,
        position: isMobile.value ? 'bottom' : 'top-right'
      })
    }
  } catch (error: any) {
    console.error('添加备注失败:', error)
    $q.notify({
      type: 'negative',
      message: '添加失败',
      caption: error.response?.data?.message || error.message,
      position: isMobile.value ? 'bottom' : 'top-right'
    })
  } finally {
    isAdding.value = false
  }
}

// 加载备注数据
const loadNotes = async () => {
  console.log('[CommonNotesUI] 开始加载备注数据...')
  try {
    const response = await api.get('/common-notes')
    console.log('[CommonNotesUI] API响应:', response)
    console.log('[CommonNotesUI] response.data:', response.data)
    console.log('[CommonNotesUI] response.data.success:', response.data.success)
    console.log('[CommonNotesUI] response.data.data:', response.data.data)
    console.log('[CommonNotesUI] response.data.data类型:', Array.isArray(response.data.data))
    
    if (response.data.success && Array.isArray(response.data.data)) {
      // 确保数据是数组并且正确赋值
      internalNotes.value = [...response.data.data]
      console.log('[CommonNotesUI] 设置internalNotes后:', internalNotes.value)
      console.log('[CommonNotesUI] internalNotes长度:', internalNotes.value.length)
      console.log('[CommonNotesUI] internalNotes是否为数组:', Array.isArray(internalNotes.value))
    } else {
      console.warn('[CommonNotesUI] API返回success=false或data不是数组')
      internalNotes.value = []
    }
  } catch (error: any) {
    console.error('[CommonNotesUI] 加载常用备注失败:', error)
    $q.notify({
      type: 'negative',
      message: '加载常用备注失败',
      caption: error.response?.data?.message || error.message,
      position: isMobile.value ? 'bottom' : 'top-right'
    })
  }
}

// 监听props变化
watch(() => props.notes, (newNotes) => {
  internalNotes.value = [...newNotes]
}, { deep: true })

// 生命周期
onMounted(() => {
  console.log('[CommonNotesUI] onMounted 被调用')
  console.log('[CommonNotesUI] props.notes:', props.notes)
  console.log('[CommonNotesUI] props.notes.length:', props.notes.length)
  console.log('[CommonNotesUI] internalNotes初始值:', internalNotes.value)
  
  // 如果没有传入notes，则从API加载
  if (props.notes.length === 0) {
    console.log('[CommonNotesUI] props.notes为空，开始从API加载')
    loadNotes()
  } else {
    console.log('[CommonNotesUI] props.notes不为空，使用传入的notes')
  }
})

// 暴露给父组件的方法
defineExpose({
  expand: () => { isExpanded.value = true },
  collapse: () => { isExpanded.value = false },
  toggle: () => { isExpanded.value = !isExpanded.value },
  refresh: loadNotes,
  addNote: handleAddNote,
  expandNote: (noteId: string) => handleNoteExpand(noteId, true),
  collapseNote: (noteId: string) => handleNoteExpand(noteId, false),
  clearExpandedNotes: () => { expandedNotes.value.clear() }
})
</script>

<style scoped>
.common-notes-ui {
  /* 基础布局 */
  display: flex;
  flex-direction: column;
  width: 100%;
  background-color: var(--color-surface);
  border: var(--border-width-thin) solid var(--color-border);
  border-radius: var(--border-radius-md);
  overflow: hidden;
  
  /* 响应式字体和间距 */
  font-size: var(--notes-ui-font-size);
  
  /* 过渡动画 */
  transition: var(--transition-all);
}

.common-notes-ui--mobile {
  margin: var(--spacing-xs);
  border-radius: var(--border-radius-lg);
}

.common-notes-ui--tablet,
.common-notes-ui--desktop {
  margin: var(--spacing-sm);
}

.notes-content {
  display: flex;
  flex-direction: column;
  min-height: 0; /* 允许flex子项收缩 */
}

/* 对话框样式 */
.add-note-dialog {
  border-radius: var(--border-radius-lg);
  background-color: var(--color-background);
}

.dialog-header {
  padding: var(--spacing-md);
  border-bottom: var(--border-width-thin) solid var(--color-border);
  background-color: var(--color-surface);
}

.dialog-header .text-h6 {
  font-size: var(--font-size-title);
  font-weight: var(--font-weight-medium);
  color: var(--color-text-primary);
  margin: 0;
}

.dialog-content {
  padding: var(--spacing-md);
}

.dialog-actions {
  padding: var(--spacing-md);
  gap: var(--spacing-sm);
  border-top: var(--border-width-thin) solid var(--color-border);
  background-color: var(--color-surface);
}

/* 移动端优化 */
.common-notes-ui--mobile .dialog-header,
.common-notes-ui--mobile .dialog-content,
.common-notes-ui--mobile .dialog-actions {
  padding: var(--spacing-lg);
}

.common-notes-ui--mobile .dialog-actions {
  flex-direction: column-reverse;
}

.common-notes-ui--mobile .dialog-actions .q-btn {
  width: 100%;
  margin: var(--spacing-xs) 0;
}

/* 主题适配 */
.theme-dark .add-note-dialog {
  background-color: var(--color-surface);
}

.theme-dark .dialog-header,
.theme-dark .dialog-actions {
  background-color: var(--color-surface-elevated);
  border-color: var(--color-border);
}

/* 高对比度模式 */
@media (prefers-contrast: high) {
  .common-notes-ui {
    border-width: var(--border-width-medium);
  }
  
  .dialog-header,
  .dialog-actions {
    border-width: var(--border-width-medium);
  }
}

/* 减少动画偏好 */
@media (prefers-reduced-motion: reduce) {
  .common-notes-ui {
    transition: none;
  }
}

/* 打印样式 */
@media print {
  .common-notes-ui {
    border: var(--border-width-thin) solid #000000;
    background-color: #FFFFFF;
  }
  
  .add-note-dialog {
    display: none;
  }
}
</style>