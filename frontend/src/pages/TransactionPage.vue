<template>
  <q-page class="q-pa-md">
    <q-header class="bg-primary">
      <q-toolbar>
        <q-btn flat round icon="arrow_back" @click="$router.back()" />
        <q-toolbar-title>交易录入</q-toolbar-title>
      </q-toolbar>
    </q-header>
    <div class="text-h6 q-mb-sm">选择交易类型</div>
    <div class="text-caption text-grey-6 q-mb-md">请选择要进行的交易操作类型</div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-sm-4" v-for="item in typeCards" :key="item.id">
        <q-card class="type-card cursor-pointer" @click="selectTransactionType(item.id)">
          <q-card-section class="text-center">
            <q-icon :name="item.icon" :color="item.color" size="48px" />
            <div class="text-subtitle1 q-mt-sm">{{ item.label }}</div>
            <div class="text-caption text-grey-7">{{ item.description }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const typeCards = [
  { id: 'deposit', label: '入账', icon: 'add_circle', color: 'positive', description: '人民币增加，港币减少' },
  { id: 'withdrawal', label: '出账', icon: 'remove_circle', color: 'negative', description: '人民币减少，港币增加' },
  { id: 'instant-buyout', label: '即时买断', icon: 'swap_horiz', color: 'info', description: '一次同时记录入账与出账' }
]

const selectTransactionType = (type) => {
  router.push(`/transaction/entry/${type}`)
}
</script>

<style scoped>
/* 使用 Quasar 默认卡片样式，移除自定义 hover 效果以保持一致视觉 */
</style>
