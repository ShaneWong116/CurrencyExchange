<template>
  <q-page>
    <!-- 顶部导航 -->
    <q-header class="bg-primary">
      <q-toolbar>
        <q-btn flat round icon="arrow_back" @click="$router.back()" />
        <q-toolbar-title>编辑草稿</q-toolbar-title>
        <q-btn flat round icon="save" @click="saveDraft" :loading="isSaving" />
      </q-toolbar>
    </q-header>

    <div class="q-pa-md" v-if="draft">
      <q-form @submit="submitDraft" class="q-gutter-md">
        <!-- 交易类型 -->
        <q-select
          v-model="draft.type"
          :options="transactionTypes"
          label="交易类型 *"
          outlined
          emit-value
          map-options
          :rules="[val => !!val || '请选择交易类型']"
        >
          <template v-slot:prepend>
            <q-icon name="swap_horiz" />
          </template>
        </q-select>

        <!-- 人民币金额 -->
        <q-input
          v-model.number="draft.rmb_amount"
          label="人民币金额 *"
          type="number"
          step="0.01"
          outlined
          :rules="[val => !!val && val > 0 || '请输入有效金额']"
        >
          <template v-slot:prepend>
            <q-icon name="currency_yuan" />
          </template>
        </q-input>

        <!-- 港币金额 -->
        <q-input
          v-model.number="draft.hkd_amount"
          label="港币金额 *"
          type="number"
          step="0.01"
          outlined
          :rules="[val => !!val && val > 0 || '请输入有效金额']"
        >
          <template v-slot:prepend>
            <q-icon name="currency_exchange" />
          </template>
        </q-input>

        <!-- 汇率 -->
        <q-input
          v-model.number="draft.exchange_rate"
          label="交易汇率 *"
          type="number"
          step="0.00001"
          outlined
          :rules="[val => !!val && val > 0 || '请输入有效汇率']"
        >
          <template v-slot:prepend>
            <q-icon name="trending_up" />
          </template>
        </q-input>

        <!-- 即时汇率（兑换交易） -->
        <q-input
          v-if="draft.type === 'exchange'"
          v-model.number="draft.instant_rate"
          label="即时汇率"
          type="number"
          step="0.00001"
          outlined
        >
          <template v-slot:prepend>
            <q-icon name="calculate" />
          </template>
        </q-input>

        <!-- 支付渠道 -->
        <q-select
          v-model="draft.channel_id"
          :options="channelOptions"
          label="支付渠道 *"
          outlined
          emit-value
          map-options
          :rules="[val => !!val || '请选择支付渠道']"
        >
          <template v-slot:prepend>
            <q-icon name="account_balance" />
          </template>
        </q-select>

        <!-- 地点（自动带入：显示为只读） -->
        <q-select
          v-model="draft.location_id"
          :options="locationOptions"
          label="交易地点"
          outlined
          emit-value
          map-options
          use-input
          input-debounce="0"
          :readonly="true"
          hint="地点由人员所属地点自动带入"
        />

        <!-- 备注 -->
        <!-- 优化后的常用备注UI -->
        <CommonNotesUI 
          :initial-expanded="false"
          header-title="常用备注"
          @note-select="handleNoteSelect"
        />
        <q-input
          v-model="draft.notes"
          label="备注"
          type="textarea"
          outlined
          rows="3"
          class="q-mt-md"
        >
          <template v-slot:prepend>
            <q-icon name="note" />
          </template>
        </q-input>

        <!-- 草稿信息 -->
        <q-card class="info-card">
          <q-card-section>
            <div class="text-subtitle2 q-mb-sm">草稿信息</div>
            <div class="text-caption text-grey-6">
              <div>创建时间: {{ formatTime(draft.created_at) }}</div>
              <div>修改时间: {{ formatTime(draft.last_modified || draft.updated_at) }}</div>
              <div>状态: {{ draft.id ? '已同步' : '本地草稿' }}</div>
            </div>
          </q-card-section>
        </q-card>

        <!-- 操作按钮 -->
        <div class="row q-gutter-md q-mt-lg">
          <div class="col">
            <q-btn
              label="保存草稿"
              color="grey-6"
              class="full-width action-button"
              @click="saveDraft"
              :loading="isSaving"
            />
          </div>
          <div class="col">
            <q-btn
              type="submit"
              label="提交录入"
              color="primary"
              class="full-width action-button"
              :loading="isSubmitting"
              :disable="!canSubmit"
            />
          </div>
        </div>
      </q-form>
    </div>

    <!-- 加载状态 -->
    <div v-else class="flex flex-center q-pa-xl">
      <q-spinner size="xl" color="primary" />
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDraftStore } from '@/stores/draft'
import { useTransactionStore } from '@/stores/transaction'
import { Dialog, Notify } from 'quasar'
import CommonNotesUI from '@/components/CommonNotesUI.vue'

