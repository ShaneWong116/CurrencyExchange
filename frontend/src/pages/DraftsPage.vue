<template>
  <q-page>
    <!-- 顶部导航 -->
    <q-header class="bg-primary">
      <q-toolbar>
        <q-btn flat round icon="arrow_back" @click="$router.back()" />
        <q-toolbar-title>草稿管理</q-toolbar-title>
        <q-btn flat round icon="sync" @click="syncDrafts" :loading="isSyncing" />
      </q-toolbar>
    </q-header>

    <div class="q-pa-md">
      <!-- 统计信息 -->
      <q-card class="info-card q-mb-md summary-card">
        <q-card-section class="row items-center">
          <div class="col">
            <div class="text-h6">草稿概览</div>
            <div class="text-grey-7 text-caption q-mt-xs">
              共 {{ totalDrafts }} 条 · 本地 {{ draftStore.localDrafts.length }} · 云端 {{ draftStore.drafts.length }}
            </div>
          </div>
          <div class="col-auto">
            <q-chip v-if="draftStore.pendingCount > 0" color="orange" text-color="white" icon="schedule">
              待提交 {{ draftStore.pendingCount }}
            </q-chip>
          </div>
        </q-card-section>
      </q-card>

      <!-- 草稿列表 -->
      <div v-if="allDrafts.length === 0" class="text-center q-pa-xl empty-state">
        <q-icon name="note_add" size="64px" color="grey-5" />
        <div class="text-h6 text-grey-7 q-mt-sm">暂无草稿</div>
        <div class="text-caption text-grey-6">保存的草稿会在这里显示</div>
        
        <q-btn
          color="primary"
          label="新建交易"
          @click="$router.push('/transaction')"
          class="q-mt-md"
        />
      </div>

      <div v-else class="q-gutter-md">
        <q-card
          v-for="draft in allDrafts"
          :key="draft.uuid || draft.id"
          class="list-item cursor-pointer draft-item"
          @click="editDraft(draft)"
        >
          <q-card-section>
            <div class="row items-center">
              <div class="col">
                <div class="row items-center q-gutter-sm">
                  <q-icon
                    :name="getTypeIcon(draft.type)"
                    :color="getTypeColor(draft.type)"
                    size="sm"
                  />
                  <span class="text-subtitle1 text-weight-medium">
                    {{ getTypeLabel(draft.type) }}
                  </span>
                  <q-badge
                    v-if="draft.id"
                    color="positive"
                    label="云端"
                  />
                  <q-badge
                    v-else
                    color="orange"
                    label="本地"
                  />
                </div>
                
                <div class="text-grey-6 q-mt-xs">
                  <div v-if="draft.rmb_amount || draft.hkd_amount">
                    <span v-if="draft.rmb_amount">￥{{ draft.rmb_amount }}</span>
                    <span v-if="draft.rmb_amount && draft.hkd_amount"> / </span>
                    <span v-if="draft.hkd_amount">HK${{ draft.hkd_amount }}</span>
                  </div>
                  <div v-if="draft.channel">{{ draft.channel.name }}</div>
                  <div>{{ formatTime(draft.last_modified || draft.updated_at) }}</div>
                </div>
              </div>
              
              <div class="col-auto">
                <q-btn-group flat>
                  <q-btn
                    flat
                    round
                    icon="send"
                    color="primary"
                    @click.stop="submitDraft(draft)"
                    :disable="!canSubmit(draft)"
                  >
                    <q-tooltip>提交</q-tooltip>
                  </q-btn>
                  <q-btn
                    flat
                    round
                    icon="delete"
                    color="negative"
                    @click.stop="deleteDraft(draft)"
                  >
                    <q-tooltip>删除</q-tooltip>
                  </q-btn>
                </q-btn-group>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- 悬浮添加按钮 -->
      <q-page-sticky position="bottom-right" :offset="[18, 18]">
        <q-btn
          fab
          icon="add"
          color="primary"
          @click="$router.push('/transaction')"
        />
      </q-page-sticky>
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDraftStore } from '@/stores/draft'
import { Dialog, Notify } from 'quasar'

