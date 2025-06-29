import '../css/app.css';
import './bootstrap';

// UnoCSS styles
import 'uno.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import naive from 'naive-ui';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Import locales
import en from './locales/en.json';
import zhCN from './locales/zh-CN.json';

// Import store for initialization
import { useGamePredictionStore } from '@/stores/gamePrediction';

/* 套用naiveui樣式 */
const meta = document.createElement('meta');
meta.name = 'naive-ui-style';
document.head.appendChild(meta);

const appName = import.meta.env.VITE_APP_NAME || 'DojoDashboard';

// Create i18n instance
const i18n = createI18n({
  legacy: false,
  locale: 'zh-CN',
  fallbackLocale: 'en',
  messages: {
    en,
    'zh-CN': zhCN
  }
});

// Create Pinia store
const pinia = createPinia();

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(pinia)
      .use(i18n)
      .use(naive)
      .use(ZiggyVue);

    // 初始化游戏预测数据store - 建立统一的WebSocket连接和数据管理
    // 这确保了在应用启动时就建立连接，避免每次切换页面都重新连接
    const gamePredictionStore = useGamePredictionStore();

    // 在应用mount后初始化store，确保DOM和所有服务都已准备好
    app.mount(el);

    // 延迟初始化，确保应用完全启动
    setTimeout(async () => {
      try {
        console.log('🚀 应用启动完成，初始化游戏数据store...');
        await gamePredictionStore.initialize();
        console.log('✅ 游戏数据store初始化完成');
      } catch (error) {
        console.error('❌ 游戏数据store初始化失败:', error);
      }
    }, 2000);

    // 监听页面卸载，清理资源
    window.addEventListener('beforeunload', () => {
      gamePredictionStore.cleanup();
    });
  },
  progress: {
    color: '#4B5563'
  }
});
