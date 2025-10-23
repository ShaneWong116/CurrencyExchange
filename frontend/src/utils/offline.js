// 离线功能工具类
import { openDB } from 'idb'
import { v4 as uuidv4 } from 'uuid'

class OfflineManager {
  constructor() {
    this.dbName = 'CurrencyExchangeDB'
    this.version = 1
    this.db = null
  }

  // 初始化IndexedDB
  async init() {
    if (this.db) return this.db

    this.db = await openDB(this.dbName, this.version, {
      upgrade(db) {
        // 创建草稿存储
        if (!db.objectStoreNames.contains('drafts')) {
          const draftStore = db.createObjectStore('drafts', { keyPath: 'uuid' })
          draftStore.createIndex('user_id', 'user_id')
          draftStore.createIndex('last_modified', 'last_modified')
        }

        // 创建待提交队列存储
        if (!db.objectStoreNames.contains('pending_transactions')) {
          const pendingStore = db.createObjectStore('pending_transactions', { keyPath: 'uuid' })
          pendingStore.createIndex('user_id', 'user_id')
          pendingStore.createIndex('created_at', 'created_at')
        }

        // 创建图片存储
        if (!db.objectStoreNames.contains('images')) {
          const imageStore = db.createObjectStore('images', { keyPath: 'uuid' })
          imageStore.createIndex('draft_uuid', 'draft_uuid')
          imageStore.createIndex('transaction_uuid', 'transaction_uuid')
        }
      }
    })

    return this.db
  }

  // 草稿操作
  async saveDraft(draft) {
    await this.init()
    const draftData = {
      ...draft,
      uuid: draft.uuid || uuidv4(),
      last_modified: new Date().toISOString()
    }
    await this.db.put('drafts', draftData)
    return draftData
  }

  async getDrafts(userId) {
    await this.init()
    const tx = this.db.transaction('drafts', 'readonly')
    const store = tx.objectStore('drafts')
    const index = store.index('user_id')
    return await index.getAll(userId)
  }

  async getDraft(uuid) {
    await this.init()
    return await this.db.get('drafts', uuid)
  }

  async updateDraft(uuid, draftData) {
    await this.init()
    const existing = await this.db.get('drafts', uuid)
    if (existing) {
      const updated = {
        ...existing,
        ...draftData,
        last_modified: new Date().toISOString()
      }
      await this.db.put('drafts', updated)
      return updated
    }
    throw new Error('Draft not found')
  }

  async deleteDraft(uuid) {
    await this.init()
    await this.db.delete('drafts', uuid)
  }

  // 待提交队列操作
  async addToPendingQueue(transaction) {
    await this.init()
    const transactionData = {
      ...transaction,
      uuid: transaction.uuid || uuidv4(),
      created_at: new Date().toISOString(),
      status: 'pending'
    }
    await this.db.put('pending_transactions', transactionData)
    return transactionData
  }

  async getPendingTransactions(userId) {
    await this.init()
    const tx = this.db.transaction('pending_transactions', 'readonly')
    const store = tx.objectStore('pending_transactions')
    const index = store.index('user_id')
    return await index.getAll(userId)
  }

  async removePendingTransaction(uuid) {
    await this.init()
    await this.db.delete('pending_transactions', uuid)
  }

  async updatePendingStatus(uuid, status) {
    await this.init()
    const existing = await this.db.get('pending_transactions', uuid)
    if (existing) {
      existing.status = status
      existing.updated_at = new Date().toISOString()
      await this.db.put('pending_transactions', existing)
    }
  }

  // 图片操作
  async saveImage(imageData) {
    await this.init()
    const data = {
      ...imageData,
      uuid: imageData.uuid || uuidv4(),
      created_at: new Date().toISOString()
    }
    await this.db.put('images', data)
    return data
  }

  async getImages(relatedUuid, type = 'draft') {
    await this.init()
    const tx = this.db.transaction('images', 'readonly')
    const store = tx.objectStore('images')
    const index = store.index(type === 'draft' ? 'draft_uuid' : 'transaction_uuid')
    return await index.getAll(relatedUuid)
  }

  async deleteImage(uuid) {
    await this.init()
    await this.db.delete('images', uuid)
  }

  // 清理过期数据
  async cleanup() {
    await this.init()
    const thirtyDaysAgo = new Date()
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30)
    const cutoffTime = thirtyDaysAgo.toISOString()

    // 清理旧草稿
    const tx = this.db.transaction('drafts', 'readwrite')
    const store = tx.objectStore('drafts')
    const index = store.index('last_modified')
    const range = IDBKeyRange.upperBound(cutoffTime)
    
    for await (const cursor of index.iterate(range)) {
      await cursor.delete()
    }
  }

  // 获取存储使用情况
  async getStorageUsage() {
    if (navigator.storage && navigator.storage.estimate) {
      return await navigator.storage.estimate()
    }
    return null
  }
}

// 创建单例实例
const offlineManager = new OfflineManager()

export default offlineManager

// 导出工具函数
export const isOnline = () => navigator.onLine

export const waitForOnline = () => {
  return new Promise((resolve) => {
    if (navigator.onLine) {
      resolve()
    } else {
      const handleOnline = () => {
        window.removeEventListener('online', handleOnline)
        resolve()
      }
      window.addEventListener('online', handleOnline)
    }
  })
}

export const retryWithBackoff = async (fn, maxRetries = 3, baseDelay = 1000) => {
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await fn()
    } catch (error) {
      if (i === maxRetries - 1) throw error
      
      const delay = baseDelay * Math.pow(2, i)
      await new Promise(resolve => setTimeout(resolve, delay))
    }
  }
}
