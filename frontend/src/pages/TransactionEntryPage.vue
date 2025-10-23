<template>
  <q-page class="q-pa-md">
    <q-header class="bg-primary">
      <q-toolbar>
        <q-btn flat round icon="arrow_back" @click="$router.back()" />
        <q-toolbar-title>{{ pageTitle }}</q-toolbar-title>
      </q-toolbar>
    </q-header>
    <q-form @submit="onSubmit" class="column q-gutter-md">
      <!-- 金额信息 -->
      <q-card class="form-card">
        <q-card-section>
          <div class="text-subtitle1 q-mb-sm">
            <q-icon name="calculate" class="q-mr-sm" />金额与汇率
          </div>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-4">
              <q-input v-model.number="form.rmbAmount" label="人民币金额" type="number" step="0.01" :rules="[val => !!val && val > 0 || '金额必须大于0']" />
            </div>
            <div class="col-12 col-sm-4">
              <q-input v-model.number="form.hkdAmount" label="港币金额" type="number" step="0.01" :rules="[val => !!val && val > 0 || '金额必须大于0']" />
            </div>
            <div class="col-12 col-sm-4">
              <q-input v-model.number="form.exchangeRate" label="汇率（CNY/HKD）" type="number" step="0.00001" :rules="[val => !!val && val > 0 || '汇率必须大于0']" hint="将按 人民币/港币 自动计算，修改任一金额会重新计算" />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- 即时买断特有字段 -->
      <q-card v-if="transactionType === 'instant-buyout'" class="form-card">
        <q-card-section>
          <div class="text-subtitle1 q-mb-sm">
            <q-icon name="bolt" class="q-mr-sm" />即时买断
          </div>
          <q-input 
            v-model.number="form.instantRate" 
            label="即时买断汇率（CNY/HKD）" 
            type="number" 
            step="0.00001" 
            :rules="instantRateRules" 
            hint="将据此计算出账港币金额：港币 = 人民币 / 即时汇率" 
          />
        </q-card-section>
      </q-card>

      <!-- 基础信息 -->
      <q-card class="form-card">
        <q-card-section>
          <div class="text-subtitle1 q-mb-sm">
            <q-icon name="info" class="q-mr-sm" />基础信息
          </div>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-6">
              <q-select v-model="form.channelId" :options="channelOptions" label="支付渠道" emit-value map-options :rules="[val => !!val || '请选择支付渠道']" />
            </div>
            <div class="col-12 col-sm-6">
              <q-input v-model="form.remarks" label="备注" type="textarea" />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- 图片上传 -->
      <q-card class="form-card">
        <q-card-section>
          <div class="text-subtitle1 q-mb-sm">
            <q-icon name="image" class="q-mr-sm" />图片上传
          </div>
          <div class="q-mb-md">
            <div
              class="upload-area cursor-pointer flex flex-center"
              @click="openFileDialog"
              @dragover.prevent
              @drop.prevent="onDrop"
            >
              <q-icon name="add_a_photo" size="32px" class="q-mr-sm" />
              <div>点击或拖拽图片到此处</div>
            </div>
            <input
              ref="fileInputRef"
              type="file"
              accept="image/*"
              multiple
              class="hidden"
              @change="onFileSelect"
            />
          </div>
          <div>
            <q-item v-for="(file, idx) in images" :key="file.name + ':' + idx" class="q-my-xs bg-grey-2 rounded-borders">
              <q-item-section class="text-body2 ellipsis">{{ file.name }}</q-item-section>
              <q-item-section side>
                <q-btn dense flat color="negative" icon="close" @click.stop="removeImage(idx)" />
              </q-item-section>
            </q-item>
          </div>
        </q-card-section>
      </q-card>

      <!-- 底部操作条 -->
      <div class="row q-gutter-sm q-mt-md no-wrap">
        <q-btn color="grey-8" text-color="white" label="存为草稿" class="col" @click.prevent="saveDraft" :loading="isSavingDraft" />
        <q-btn color="primary" label="提交录入" class="col" type="submit" :loading="isSubmitting" />
      </div>
    </q-form>
  </q-page>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTransactionStore } from '@/stores/transaction'
