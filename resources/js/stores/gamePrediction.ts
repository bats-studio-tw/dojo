import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/utils/api';

// 类型定义
export interface TokenAnalysis {
  symbol: string;
  name: string;
  change_5m: number | null;
  change_1h: number | null;
  change_4h: number | null;
  change_24h: number | null;
  volume_24h: string;
  market_cap: number | null;
  logo: string | null;

  // v8 H2H 对战关系分析核心数据
  absolute_score?: number;
  relative_score?: number;
  h2h_score?: number;
  risk_adjusted_score?: number;
  predicted_final_value?: number;
  rank_confidence?: number;

  // 传统数据字段
  prediction_score: number;
  market_momentum_score?: number;
  final_prediction_score?: number;
  win_rate: number;
  top3_rate: number;
  avg_rank: number;
  total_games: number;
  wins: number;
  top3: number;
  predicted_rank: number;

  // v8 补充数据
  value_stddev?: number;
  recent_avg_value?: number;
  avg_value?: number;
}

export interface PredictionData {
  symbol: string;
  predicted_rank: number;
  prediction_score: number;
  predicted_at: string;
}

export interface ResultData {
  symbol: string;
  actual_rank: number;
  value: string;
}

export interface AccuracyDetail {
  symbol: string;
  predicted_rank: number;
  actual_rank: number;
  rank_difference: number;
  is_exact_match: boolean;
  is_close_match: boolean;
}

export interface Accuracy {
  total_predictions: number;
  exact_matches: number;
  close_matches: number;
  exact_accuracy: number;
  close_accuracy: number;
  avg_rank_difference: number;
  details: AccuracyDetail[];
}

export interface PredictionHistoryRound {
  id: number;
  round_id: string;
  settled_at: string | null;
  predictions: PredictionData[];
  results: ResultData[];
  accuracy: Accuracy;
}

export interface AnalysisMeta {
  round_id: string;
  status: string;
  updated_at: string;
  [key: string]: any;
}

// WebSocket连接状态类型
export interface WebSocketStatus {
  status: 'connecting' | 'connected' | 'disconnected' | 'error';
  message: string;
  reconnectAttempts: number;
  lastConnectedAt: string | null;
  lastError: string | null;
}

