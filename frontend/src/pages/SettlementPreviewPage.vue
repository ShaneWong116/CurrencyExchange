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

      <!-- 预览内容 -->
      <div v-if="!loading">
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
              
              <q-item clickable @click="showChannelRmbBalances = !showChannelRmbBalances">
                <q-item-section>
                  <q-item-label class="text-grey-7">✓ 人民币结余</q-item-label>
                  <q-item-label class="text-h6 text-weight-bold text-primary">
                    {{ formatCurrency(preview.rmb_balance) }} CNY
                    <q-icon 
                      :name="showChannelRmbBalances ? 'expand_less' : 'expand_more'" 
                      size="20px" 
                      class="q-ml-xs"
                    />
                  </q-item-label>
                  <q-item-label caption class="text-grey-6">
                    点击查看各渠道余额
                  </q-item-label>
                </q-item-section>
              </q-item>
              
              <!-- 各渠道人民币余额明细 -->
              <q-slide-transition>
                <div v-show="showChannelRmbBalances">
                  <q-list dense class="bg-grey-1 q-mx-md q-mb-sm" style="border-radius: 8px;">
                    <q-item v-for="channel in preview.channel_rmb_balances" :key="channel.id">
                      <q-item-section>
                        <q-item-label class="text-grey-8">{{ channel.name }}</q-item-label>
                      </q-item-section>
                      <q-item-section side>
                        <q-item-label class="text-primary text-weight-medium">
                          {{ formatCurrency(channel.rmb_balance) }} CNY
                        </q-item-label>
                      </q-item-section>
                    </q-item>
                  </q-list>
                </div>
              </q-slide-transition>
              
              <q-item>
                <q-item-section>
                  <q-item-label class="text-grey-7">✓ 利润（本次结余）</q-item-label>
                  <q-item-label class="text-h6 text-weight-bold" :class="preview.total_profit >= 0 ? 'text-positive' : 'text-negative'">
                    {{ formatInteger(preview.total_profit) }} HKD
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
              
              <q-separator />
              
              <!-- 出账汇总 -->
              <q-item>
                <q-item-section>
                  <q-item-label class="text-grey-7">✓ 出账汇总</q-item-label>
                  <q-item-label class="text-h6 text-weight-bold text-primary">
                    {{ formatInteger(preview.unsettled_outcome_rmb) }} CNY / {{ formatInteger(preview.unsettled_outcome_hkd) }} HKD
                  </q-item-label>
                </q-item-section>
              </q-item>
              
              <!-- 即时买断汇总 -->
              <q-item>
                <q-item-section>
                  <q-item-label class="text-grey-7">✓ 即时买断汇总</q-item-label>
                  <q-item-label class="text-h6 text-weight-bold text-primary">
                    {{ formatInteger(preview.instant_rmb_total) }} CNY / {{ formatInteger(preview.instant_actual_hkd) }} HKD
                  </q-item-label>
                  <q-item-label class="text-h6 text-weight-bold text-primary">
                    +利 {{ formatInteger(preview.instant_actual_hkd + preview.instant_profit) }} HKD
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
                    <q-item-label>{{ formatInteger(preview.outgoing_profit) }} HKD</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item>
                  <q-item-section>
                    <q-item-label caption>即时买断利润</q-item-label>
                    <q-item-label>{{ formatInteger(preview.instant_profit) }} HKD</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item>
                  <q-item-section>
                    <q-item-label caption>总利润</q-item-label>
                    <q-item-label class="text-weight-bold">{{ formatInteger(preview.total_profit) }} HKD</q-item-label>
                  </q-item-section>
                </q-item>
                <q-separator class="q-my-sm" />
                
                <!-- 成本汇率计算明细 -->
                <q-item-label header class="text-weight-bold text-grey-8">成本汇率计算</q-item-label>
                <q-item dense>
                  <q-item-section>
                    <q-item-label caption>期初人民币结余</q-item-label>
                    <q-item-label>{{ formatCurrency(preview.previous_rmb_balance) }} CNY</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item dense>
                  <q-item-section>
                    <q-item-label caption>+ 当日入账人民币</q-item-label>
                    <q-item-label>{{ formatCurrency(preview.unsettled_income_rmb) }} CNY</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item dense>
                  <q-item-section>
                    <q-item-label caption>= 人民币总量</q-item-label>
                    <q-item-label class="text-weight-bold">{{ formatCurrency(preview.cost_rmb_total) }} CNY</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item dense>
                  <q-item-section>
                    <q-item-label caption>期初港币结余</q-item-label>
                    <q-item-label>{{ formatCurrency(preview.previous_hkd_balance) }} HKD</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item dense>
                  <q-item-section>
                    <q-item-label caption>+ 当日入账港币</q-item-label>
                    <q-item-label>{{ formatCurrency(preview.unsettled_income_hkd) }} HKD</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item dense>
                  <q-item-section>
                    <q-item-label caption>= 港币总量</q-item-label>
                    <q-item-label class="text-weight-bold">{{ formatCurrency(preview.cost_hkd_total) }} HKD</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item>
                  <q-item-section>
                    <q-item-label caption>成本汇率 = 人民币总量 ÷ 港币总量</q-item-label>
                    <q-item-label class="text-h6 text-weight-bold text-primary">{{ formatRate(preview.settlement_rate) }}</q-item-label>
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
            <div class="text-subtitle2 q-mb-md">
              <q-icon name="remove_circle" color="negative" class="q-mr-xs" />
              其他支出（可选）
            </div>
            
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

            <div class="text-right q-mt-md">
              <div class="text-subtitle2">总支出：<span class="text-h6 text-weight-bold text-negative">-{{ totalExpenses }} HKD</span></div>
            </div>
          </q-card-section>
        </q-card>

        <!-- 其他收入 -->
        <q-card class="q-mb-md">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md">
              <q-icon name="add_circle" color="positive" class="q-mr-xs" />
              其他收入（可选）
            </div>
            
            <div v-for="(income, index) in incomes" :key="index" class="row q-gutter-md q-mb-md">
              <div class="col">
                <q-input
                  v-model="income.item_name"
                  label="收入项目名称"
                  outlined
                  dense
                />
              </div>
              <div class="col-4">
                <q-input
                  v-model.number="income.amount"
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
                  @click="removeIncome(index)"
                />
              </div>
            </div>

            <q-btn
              flat
              color="positive"
              icon="add"
              label="添加收入项"
              @click="addIncome"
            />

            <div class="text-right q-mt-md">
              <div class="text-subtitle2">总收入：<span class="text-h6 text-weight-bold text-positive">+{{ totalIncomes }} HKD</span></div>
            </div>
          </q-card-section>
        </q-card>

        <!-- 汇总 -->
        <q-card class="q-mb-md" style="border: 2px solid #4caf50; border-radius: 10px;">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md text-weight-bold">本金变化汇总</div>
            <div class="row q-gutter-md">
              <div class="col text-center">
                <div class="text-caption text-grey-7">利润</div>
                <div class="text-h6" :class="preview.total_profit >= 0 ? 'text-positive' : 'text-negative'">
                  {{ preview.total_profit >= 0 ? '+' : '' }}{{ formatInteger(preview.total_profit) }}
                </div>
              </div>
              <div class="col text-center">
                <div class="text-caption text-grey-7">其他支出</div>
                <div class="text-h6 text-negative">-{{ totalExpenses }}</div>
              </div>
              <div class="col text-center">
                <div class="text-caption text-grey-7">其他收入</div>
                <div class="text-h6 text-positive">+{{ totalIncomes }}</div>
              </div>
            </div>
            <q-separator class="q-my-md" />
            <div class="text-center">
              <div class="text-caption text-grey-7">实际新本金</div>
              <div class="text-h5 text-weight-bold text-primary">
                {{ formatCurrency(preview.new_capital - totalExpenses + totalIncomes) }} HKD
              </div>
            </div>
          </q-card-section>
        </q-card>

        <!-- 备注 -->
        <q-card class="q-mb-md">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md">备注（可选）</div>
            
            <!-- 备注输入框 -->
            <q-input
              v-model="notes"
              type="textarea"
              label="备注内容"
              outlined
              :rows="3"
              maxlength="500"
              counter
              placeholder="请输入备注内容，或从下方常用备注中选择"
            />
            
            <!-- 常用备注选择器 -->
            <div class="q-mt-md">
              <CommonNotesSelector 
                v-model="notes"
              />
            </div>
          </q-card-section>
        </q-card>

        <!-- 结余日期选择 - 移到最下方 -->
        <q-card class="q-mb-md" style="border: 2px solid #f59e0b; border-radius: 10px;">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md text-weight-bold">
              <q-icon name="event" color="primary" class="q-mr-xs" />
              📅 选择结余日期
            </div>
            
            <!-- 警示提示框 -->
            <q-banner 
              v-if="dateWarning" 
              class="bg-warning text-white q-mb-md" 
              rounded
              dense
            >
              <template v-slot:avatar>
                <q-icon name="warning" size="md" />
              </template>
              <div class="text-subtitle2 text-weight-bold">⚠️ {{ dateWarning }}</div>
              <div class="text-caption q-mt-xs">可以选择任何没有结算记录的日期</div>
            </q-banner>
            
            <q-banner 
              v-if="!dateWarning" 
              class="bg-positive text-white q-mb-md" 
              rounded
              dense
            >
              <template v-slot:avatar>
                <q-icon name="check_circle" size="md" />
              </template>
              <div class="text-subtitle2 text-weight-bold">✓ 今日尚未结余</div>
              <div class="text-caption q-mt-xs">可以选择任何没有结算记录的日期</div>
            </q-banner>
            
            <!-- 直接嵌入日历组件 -->
            <q-date 
              v-model="settlementDate"
              :options="dateOptions"
              mask="YYYY/MM/DD"
              class="full-width"
              minimal
            />
            
            <!-- 已选择日期显示 -->
            <div v-if="settlementDate" class="q-mt-md q-pa-md bg-primary text-white rounded-borders">
              <div class="text-center">
                <div class="text-caption">已选择日期</div>
                <div class="text-h6">{{ formatDateDisplay(settlementDate) }}</div>
              </div>
            </div>
            <div v-else class="q-mt-md q-pa-md bg-grey-3 rounded-borders">
              <div class="text-center text-grey-7">
                <q-icon name="event" size="md" class="q-mb-xs" />
                <div class="text-caption">请选择结余日期（灰色日期已有结算记录）</div>
              </div>
            </div>
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
              :disable="!canSubmit || !settlementDate"
              @click="showPasswordDialog = true"
            >
              <q-tooltip v-if="!settlementDate">
                请先选择结余日期
              </q-tooltip>
            </q-btn>
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
import CommonNotesSelector from '@/components/CommonNotesSelector.vue'