import { useDraftStore } from '@/stores/draft'
import { Dialog, Notify } from 'quasar'
import { v4 as uuidv4 } from 'uuid'

const route = useRoute()
const router = useRouter()
const transactionStore = useTransactionStore()
const draftStore = useDraftStore()

const isEditing = computed(() => route.name === 'DraftEdit' || !!route.params.id)

const transactionType = ref(route.params.type || null)

const form = ref({
  rmbAmount: null,
  hkdAmount: null,
  exchangeRate: null,
  instantRate: null,
  channelId: null,
  remarks: ''
})

const editingDraftRef = ref(null) // 保存正在编辑的草稿对象（云端或本地）
const images = ref([])
const fileInputRef = ref(null)
const isSavingDraft = ref(false)
const isSubmitting = ref(false)

const pageTitle = computed(() => {
  const t = transactionType.value
  if (t === 'deposit') return '入账录入'
  if (t === 'withdrawal') return '出账录入'
  if (t === 'instant-buyout') return '即时买断录入'
  return '交易录入'
})

const channelOptions = computed(() => {
  return transactionStore.activeChannels.map(c => ({ label: c.name, value: c.id }))
})

// 校验：即时买断时即时汇率需大于0且不等于入账汇率
const instantRateRules = [
  val => !!val && val > 0 || '即时买断汇率必须大于0',
  val => val !== form.value.exchangeRate || '即时买断汇率不能与入账汇率相同'
]

// 自动计算汇率（人民币/港币），当两者均有效时始终重新计算
const autoCalcRate = () => {
  const rmb = Number(form.value.rmbAmount)
  const hkd = Number(form.value.hkdAmount)
  if (rmb > 0 && hkd > 0) {
    form.value.exchangeRate = Number((rmb / hkd).toFixed(5))
  }
}

watch(() => [form.value.rmbAmount, form.value.hkdAmount], () => {
  autoCalcRate()
})

const toBackendPayload = () => {
  // 统一转换为后端字段
  const mappedType = transactionType.value === 'deposit' ? 'income' : transactionType.value === 'withdrawal' ? 'outcome' : 'exchange'
  return {
    type: mappedType,
    rmb_amount: form.value.rmbAmount,
    hkd_amount: form.value.hkdAmount,
    exchange_rate: form.value.exchangeRate,
    instant_rate: form.value.instantRate,
    channel_id: form.value.channelId,
    notes: form.value.remarks
  }
}
const addFiles = (files) => {
  const list = Array.from(files || [])
    .filter(f => f && f.type && f.type.startsWith('image/'))
  list.forEach(file => {
    images.value.push(file)
  })
}

const onFileSelect = (evt) => {
  addFiles(evt.target?.files)
  if (evt?.target) evt.target.value = ''
}

const onDrop = (evt) => {
  addFiles(evt.dataTransfer?.files)
}

const openFileDialog = () => {
  fileInputRef.value?.click()
}

const removeImage = (index) => {
  images.value.splice(index, 1)
}


const loadDraftIfEditing = () => {
  if (!isEditing.value) return
  const draftId = route.params.id
  if (!draftId) return
  // 先云端再本地
  let found = draftStore.drafts.find(d => d.id?.toString() === draftId)
  if (!found) found = draftStore.localDrafts.find(d => d.uuid === draftId)
  if (!found) {
    Notify.create({ type: 'negative', message: '草稿不存在', position: 'top' })
    router.back()
    return
  }
  editingDraftRef.value = found

  // 映射到表单
  form.value.rmbAmount = found.rmb_amount ?? null
  form.value.hkdAmount = found.hkd_amount ?? null
  form.value.exchangeRate = found.exchange_rate ?? null
  form.value.instantRate = found.instant_rate ?? null
  form.value.channelId = found.channel_id ?? null
  form.value.remarks = found.notes ?? ''

  // 推断交易类型
  if (!transactionType.value) {
    if (found.type === 'income') transactionType.value = 'deposit'
    else if (found.type === 'outcome') transactionType.value = 'withdrawal'
    else if (found.type === 'exchange') transactionType.value = 'instant-buyout'
  }
}

