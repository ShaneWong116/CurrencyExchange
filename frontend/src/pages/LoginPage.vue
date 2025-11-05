<template>
  <q-page class="flex flex-center bg-grey-1">
    <div class="q-pa-md login-container">
      <div class="text-center q-mb-lg">
        <q-icon name="account_balance" size="4rem" color="primary" />
        <h4 class="text-primary q-mt-sm q-mb-none">财务管理系统</h4>
        <p class="text-grey-6">外勤人员交易录入</p>
      </div>

      <!-- 错误提示横幅 -->
      <q-banner v-if="errorMessage" class="bg-negative text-white q-mb-md" rounded dense>
        <template v-slot:avatar>
          <q-icon name="error" color="white" />
        </template>
        {{ errorMessage }}
        <template v-slot:action>
          <q-btn flat dense icon="close" @click="errorMessage = ''" />
        </template>
      </q-banner>

      <q-form @submit="handleLogin" class="q-gutter-md">
        <q-input
          v-model="form.username"
          label="用户名"
          outlined
          :disable="authStore.isLoading"
          :rules="[val => !!val || '请输入用户名']"
          :error="!!fieldErrors.username"
          :error-message="fieldErrors.username"
          @update:model-value="clearFieldError('username')"
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
          :error="!!fieldErrors.password"
          :error-message="fieldErrors.password"
          @update:model-value="clearFieldError('password')"
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
          测试账号: abc123 · 密码: 123456
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

const errorMessage = ref('')
const fieldErrors = ref({
  username: '',
  password: ''
})

// 清除字段错误
const clearFieldError = (field) => {
  fieldErrors.value[field] = ''
  if (!fieldErrors.value.username && !fieldErrors.value.password) {
    errorMessage.value = ''
  }
}

// 处理登录
const handleLogin = async () => {
  // 清除之前的错误
  errorMessage.value = ''
  fieldErrors.value = {
    username: '',
    password: ''
  }

  const result = await authStore.login(form.value)
  
  if (result.success) {
    router.push('/home')
  } else {
    // 处理登录失败
    const message = result.message || '登录失败，请检查用户名和密码'
    
    // 根据错误类型设置不同的提示
    if (message.includes('用户名或密码错误') || message.includes('password')) {
      fieldErrors.value.username = '用户名或密码错误'
      fieldErrors.value.password = '用户名或密码错误'
      errorMessage.value = '用户名或密码错误，请重新输入'
    } else if (message.includes('禁用') || message.includes('disabled')) {
      errorMessage.value = '该账户已被禁用，请联系管理员'
    } else if (message.includes('网络') || message.includes('network')) {
      errorMessage.value = '网络连接失败，请检查网络后重试'
    } else if (message.includes('服务器') || message.includes('500')) {
      errorMessage.value = '服务器错误，请稍后重试或联系管理员'
    } else {
      errorMessage.value = message
    }
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