const route = useRoute()
const router = useRouter()
const draftStore = useDraftStore()
const transactionStore = useTransactionStore()
const locationOptions = computed(() => {
  return (transactionStore.locations || []).map(l => ({ label: l.name, value: l.id }))
})

const draft = ref(null)
const isSaving = ref(false)
const isSubmitting = ref(false)

// 交易类型选项
const transactionTypes = [
  { label: '入账（人民币增加，港币减少）', value: 'income' },
  { label: '出账（人民币减少，港币增加）', value: 'outcome' },
  { label: '兑换交易', value: 'exchange' }
]

// 渠道选项
const channelOptions = computed(() => {
  return transactionStore.activeChannels.map(channel => ({
    label: channel.name,
    value: channel.id
  }))
})

// 检查是否可以提交
const canSubmit = computed(() => {
  return draft.value &&
         draft.value.rmb_amount &&
         draft.value.hkd_amount &&
         draft.value.exchange_rate &&
         draft.value.channel_id &&
         navigator.onLine
})

// 格式化时间
const formatTime = (timeString) => {
  if (!timeString) return ''
  const date = new Date(timeString)
  return date.toLocaleString('zh-CN')
}

// 处理备注选择
const handleNoteSelect = (note) => {
  if (draft.value) {
    draft.value.notes = note.content
  }
}

// 保存草稿
const saveDraft = async () => {
  if (!draft.value) return
  
  isSaving.value = true
  try {
    if (draft.value.id) {
      // 更新现有草稿
      await draftStore.updateDraft(draft.value.id, draft.value)
    } else {
      // 保存本地草稿
      const saveResult = await draftStore.saveDraft(draft.value)
      if (saveResult.success) {
        draft.value = saveResult.draft
      }
    }
  } finally {
    isSaving.value = false
  }
}

// 提交草稿
const submitDraft = async () => {
  if (!canSubmit.value) {
    Notify.create({
      type: 'warning',
      message: '信息不完整或网络异常，无法提交',
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
    isSubmitting.value = true
    try {
      if (draft.value.id) {
        // 云端草稿直接提交
        const result = await draftStore.submitDraft(draft.value.id)
        if (result.success) {
          router.push('/home')
        }
      } else {
        // 本地草稿先保存再提交
        const saveResult = await draftStore.saveDraft(draft.value)
        if (saveResult.success && saveResult.draft.id) {
          const submitResult = await draftStore.submitDraft(saveResult.draft.id)
          if (submitResult.success) {
            // 从本地草稿中移除
            draftStore.localDrafts = draftStore.localDrafts.filter(d => d.uuid !== draft.value.uuid)
            router.push('/home')
          }
        }
      }
    } finally {
      isSubmitting.value = false
    }
  })
}

// 加载草稿数据
const loadDraft = () => {
  const draftId = route.params.id
  
  // 先从云端草稿中查找
  let foundDraft = draftStore.drafts.find(d => d.id.toString() === draftId)
  
  // 如果没找到，从本地草稿中查找
  if (!foundDraft) {
    foundDraft = draftStore.localDrafts.find(d => d.uuid === draftId)
  }
  
  if (foundDraft) {
    draft.value = { ...foundDraft }
  } else {
    Notify.create({
      type: 'negative',
      message: '草稿不存在',
      position: 'top'
    })
    router.back()
  }
}

onMounted(async () => {
  // 确保数据已加载
  await draftStore.fetchDrafts()
  await transactionStore.fetchChannels()
  await transactionStore.fetchLocations()
  
  loadDraft()
})
</script>
