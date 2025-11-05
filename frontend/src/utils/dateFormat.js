/**
 * 日期时间格式化工具
 * 处理UTC时间到本地时区的转换
 */

/**
 * 将UTC时间字符串转换为本地时间并格式化
 * @param {string} datetime - UTC时间字符串,支持ISO 8601格式或MySQL格式
 * @param {boolean} includeSeconds - 是否包含秒
 * @returns {string} 格式化后的本地时间字符串
 */
export function formatDateTime(datetime, includeSeconds = true) {
  if (!datetime) return ''
  
  try {
    // 创建Date对象会自动转换为本地时区
    const date = new Date(datetime)
    
    // 检查日期是否有效
    if (isNaN(date.getTime())) {
      return datetime
    }
    
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')
    const seconds = String(date.getSeconds()).padStart(2, '0')
    
    if (includeSeconds) {
      return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
    } else {
      return `${year}-${month}-${day} ${hours}:${minutes}`
    }
  } catch (error) {
    console.error('日期格式化错误:', error, datetime)
    return datetime
  }
}

/**
 * 格式化为短日期时间 (MM-DD HH:mm)
 * @param {string} datetime - UTC时间字符串
 * @returns {string} 格式化后的短时间字符串
 */
export function formatShortDateTime(datetime) {
  if (!datetime) return ''
  
  try {
    const date = new Date(datetime)
    
    if (isNaN(date.getTime())) {
      return datetime
    }
    
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')
    
    return `${month}-${day} ${hours}:${minutes}`
  } catch (error) {
    console.error('短日期格式化错误:', error, datetime)
    return datetime
  }
}

/**
 * 格式化为日期 (YYYY-MM-DD)
 * @param {string} datetime - UTC时间字符串
 * @returns {string} 格式化后的日期字符串
 */
export function formatDate(datetime) {
  if (!datetime) return ''
  
  try {
    const date = new Date(datetime)
    
    if (isNaN(date.getTime())) {
      return datetime
    }
    
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    
    return `${year}-${month}-${day}`
  } catch (error) {
    console.error('日期格式化错误:', error, datetime)
    return datetime
  }
}

/**
 * 格式化为时间 (HH:mm:ss)
 * @param {string} datetime - UTC时间字符串
 * @returns {string} 格式化后的时间字符串
 */
export function formatTime(datetime) {
  if (!datetime) return ''
  
  try {
    const date = new Date(datetime)
    
    if (isNaN(date.getTime())) {
      return datetime
    }
    
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')
    const seconds = String(date.getSeconds()).padStart(2, '0')
    
    return `${hours}:${minutes}:${seconds}`
  } catch (error) {
    console.error('时间格式化错误:', error, datetime)
    return datetime
  }
}

/**
 * 获取相对时间描述 (刚刚、X分钟前、X小时前等)
 * @param {string} datetime - UTC时间字符串
 * @returns {string} 相对时间描述
 */
export function getRelativeTime(datetime) {
  if (!datetime) return ''
  
  try {
    const date = new Date(datetime)
    const now = new Date()
    
    if (isNaN(date.getTime())) {
      return datetime
    }
    
    const diff = now - date // 毫秒差
    const seconds = Math.floor(diff / 1000)
    const minutes = Math.floor(seconds / 60)
    const hours = Math.floor(minutes / 60)
    const days = Math.floor(hours / 24)
    
    if (seconds < 60) {
      return '刚刚'
    } else if (minutes < 60) {
      return `${minutes}分钟前`
    } else if (hours < 24) {
      return `${hours}小时前`
    } else if (days < 7) {
      return `${days}天前`
    } else {
      return formatDate(datetime)
    }
  } catch (error) {
    console.error('相对时间计算错误:', error, datetime)
    return datetime
  }
}

