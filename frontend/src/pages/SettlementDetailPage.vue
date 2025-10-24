<template>
  <q-page>
    <!-- 顶部导航 -->
    <q-header class="bg-primary">
      <q-toolbar>
        <q-btn flat round icon="arrow_back" @click="$router.back()" />
        <q-toolbar-title>结余详情</q-toolbar-title>
      </q-toolbar>
    </q-header>

    <div class="q-pa-md">
      <!-- 加载状态 -->
      <div v-if="loading" class="text-center q-py-xl">
        <q-spinner color="primary" size="50px" />
        <div class="q-mt-md text-grey-7">加载中...</div>
      </div>

      <!-- 详情内容 -->
      <div v-else-if="settlement">
        <!-- 标题卡片 -->
        <q-card class="q-mb-md" style="border-radius: 10px;">
          <q-card-section class="bg-primary text-white">
            <div class="text-h5">收入支出表</div>
            <div class="text-subtitle2">日期: {{ formatDate(settlement.settlement_date) }}</div>
          </q-card-section>
        </q-card>

        <!-- 核心数据 -->
        <q-card class="q-mb-md">
          <q-card-section>
            <q-list>
              <q-item>
                <q-item-section>
                  <q-item-label caption>原本金</q-item-label>
                  <q-item-label class="text-h6">{{ formatCurrency(settlement.previous_capital) }} HKD</q-item-label>
                </q-item-section>
              </q-item>

              <q-separator spaced />

              <q-item>
                <q-item-section>
                  <q-item-label caption>出账利润</q-item-label>
                  <q-item-label :class="settlement.outgoing_profit >= 0 ? 'text-positive' : 'text-negative'">
                    {{ formatCurrency(settlement.outgoing_profit) }} HKD
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-item>
                <q-item-section>
                  <q-item-label caption>即时买断利润</q-item-label>
                  <q-item-label :class="settlement.instant_profit >= 0 ? 'text-positive' : 'text-negative'">
                    {{ formatCurrency(settlement.instant_profit) }} HKD
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-item>
                <q-item-section>
                  <q-item-label caption>总利润</q-item-label>
                  <q-item-label class="text-h6 text-weight-bold" :class="settlement.profit >= 0 ? 'text-positive' : 'text-negative'">
                    {{ formatCurrency(settlement.profit) }} HKD
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-separator spaced />

              <q-item>
                <q-item-section>
                  <q-item-label caption>总支出</q-item-label>
                  <q-item-label class="text-negative">
                    {{ formatCurrency(settlement.other_expenses_total) }} HKD
                  </q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-btn
                    v-if="settlement.expenses && settlement.expenses.length > 0"
                    flat
                    dense
                    color="primary"
                    label="查看明细"
                    @click="showExpensesDialog = true"
                  />
                </q-item-section>
              </q-item>

              <q-separator spaced />

              <q-item>
                <q-item-section>
                  <q-item-label caption>结余本金</q-item-label>
                  <q-item-label class="text-h6 text-weight-bold text-primary">
                    {{ formatCurrency(settlement.new_capital) }} HKD
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-item>
                <q-item-section>
                  <q-item-label caption>人民币结余</q-item-label>
                  <q-item-label class="text-h6">
                    {{ formatCurrency(settlement.rmb_balance_total) }} CNY
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-separator spaced />

              <q-item>
                <q-item-section>
                  <q-item-label caption>结余汇率</q-item-label>
                  <q-item-label>{{ settlement.settlement_rate }}</q-item-label>
                </q-item-section>
              </q-item>

              <q-item v-if="settlement.instant_buyout_rate">
                <q-item-section>
                  <q-item-label caption>即时买断汇率</q-item-label>
                  <q-item-label>{{ settlement.instant_buyout_rate }}</q-item-label>
                </q-item-section>
              </q-item>

              <q-separator spaced v-if="settlement.notes" />

              <q-item v-if="settlement.notes">
                <q-item-section>
                  <q-item-label caption>备注</q-item-label>
                  <q-item-label>{{ settlement.notes }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>

        <!-- 交易统计 -->
        <q-card v-if="detail" class="q-mb-md">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md">交易统计</div>
            <div class="row q-gutter-md">
              <div class="col">
                <q-card flat bordered class="text-center">
                  <q-card-section class="q-pa-sm">
                    <div class="text-h6 text-positive">{{ detail.income_transactions_count || 0 }}</div>
                    <div class="text-caption">入账交易</div>
                  </q-card-section>
                </q-card>
              </div>
              <div class="col">
                <q-card flat bordered class="text-center">
                  <q-card-section class="q-pa-sm">
                    <div class="text-h6 text-negative">{{ detail.outcome_transactions_count || 0 }}</div>
                    <div class="text-caption">出账交易</div>
                  </q-card-section>
                </q-card>
              </div>
              <div class="col">
                <q-card flat bordered class="text-center">
                  <q-card-section class="q-pa-sm">
                    <div class="text-h6 text-primary">{{ detail.transactions_count || 0 }}</div>
                    <div class="text-caption">总交易</div>
                  </q-card-section>
                </q-card>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- 无数据 -->
      <div v-else class="text-center q-py-xl">
        <q-icon name="error_outline" size="64px" color="grey-5" />
        <div class="text-grey-7 q-mt-md">未找到结余记录</div>
      </div>
    </div>

    <!-- 支出明细对话框 -->
    <q-dialog v-model="showExpensesDialog">
      <q-card style="min-width: 350px;">
        <q-card-section>
          <div class="text-h6">其他支出明细</div>
        </q-card-section>

        <q-card-section>
          <q-list separator>
            <q-item v-for="expense in settlement.expenses" :key="expense.id">
              <q-item-section>
                <q-item-label>{{ expense.item_name }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-item-label class="text-negative">{{ formatCurrency(expense.amount) }} HKD</q-item-label>
              </q-item-section>
            </q-item>

            <q-item>
              <q-item-section>
                <q-item-label class="text-weight-bold">总支出</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-item-label class="text-weight-bold text-negative">
                  {{ formatCurrency(settlement.other_expenses_total) }} HKD
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="关闭" color="primary" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { date } from 'quasar'
import api from '@/utils/api'

const route = useRoute()
const $q = useQuasar()

// 数据
const loading = ref(false)
const settlement = ref(null)
const detail = ref(null)
const showExpensesDialog = ref(false)

// 方法
const formatCurrency = (value) => {
  return parseFloat(value || 0).toFixed(2)
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return date.formatDate(dateString, 'YYYY-MM-DD')
}

const loadSettlement = async () => {
  loading.value = true
  try {
    const response = await api.get(`/settlements/${route.params.id}`)
    
    if (response.data.success) {
      settlement.value = response.data.data.settlement
      detail.value = response.data.data
    }
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: '加载结余详情失败',
      caption: error.response?.data?.message || error.message
    })
  } finally {
    loading.value = false
  }
}

// 生命周期
onMounted(() => {
  loadSettlement()
})
</script>

<style scoped>
.q-card {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
</style>