const router = useRouter()
const draftStore = useDraftStore()

const isSyncing = ref(false)

// 计算属性
const allDrafts = computed(() => draftStore.allDrafts)
const totalDrafts = computed(() => allDrafts.value.length)

// 获取交易类型图标
const getTypeIcon = (type) => {
  const icons = {
    income: 'add_circle',
    outcome: 'remove_circle',
    exchange: 'swap_horiz'
  }
  return icons[type] || 'help'
}

// 获取交易类型颜色
const getTypeColor = (type) => {
  const colors = {
    income: 'positive',
    outcome: 'negative',
    exchange: 'info'
  }
  return colors[type] || 'grey'
}

// 获取交易类型标签
const getTypeLabel = (type) => {
  const labels = {
    income: '入账',
    outcome: '出账',
    exchange: '兑换'
  }
  return labels[type] || '未知'
}

// 格式化时间
const formatTime = (timeString) => {
  if (!timeString) return ''
  const date = new Date(timeString)
  return date.toLocaleString('zh-CN')
}

// 检查是否可以提交
// 云端草稿：只要在线即可点击，由后端做完整性校验
// 本地草稿：仍需基本字段完整
const canSubmit = (draft) => {
  if (!navigator.onLine) return false
  if (draft.id) return true
  return draft.rmb_amount && draft.hkd_amount && draft.exchange_rate && draft.channel_id
}

// 编辑草稿
const editDraft = (draft) => {
  router.push(`/drafts/${draft.id || draft.uuid}/edit`)
}

// 提交草稿
const submitDraft = async (draft) => {
  if (!canSubmit(draft)) {
    Notify.create({
      type: 'warning',
      message: '草稿信息不完整或网络异常，无法提交',
      position: 'top'
    })
    return
  }

  Dialog.create({
    title: '确认提交',
    message: '确定要提交这个草稿吗？提交后将转为正式交易记录。',
    cancel: true,
    persistent: true
  }).onOk(async () => {
    if (draft.id) {
      // 云端草稿
      const result = await draftStore.submitDraft(draft.id)
      if (result.success) {
        Notify.create({
          type: 'positive',
          message: '草稿提交成功',
          position: 'top'
        })
      }
    } else {
      // 本地草稿，先保存到云端再提交
      const saveResult = await draftStore.saveDraft(draft)
      if (saveResult.success && saveResult.draft.id) {
        const submitResult = await draftStore.submitDraft(saveResult.draft.id)
        if (submitResult.success) {
          // 从本地草稿中移除
          draftStore.localDrafts = draftStore.localDrafts.filter(d => d.uuid !== draft.uuid)
        }
      }
    }
  })
}

// 删除草稿
const deleteDraft = (draft) => {
  Dialog.create({
    title: '确认删除',
    message: '确定要删除这个草稿吗？此操作无法撤销。',
    cancel: true,
    persistent: true
  }).onOk(async () => {
    if (draft.id) {
      // 云端草稿
      await draftStore.deleteDraft(draft.id)
    } else {
      // 本地草稿
      draftStore.localDrafts = draftStore.localDrafts.filter(d => d.uuid !== draft.uuid)
      Notify.create({
        type: 'positive',
        message: '草稿删除成功',
        position: 'top'
      })
    }
  })
}

// 同步草稿
const syncDrafts = async () => {
  if (!navigator.onLine) {
    Notify.create({
      type: 'warning',
      message: '网络连接异常，无法同步',
      position: 'top'
    })
    return
  }

  isSyncing.value = true
  try {
    await draftStore.syncDrafts()
    await draftStore.fetchDrafts()
  } finally {
    isSyncing.value = false
  }
}

onMounted(() => {
  draftStore.fetchDrafts()
})
</script>

<style scoped>
.list-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.summary-card {
  border-radius: 12px;
}
.draft-item {
  border-radius: 10px;
}
.empty-state {
  opacity: 0.9;
}
</style>
