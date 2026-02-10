<template>
  <q-page class="transaction-entry-page">
    <!-- Header -->
    <header class="page-header">
      <div class="header-content">
        <q-btn 
          flat 
          round 
          icon="arrow_back" 
          @click="$router.back()" 
          class="back-btn"
        />
        <div class="header-title">{{ pageTitle }}</div>
      </div>
    </header>

    <div class="form-container">
      <q-form @submit="onSubmit" class="transaction-form">
      <!-- 金额信息 -->
      <q-card class="form-card">
        <q-card-section>
          <div class="text-subtitle1 q-mb-sm">
            <q-icon name="calculate" class="q-mr-sm" />金额与汇率
          </div>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-4">
              <q-input v-model.number="form.rmbAmount" label="人民币金额" type="number" step="0.01" :rules="[val => val !== null && val !== undefined && val >= 0 || '金额必须大于等于0']" />
            </div>
            <div class="col-12 col-sm-4">
              <q-input v-model.number="form.hkdAmount" label="港币金额" type="number" step="0.01" :rules="[val => val !== null && val !== undefined && val >= 0 || '金额必须大于等于0']" />
            </div>
            <div class="col-12 col-sm-4">
              <q-input 
                v-model="exchangeRateDisplay" 
                label="汇率（CNY/HKD）" 
                type="text"
                inputmode="decimal"
                @blur="formatExchangeRate"
                @focus="onExchangeRateFocus"
                :rules="exchangeRateRules" 
                hint="保留三位小数，如：0.880"
              />
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
            v-model="instantRateDisplay" 
            label="即时买断汇率（CNY/HKD）" 
            type="text"
            inputmode="decimal"
            @blur="formatInstantRate"
            @focus="onInstantRateFocus"
            :rules="instantRateDisplayRules" 
            hint="保留三位小数，如：0.875。将据此计算出账港币金额" 
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
              <!-- 优化后的常用备注UI -->
              <CommonNotesUI 
                :initial-expanded="false"
                header-title="常用备注"
                @note-select="handleNoteSelect"
              />
              <q-input v-model="form.remarks" label="备注" type="textarea" class="q-mt-md" />
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
              <div>点击或拖拽图片到此处（最多{{ MAX_IMAGES }}张）</div>
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
          <div v-if="images.length > 0" class="image-list">
            <div v-for="(file, idx) in images" :key="idx" class="image-item">
              <img :src="getPreviewUrl(file)" class="image-thumb" />
              <span class="image-name">{{ file.name }}</span>
              <q-btn dense flat round color="negative" icon="close" size="sm" @click.stop="removeImage(idx)" />
            </div>
          </div>
        </q-card-section>
      </q-card>

        <!-- 底部操作条 -->
        <div class="action-buttons">
          <button 
            type="button"
            class="action-btn draft-btn" 
            @click.prevent="saveDraft" 
            :disabled="isSavingDraft"
          >
            <q-spinner v-if="isSavingDraft" size="20px" color="white" />
            <q-icon v-else name="save" size="20px" />
            <span>存为草稿</span>
          </button>
          
          <button 
            type="submit"
            class="action-btn submit-btn" 
            :disabled="isSubmitting"
          >
            <q-spinner v-if="isSubmitting" size="20px" color="white" />
            <q-icon v-else name="check_circle" size="20px" />
            <span>提交录入</span>
          </button>
        </div>
      </q-form>
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTransactionStore } from '@/stores/transaction'
import { useDraftStore } from '@/stores/draft'
import { useAuthStore } from '@/stores/auth'
import { Dialog, Notify } from 'quasar'
import { v4 as uuidv4 } from 'uuid'
import { api } from '@/utils/api'
import CommonNotesUI from '@/components/CommonNotesUI.vue'

const route = useRoute()
const router = useRouter()
const transactionStore = useTransactionStore()
const draftStore = useDraftStore()
const authStore = useAuthStore()

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

// 汇率显示值(文本格式,保留3位小数)
const exchangeRateDisplay = ref('')
const instantRateDisplay = ref('')

const editingDraftRef = ref(null) // 保存正在编辑的草稿对象（云端或本地）
const images = ref([])
const previewUrls = ref(new Map()) // 缓存预览URL
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

// 汇率验证规则
const exchangeRateRules = [
  val => {
    if (!val || val.trim() === '') return '汇率不能为空'
    const num = parseFloat(val)
    return !isNaN(num) && num > 0 || '汇率必须大于0'
  }
]