const saveDraft = async () => {
  isSavingDraft.value = true
  try {
    const draft = toBackendPayload()
    if (editingDraftRef.value?.id) {
      await draftStore.updateDraft(editingDraftRef.value.id, { ...editingDraftRef.value, ...draft })
    } else if (editingDraftRef.value?.uuid) {
      // 更新本地草稿
      await draftStore.saveDraft({ ...editingDraftRef.value, ...draft })
    } else {
      await draftStore.saveDraft(draft)
    }
    Notify.create({ type: 'positive', message: '草稿已保存', position: 'top' })
    router.push('/drafts')
  } finally {
    isSavingDraft.value = false
  }
}

const onSubmit = async () => {
  isSubmitting.value = true
  try {
    const data = toBackendPayload()

    // 离线模式：加入待提交队列
    if (!navigator.onLine) {
      if (transactionType.value === 'instant-buyout') {
        const depositRecord = {
          type: 'income',
          rmb_amount: data.rmb_amount,
          hkd_amount: data.hkd_amount,
          exchange_rate: data.exchange_rate,
          channel_id: data.channel_id,
          // location_id 留空，后端将使用人员所属地点
          notes: data.notes,
          uuid: uuidv4()
        }
        const withdrawalRecord = {
          type: 'outcome',
          rmb_amount: data.rmb_amount,
          hkd_amount: Number((data.rmb_amount / data.instant_rate).toFixed(4)),
          exchange_rate: data.instant_rate,
          channel_id: data.channel_id,
          // location_id 留空，后端将使用人员所属地点
          notes: data.notes,
          uuid: uuidv4()
        }
        draftStore.addToPendingQueue(depositRecord)
        draftStore.addToPendingQueue(withdrawalRecord)
      } else {
        draftStore.addToPendingQueue({ ...data, uuid: uuidv4() })
      }
      Notify.create({ type: 'info', message: '离线模式：已加入待提交队列', position: 'top' })
      router.push('/home')
      return
    }

    // 编辑模式下，优先使用草稿提交流程
    if (editingDraftRef.value?.id) {
      const ok = await Dialog.create({ title: '确认提交', message: '提交后将转为正式交易记录', cancel: true, persistent: true }).onOk(() => true)
      if (ok) {
        const res = await draftStore.submitDraft(editingDraftRef.value.id)
        if (res.success) {
          Notify.create({ type: 'positive', message: '提交成功', position: 'top' })
          router.push('/home')
        }
      }
      return
    }

    // 非编辑或本地草稿：即时买断用批处理，其它直接创建
    if (transactionType.value === 'instant-buyout') {
      const result = await transactionStore.submitInstantBuyout(data)
      if (result.success) {
        Notify.create({ type: 'positive', message: '即时买断提交成功', position: 'top' })
        router.push('/home')
      }
    } else {
      const result = await transactionStore.createTransaction(data)
      if (result.success) {
        Notify.create({ type: 'positive', message: '交易提交成功', position: 'top' })
        router.push('/home')
      }
    }
  } finally {
    isSubmitting.value = false
  }
}

onMounted(async () => {
  await transactionStore.fetchChannels()
  loadDraftIfEditing()
})

onBeforeUnmount(() => {})
</script>

<style scoped>
/* 使用 Quasar 默认卡片风格，移除自定义圆角覆盖 */
.upload-area {
  border: 1px dashed rgba(0, 0, 0, 0.3);
  min-height: 120px;
  border-radius: 6px;
  color: #666;
}
.hidden { display: none; }
.ellipsis { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>


