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

// Import WebSocket manager
import { websocketManager } from '@/utils/websocketManager';

/* 套用naiveui樣式 */
const meta = document.createElement('meta');
meta.name = 'naive-ui-style';
document.head.appendChild(meta);

const appName = import.meta.env.VITE_APP_NAME || 'Dojo AutoBetting';

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

    // 在应用mount后初始化，确保DOM和所有服务都已准备好
    app.mount(el);

    // 延迟初始化，确保应用完全启动
    setTimeout(async () => {
      try {
        // 初始化WebSocket状态监控器
        websocketManager.initialize();

        // 初始化游戏数据store
        const gamePredictionStore = useGamePredictionStore();
        await gamePredictionStore.initialize();
      } catch (error) {
        console.error('❌ 服务初始化失败:', error);
      }
    }, 2000);
  },
  progress: {
    color: '#4B5563'
  }
});
