declare module '*.vue' {
  import type { DefineComponent } from 'vue';
  const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;
  export default component;
}

// 国际化语言包类型定义
interface LocaleMessages {
  [key: string]: string | LocaleMessages;
}

declare module '@/locales/*.json' {
  const value: LocaleMessages;
  export default value;
}