// 即时买断汇率验证规则
const instantRateDisplayRules = [
  val => {
    if (!val || val.trim() === '') return '即时买断汇率不能为空'
    const num = parseFloat(val)
    if (isNaN(num) || num <= 0) return '即时买断汇率必须大于0'
    return num !== form.value.exchangeRate || '即时买断汇率不能与入账汇率相同'
  }
]

// 汇率格式化方法
const formatExchangeRate = () => {
  const val = exchangeRateDisplay.value
  if (!val || val.trim() === '') return
  
  const num = parseFloat(val)
  if (!isNaN(num) && num > 0) {
    form.value.exchangeRate = num
    exchangeRateDisplay.value = num.toFixed(3)
  }
}

const onExchangeRateFocus = () => {
  // 聚焦时如果是格式化的值,保持原样
}

// 即时买断汇率格式化方法
const formatInstantRate = () => {
  const val = instantRateDisplay.value
  if (!val || val.trim() === '') return
  
  const num = parseFloat(val)
  if (!isNaN(num) && num > 0) {
    form.value.instantRate = num
    instantRateDisplay.value = num.toFixed(3)
  }
}

const onInstantRateFocus = () => {
  // 聚焦时如果是格式化的值,保持原样
}

// 自动计算汇率（人民币/港币），当两者均有效时始终重新计算
const autoCalcRate = () => {
  const rmb = Number(form.value.rmbAmount)
  const hkd = Number(form.value.hkdAmount)
  // 只有当港币大于0时才计算汇率，避免除以0
  // 人民币可以为0，但港币必须大于0才能计算汇率
  if (rmb >= 0 && hkd > 0) {
    const rate = Number((rmb / hkd).toFixed(3))
    form.value.exchangeRate = rate
    exchangeRateDisplay.value = rate.toFixed(3)
  }
}

// 监听汇率显示值的变化,同步到form
watch(exchangeRateDisplay, (newVal) => {
  const num = parseFloat(newVal)
  if (!isNaN(num) && num > 0) {
    form.value.exchangeRate = num
  }
})

watch(instantRateDisplay, (newVal) => {
  const num = parseFloat(newVal)
  if (!isNaN(num) && num > 0) {
    form.value.instantRate = num
  }
})

watch(() => [form.value.rmbAmount, form.value.hkdAmount], () => {
  autoCalcRate()
})

// 处理备注选择
const handleNoteSelect = (note) => {
  form.value.remarks = note.content
}

const toBackendPayload = () => {
  // 统一转换为后端字段
  let mappedType
  if (transactionType.value === 'deposit') {
    mappedType = 'income'
  } else if (transactionType.value === 'withdrawal') {
    mappedType = 'outcome'
  } else if (transactionType.value === 'instant-buyout') {
    mappedType = 'instant_buyout'
  } else {
    mappedType = 'exchange'
  }
  
  const payload = {
    type: mappedType,
    rmb_amount: form.value.rmbAmount,
    hkd_amount: form.value.hkdAmount,
    exchange_rate: form.value.exchangeRate,
    instant_rate: form.value.instantRate,
    channel_id: form.value.channelId,
    location_id: authStore.user?.location_id || null,
    notes: form.value.remarks
  }
  
  // 如果是即时买断交易，计算利润（向上取整到十位）
  if (mappedType === 'instant_buyout' && form.value.instantRate > 0) {
    const rmbAmount = parseFloat(form.value.rmbAmount) || 0
    const hkdCost = parseFloat(form.value.hkdAmount) || 0
    const instantRate = parseFloat(form.value.instantRate) || 0
    
    // 港币卖出金额 = 人民币金额 ÷ 即时买断汇率
    const hkdSellAmount = rmbAmount / instantRate
    // 利润 = 卖出金额 - 成本
    const profit = hkdSellAmount - hkdCost
    // 向上取整到十位（例如 118 -> 120）
    const roundedProfit = Math.ceil(profit / 10) * 10
    
    payload.instant_profit = roundedProfit
  }
  
  return payload
}
const MAX_IMAGES = 5 // 最多上传5张图片

