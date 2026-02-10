# 常用备注UI优化 - 样式系统

本目录包含了常用备注UI优化的完整样式系统，实现了响应式设计、主题切换和无障碍支持。

## 文件结构

```
styles/
├── common-notes-variables.css  # CSS变量系统和主题配置
├── common-notes.css           # 主样式文件和组件样式
└── README.md                  # 本文档
```

## 使用方法

### 1. 导入样式

在你的Vue组件或主应用文件中导入样式：

```javascript
// 在main.js或组件中导入
import '@/styles/common-notes.css'
```

### 2. 应用CSS类

在组件模板中使用预定义的CSS类：

```vue
<template>
  <div class="common-notes-ui">
    <div class="notes-container">
      <div class="notes-header">
        <h3 class="notes-header__title">常用备注</h3>
        <button class="notes-header__toggle">
          展开/收起
        </button>
      </div>
      
      <div class="notes-list-container">
        <div class="notes-list">
          <div class="note-item">
            <div class="note-content">备注内容</div>
            <button class="expand-toggle">查看更多</button>
          </div>
        </div>
      </div>
      
      <div class="notes-footer">
        <button class="notes-footer__add-button">添加备注</button>
      </div>
    </div>
  </div>
</template>
```

### 3. 主题切换

使用主题管理组合式API：

```javascript
import { useTheme } from '@/composables/useTheme'

const { toggleTheme, setThemeMode, isDark } = useTheme()

// 切换主题
toggleTheme()

// 设置特定主题
setThemeMode('dark')
setThemeMode('light')
setThemeMode('system')
```

## CSS变量系统

### 颜色变量
- `--color-primary`: 主色调
- `--color-background`: 背景色
- `--color-text-primary`: 主要文字颜色
- `--color-text-secondary`: 次要文字颜色
- `--color-border`: 边框颜色

### 字体变量
- `--font-size-base`: 基础字体大小
- `--font-size-title`: 标题字体大小
- `--font-size-caption`: 说明文字字体大小

### 间距变量
- `--spacing-unit`: 基础间距单位
- `--spacing-component`: 组件间距
- `--spacing-content-padding`: 内容内边距
- `--spacing-list-item`: 列表项间距

### 触控变量
- `--touch-target-min-size`: 最小触控目标尺寸
- `--touch-target-padding`: 触控目标内边距

### 动画变量
- `--transition-duration-normal`: 标准动画时长
- `--transition-easing-ease-in-out`: 缓动函数
- `--long-note-animation-duration`: 长备注展开动画时长

## 响应式断点

- **移动端**: < 768px
- **平板端**: 768px - 1023px  
- **桌面端**: ≥ 1024px

## 主题支持

### 亮色主题 (默认)
- 白色背景
- 深色文字
- 蓝色主色调

### 暗色主题
- 深色背景
- 浅色文字
- 保持蓝色主色调

### 系统主题
- 自动跟随系统设置
- 支持动态切换

## 无障碍特性

- **键盘导航**: 支持Tab键和方向键导航
- **屏幕阅读器**: 提供适当的ARIA标签
- **高对比度**: 支持高对比度模式
- **减少动画**: 支持用户的减少动画偏好
- **焦点指示**: 清晰的焦点指示器

## 性能优化

- **CSS变量**: 高效的主题切换
- **硬件加速**: 使用transform进行动画
- **防抖处理**: 响应式断点变化防抖
- **最小重绘**: 优化的CSS选择器

## 浏览器兼容性

支持以下浏览器：
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- iOS Safari 12+
- Android Chrome 60+

## 自定义主题

你可以通过覆盖CSS变量来自定义主题：

```css
:root {
  --color-primary: #your-color;
  --color-background: #your-background;
  /* 其他变量... */
}
```

或者使用JavaScript动态设置：

```javascript
import { setCustomTheme } from '@/composables/useTheme'

setCustomTheme({
  mode: 'light',
  primaryColor: '#your-color',
  backgroundColor: '#your-background',
  textColor: '#your-text-color',
  borderColor: '#your-border-color',
  accentColor: '#your-accent-color'
})
```