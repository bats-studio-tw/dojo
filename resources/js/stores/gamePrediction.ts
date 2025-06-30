import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

// ==================== 类型定义（为了兼容性保留） ====================
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
  prediction_score: number;
  win_rate: number;
  top3_rate: number;
  avg_rank: number;
  total_games: number;
  wins: number;
  top3: number;
  predicted_rank: number;
  // v8 H2H 数据
  absolute_score?: number;
  relative_score?: number;
  h2h_score?: number;
  risk_adjusted_score?: number;
  rank_confidence?: number;
  final_prediction_score?: number;
  market_momentum_score?: number;
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

// 最简单的WebSocket状态类型
export interface WebSocketStatus {
  status: 'connecting' | 'connected' | 'disconnected' | 'error';
  message: string;
  lastConnectedAt: string | null;
}

export const useGamePredictionStore = defineStore('gamePrediction', () => {
  // ==================== 状态管理 ====================
  const websocketStatus = ref<WebSocketStatus>({
    status: 'disconnected',
    message: '未连接',
    lastConnectedAt: null
  });

  // ==================== 为了兼容性添加的空数据 ====================
  const currentAnalysis = ref<TokenAnalysis[]>([]);
  const analysisMeta = ref<AnalysisMeta | null>(null);
  const predictionHistory = ref<PredictionHistoryRound[]>([]);
  const latestGameData = ref<any>(null);

  // 加载状态（空实现）
  const analysisLoading = ref(false);
  const historyLoading = ref(false);

  // 错误状态（空实现）
  const analysisError = ref<string | null>(null);
  const historyError = ref<string | null>(null);

  // WebSocket连接引用
  let gameUpdatesChannel: any = null;
  let predictionsChannel: any = null;

  // ==================== 计算属性 ====================
  const isConnected = computed(() => websocketStatus.value.status === 'connected');

  // ==================== 为了兼容性添加的计算属性 ====================
  const hasCurrentAnalysis = computed(() => currentAnalysis.value.length > 0);
  const totalHistoryRounds = computed(() => predictionHistory.value.length);

  const currentRoundId = computed(() => {
    if (latestGameData.value?.rdId) {
      return latestGameData.value.rdId;
    }
    if (analysisMeta.value?.round_id) {
      return analysisMeta.value.round_id;
    }
    return null;
  });

  const currentGameStatus = computed(() => {
    return latestGameData.value?.status || 'unknown';
  });

  const currentGameTokens = computed(() => {
    if (!latestGameData.value?.token) return [];
    return Object.keys(latestGameData.value.token);
  });

  const currentGameTokensWithRanks = computed(() => {
    if (!latestGameData.value?.token) return [];
    return Object.entries(latestGameData.value.token).map(([symbol, data]: [string, any]) => ({
      symbol,
      rank: data.s || data.rank,
      price: data.p || data.price,
      ...data
    }));
  });

  const currentAnalysisFormatted = computed(() => {
    return {
      predictions: currentAnalysis.value,
      meta: analysisMeta.value,
      game_data: latestGameData.value,
      round_id: currentRoundId.value,
      status: currentGameStatus.value
    };
  });

  const canBet = computed(() => {
    const status = currentGameStatus.value;
    return status === 'bet';
  });

  const isSettled = computed(() => {
    const status = currentGameStatus.value;
    return status === 'settled';
  });

  const isSettling = computed(() => {
    const status = currentGameStatus.value;
    return status === 'settling';
  });

  const isLocked = computed(() => {
    const status = currentGameStatus.value;
    return status === 'lock';
  });

  // ==================== 最简单的WebSocket初始化 ====================
  const initializeWebSocket = () => {
    console.log('🔄 [DEBUG] 开始初始化WebSocket连接...');

    if (!window.Echo) {
      console.error('❌ [DEBUG] Echo WebSocket未初始化');
      websocketStatus.value = {
        status: 'error',
        message: 'WebSocket Echo未初始化',
        lastConnectedAt: null
      };
      return;
    }

    // 检查Echo状态
    console.log('🔍 [DEBUG] Echo实例:', window.Echo);
    console.log('🔍 [DEBUG] Echo connector:', window.Echo.connector);

    if (window.Echo.connector?.pusher) {
      console.log('🔍 [DEBUG] Pusher连接状态:', window.Echo.connector.pusher.connection.state);
    }

    websocketStatus.value = {
      status: 'connecting',
      message: '正在连接WebSocket...',
      lastConnectedAt: null
    };

    try {
      // 1. 监听游戏数据更新
      console.log('🎮 [DEBUG] 开始监听 game-updates 频道...');
      gameUpdatesChannel = window.Echo.channel('game-updates');

      gameUpdatesChannel
        .subscribed(() => {
          console.log('✅ [DEBUG] 成功订阅 game-updates 频道');
        })
        .listen('game.data.updated', (data: any) => {
          console.log('📨 [DEBUG] ========== 收到 game.data.updated 事件 ==========');
          console.log('📨 [DEBUG] 完整数据:', data);
          console.log('📨 [DEBUG] 数据类型:', typeof data);
          console.log('📨 [DEBUG] 数据键:', Object.keys(data));
          console.log('📨 [DEBUG] 时间戳:', new Date().toLocaleString());
          console.log('📨 [DEBUG] ==========================================');

          // 🔍 [DEBUG] 简单更新游戏数据（用于其他组件兼容性）
          if (data.data) {
            latestGameData.value = { ...data.data };
            console.log('📨 [DEBUG] 已更新latestGameData用于兼容性');
          }
        })
        .error((error: any) => {
          console.error('❌ [DEBUG] game-updates 频道错误:', error);
        });

      // 2. 监听预测数据更新
      console.log('🧠 [DEBUG] 开始监听 predictions 频道...');
      predictionsChannel = window.Echo.channel('predictions');

      predictionsChannel
        .subscribed(() => {
          console.log('✅ [DEBUG] 成功订阅 predictions 频道');
        })
        .listen('prediction.updated', (data: any) => {
          console.log('🔮 [DEBUG] ========== 收到 prediction.updated 事件 ==========');
          console.log('🔮 [DEBUG] 完整数据:', data);
          console.log('🔮 [DEBUG] 数据类型:', typeof data);
          console.log('🔮 [DEBUG] 数据键:', Object.keys(data));
          console.log('🔮 [DEBUG] 时间戳:', new Date().toLocaleString());
          console.log('🔮 [DEBUG] ==========================================');

          // 🔍 [DEBUG] 简单更新预测数据（用于其他组件兼容性）
          try {
            let parsedData = data;
            if (typeof data === 'string') {
              parsedData = JSON.parse(data);
            } else if (data.data && typeof data.data === 'string') {
              parsedData = JSON.parse(data.data);
            }

            if (parsedData.success && parsedData.data && Array.isArray(parsedData.data)) {
              currentAnalysis.value = [...parsedData.data];
              analysisMeta.value = parsedData.meta || null;
              console.log('🔮 [DEBUG] 已更新currentAnalysis和analysisMeta用于兼容性');
            } else if (parsedData.data && Array.isArray(parsedData.data)) {
              currentAnalysis.value = [...parsedData.data];
              console.log('🔮 [DEBUG] 已更新currentAnalysis用于兼容性（简化格式）');
            }
          } catch (error) {
            console.error('❌ [DEBUG] 解析预测数据失败:', error);
          }
        })
        .error((error: any) => {
          console.error('❌ [DEBUG] predictions 频道错误:', error);
        });

      // 连接成功
      websocketStatus.value = {
        status: 'connected',
        message: '已连接',
        lastConnectedAt: new Date().toISOString()
      };

      console.log('✅ [DEBUG] WebSocket连接成功建立');

      // 输出连接后的状态信息
      setTimeout(() => {
        console.log('🔍 [DEBUG] 连接建立后的状态检查:');
        if (window.Echo?.connector?.pusher) {
          console.log('🔍 [DEBUG] Pusher连接状态:', window.Echo.connector.pusher.connection.state);
          console.log('🔍 [DEBUG] 已订阅的频道:', Object.keys(window.Echo.connector.pusher.channels.channels));

          // 检查频道详情
          const channels = window.Echo.connector.pusher.channels.channels;
          if (channels['game-updates']) {
            console.log('🔍 [DEBUG] game-updates 频道存在');
          }
          if (channels['predictions']) {
            console.log('🔍 [DEBUG] predictions 频道存在');
          }
        }
      }, 2000);
    } catch (error) {
      console.error('❌ [DEBUG] WebSocket连接失败:', error);
      websocketStatus.value = {
        status: 'error',
        message: `连接失败: ${error instanceof Error ? error.message : String(error)}`,
        lastConnectedAt: null
      };
    }
  };

  // ==================== 断开连接 ====================
  const disconnectWebSocket = () => {
    console.log('🔌 [DEBUG] 断开WebSocket连接');

    if (gameUpdatesChannel) {
      window.Echo.leaveChannel('game-updates');
      gameUpdatesChannel = null;
      console.log('🔌 [DEBUG] 已断开 game-updates 频道');
    }

    if (predictionsChannel) {
      window.Echo.leaveChannel('predictions');
      predictionsChannel = null;
      console.log('🔌 [DEBUG] 已断开 predictions 频道');
    }

    websocketStatus.value = {
      status: 'disconnected',
      message: '已断开连接',
      lastConnectedAt: null
    };
  };

  // ==================== 重连 ====================
  const reconnectWebSocket = () => {
    console.log('🔄 [DEBUG] 手动重连WebSocket...');
    disconnectWebSocket();
    setTimeout(() => {
      initializeWebSocket();
    }, 1000);
  };

  // ==================== 为了兼容性添加的空方法 ====================
  const fetchCurrentAnalysis = async () => {
    console.log('📊 [DEBUG] fetchCurrentAnalysis 被调用（简化实现，不做任何操作）');
  };

  const fetchPredictionHistory = async () => {
    console.log('📈 [DEBUG] fetchPredictionHistory 被调用（简化实现，不做任何操作）');
  };

  const fetchInitialData = async () => {
    console.log('🚀 [DEBUG] fetchInitialData 被调用（简化实现，不做任何操作）');
  };

  const refreshAllPredictionData = async () => {
    console.log('🔄 [DEBUG] refreshAllPredictionData 被调用（简化实现，不做任何操作）');
  };

  const clearErrors = () => {
    console.log('🧹 [DEBUG] clearErrors 被调用（简化实现，不做任何操作）');
    analysisError.value = null;
    historyError.value = null;
  };

  // ==================== 初始化 ====================
  const initialize = async () => {
    console.log('🏗️ [DEBUG] 初始化游戏预测数据store...');

    // 延迟初始化WebSocket，确保Echo已准备好
    setTimeout(() => {
      initializeWebSocket();
    }, 1000);
  };

  // ==================== 清理 ====================
  const cleanup = () => {
    console.log('🧹 [DEBUG] 清理游戏预测数据store资源...');
    disconnectWebSocket();
  };

  // ==================== 测试方法 ====================
  const testConnection = () => {
    console.log('🧪 [DEBUG] ========== 连接测试开始 ==========');
    console.log('🧪 [DEBUG] WebSocket状态:', websocketStatus.value);
    console.log('🧪 [DEBUG] Echo实例:', window.Echo);

    if (window.Echo?.connector?.pusher) {
      const pusher = window.Echo.connector.pusher;
      console.log('🧪 [DEBUG] Pusher连接状态:', pusher.connection.state);
      console.log('🧪 [DEBUG] 已订阅的频道:', Object.keys(pusher.channels.channels));

      // 检查频道状态
      Object.entries(pusher.channels.channels).forEach(([channelName, channel]: [string, any]) => {
        console.log(`🧪 [DEBUG] 频道 ${channelName}:`, {
          subscribed: channel.subscribed,
          state: channel.state,
          callbacks: Object.keys(channel.callbacks || {})
        });
      });
    }
    console.log('🧪 [DEBUG] ========== 连接测试结束 ==========');
  };

  return {
    // ==================== 状态导出 ====================
    websocketStatus,
    isConnected,

    // ==================== 为了兼容性导出的属性 ====================
    currentAnalysis,
    analysisMeta,
    predictionHistory,
    latestGameData,
    analysisLoading,
    historyLoading,
    analysisError,
    historyError,
    hasCurrentAnalysis,
    totalHistoryRounds,
    currentRoundId,
    currentGameStatus,
    currentGameTokens,
    currentGameTokensWithRanks,
    currentAnalysisFormatted,
    canBet,
    isLocked,
    isSettled,
    isSettling,

    // ==================== 方法导出 ====================
    initializeWebSocket,
    disconnectWebSocket,
    reconnectWebSocket,
    initialize,
    cleanup,
    testConnection,

    // ==================== 为了兼容性导出的方法 ====================
    fetchCurrentAnalysis,
    fetchPredictionHistory,
    fetchInitialData,
    refreshAllPredictionData,
    clearErrors
  };
});