const addFiles = (files) => {
  const fileList = files ? Array.from(files) : []
  const validFiles = fileList.filter(f => f && f.type && f.type.startsWith('image/'))
  
  if (validFiles.length === 0) return
  
  const remaining = MAX_IMAGES - images.value.length
  if (remaining <= 0) {
    Notify.create({ type: 'warning', message: `最多只能上传${MAX_IMAGES}张图片`, position: 'top' })
    return
  }
  
  const filesToAdd = validFiles.slice(0, remaining)
  if (filesToAdd.length < validFiles.length) {
    Notify.create({ type: 'warning', message: `已达到上限，只添加了${filesToAdd.length}张图片`, position: 'top' })
  }
  
  images.value = [...images.value, ...filesToAdd]
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

// 获取图片预览URL
const getPreviewUrl = (file) => {
  if (!previewUrls.value.has(file)) {
    previewUrls.value.set(file, URL.createObjectURL(file))
  }
  return previewUrls.value.get(file)
}

const removeImage = (index) => {
  const file = images.value[index]
  if (previewUrls.value.has(file)) {
    URL.revokeObjectURL(previewUrls.value.get(file))
    previewUrls.value.delete(file)
  }
  images.value.splice(index, 1)
}

// 压缩图片并转换为 Base64
const compressAndConvert = (file, maxWidth = 1200, quality = 0.8) => {
  return new Promise((resolve, reject) => {
    const img = new Image()
    img.onload = () => {
      let { width, height } = img
      // 如果图片宽度超过maxWidth，按比例缩小
      if (width > maxWidth) {
        height = Math.round((height * maxWidth) / width)
        width = maxWidth
      }
      
      const canvas = document.createElement('canvas')
      canvas.width = width
      canvas.height = height
      const ctx = canvas.getContext('2d')
      ctx.drawImage(img, 0, 0, width, height)
      
      // 转为 base64，去掉前缀
      const dataUrl = canvas.toDataURL('image/jpeg', quality)
      const base64 = dataUrl.split(',')[1]
      URL.revokeObjectURL(img.src)
      resolve(base64)
    }
    img.onerror = () => {
      URL.revokeObjectURL(img.src)
      reject(new Error('图片加载失败'))
    }
    img.src = URL.createObjectURL(file)
  })
}

// 上传图片到服务器（压缩后上传）
const uploadImages = async (transactionId = null, draftId = null) => {
  if (images.value.length === 0) return { success: true }
  
  try {
    // 压缩并转为 Base64
    const base64Images = await Promise.all(
      images.value.map(file => compressAndConvert(file))
    )
    
    // 调用批量上传 API
    const response = await api.post('/images/batch', {
      images: base64Images,
      transaction_id: transactionId,
      draft_id: draftId
    })
    
    return { success: true, data: response.data }
  } catch (error) {
    console.error('图片上传失败:', error)
    return { success: false, message: error.response?.data?.message || '图片上传失败' }
  }
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
  
  // 同步汇率显示值(保留3位小数)
  if (found.exchange_rate != null) {
    exchangeRateDisplay.value = Number(found.exchange_rate).toFixed(3)
  }
  if (found.instant_rate != null) {
    instantRateDisplay.value = Number(found.instant_rate).toFixed(3)
  }

  // 推断交易类型
  if (!transactionType.value) {
    if (found.type === 'income') transactionType.value = 'deposit'
    else if (found.type === 'outcome') transactionType.value = 'withdrawal'
    else if (found.type === 'instant_buyout') transactionType.value = 'instant-buyout'
    else if (found.type === 'exchange') transactionType.value = 'exchange'
  }
}

const saveDraft = async () => {
  isSavingDraft.value = true
  try {
    const draft = toBackendPayload()
    let savedDraft = null
    
    if (editingDraftRef.value?.id) {
      savedDraft = await draftStore.updateDraft(editingDraftRef.value.id, { ...editingDraftRef.value, ...draft })
    } else if (editingDraftRef.value?.uuid) {
      // 更新本地草稿
      savedDraft = await draftStore.saveDraft({ ...editingDraftRef.value, ...draft })
    } else {
      savedDraft = await draftStore.saveDraft(draft)
    }
    
    // 上传图片到草稿（仅云端草稿支持）
    if (images.value.length > 0 && savedDraft?.id) {
      const uploadResult = await uploadImages(null, savedDraft.id)
      if (!uploadResult.success) {
        Notify.create({ type: 'warning', message: '草稿已保存，但图片上传失败', position: 'top' })
      }
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
      // 即时买断也是一条交易记录，type为instant_buyout
      draftStore.addToPendingQueue({ ...data, uuid: uuidv4() })
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
        // 上传图片
        if (images.value.length > 0) {
          const uploadResult = await uploadImages(result.data.id, null)
          if (!uploadResult.success) {
            Notify.create({ type: 'warning', message: '交易已提交，但图片上传失败', position: 'top' })
          }
        }
        Notify.create({ type: 'positive', message: '即时买断提交成功', position: 'top' })
        router.push('/home')
      } else {
        Notify.create({ type: 'negative', message: result.message || '即时买断提交失败', position: 'top' })
      }
    } else {
      const result = await transactionStore.createTransaction(data)
      if (result.success) {
        // 上传图片
        if (images.value.length > 0) {
          const uploadResult = await uploadImages(result.data.id, null)
          if (!uploadResult.success) {
            Notify.create({ type: 'warning', message: '交易已提交，但图片上传失败', position: 'top' })
          }
        }
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

onBeforeUnmount(() => {
  // 清理预览URL
  previewUrls.value.forEach(url => URL.revokeObjectURL(url))
  previewUrls.value.clear()
})
</script>

<style scoped>
/* Page Layout */
.transaction-entry-page {
  background: #f5f5f5;
  min-height: 100vh;
  padding-bottom: 40px;
}

/* Header */
.page-header {
  background: linear-gradient(135deg, #1976D2 0%, #1565C0 50%, #0D47A1 100%);
  padding: 16px 20px 24px;
  box-shadow: 0 4px 20px rgba(25, 118, 210, 0.3);
  position: relative;
  overflow: hidden;
}

.page-header::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -20%;
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  border-radius: 50%;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 16px;
  position: relative;
  z-index: 1;
}

.back-btn {
  color: white !important;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
}

.header-title {
  font-size: 20px;
  font-weight: 700;
  color: white;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  letter-spacing: 0.5px;
}

/* Form Container */
.form-container {
  padding: 20px 16px;
  max-width: 900px;
  margin: 0 auto;
}

.transaction-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Form Cards */
.form-card {
  border-radius: 16px !important;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08) !important;
  transition: all 0.3s ease;
}

.form-card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12) !important;
}

.form-card .text-subtitle1 {
  font-size: 16px;
  font-weight: 700;
  color: #333;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
}

.form-card .text-subtitle1::before {
  content: '';
  width: 3px;
  height: 18px;
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
  border-radius: 2px;
}

/* Upload Area */
.upload-area {
  border: 2px dashed #d9d9d9;
  min-height: 140px;
  border-radius: 12px;
  color: #999;
  background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
  transition: all 0.3s ease;
}

.upload-area:hover {
  border-color: #1976D2;
  background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);
  color: #1976D2;
}

