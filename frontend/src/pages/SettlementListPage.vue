<template>
  <q-page>
    <!-- 顶部导航 -->
    <q-header class="bg-primary">
      <q-toolbar>
        <q-btn flat round icon="arrow_back" @click="$router.back()" />
        <q-toolbar-title>结余记录</q-toolbar-title>
        <q-btn flat round icon="add" @click="goToSettlement" />
      </q-toolbar>
    </q-header>

    <div class="q-pa-md">
      <!-- 加载状态 -->
      <div v-if="loading && settlements.length === 0" class="text-center q-py-xl">
        <q-spinner color="primary" size="50px" />
        <div class="q-mt-md text-grey-7">加载中...</div>
      </div>

      <!-- 结余列表 -->
      <div v-else-if="settlements.length > 0">
        <q-card
          v-for="settlement in settlements"
          :key="settlement.id"
          class="q-mb-md cursor-pointer hover-shadow"
          @click="viewDetail(settlement.id)"
          style="border-radius: 10px;"
        >
          <q-card-section>
            <div class="row items-center q-mb-sm">
              <div class="col">
                <div class="text-h6">{{ formatDate(settlement.settlement_date) }}</div>
                <div v-if="settlement.creator_name" class="text-caption text-grey-7 q-mt-xs">
                  <q-icon name="person" size="14px" class="q-mr-xs" />
                  操作人: {{ settlement.creator_name }}
                </div>
                <div v-if="settlement.notes" class="text-caption text-grey-7">{{ settlement.notes }}</div>
              </div>
              <div class="col-auto">
                <q-badge :color="settlement.profit >= 0 ? 'positive' : 'negative'">
                  {{ settlement.profit >= 0 ? '+' : '' }}{{ formatCurrency(settlement.profit) }}
                </q-badge>
              </div>
            </div>

            <q-separator class="q-my-sm" />

            <div class="row q-gutter-md text-center">
              <div class="col">
                <div class="text-caption text-grey-7">本金</div>
                <div class="text-body2 text-weight-medium">{{ formatCurrency(settlement.previous_capital) }}</div>
              </div>
              <div class="col">
                <div class="text-caption text-grey-7">利润</div>
                <div class="text-body2 text-weight-medium" :class="settlement.profit >= 0 ? 'text-positive' : 'text-negative'">
                  {{ formatCurrency(settlement.profit) }}
                </div>
              </div>
              <div class="col">
                <div class="text-caption text-grey-7">支出</div>
                <div class="text-body2 text-weight-medium text-negative">-{{ formatCurrency(settlement.other_expenses_total) }}</div>
              </div>
              <div class="col">
                <div class="text-caption text-grey-7">收入</div>
                <div class="text-body2 text-weight-medium text-positive">+{{ formatCurrency(settlement.other_incomes_total || 0) }}</div>
              </div>
            </div>

            <q-separator class="q-my-sm" />

            <div class="row q-gutter-md text-center">
              <div class="col">
                <div class="text-caption text-grey-7">结余本金</div>
                <div class="text-body2 text-weight-bold text-primary">{{ formatCurrency(settlement.new_capital) }}</div>
              </div>
              <div class="col">
                <div class="text-caption text-grey-7">人民币结余</div>
                <div class="text-body2 text-weight-medium">{{ formatCurrency(settlement.rmb_balance_total) }}</div>
              </div>
            </div>
          </q-card-section>

          <q-card-actions align="right">
            <q-btn flat dense color="primary" label="查看详情" @click.stop="viewDetail(settlement.id)" />
          </q-card-actions>
        </q-card>

        <!-- 加载更多 -->
        <div v-if="hasMore" class="text-center q-py-md">
          <q-btn
            outline
            color="primary"
            label="加载更多"
            :loading="loading"
            @click="loadMore"
          />
        </div>
      </div>

      <!-- 无数据 -->
      <div v-else class="text-center q-py-xl">
        <q-icon name="receipt_long" size="64px" color="grey-5" />
        <div class="text-grey-7 q-mt-md">暂无结余记录</div>
        <q-btn
          class="q-mt-md"
          color="primary"
          label="开始结余"
          @click="goToSettlement"
        />
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { date } from 'quasar'
import api from '@/utils/api'

const router = useRouter()
const $q = useQuasar()

// 数据
const loading = ref(false)
const settlements = ref([])
const currentPage = ref(1)
const perPage = ref(20)
const hasMore = ref(true)

// 方法
const formatCurrency = (value) => {
  return parseFloat(value || 0).toFixed(2)
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return date.formatDate(dateString, 'YYYY-MM-DD')
}

const loadSettlements = async (page = 1) => {
  loading.value = true
  try {
    const response = await api.get('/settlements', {
      params: {
        page,
        per_page: perPage.value
      }
    })
    
    if (response.data.success) {
      if (page === 1) {
        settlements.value = response.data.data
      } else {
        settlements.value.push(...response.data.data)
      }
      
      const pagination = response.data.pagination
      hasMore.value = pagination.current_page < pagination.last_page
      currentPage.value = pagination.current_page
    }
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: '加载结余记录失败',
      caption: error.response?.data?.message || error.message
    })
  } finally {
    loading.value = false
  }
}

const loadMore = () => {
  if (!loading.value && hasMore.value) {
    loadSettlements(currentPage.value + 1)
  }
}

const viewDetail = (id) => {
  router.push(`/settlements/${id}`)
}

const goToSettlement = () => {
  router.push('/settlement/preview')
}

// 生命周期
onMounted(() => {
  loadSettlements()
})
</script>

<style scoped>
.hover-shadow {
  transition: box-shadow 0.3s;
}

.hover-shadow:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.cursor-pointer {
  cursor: pointer;
}

.q-card {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
</style>

