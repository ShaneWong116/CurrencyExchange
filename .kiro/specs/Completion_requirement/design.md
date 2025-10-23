# 设计文档

## 概述

本设计文档描述了货币兑换系统前台交易录入功能的修改方案。基于现有的Vue 3 + Quasar + Pinia技术栈，我们将简化现有功能，重新设计交易录入流程，专注于三种核心交易类型：入账、出账和即时买断，同时优化草稿管理功能。

## 架构

### 技术栈
- **前端框架**: Vue 3 (Composition API)
- **UI组件库**: Quasar Framework
- **状态管理**: Pinia
- **路由管理**: Vue Router 4
- **HTTP客户端**: Axios
- **本地存储**: IndexedDB (通过idb库)
- **构建工具**: Vite
- **PWA支持**: Vite PWA Plugin

### 架构模式
- **组件化架构**: 基于Vue 3单文件组件
- **状态管理模式**: 使用Pinia进行集中状态管理
- **响应式设计**: 移动优先的响应式布局
- **离线优先**: PWA支持离线功能

## 组件和接口

### 页面组件重构

#### 1. HomePage.vue (主页)
**功能**: 简化主页，移除交易历史和统计功能
```vue
<template>
  <q-page class="flex flex-center">
    <div class="column q-gutter-md">
      <q-btn 
        color="primary" 
        label="交易录入" 
        @click="$router.push('/transaction')"
        size="lg"
      />
      <q-btn 
        color="secondary" 
        label="草稿箱" 
        @click="$router.push('/drafts')"
        size="lg"
      />
    </div>
  </q-page>
</template>
```

#### 2. TransactionPage.vue (交易录入页面)
**功能**: 重新设计为交易类型选择页面
```vue
<template>
  <q-page class="q-pa-md">
    <div class="text-h6 q-mb-md">选择交易类型</div>
    <div class="column q-gutter-md">
      <q-btn 
        color="positive" 
        label="入账" 
        @click="selectTransactionType('deposit')"
        icon="add"
      />
      <q-btn 
        color="negative" 
        label="出账" 
        @click="selectTransactionType('withdrawal')"
        icon="remove"
      />
      <q-btn 
        color="info" 
        label="即时买断" 
        @click="selectTransactionType('instant-buyout')"
        icon="swap_horiz"
      />
    </div>
  </q-page>
</template>
```

#### 3. TransactionEntryPage.vue (新增 - 交易录入表单页面)
**功能**: 统一的交易录入表单，根据交易类型显示不同字段
```vue
<template>
  <q-page class="q-pa-md">
    <q-form @submit="onSubmit" class="column q-gutter-md">
      <!-- 基础字段 -->
      <q-input v-model="form.rmbAmount" label="人民币金额" type="number" />
      <q-input v-model="form.hkdAmount" label="港币金额" type="number" />
      <q-input v-model="form.exchangeRate" label="汇率" type="number" />
      
      <!-- 即时买断特有字段 -->
      <q-input 
        v-if="transactionType === 'instant-buyout'"
        v-model="form.instantRate" 
        label="即时买断汇率" 
        type="number" 
      />
      
      <!-- 公共字段 -->
      <q-select v-model="form.channel" :options="channels" label="支付渠道" />
      <q-input v-model="form.remarks" label="备注" type="textarea" />
      
      <!-- 图片上传 -->
      <q-file v-model="form.images" multiple accept="image/*" label="上传图片" />
      
      <!-- 操作按钮 -->
      <div class="row q-gutter-md">
        <q-btn color="grey" label="存为草稿" @click="saveDraft" />
        <q-btn color="primary" label="提交录入" type="submit" />
      </div>
    </q-form>
  </q-page>
</template>
```

### 新增组件

#### 1. TransactionTypeSelector.vue
**功能**: 交易类型选择组件
```javascript
// 组件接口
interface TransactionType {
  id: string;
  label: string;
  icon: string;
  color: string;
  description: string;
}
```

#### 2. TransactionForm.vue
**功能**: 可复用的交易表单组件
```javascript
// Props接口
interface TransactionFormProps {
  transactionType: 'deposit' | 'withdrawal' | 'instant-buyout';
  initialData?: TransactionData;
  isEdit?: boolean;
}

// Emits接口
interface TransactionFormEmits {
  submit: (data: TransactionData) => void;
  saveDraft: (data: TransactionData) => void;
  cancel: () => void;
}
```

### 状态管理 (Pinia Stores)

#### 1. useTransactionStore
```javascript
export const useTransactionStore = defineStore('transaction', {
  state: () => ({
    currentTransaction: null,
    transactionType: null,
    channels: [],
    isLoading: false
  }),
  
  actions: {
    async submitTransaction(data) {
      // 处理不同类型的交易提交逻辑
      if (data.type === 'instant-buyout') {
        return await this.submitInstantBuyout(data);
      }
      return await this.submitRegularTransaction(data);
    },
    
    async submitInstantBuyout(data) {
      // 生成两条记录的逻辑
      const depositRecord = {
        type: 'deposit',
        rmbAmount: data.rmbAmount,
        hkdAmount: data.hkdAmount,
        exchangeRate: data.exchangeRate,
        // ... 其他字段
      };
      
      const withdrawalRecord = {
        type: 'withdrawal',
        rmbAmount: data.rmbAmount,
        hkdAmount: data.rmbAmount / data.instantRate,
        exchangeRate: data.instantRate,
        // ... 其他字段
      };
      
      return await Promise.all([
        this.submitRecord(depositRecord),
        this.submitRecord(withdrawalRecord)
      ]);
    }
  }
});
```

