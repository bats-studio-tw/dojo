import { PageProps as InertiaPageProps } from '@inertiajs/core';
import { AxiosInstance } from 'axios';
import { route as ziggyRoute } from 'ziggy-js';
import { PageProps as AppPageProps } from './';
import Echo from 'laravel-echo';

declare global {
  interface Window {
    axios: AxiosInstance;
    Echo: Echo;
    Pusher: any;
  }

  var route: typeof ziggyRoute;
}

declare module 'vue' {
  interface ComponentCustomProperties {
    route: typeof ziggyRoute;
  }
}

declare module '@inertiajs/core' {
  interface PageProps extends InertiaPageProps, AppPageProps {}
}

// 导出游戏相关类型供其他组件使用
export type {
  GameStatus,
  GameUpdateType,
  TokenPriceData,
  TokenBetData,
  GroupData,
  GameTimeData,
  GameData,
  GameDataUpdateEvent,
  TokenAnalysis,
  PredictionUpdateEvent,
  WebSocketStatus
} from '@/stores/gamePrediction';
