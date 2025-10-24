<template>
  <q-page>
    <!-- 顶部导航 -->
    <q-header class="bg-primary">
      <q-toolbar>
        <q-btn flat round icon="arrow_back" @click="$router.back()" />
        <q-toolbar-title>结余预览</q-toolbar-title>
      </q-toolbar>
    </q-header>

    <div class="q-pa-md">
      <!-- 加载状态 -->
      <div v-if="loading" class="text-center q-py-xl">
        <q-spinner color="primary" size="50px" />
        <div class="q-mt-md text-grey-7">加载中...</div>
      </div>

      <!-- 今日已结余提示 -->
      <q-banner v-else-if="todaySettled" class="bg-warning text-white q-mb-md" rounded>
        <template v-slot:avatar>
          <q-icon name="warning" />
        </template>
        今日已完成结余，无法重复操作
        <template v-slot:action>
          <q-btn flat label="查看详情" @click="viewTodaySettlement" />
        </template>
      </q-banner>

      <!-- 预览内容 -->
      <div v-else>
        <!--核对数据区域（醒目显示） -->
        <q-card class="q-mb-md" style="border: 2px solid #1976d2; border-radius: 10px;">
          <q-card-section class="bg-primary text-white">
            <div class="text-h6">请核对以下数据</div>
          </q-card-section>
          <q-card-section>
            <q-list separator>
              <q-item>
                <q-item-section>
                  <q-item-label class="text-grey-7">✓ 原本金（上次结余后）</q-item-label>
                  <q-item-label class="text-h6 text-weight-bold text-primary">
                    {{ formatCurrency(preview.previous_capital) }} HKD
                  </q-item-label>
                </q-item-section>
              </q-item>
              
              <q-item>
                <q-item-section>
                  <q-item-label class="text-grey-7">✓ 人民币结余</q-item-label>
                  <q-item-label class="text-h6 text-weight-bold text-primary">
                    {{ formatCurrency(preview.rmb_balance) }} CNY
                  </q-item-label>
                </q-item-section>
              </q-item>
              
              <q-item>
                <q-item-section>
                  <q-item-label class="text-grey-7">✓ 利润（本次结余）</q-item-label>
                  <q-item-label class="text-h6 text-weight-bold" :class="preview.total_profit >= 0 ? 'text-positive' : 'text-negative'">
                    {{ formatCurrency(preview.total_profit) }} HKD
                  </q-item-label>
                </q-item-section>
              </q-item>
              
              <q-item>
                <q-item-section>
                  <q-item-label class="text-grey-7">✓ 新本金（本次结余后）</q-item-label>
                  <q-item-label class="text-h6 text-weight-bold text-primary">
                    {{ formatCurrency(preview.new_capital) }} HKD
                  </q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>

        <!-- 详细计算 -->
        <q-expansion-item
          expand-separator
          label="详细计算"
          header-class="bg-grey-2"
          class="q-mb-md"
          style="border-radius: 10px;"
        >
          <q-card>
            <q-card-section>
              <q-list>
                <q-item>
                  <q-item-section>
                    <q-item-label caption>出账利润</q-item-label>
                    <q-item-label>{{ formatCurrency(preview.outgoing_profit) }} HKD</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item>
                  <q-item-section>
                    <q-item-label caption>即时买断利润</q-item-label>
                    <q-item-label>{{ formatCurrency(preview.instant_profit) }} HKD</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item>
                  <q-item-section>
                    <q-item-label caption>总利润</q-item-label>
                    <q-item-label class="text-weight-bold">{{ formatCurrency(preview.total_profit) }} HKD</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item>
                  <q-item-section>
                    <q-item-label caption>结余汇率</q-item-label>
                    <q-item-label>{{ preview.settlement_rate }}</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item v-if="preview.needs_instant_rate">
                  <q-item-section>
                    <q-item-label caption>即时买断汇率</q-item-label>
                    <q-item-label>{{ instantBuyoutRate || '（未设置）' }}</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card-section>
          </q-card>
        </q-expansion-item>

        <!-- 即时买断汇率输入（如需要） -->
        <q-card v-if="preview.needs_instant_rate" class="q-mb-md">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md">即时买断汇率</div>
            <q-input
              v-model.number="instantBuyoutRate"
              type="number"
              step="0.001"
              label="即时买断汇率"
              outlined
              hint="存在即时买断交易，请输入汇率"
              :rules="[val => val > 0 || '汇率必须大于0']"
              @update:model-value="reloadPreview"
            />
          </q-card-section>
        </q-card>

        <!-- 其他支出 -->
        <q-card class="q-mb-md">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md">其他支出（可选）</div>
            
            <div v-for="(expense, index) in expenses" :key="index" class="row q-gutter-md q-mb-md">
              <div class="col">
                <q-input
                  v-model="expense.item_name"
                  label="支出项目名称"
                  outlined
                  dense
                />
              </div>
              <div class="col-4">
                <q-input
                  v-model.number="expense.amount"
                  type="number"
                  label="金额"
                  outlined
                  dense
                  suffix="HKD"
                />
              </div>
              <div class="col-auto">
                <q-btn
                  flat
                  round
                  color="negative"
                  icon="delete"
                  @click="removeExpense(index)"
                />
              </div>
            </div>

            <q-btn
              flat
              color="primary"
              icon="add"
              label="添加支出项"
              @click="addExpense"
            />

            <q-separator class="q-my-md" />

            <div class="text-right">
              <div class="text-subtitle2">总支出：<span class="text-h6 text-weight-bold text-negative">{{ totalExpenses }} HKD</span></div>
              <div class="text-caption text-grey-7 q-mt-sm">
                实际新本金：{{ formatCurrency(preview.new_capital - totalExpenses) }} HKD
              </div>
            </div>
          </q-card-section>
        </q-card>

        <!-- 备注 -->
        <q-card class="q-mb-md">
          <q-card-section>
            <q-input
              v-model="notes"
              label="备注（可选）"
              type="textarea"
              outlined
              rows="3"
              maxlength="1000"
              counter
            />
          </q-card-section>
        </q-card>

        <!-- 操作按钮 -->
        <div class="row q-gutter-md">
          <div class="col">
            <q-btn
              label="返回"
              flat
              color="grey-7"
              class="full-width"
              @click="$router.back()"
            />
          </div>
          <div class="col">
            <q-btn
              label="确认结余"
              color="primary"
              class="full-width"
              :disable="!canSubmit"
              @click="showPasswordDialog = true"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- 密码验证对话框 -->
    <q-dialog v-model="showPasswordDialog" persistent>
      <q-card style="min-width: 350px;">
        <q-card-section>
          <div class="text-h6">密码验证</div>
        </q-card-section>

        <q-card-section>
          <div class="text-body2 q-mb-md">请输入确认密码以完成结余操作：</div>
          <q-input
            v-model="password"
            type="password"
            label="确认密码"
            outlined
            autofocus
            @keyup.enter="confirmSettlement"
          >
            <template v-slot:prepend>
              <q-icon name="lock" />
            </template>
          </q-input>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="取消" color="grey-7" v-close-popup />
          <q-btn
            label="确认"
            color="primary"
            :loading="submitting"
            @click="confirmSettlement"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import api from '@/utils/api'