#### 2. useDraftStore
```javascript
export const useDraftStore = defineStore('draft', {
  state: () => ({
    drafts: [],
    isLoading: false
  }),
  
  actions: {
    async saveDraft(data) {
      const draft = {
        id: generateId(),
        ...data,
        createdAt: new Date(),
        updatedAt: new Date()
      };
      
      this.drafts.push(draft);
      await this.persistDrafts();
    },
    
    async loadDraft(id) {
      return this.drafts.find(draft => draft.id === id);
    },
    
    async deleteDraft(id) {
      const index = this.drafts.findIndex(draft => draft.id === id);
      if (index > -1) {
        this.drafts.splice(index, 1);
        await this.persistDrafts();
      }
    }
  }
});
```

## 数据模型

### TransactionData接口
```typescript
interface TransactionData {
  id?: string;
  type: 'deposit' | 'withdrawal' | 'instant-buyout';
  rmbAmount: number;
  hkdAmount: number;
  exchangeRate: number;
  instantRate?: number; // 仅用于即时买断
  channel: string;
  remarks?: string;
  images?: File[];
  createdAt?: Date;
  updatedAt?: Date;
  status: 'draft' | 'submitted' | 'pending';
}
```

### DraftData接口
```typescript
interface DraftData extends TransactionData {
  status: 'draft';
  localId: string;
}
```

### ChannelData接口
```typescript
interface ChannelData {
  id: string;
  name: string;
  label: string;
  category: string;
  enabled: boolean;
}
```

## 路由设计

### 路由配置
```javascript
const routes = [
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      { 
        path: '', 
        component: () => import('pages/HomePage.vue') 
      },
      { 
        path: '/transaction', 
        component: () => import('pages/TransactionPage.vue') 
      },
      { 
        path: '/transaction/entry/:type', 
        component: () => import('pages/TransactionEntryPage.vue'),
        props: true
      },
      { 
        path: '/drafts', 
        component: () => import('pages/DraftsPage.vue') 
      },
      { 
        path: '/drafts/:id/edit', 
        component: () => import('pages/TransactionEntryPage.vue'),
        props: true
      }
    ]
  }
];
```

### 导航流程
1. **主页** → **交易录入类型选择**
2. **交易录入类型选择** → **具体交易录入表单**
3. **交易录入表单** → **主页** (提交后) 或 **草稿箱** (保存草稿后)
4. **主页** → **草稿箱**
5. **草稿箱** → **交易录入表单** (编辑模式)

## 错误处理

### 表单验证
```javascript
const validationRules = {
  rmbAmount: [
    val => val > 0 || '人民币金额必须大于0',
    val => !isNaN(val) || '请输入有效的数字'
  ],
  hkdAmount: [
    val => val > 0 || '港币金额必须大于0',
    val => !isNaN(val) || '请输入有效的数字'
  ],
  exchangeRate: [
    val => val > 0 || '汇率必须大于0',
    val => !isNaN(val) || '请输入有效的汇率'
  ]
};
```

### 网络错误处理
```javascript
// 在axios拦截器中处理
axios.interceptors.response.use(
  response => response,
  error => {
    if (error.code === 'NETWORK_ERROR') {
      // 离线模式处理
      return handleOfflineSubmission(error.config);
    }
    return Promise.reject(error);
  }
);
```

### 业务逻辑错误处理
```javascript
// 即时买断计算验证
function validateInstantBuyout(data) {
  if (data.instantRate <= 0) {
    throw new Error('即时买断汇率必须大于0');
  }
  
  if (data.instantRate === data.exchangeRate) {
    throw new Error('即时买断汇率不能与入账汇率相同');
  }
}
```

## 测试策略

### 单元测试
- **组件测试**: 使用Vue Test Utils测试各个组件
- **Store测试**: 测试Pinia store的actions和getters
- **工具函数测试**: 测试计算逻辑和验证函数

### 集成测试
- **路由测试**: 测试页面间的导航流程
- **表单提交测试**: 测试完整的交易录入流程
- **离线功能测试**: 测试PWA离线功能

### E2E测试
- **用户流程测试**: 测试完整的用户操作流程
- **即时买断测试**: 测试复杂的即时买断业务逻辑
- **草稿管理测试**: 测试草稿的保存、编辑和删除

### 测试用例示例
```javascript
// 即时买断计算测试
describe('即时买断计算', () => {
  test('应该正确计算出账港币金额', () => {
    const input = {
      rmbAmount: 9600,
      hkdAmount: 10000,
      exchangeRate: 0.96,
      instantRate: 0.95
    };
    
    const result = calculateInstantBuyout(input);
    
    expect(result.depositRecord.hkdAmount).toBe(10000);
    expect(result.withdrawalRecord.hkdAmount).toBe(10105.2632);
  });
});
```

## 性能优化

### 代码分割
- 按路由进行代码分割
- 懒加载非关键组件
- 图片懒加载

### 缓存策略
- 使用PWA缓存静态资源
- 实施API响应缓存
- 本地存储用户偏好设置

### 移动端优化
- 触摸友好的UI设计
- 优化移动端性能
- 减少网络请求

## 安全考虑

### 数据验证
- 前端表单验证
- 后端API验证
- 防止XSS攻击

### 敏感数据处理
- 不在前端存储敏感信息
- 使用HTTPS传输
- 实施适当的认证机制

### 离线数据安全
- 加密本地存储的敏感数据
- 安全的数据同步机制
- 防止数据泄露