const router = useRouter()
const $q = useQuasar()

// 数据
const loading = ref(false)
const preview = ref({
  previous_capital: 0,
  rmb_balance: 0,
  channel_rmb_balances: [],
  total_profit: 0,
  new_capital: 0,
  outgoing_profit: 0,
  instant_profit: 0,
  settlement_rate: 0,
  needs_instant_rate: false,
  // 成本汇率计算明细
  previous_rmb_balance: 0,
  previous_hkd_balance: 0,
  cost_rmb_total: 0,
  cost_hkd_total: 0,
  unsettled_income_rmb: 0,
  unsettled_income_hkd: 0,
  // 出账汇总
  unsettled_outcome_rmb: 0,
  unsettled_outcome_hkd: 0,
  // 即时买断汇总
  instant_rmb_total: 0,
  instant_actual_hkd: 0,
})
const showChannelRmbBalances = ref(false)
const instantBuyoutRate = ref(null)
const expenses = ref([])
const incomes = ref([])
const notes = ref('')
const showPasswordDialog = ref(false)
const password = ref('')
const submitting = ref(false)

// 日期选择相关
const settlementDate = ref(null)  // 选择的结余日期
const usedDates = ref([])  // 已使用的日期列表
const recommendedDate = ref(null)  // 推荐日期
const dateWarning = ref(null)  // 日期警告信息

