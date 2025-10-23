# 结余功能API文档

## 1. 获取结余预览
获取当前系统的结余预览信息,包括本金、港币结余、利润、结余汇率等。

**接口地址**: `GET /api/settlements/preview`

**请求头**:
```
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "current_capital": 1000000.00,
    "current_hkd_balance": 526315.00,
    "rmb_balance_total": 514250.00,
    "settlement_rate": 0.97600,
    "profit": 400.000,
    "unsettled_income_count": 2,
    "unsettled_outcome_count": 1,
    "unsettled_income_rmb": 28500.00,
    "unsettled_income_hkd": 30000.00,
    "unsettled_outcome_rmb": 14250.00,
    "unsettled_outcome_hkd": 15000.00,
    "outcome_hkd_cost": 14600.000
  }
}
```

**字段说明**:
- `current_capital`: 当前本金(HKD)
- `current_hkd_balance`: 当前港币结余(HKD)
- `rmb_balance_total`: 人民币余额汇总(CNY)
- `settlement_rate`: 当前结余汇率(CNY/HKD)
- `profit`: 本次利润(HKD)
- `unsettled_income_count`: 未结余入账交易数量
- `unsettled_outcome_count`: 未结余出账交易数量
- `unsettled_income_rmb`: 未结余入账人民币总额
- `unsettled_income_hkd`: 未结余入账港币总额
- `unsettled_outcome_rmb`: 未结余出账人民币总额
- `unsettled_outcome_hkd`: 未结余出账港币总额
- `outcome_hkd_cost`: 出账港币成本

---

## 2. 执行结余操作
执行结余操作,将所有未结余的交易标记为已结余,并更新系统本金和港币结余。

**接口地址**: `POST /api/settlements`

**请求头**:
```
Authorization: Bearer {token}
Content-Type: application/json
```

**请求体**:
```json
{
  "expenses": [
    {
      "item_name": "薪金",
      "amount": 100
    },
    {
      "item_name": "金流费用",
      "amount": 200
    }
  ],
  "notes": "2025年10月结余"
}
```

**字段说明**:
- `expenses`: 其他支出明细(可选,数组)
  - `item_name`: 支出项目名称(必填,最长100字符)
  - `amount`: 支出金额(必填,数字,>=0)
- `notes`: 备注(可选,最长1000字符)

**响应示例**:
```json
{
  "success": true,
  "message": "结余操作成功",
  "data": {
    "settlement": {
      "id": 1,
      "previous_capital": 1000000.00,
      "previous_hkd_balance": 526315.00,
      "profit": 400.000,
      "other_expenses_total": 300.00,
      "new_capital": 1000100.00,
      "new_hkd_balance": 526715.00,
      "settlement_rate": 0.97600,
      "rmb_balance_total": 514250.00,
      "sequence_number": 1,
      "notes": "2025年10月结余",
      "created_at": "2025-10-23T10:00:00.000000Z",
      "updated_at": "2025-10-23T10:00:00.000000Z"
    },
    "expenses": [
      {
        "id": 1,
        "settlement_id": 1,
        "item_name": "薪金",
        "amount": 100.00,
        "created_at": "2025-10-23T10:00:00.000000Z",
        "updated_at": "2025-10-23T10:00:00.000000Z"
      },
      {
        "id": 2,
        "settlement_id": 1,
        "item_name": "金流费用",
        "amount": 200.00,
        "created_at": "2025-10-23T10:00:00.000000Z",
        "updated_at": "2025-10-23T10:00:00.000000Z"
      }
    ],
    "transactions_count": 3,
    "income_transactions_count": 2,
    "outcome_transactions_count": 1
  }
}
```

---

## 3. 获取结余历史列表
获取所有历史结余记录,支持分页。

**接口地址**: `GET /api/settlements?page=1&per_page=20`

**请求头**:
```
Authorization: Bearer {token}
```

**查询参数**:
- `page`: 页码(可选,默认1)
- `per_page`: 每页数量(可选,默认20)

