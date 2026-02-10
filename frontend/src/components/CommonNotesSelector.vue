<template>
  <div 
    class="common-notes-selector"
    :class="selectorClasses"
  >
    <!-- 优化后的标题区域 -->
    <div 
      class="notes-header"
      :class="headerClasses"
    >
      <button
        class="header-toggle-btn"
        :class="toggleButtonClasses"
        :aria-expanded="expanded"
        :aria-label="expanded ? '收起常用备注' : '展开常用备注'"
        @click="expanded = !expanded"
        @keydown.enter="expanded = !expanded"
        @keydown.space.prevent="expanded = !expanded"
      >
        <!-- 图标 -->
        <q-icon 
          :name="expanded ? 'expand_less' : 'expand_more'"
          class="toggle-icon"
          :class="iconClasses"
        />
        
        <!-- 标题文字 -->
        <span class="header-title">常用备注</span>
        
        <!-- 状态指示器 -->
        <div 
          class="status-indicator"
          :class="statusClasses"
          :aria-hidden="true"
        />
      </button>
    </div>
    
    <!-- 优化后的备注列表（可折叠） -->
    <q-slide-transition>
      <div v-show="expanded" class="notes-content">
        <!-- 空状态 -->
        <div 
          v-if="notes.length === 0" 
          class="empty-state"
          :class="emptyStateClasses"
          role="status"
          aria-label="暂无常用备注"
        >
          <div class="empty-icon-wrapper">
            <q-icon 
              name="note_add" 
              class="empty-icon"
              :class="emptyIconClasses"
            />
          </div>
          
          <div class="empty-message">
            <h3 class="message-title">暂无常用备注</h3>
            <p class="message-description">点击下方"添加"按钮创建您的第一个常用备注</p>
          </div>
        </div>
        
        <!-- 优化后的备注列表 -->
        <div v-else class="notes-list-container">
          <div class="notes-list" role="list" :aria-label="`共${notes.length}条备注`">
            <div
              v-for="note in notes"
              :key="note.id"
              class="note-item"
              :class="noteItemClasses(note)"
              :aria-expanded="false"
              :aria-selected="false"
              :tabindex="0"
              role="listitem"
            >
              <!-- 备注内容 - 点击选择 -->
              <div 
                class="note-content"
                @click="selectNote(note)"
                @keydown.enter="selectNote(note)"
                @keydown.space.prevent="selectNote(note)"
              >
                {{ note.content }}
              </div>
              
              <!-- 删除按钮 -->
              <q-btn
                flat
                dense
                round
                icon="delete_outline"
                :aria-label="`删除备注: ${note.content.substring(0, 20)}...`"
                class="delete-btn"
                @click.stop="confirmDelete(note)"
              />
            </div>
          </div>
        </div>
      </div>
    </q-slide-transition>
    
    <!-- 添加按钮 - 移到外面，始终显示 -->
    <div class="notes-footer">
      <q-btn
        label="添加备注"
        color="primary"
        icon="add"
        @click="showAddDialog = true"
      />
    </div>
    
    <!-- 优化后的添加对话框 -->
    <q-dialog v-model="showAddDialog" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">添加常用备注</div>
        </q-card-section>
        
        <q-card-section>
          <q-input
            v-model="newNoteContent"
            type="textarea"
            label="备注内容"
            :rows="4"
            maxlength="500"
            counter
            autofocus
            outlined
            @keyup.ctrl.enter="addNote"
          />
        </q-card-section>
        
        <q-card-actions align="right">
          <q-btn 
            flat 
            label="取消" 
            @click="cancelAdd"
          />
          <q-btn
            label="添加"
            color="primary"
            :loading="adding"
            @click="addNote"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import api from '@/utils/api'

const props = defineProps({
  modelValue: String
})

const emit = defineEmits(['update:modelValue'])

const $q = useQuasar()
const expanded = ref(false)
const notes = ref([])
const showAddDialog = ref(false)
const newNoteContent = ref('')
const adding = ref(false)

