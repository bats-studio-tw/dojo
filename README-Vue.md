# Laravel + Vue 3 技术栈

## 🚀 技术栈

### 前端

-   **Vue 3** - 渐进式 JavaScript 框架
-   **TypeScript** - 类型安全的 JavaScript 超集
-   **Vite** - 现代化构建工具
-   **UnoCSS** - 原子化 CSS 框架
-   **Pinia** - Vue 状态管理
-   **Naive UI** - Vue 3 组件库
-   **Vue i18n** - 国际化支持

### 后端

-   **Laravel 12.0** - PHP 框架
-   **PHP 8.2+** - 服务端语言

## 📂 项目结构

```
resources/
├── js/
│   ├── App.vue              # 主Vue组件
│   ├── app.ts              # Vue应用入口
│   ├── vue-shim.d.ts       # TypeScript声明
│   ├── stores/
│   │   └── counter.ts      # Pinia状态管理
│   └── locales/
│       ├── en.json         # 英文语言包
│       └── zh-CN.json      # 中文语言包
├── css/
│   └── app.css             # 样式文件
└── views/
    └── app.blade.php       # Vue应用模板

uno.config.ts               # UnoCSS配置
tsconfig.json              # TypeScript配置
vite.config.js             # Vite配置
```

## 🛠️ 开发指令

```bash
# 安装依赖
npm install

# 启动开发服务器
npm run dev

# TypeScript类型检查
npm run type-check

# 构建生产版本
npm run build

# Laravel开发服务器
php artisan serve
```

## ✨ 功能特性

### 已实现功能

-   ✅ Vue 3 Composition API
-   ✅ TypeScript 支持
-   ✅ 响应式 UI 组件
-   ✅ 状态管理 (Pinia)
-   ✅ 国际化支持 (中文/英文)
-   ✅ 图标系统 (Tabler Icons)
-   ✅ 原子化 CSS (UnoCSS)
-   ✅ 开发热重载

### 组件示例

-   导航栏与语言切换
-   卡片式布局
-   模态框组件
-   通知系统
-   计数器状态管理

## 🎨 UI 组件库

使用 **Naive UI** 提供丰富的 Vue 3 组件：

-   按钮、卡片、模态框
-   表单组件
-   导航组件
-   反馈组件
-   数据展示组件

## 🌍 国际化

支持多语言切换：

-   中文 (zh-CN)
-   英文 (en)

语言文件位置：`resources/js/locales/`

## 🎯 开发建议

1. 使用 TypeScript 进行类型安全开发
2. 遵循 Vue 3 Composition API 最佳实践
3. 使用 UnoCSS 原子化类名进行样式开发
4. 利用 Pinia 进行状态管理
5. 使用 vue-tsc 进行类型检查

## 📚 相关文档

-   [Vue 3 官方文档](https://vuejs.org/)
-   [TypeScript 官方文档](https://www.typescriptlang.org/)
-   [Vite 官方文档](https://vitejs.dev/)
-   [UnoCSS 官方文档](https://uno.antfu.me/)
-   [Pinia 官方文档](https://pinia.vuejs.org/)
-   [Naive UI 官方文档](https://www.naiveui.com/)
-   [Vue i18n 官方文档](https://vue-i18n.intlify.dev/)