.upload-area .q-icon {
  transition: all 0.3s ease;
}

.upload-area:hover .q-icon {
  transform: scale(1.1);
}

/* Image List */
.image-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
}

.image-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  background: #f5f5f5;
  border-radius: 8px;
}

.image-thumb {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 6px;
  flex-shrink: 0;
}

.image-name {
  flex: 1;
  font-size: 13px;
  color: #333;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Action Buttons */
.action-buttons {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-top: 24px;
}

.action-btn {
  width: 100%;
  padding: 16px 24px;
  border-radius: 12px;
  border: none;
  font-size: 15px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.action-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.action-btn:not(:disabled):active {
  transform: scale(0.98);
}

.draft-btn {
  background: white;
  color: #666;
  border: 2px solid #d9d9d9;
}

.draft-btn:not(:disabled):hover {
  background: #fafafa;
  border-color: #bbb;
  color: #333;
}

.submit-btn {
  background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
  color: white;
}

.submit-btn:not(:disabled):hover {
  box-shadow: 0 4px 16px rgba(25, 118, 210, 0.4);
  transform: translateY(-2px);
}

/* Input Customization */
:deep(.q-field__control) {
  border-radius: 10px !important;
}

:deep(.q-field--outlined .q-field__control:before) {
  border-color: #e0e0e0;
}

:deep(.q-field--outlined .q-field__control:hover:before) {
  border-color: #1976D2;
}

:deep(.q-field--focused .q-field__control) {
  box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1);
}

/* Utility Classes */
.hidden { 
  display: none; 
}

.ellipsis { 
  white-space: nowrap; 
  overflow: hidden; 
  text-overflow: ellipsis; 
}

.cursor-pointer {
  cursor: pointer;
}

.flex {
  display: flex;
}

.flex-center {
  justify-content: center;
  align-items: center;
}

/* Responsive */
@media (max-width: 600px) {
  .form-container {
    padding: 16px 12px;
  }
  
  .action-buttons {
    grid-template-columns: 1fr;
  }
  
  :deep(.row.q-col-gutter-md) {
    margin-left: 0 !important;
    margin-right: 0 !important;
  }
  
  :deep(.row.q-col-gutter-md > div) {
    padding-left: 0 !important;
    padding-right: 0 !important;
  }
}
</style>