// 响应式设计检测
const isMobile = computed(() => {
  if (typeof window === 'undefined') return true
  return window.innerWidth < 768
})

const isTablet = computed(() => {
  if (typeof window === 'undefined') return false
  return window.innerWidth >= 768 && window.innerWidth < 1024
})

const isDesktop = computed(() => {
  if (typeof window === 'undefined') return false
  return window.innerWidth >= 1024
})

// 样式计算
const selectorClasses = computed(() => ({
  'common-notes-selector--mobile': isMobile.value,
  'common-notes-selector--tablet': isTablet.value,
  'common-notes-selector--desktop': isDesktop.value,
  'common-notes-selector--expanded': expanded.value,
  'common-notes-selector--collapsed': !expanded.value
}))

const headerClasses = computed(() => ({
  'notes-header--mobile': isMobile.value,
  'notes-header--desktop': isDesktop.value,
  'notes-header--expanded': expanded.value,
  'notes-header--collapsed': !expanded.value
}))

const toggleButtonClasses = computed(() => ({
  'toggle-btn--expanded': expanded.value,
  'toggle-btn--collapsed': !expanded.value,
  'toggle-btn--mobile': isMobile.value,
  'toggle-btn--desktop': isDesktop.value
}))

const iconClasses = computed(() => ({
  'icon--expanded': expanded.value,
  'icon--collapsed': !expanded.value
}))

const statusClasses = computed(() => ({
  'status--expanded': expanded.value,
  'status--collapsed': !expanded.value
}))

const emptyStateClasses = computed(() => ({
  'empty-state--mobile': isMobile.value,
  'empty-state--desktop': isDesktop.value
}))

const emptyIconClasses = computed(() => ({
  'icon--mobile': isMobile.value,
  'icon--desktop': isDesktop.value
}))

const listClasses = computed(() => ({
  'notes-list--mobile': isMobile.value,
  'notes-list--desktop': isDesktop.value
}))

const noteItemClasses = (note) => ({
  'note-item--mobile': isMobile.value,
  'note-item--desktop': isDesktop.value
})

