declare module '*.vue' {
  import type { DefineComponent } from 'vue';
  const component: DefineComponent<{}, {}, any>;
  export default component;
}

declare module '@/locales/*.json' {
  const value: Record<string, any>;
  export default value;
}