export const useGamePredictionStore = defineStore('gamePrediction', () => {
  // ==================== 状态管理 ====================

  // 核心数据状态
  const currentAnalysis = ref<TokenAnalysis[]>([]);
  const analysisMeta = ref<AnalysisMeta | null>(null);
  const predictionHistory = ref<PredictionHistoryRound[]>([]);
  const latestGameData = ref<any>(null);

  // 加载状态
  const analysisLoading = ref(false);
  const historyLoading = ref(false);

  // 错误状态
  const analysisError = ref<string | null>(null);
  const historyError = ref<string | null>(null);

  // WebSocket状态
  const websocketStatus = ref<WebSocketStatus>({
    status: 'disconnected',
    message: '未连接',
    reconnectAttempts: 0,
    lastConnectedAt: null,
    lastError: null
  });

  // WebSocket连接引用
  let gameUpdatesChannel: any = null;
  let predictionsChannel: any = null;
  let reconnectTimer: number | null = null;
  const maxReconnectAttempts = 5;

  // ==================== 计算属性 ====================

  const hasCurrentAnalysis = computed(() => currentAnalysis.value.length > 0);
  const totalHistoryRounds = computed(() => predictionHistory.value.length);
  const isConnected = computed(() => websocketStatus.value.status === 'connected');
  const shouldReconnect = computed(
    () =>
      websocketStatus.value.status === 'disconnected' && websocketStatus.value.reconnectAttempts < maxReconnectAttempts
  );

  // ==================== 数据获取方法 ====================

  /**
   * 获取预测历史数据
   */
  const fetchPredictionHistory = async () => {
    historyLoading.value = true;
    historyError.value = null;

    try {
      const response = await api.get('/game/prediction-history');
      if (response.data.success) {
        predictionHistory.value = response.data.data || [];
        console.log('📈 更新预测历史数据:', predictionHistory.value.length, '局');
      } else {
        historyError.value = response.data.message || '获取预测历史数据失败';
      }
    } catch (error) {
      console.error('获取预测历史数据失败:', error);
      historyError.value = '网络错误，获取预测历史数据失败';
    } finally {
      historyLoading.value = false;
    }
  };

  // ==================== WebSocket管理 ====================

  /**
   * 初始化WebSocket连接
   */
  const initializeWebSocket = () => {
    if (!window.Echo) {
      console.warn('Echo WebSocket未初始化');
      websocketStatus.value = {
        ...websocketStatus.value,
        status: 'error',
        message: 'WebSocket Echo未初始化',
        lastError: 'Echo不可用'
      };
      return;
    }

    // 防止重复连接
    if (websocketStatus.value.status === 'connected' || websocketStatus.value.status === 'connecting') {
      console.log('WebSocket已连接或正在连接中，跳过初始化');
      return;
    }

    websocketStatus.value = {
      ...websocketStatus.value,
      status: 'connecting',
      message: '正在连接WebSocket...'
    };

    try {
      console.log('🔄 初始化游戏数据WebSocket连接...');

      // 监听游戏数据更新 - 包含当前轮次信息
      gameUpdatesChannel = window.Echo.channel('game-updates').listen('game.data.updated', (data: any) => {
        console.log('🎮 收到游戏数据更新:', data);

        // 更新最新游戏数据
        latestGameData.value = data.data;

        // 如果是结算数据，刷新预测历史
        if (data.type === 'settlement') {
          console.log('📊 检测到结算数据，刷新预测历史');
          fetchPredictionHistory();
        }
      });

      // 监听预测数据更新 - 实时更新current-analysis
      predictionsChannel = window.Echo.channel('predictions').listen('prediction.updated', (data: any) => {
        console.log('🧠 收到预测数据更新:', data);

        // 直接更新当前分析数据
        if (data.data && Array.isArray(data.data)) {
          currentAnalysis.value = data.data;

          // 更新分析元数据
          if (data.round_id) {
            analysisMeta.value = {
              round_id: data.round_id,
              status: analysisMeta.value?.status || 'unknown',
              updated_at: data.timestamp || new Date().toISOString(),
              source: 'websocket'
            };
          }

          console.log(`✅ 已更新预测分析数据: ${data.data.length} 个代币`);
        }
      });

      // 连接成功
      websocketStatus.value = {
        status: 'connected',
        message: '已连接',
        reconnectAttempts: 0,
        lastConnectedAt: new Date().toISOString(),
        lastError: null
      };

      console.log('✅ WebSocket连接成功建立');
    } catch (error) {
      console.error('❌ WebSocket连接失败:', error);
      websocketStatus.value = {
        ...websocketStatus.value,
        status: 'error',
        message: '连接失败',
        lastError: error instanceof Error ? error.message : String(error)
      };

      // 尝试重连
      scheduleReconnect();
    }
  };

  /**
   * 断开WebSocket连接
   */
  const disconnectWebSocket = () => {
    console.log('🔌 断开WebSocket连接');

    // 清理重连定时器
    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }

    // 断开频道连接
    if (gameUpdatesChannel) {
      window.Echo.leaveChannel('game-updates');
      gameUpdatesChannel = null;
    }

    if (predictionsChannel) {
      window.Echo.leaveChannel('predictions');
      predictionsChannel = null;
    }

    websocketStatus.value = {
      ...websocketStatus.value,
      status: 'disconnected',
      message: '已断开连接'
    };
  };

  /**
   * 获取当前分析数据 - 保留作为初始化和备用
   */
  const fetchCurrentAnalysis = async () => {
    analysisLoading.value = true;
    analysisError.value = null;

    try {
      const response = await api.get('/game/current-analysis');
      if (response.data.success) {
        currentAnalysis.value = response.data.data || [];
        analysisMeta.value = response.data.meta || null;
        console.log('📊 通过API获取预测分析数据:', currentAnalysis.value.length, '个代币');
      } else {
        analysisError.value = response.data.message || '获取预测分析数据失败';
      }
    } catch (error) {
      console.error('获取预测分析数据失败:', error);
      analysisError.value = '网络错误，获取预测分析数据失败';
    } finally {
      analysisLoading.value = false;
    }
  };

  /**
   * 手动重连
   */
  const reconnectWebSocket = () => {
    disconnectWebSocket();
    websocketStatus.value.reconnectAttempts = 0; // 重置重连计数
    setTimeout(() => {
      initializeWebSocket();
    }, 1000);
  };

  /**
   * 获取初始数据 - 页面首次加载时调用
   */
  const fetchInitialData = async () => {
    console.log('🚀 获取初始数据...');

    // 并行获取当前分析数据和历史数据
    await Promise.all([fetchCurrentAnalysis(), fetchPredictionHistory()]);

    // 尝试从WebSocket获取最新缓存数据
    try {
      const response = await window.axios.get('/websocket/latest-data');
      if (response.data.success) {
        const data = response.data.data;
        if (data.latest_game_data) {
          latestGameData.value = data.latest_game_data.data;
          console.log('📦 获取WebSocket缓存的游戏数据');
        }
      }
    } catch (error) {
      console.warn('获取WebSocket缓存数据失败:', error);
    }
  };

  /**
   * 刷新所有预测数据
   */
  const refreshAllPredictionData = async () => {
    await Promise.all([fetchCurrentAnalysis(), fetchPredictionHistory()]);
  };

  /**
   * 清除错误状态
   */
  const clearErrors = () => {
    analysisError.value = null;
    historyError.value = null;
    if (websocketStatus.value.lastError) {
      websocketStatus.value = {
        ...websocketStatus.value,
        lastError: null
      };
    }
  };

  // ==================== 生命周期管理 ====================

  /**
   * 初始化store - 应该在应用启动时调用一次
   */
  const initialize = async () => {
    console.log('🏗️ 初始化游戏预测数据store...');

    // 获取初始数据
    await fetchInitialData();

    // 延迟初始化WebSocket，确保Echo已准备好
    setTimeout(() => {
      initializeWebSocket();
    }, 1000);
  };

  /**
   * 清理资源
   */
  const cleanup = () => {
    console.log('🧹 清理游戏预测数据store资源...');
    disconnectWebSocket();
    clearErrors();
  };

  return {
    // ==================== 状态导出 ====================
    currentAnalysis,
    analysisMeta,
    predictionHistory,
    latestGameData,

    // 加载状态
    analysisLoading,
    historyLoading,

    // 错误状态
    analysisError,
    historyError,

    // WebSocket状态
    websocketStatus,

    // ==================== 计算属性导出 ====================
    hasCurrentAnalysis,
    totalHistoryRounds,
    isConnected,
    shouldReconnect,

    // ==================== 方法导出 ====================

    // WebSocket管理
    initializeWebSocket,
    disconnectWebSocket,
    reconnectWebSocket,

    // 数据获取
    fetchCurrentAnalysis,
    fetchPredictionHistory,
    fetchInitialData,
    refreshAllPredictionData,

    // 工具方法
    clearErrors,

    // 生命周期
    initialize,
    cleanup
  };
});