const router = useRouter()
const $q = useQuasar()

// 数据
const loading = ref(false)
const todaySettled = ref(false)
const todaySettlementId = ref(null)
const preview = ref({
  previous_capital: 0,
  rmb_balance: 0,
  total_profit: 0,
  new_capital: 0,
  outgoing_profit: 0,
  instant_profit: 0,
  settlement_rate: 0,
  needs_instant_rate: false,
})
const instantBuyoutRate = ref(null)
const expenses = ref([])
const notes = ref('')
const showPasswordDialog = ref(false)
const password = ref('')
const submitting = ref(false)

// 计算属性
const totalExpenses = computed(() => {
  return expenses.value.reduce((sum, exp) => sum + (parseFloat(exp.amount) || 0), 0)
})

const canSubmit = computed(() => {
  if (preview.value.needs_instant_rate && !instantBuyoutRate.value) {
    return false
  }
  return true
})

// 方法
const formatCurrency = (value) => {
  return parseFloat(value || 0).toFixed(2)
}

const checkTodaySettlement = async () => {
  try {
    const response = await api.get('/settlements/check-today')
    if (response.data.success && response.data.data.settled) {
      todaySettled.value = true
      todaySettlementId.value = response.data.data.settlement_id
    }
  } catch (error) {
    console.error('检查今日结余失败:', error)
  }
}

const loadPreview = async () => {
  loading.value = true
  try {
    const params = {}
    if (instantBuyoutRate.value) {
      params.instant_buyout_rate = instantBuyoutRate.value
    }
    
    const response = await api.get('/settlements/preview', { params })
    
    if (response.data.success) {
      preview.value = response.data.data
    }
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: '加载结余预览失败',
      caption: error.response?.data?.message || error.message
    })
  } finally {
    loading.value = false
  }
}

const reloadPreview = () => {
  if (instantBuyoutRate.value > 0) {
    loadPreview()
  }
}

const addExpense = () => {
  expenses.value.push({
    item_name: '',
    amount: 0
  })
}

const removeExpense = (index) => {
  expenses.value.splice(index, 1)
}

const confirmSettlement = async () => {
  if (!password.value) {
    $q.notify({
      type: 'warning',
      message: '请输入确认密码'
    })
    return
  }

  submitting.value = true
  try {
    // 构建请求数据
    const data = {
      password: password.value,
      expenses: expenses.value.filter(exp => exp.item_name && exp.amount > 0),
      notes: notes.value || null
    }
    
    if (preview.value.needs_instant_rate) {
      data.instant_buyout_rate = instantBuyoutRate.value
    }

    const response = await api.post('/settlements', data)
    
    if (response.data.success) {
      $q.notify({
        type: 'positive',
        message: '结余操作成功'
      })
      
      // 跳转到结余详情页
      router.push(`/settlements/${response.data.data.settlement.id}`)
    }
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || '结余操作失败'
    })
  } finally {
    submitting.value = false
  }
}

const viewTodaySettlement = () => {
  if (todaySettlementId.value) {
    router.push(`/settlements/${todaySettlementId.value}`)
  }
}

// 生命周期
onMounted(async () => {
  await checkTodaySettlement()
  if (!todaySettled.value) {
    await loadPreview()
  }
})
</script>

<style scoped>
.q-card {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
</style>

