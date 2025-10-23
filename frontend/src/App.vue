<template>
  <div id="q-app">
    <q-layout view="lHh Lpr lFf">
      <q-page-container>
        <router-view />
      </q-page-container>
    </q-layout>
  </div>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'App'
})
</script>

<style>
/* 全局样式 */
.q-page {
  padding: 16px;
}

/* 移动端适配 */
@media (max-width: 600px) {
  .q-page {
    padding: 12px;
  }
}

/* 自定义样式 */
.full-width {
  width: 100%;
}

.text-center {
  text-align: center;
}

.q-mb-md {
  margin-bottom: 16px;
}

.q-mt-md {
  margin-top: 16px;
}

/* 表单输入框样式 */
.q-field {
  margin-bottom: 16px;
}

/* 按钮样式 */
.action-button {
  width: 100%;
  height: 48px;
  font-size: 16px;
  font-weight: 500;
}

/* 卡片样式 */
.info-card {
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* 列表项样式 */
.list-item {
  border-radius: 8px;
  margin-bottom: 8px;
  background: white;
}

/* 安全区域适配 */
.safe-area-bottom {
  padding-bottom: env(safe-area-inset-bottom);
}
</style>