// 日期格式化
const formatDate = (dateString) => {
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

// 加载常用备注
const loadNotes = async () => {
  try {
    const response = await api.get('/common-notes')
    
    if (response.data.success) {
      notes.value = response.data.data
    }
  } catch (error) {
    console.error('加载常用备注失败:', error)
    $q.notify({
      type: 'negative',
      message: '加载常用备注失败',
      caption: error.response?.data?.message || error.message,
      position: isMobile.value ? 'bottom' : 'top-right'
    })
  }
}

// 选择备注
const selectNote = (note) => {
  emit('update:modelValue', note.content)
  $q.notify({
    type: 'positive',
    message: '已填充备注',
    caption: note.content.length > 30 
      ? `${note.content.substring(0, 30)}...` 
      : note.content,
    timeout: 2000,
    position: isMobile.value ? 'bottom' : 'top-right'
  })
}

// 添加备注
const addNote = async () => {
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
  
  adding.value = true
  try {
    const response = await api.post('/common-notes', {
      content: content
    })
    
    if (response.data.success) {
      // 检查返回的数据结构
      if (Array.isArray(response.data.data)) {
        // 如果返回的是数组（整个列表），直接替换
        notes.value = response.data.data
      } else if (response.data.data && typeof response.data.data === 'object') {
        // 如果返回的是单个对象（新添加的备注），添加到列表开头
        notes.value.unshift(response.data.data)
      } else {
        // 如果数据结构不明确，重新加载列表
        await loadNotes()
      }
      
      newNoteContent.value = ''
      showAddDialog.value = false
      $q.notify({
        type: 'positive',
        message: '添加成功',
        timeout: 2000,
        position: isMobile.value ? 'bottom' : 'top-right'
      })
    } else {
      $q.notify({
        type: 'negative',
        message: response.data.message || '添加失败',
        position: isMobile.value ? 'bottom' : 'top-right'
      })
    }
  } catch (error) {
    console.error('添加备注失败:', error)
    $q.notify({
      type: 'negative',
      message: '添加失败',
      caption: error.response?.data?.message || error.message,
      position: isMobile.value ? 'bottom' : 'top-right'
    })
  } finally {
    adding.value = false
  }
}

// 取消添加
const cancelAdd = () => {
  showAddDialog.value = false
  newNoteContent.value = ''
}

// 确认删除
const confirmDelete = (note) => {
  $q.dialog({
    title: '确认删除',
    message: `确定要删除这条备注吗？\n\n"${note.content.substring(0, 50)}${note.content.length > 50 ? '...' : ''}"`,
    cancel: {
      label: '取消',
      flat: true
    },
    ok: {
      label: '删除',
      color: 'negative'
    },
    persistent: true
  }).onOk(async () => {
    try {
      const response = await api.delete(`/common-notes/${note.id}`)
      if (response.data.success) {
        notes.value = notes.value.filter(n => n.id !== note.id)
        $q.notify({
          type: 'positive',
          message: '删除成功',
          timeout: 2000,
          position: isMobile.value ? 'bottom' : 'top-right'
        })
      }
    } catch (error) {
      $q.notify({
        type: 'negative',
        message: '删除失败',
        caption: error.response?.data?.message || error.message,
        position: isMobile.value ? 'bottom' : 'top-right'
      })
    }
  })
}

onMounted(() => {
  loadNotes()
})
</script>

<style scoped>
/* 导入优化后的CSS变量系统 */
@import '@/styles/common-notes-variables.css';

/* ===== 主容器样式 ===== */
.common-notes-selector {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  margin-bottom: var(--spacing-component);
  background-color: var(--notes-container-background);
  border: none;
  border-radius: var(--notes-container-border-radius);
  transition: var(--transition-all);
  -webkit-tap-highlight-color: transparent;
}

.common-notes-selector:focus-within {
  box-shadow: none;
}

/* 响应式容器类 */
.common-notes-selector--mobile {
  font-size: var(--font-size-base);
}

.common-notes-selector--desktop {
  font-size: var(--font-size-base-desktop);
}

/* ===== 标题区域样式 ===== */
.notes-header {
  padding: var(--spacing-xs) var(--spacing-md);
  border-bottom: var(--border-width-thin) solid var(--color-border-light);
}

.notes-header--expanded {
  border-bottom-color: var(--color-border);
}

.header-toggle-btn {
  width: 100%;
  min-height: 36px;
  padding: 8px 12px;
  background: transparent;
  border: none;
  border-radius: var(--border-radius-sm);
  color: var(--color-text-primary);
  cursor: pointer;
  transition: var(--transition-all);
  display: flex;
  align-items: center;
  justify-content: space-between;
  text-align: left;
}

.header-toggle-btn:hover {
  background-color: var(--color-surface);
}

.header-toggle-btn:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.header-toggle-btn:active {
  transform: scale(0.98);
}

.toggle-icon {
  font-size: 18px;
  color: var(--color-primary);
  transition: var(--transition-transform);
}

.icon--expanded {
  transform: rotate(180deg);
}

.header-title {
  font-size: 15px;
  font-weight: var(--font-weight-medium);
  color: var(--color-text-primary);
  flex: 1;
  margin-left: var(--spacing-xs);
}

.status-indicator {
  width: 6px;
  height: 6px;
  border-radius: var(--border-radius-round);
  background-color: var(--color-text-tertiary);
  transition: var(--transition-all);
}

.status--expanded {
  background-color: var(--color-primary);
}

/* ===== 内容区域样式 ===== */
.notes-content {
  padding: var(--spacing-sm) var(--spacing-md);
}

/* ===== 空状态样式 ===== */
.empty-state {
  text-align: center;
  padding: var(--spacing-xl);
  color: var(--color-text-secondary);
}

.empty-icon-wrapper {
  margin-bottom: var(--spacing-md);
}

.empty-icon {
  font-size: 48px;
  color: var(--color-text-tertiary);
}

.empty-message {
  margin-bottom: var(--spacing-lg);
}

.message-title {
  font-size: var(--font-size-title);
  font-weight: var(--font-weight-medium);
  color: var(--color-text-primary);
  margin: 0 0 var(--spacing-xs) 0;
}

.message-description {
  font-size: var(--font-size-base);
  color: var(--color-text-secondary);
  line-height: var(--line-height-base);
  margin: 0;
}

/* ===== 列表容器样式 ===== */
.notes-list-container {
  margin-bottom: var(--spacing-md);
}

.notes-list {
  max-height: 250px;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-list-item);
  margin-bottom: var(--spacing-md);
  scrollbar-width: thin;
  scrollbar-color: var(--color-border) transparent;
}

