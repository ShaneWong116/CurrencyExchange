<template>
  <q-page class="flex flex-center bg-grey-1">
    <div class="q-pa-md login-container">
      <div class="text-center q-mb-lg">
        <q-icon name="account_balance" size="4rem" color="primary" />
        <h4 class="text-primary q-mt-sm q-mb-none">财务管理系统</h4>
        <p class="text-grey-6">外勤人员交易录入</p>
      </div>

      <q-form @submit="handleLogin" class="q-gutter-md">
        <q-input
          v-model="form.username"
          label="用户名"
          outlined
          :disable="authStore.isLoading"
          :rules="[val => !!val || '请输入用户名']"
        >
          <template v-slot:prepend>
            <q-icon name="person" />
          </template>
        </q-input>

        <q-input
          v-model="form.password"
          label="密码"
          type="password"
          outlined
          :disable="authStore.isLoading"
          :rules="[val => !!val || '请输入密码']"
        >
          <template v-slot:prepend>
            <q-icon name="lock" />
          </template>
        </q-input>

        <q-btn
          type="submit"
          label="登录"
          color="primary"
          class="full-width action-button"
          :loading="authStore.isLoading"
          :disable="!form.username || !form.password"
        />
      </q-form>

      <q-banner inline-actions class="bg-grey-2 text-grey-8 q-mt-lg" rounded>
        <div class="text-caption">
          测试账号: field001 / field002 / field003 · 密码: 123456
        </div>
      </q-banner>
    </div>
  </q-page>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  username: '',
  password: ''
})

const handleLogin = async () => {
  const result = await authStore.login(form.value)
  if (result.success) {
    router.push('/home')
  }
}
</script>

<style scoped>
.login-container {
  width: 100%;
  max-width: 400px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

@media (max-width: 600px) {
  .login-container {
    margin: 16px;
    box-shadow: none;
    background: transparent;
  }
}
</style>
