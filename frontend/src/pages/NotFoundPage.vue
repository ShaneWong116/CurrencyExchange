<template>
  <q-page class="flex flex-center">
    <div class="text-center">
      <div class="text-h1 text-primary">404</div>
      <div class="text-h5 q-mt-md text-grey-7">页面未找到</div>
      <div class="text-body1 q-mt-sm text-grey-5">
        抱歉，您访问的页面不存在
      </div>
      
      <div class="q-mt-xl q-gutter-md">
        <q-btn
          color="primary"
          label="返回首页"
          @click="$router.push('/home')"
        />
        <q-btn
          flat
          label="返回上页"
          @click="$router.back()"
        />
      </div>
    </div>
  </q-page>
</template>

<script setup>
// 404页面，无需额外逻辑
</script>

<style scoped>
.text-h1 {
  font-size: 8rem;
  font-weight: 300;
}

@media (max-width: 600px) {
  .text-h1 {
    font-size: 6rem;
  }
}
</style>