.notes-list::-webkit-scrollbar {
  width: 6px;
}

.notes-list::-webkit-scrollbar-track {
  background: transparent;
}

.notes-list::-webkit-scrollbar-thumb {
  background-color: var(--color-border);
  border-radius: var(--border-radius-sm);
}

/* ===== 备注项样式 ===== */
.note-item {
  background-color: var(--note-item-background);
  border-radius: var(--note-item-border-radius);
  border: var(--border-width-thin) solid var(--color-border);
  transition: var(--transition-all);
  cursor: pointer;
  position: relative;
  padding: 12px 40px 12px 12px;
  min-height: 50px;
}

.note-item:hover {
  background-color: var(--note-item-background-hover);
  border-color: var(--color-primary);
  box-shadow: var(--shadow-sm);
}

.note-item:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

/* ===== 备注内容样式 ===== */
.note-content {
  font-size: 14px;
  line-height: 1.5;
  color: var(--color-text-primary);
  word-wrap: break-word;
  overflow-wrap: break-word;
  white-space: pre-wrap;
  /* 限制最多显示2行 */
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* ===== 删除按钮样式 ===== */
.delete-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  color: var(--color-text-tertiary);
  transition: var(--transition-all);
}

.delete-btn:hover {
  color: var(--color-error);
  background-color: rgba(220, 53, 69, 0.1);
}

/* ===== 底部操作区样式 ===== */
.notes-footer {
  display: flex;
  justify-content: flex-end;
  padding: 12px 12px 0 0;
  border-top: var(--border-width-thin) solid var(--color-border-light);
}

/* ===== 响应式适配 ===== */
@media (max-width: 767px) {
  .common-notes-selector {
    font-size: var(--font-size-base);
  }
  
  .header-title {
    font-size: var(--font-size-title);
  }
  
  .note-content {
    font-size: var(--font-size-base);
  }
}

@media (min-width: 768px) {
  .note-item {
    padding: var(--spacing-md);
  }
  
  .header-title {
    font-size: var(--font-size-title-desktop);
  }
  
  .note-content {
    font-size: var(--font-size-base-desktop);
  }
}

/* ===== 无障碍优化 ===== */
@media (prefers-reduced-motion: reduce) {
  .note-item,
  .header-toggle-btn,
  .toggle-icon {
    transition: none;
  }
}

@media (prefers-contrast: high) {
  .note-item {
    border-width: var(--border-width-medium);
  }
  
  .header-toggle-btn:focus,
  .note-item:focus {
    outline-width: 3px;
  }
}

/* ===== 打印样式 ===== */
@media print {
  .header-toggle-btn,
  .notes-footer,
  .delete-btn {
    display: none;
  }
  
  .notes-content {
    padding: 0;
  }
  
  .note-item {
    border: var(--border-width-thin) solid #000;
    box-shadow: none;
    break-inside: avoid;
  }
  
  .note-content {
    -webkit-line-clamp: unset;
    line-clamp: unset;
    overflow: visible;
  }
}
</style>