**响应示例**:
```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "previous_capital": 1000100.00,
      "previous_hkd_balance": 526715.00,
      "profit": 250.000,
      "other_expenses_total": 150.00,
      "new_capital": 1000200.00,
      "new_hkd_balance": 526965.00,
      "settlement_rate": 0.98000,
      "rmb_balance_total": 520000.00,
      "sequence_number": 2,
      "notes": null,
      "created_at": "2025-10-24T10:00:00.000000Z",
      "updated_at": "2025-10-24T10:00:00.000000Z",
      "expenses": [
        {
          "id": 3,
          "settlement_id": 2,
          "item_name": "电费",
          "amount": 150.00,
          "created_at": "2025-10-24T10:00:00.000000Z",
          "updated_at": "2025-10-24T10:00:00.000000Z"
        }
      ]
    },
    {
      "id": 1,
      "previous_capital": 1000000.00,
      "previous_hkd_balance": 526315.00,
      "profit": 400.000,
      "other_expenses_total": 300.00,
      "new_capital": 1000100.00,
      "new_hkd_balance": 526715.00,
      "settlement_rate": 0.97600,
      "rmb_balance_total": 514250.00,
      "sequence_number": 1,
      "notes": "2025年10月结余",
      "created_at": "2025-10-23T10:00:00.000000Z",
      "updated_at": "2025-10-23T10:00:00.000000Z",
      "expenses": [
        {
          "id": 1,
          "settlement_id": 1,
          "item_name": "薪金",
          "amount": 100.00,
          "created_at": "2025-10-23T10:00:00.000000Z",
          "updated_at": "2025-10-23T10:00:00.000000Z"
        },
        {
          "id": 2,
          "settlement_id": 1,
          "item_name": "金流费用",
          "amount": 200.00,
          "created_at": "2025-10-23T10:00:00.000000Z",
          "updated_at": "2025-10-23T10:00:00.000000Z"
        }
      ]
    }
  ],
  "pagination": {
    "total": 2,
    "per_page": 20,
    "current_page": 1,
    "last_page": 1,
    "from": 1,
    "to": 2
  }
}
```

---

## 4. 获取结余详情
获取指定结余记录的详细信息。

**接口地址**: `GET /api/settlements/{id}`

**请求头**:
```
Authorization: Bearer {token}
```

**路径参数**:
- `id`: 结余记录ID

**响应示例**:
```json
{
  "success": true,
  "data": {
    "settlement": {
      "id": 1,
      "previous_capital": 1000000.00,
      "previous_hkd_balance": 526315.00,
      "profit": 400.000,
      "other_expenses_total": 300.00,
      "new_capital": 1000100.00,
      "new_hkd_balance": 526715.00,
      "settlement_rate": 0.97600,
      "rmb_balance_total": 514250.00,
      "sequence_number": 1,
      "notes": "2025年10月结余",
      "created_at": "2025-10-23T10:00:00.000000Z",
      "updated_at": "2025-10-23T10:00:00.000000Z"
    },
    "expenses": [
      {
        "id": 1,
        "settlement_id": 1,
        "item_name": "薪金",
        "amount": 100.00,
        "created_at": "2025-10-23T10:00:00.000000Z",
        "updated_at": "2025-10-23T10:00:00.000000Z"
      },
      {
        "id": 2,
        "settlement_id": 1,
        "item_name": "金流费用",
        "amount": 200.00,
        "created_at": "2025-10-23T10:00:00.000000Z",
        "updated_at": "2025-10-23T10:00:00.000000Z"
      }
    ],
    "transactions_count": 3,
    "income_transactions_count": 2,
    "outcome_transactions_count": 1
  }
}
```

---

## 5. 业务规则说明

### 5.1 结余汇率计算
```
当前结余汇率 = 人民币总量 ÷ 港币总量

其中:
- 人民币总量 = 当前各渠道人民币余额汇总 + 未结余入账人民币金额之和
- 港币总量 = 当前港币结余 + 未结余入账港币金额之和
```

### 5.2 利润计算
```
利润 = 当前未结余的出账港币总额 - 当前未结余的出账港币成本

其中:
- 出账港币总额 = 未结余出账交易的港币金额总和
- 出账港币成本 = 未结余出账交易的人民币金额总和 ÷ 当前结余汇率
```

### 5.3 结余后本金和港币结余
```
结余后本金 = 当前本金 + 利润 - 其他支出总额
结余后港币结余 = 当前港币结余 + 利润
```

### 5.4 重要说明
1. **入账、出账交易不影响港币结余**,仅结余时更新
2. **已结余的交易不可修改和删除**
3. **结余操作使用数据库事务**,保证数据一致性
4. **所有计算结果保留至小数点后指定位数**(金额2位,汇率5位,利润3位)

---

## 6. 错误响应
所有接口在出错时返回统一格式:
```json
{
  "success": false,
  "message": "错误描述信息"
}
```

常见错误状态码:
- `422`: 数据验证失败
- `404`: 资源不存在
- `500`: 服务器内部错误