// 计算属性
const totalExpenses = computed(() => {
  return expenses.value.reduce((sum, exp) => sum + (parseFloat(exp.amount) || 0), 0)
})

const totalIncomes = computed(() => {
  return incomes.value.reduce((sum, inc) => sum + (parseFloat(inc.amount) || 0), 0)
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

const formatRate = (value) => {
  return parseFloat(value || 0).toFixed(3)
}

const formatInteger = (value) => {
  return Math.round(parseFloat(value || 0)).toString()
}

// checkTodaySettlement 已移除,改用日期推荐逻辑

// 加载日期信息
const loadDateInfo = async () => {
  try {
    // 获取推荐日期
    const recResponse = await api.get('/settlements/recommended-date')
    if (recResponse.data.success) {
      const rec = recResponse.data.data
      recommendedDate.value = rec.recommended_date
      settlementDate.value = null  // 默认为空,让用户选择
      dateWarning.value = rec.message
    }
    
    // 获取已使用日期
    const usedResponse = await api.get('/settlements/used-dates')
    if (usedResponse.data.success) {
      usedDates.value = usedResponse.data.data
    }
  } catch (error) {
    console.error('加载日期信息失败:', error)
  }
}

// 格式化日期显示
const formatDateDisplay = (date) => {
  if (!date) return ''
  // 统一转换为 YYYY-MM-DD 格式再解析
  const dateDash = date.replace(/\//g, '-')
  const d = new Date(dateDash + 'T00:00:00')
  const year = d.getFullYear()
  const month = d.getMonth() + 1
  const day = d.getDate()
  const weekday = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'][d.getDay()]
  return `${year}年${month}月${day}日 ${weekday}`
}

// 日期选项过滤:只禁用已使用的日期，允许选择过去但没有结算的日期
const dateOptions = (date) => {
  // 只禁用已使用的日期
  if (usedDates.value && usedDates.value.length > 0) {
    const checkDateDash = date.replace(/\//g, '-')
    if (usedDates.value.includes(checkDateDash)) {
      return false
    }
  }
  
  return true
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

const addIncome = () => {
  incomes.value.push({
    item_name: '',
    amount: 0
  })
}

const removeIncome = (index) => {
  incomes.value.splice(index, 1)
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
      settlement_date: settlementDate.value.replace(/\//g, '-'),  // 转换为YYYY-MM-DD格式
      expenses: expenses.value
        .filter(exp => exp.amount !== 0 && exp.amount !== '' && exp.amount !== null)
        .map(exp => ({ item_name: exp.item_name || '支出', amount: exp.amount })),
      incomes: incomes.value
        .filter(inc => inc.amount !== 0 && inc.amount !== '' && inc.amount !== null)
        .map(inc => ({ item_name: inc.item_name || '收入', amount: inc.amount })),
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

// viewTodaySettlement 已移除

// 生命周期
onMounted(async () => {
  await loadDateInfo()
  await loadPreview()
})
</script>

<style scoped>
.q-card {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
</style>

