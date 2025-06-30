import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

// ==================== WebSocket游戏数据类型定义 ====================

/**
 * 游戏状态类型
 */
export type GameStatus = 'bet' | 'lock' | 'settling' | 'settled';

/**
 * 游戏数据更新事件类型
 */
export type GameUpdateType = 'bet' | 'lock' | 'settlement';

/**
 * Token价格和排名数据
 */
export interface TokenPriceData {
  p: number; // 价格变化
  s: number; // 当前排名 (rank)
}

/**
 * Token投注数据
 */
export interface TokenBetData {
  a: number; // 投注金额 (amount)
  c: number; // 投注次数 (count)
}

/**
 * 分组数据 (dummy/real)
 */
export interface GroupData {
  PAmt: number; // 总投注金额
  PStart: number; // 游戏开始时间戳
  TAmt: number; // Token总金额
  TCount: number; // 总投注次数
  mts: boolean; // 是否维护状态
  token: Record<string, TokenBetData>; // Token投注数据
}

/**
 * 时间数据
 */
export interface GameTimeData {
  bet: number; // 投注开始时间
  lock: number; // 锁定时间
  settle: number; // 结算时间
}

/**
 * 完整的游戏数据结构
 */
export interface GameData {
  gmId: string; // 游戏ID
  groupDatas: {
    dummy: GroupData; // 模拟数据
    real: GroupData; // 真实数据
  };
  rdId: string; // 轮次ID
  status: GameStatus; // 游戏状态
  time: {
    next: GameTimeData; // 下一轮时间
    now: GameTimeData; // 当前轮时间
  };
  token: Record<string, TokenPriceData>; // Token价格和排名数据
  ts: number; // 时间戳
  type: string; // 数据类型 (通常是 "round")
}

/**
 * WebSocket游戏数据更新事件
 */
export interface GameDataUpdateEvent {
  type: GameUpdateType; // 事件类型
  data: GameData; // 游戏数据
  timestamp: string; // 事件时间戳
}

// ==================== 预测数据类型定义（为了兼容性保留） ====================
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

/**
 * 预测数据更新事件
 */
export interface PredictionUpdateEvent {
  success: boolean;
  data: TokenAnalysis[];
  meta: AnalysisMeta;
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

  // ==================== 数据状态管理 ====================
  const currentAnalysis = ref<TokenAnalysis[]>([]);
  const analysisMeta = ref<AnalysisMeta | null>(null);
  const predictionHistory = ref<PredictionHistoryRound[]>([]);
  const latestGameData = ref<GameData | null>(null);

  // 加载状态（空实现）
  const analysisLoading = ref(false);
  const historyLoading = ref(false);

  // 错误状态（空实现）
  const analysisError = ref<string | null>(null);
  const historyError = ref<string | null>(null);

  // WebSocket连接引用和状态控制
  let gameUpdatesChannel: any = null;
  let predictionsChannel: any = null;
  let isInitialized = false; // 防止重复初始化

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

  const currentGameStatus = computed((): GameStatus | 'unknown' => {
    return latestGameData.value?.status || 'unknown';
  });

  const currentGameTokens = computed((): string[] => {
    if (!latestGameData.value?.token) return [];
    return Object.keys(latestGameData.value.token);
  });

  const currentGameTokensWithRanks = computed(() => {
    if (!latestGameData.value?.token) return [];
    return Object.entries(latestGameData.value.token).map(([symbol, tokenData]) => ({
      symbol,
      rank: tokenData.s, // 当前排名
      priceChange: tokenData.p, // 价格变化
      ...tokenData
    }));
  });

  // 新增：获取Token排名映射
  const tokenRankings = computed(() => {
    if (!latestGameData.value?.token) return {};
    const rankings: Record<string, number> = {};
    Object.entries(latestGameData.value.token).forEach(([symbol, data]) => {
      rankings[symbol] = data.s;
    });
    return rankings;
  });

  // 新增：获取游戏时间信息
  const gameTimeInfo = computed(() => {
    if (!latestGameData.value?.time) return null;
    return {
      current: latestGameData.value.time.now,
      next: latestGameData.value.time.next,
      currentPhase: latestGameData.value.status
    };
  });

  // 新增：获取投注统计信息
  const bettingStats = computed(() => {
    if (!latestGameData.value?.groupDatas) return null;
    return {
      real: latestGameData.value.groupDatas.real,
      dummy: latestGameData.value.groupDatas.dummy
    };
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

  // ==================== WebSocket初始化（修复重复订阅问题） ====================
  const initializeWebSocket = () => {
    // 防止重复初始化
    if (isInitialized) {
      console.log('⚠️ [DEBUG] WebSocket已经初始化，跳过重复初始化');
      return;
    }

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
      // 1. 监听游戏数据更新 - 只使用Echo方式，避免重复
      console.log('🎮 [DEBUG] 开始监听 game-updates 频道...');
      gameUpdatesChannel = window.Echo.channel('game-updates');

      gameUpdatesChannel
        .subscribed(() => {
          console.log('✅ [DEBUG] 成功订阅 game-updates 频道');
        })
        .listen('.game.data.updated', (event: GameDataUpdateEvent) => {
          console.log('📨 [DEBUG] ========== 收到 game.data.updated 事件 ==========');
          console.log('📨 [DEBUG] 事件类型:', event.type);
          console.log('📨 [DEBUG] 游戏状态:', event.data.status);
          console.log('📨 [DEBUG] 轮次ID:', event.data.rdId);
          console.log('📨 [DEBUG] Token数据:', event.data.token);
          console.log('📨 [DEBUG] 完整数据:', event);
          console.log('📨 [DEBUG] 时间戳:', new Date().toLocaleString());
          console.log('📨 [DEBUG] ==========================================');

          // 更新游戏数据
          if (event.data) {
            latestGameData.value = { ...event.data };
            console.log('📨 [DEBUG] 已更新latestGameData - 状态:', event.data.status);
            console.log(
              '📨 [DEBUG] 当前Token排名:',
              Object.entries(event.data.token)
                .map(([symbol, data]) => `${symbol}: ${data.s}`)
                .join(', ')
            );
          }
        })
        .error((error: any) => {
          console.error('❌ [DEBUG] game-updates 频道错误:', error);
        });

      // 2. 监听预测数据更新 - 只使用Echo方式，避免重复
      console.log('🧠 [DEBUG] 开始监听 predictions 频道...');
      predictionsChannel = window.Echo.channel('predictions');

      predictionsChannel
        .subscribed(() => {
          console.log('✅ [DEBUG] 成功订阅 predictions 频道');
        })
        .listen('.prediction.updated', (rawEvent: any) => {
          console.log('🔮 [DEBUG] ========== 收到 prediction.updated 事件 ==========');
          console.log('🔮 [DEBUG] 原始事件数据:', rawEvent);
          console.log('🔮 [DEBUG] 数据类型:', typeof rawEvent.data);
          console.log('🔮 [DEBUG] 时间戳:', new Date().toLocaleString());
          console.log('🔮 [DEBUG] ==========================================');

          // 解析WebSocket数据
          try {
            let actualData: any;

            // WebSocket的data字段可能是JSON字符串，需要解析
            if (typeof rawEvent.data === 'string') {
              actualData = JSON.parse(rawEvent.data);
              console.log('🔮 [DEBUG] 解析后的数据:', actualData);
            } else {
              actualData = rawEvent.data;
            }

            // 检查数据格式并适配不同的数据结构
            let predictionArray: TokenAnalysis[] = [];
            let metaInfo: AnalysisMeta | null = null;

            if (Array.isArray(actualData)) {
              // 直接是数组格式的预测数据
              console.log('🔮 [DEBUG] 检测到数组格式的预测数据，长度:', actualData.length);
              predictionArray = actualData;

              // 从当前游戏数据或其他地方获取meta信息
              if (latestGameData.value?.rdId) {
                metaInfo = {
                  round_id: latestGameData.value.rdId,
                  status: latestGameData.value.status || 'unknown',
                  updated_at: new Date().toISOString(),
                  prediction_algorithm: 'websocket_direct'
                };
              }
            } else if (actualData && typeof actualData === 'object') {
              // 检查是否是包装格式 {success, data, meta}
              if (actualData.success !== undefined && actualData.data) {
                console.log('🔮 [DEBUG] 检测到包装格式的预测数据');
                console.log('🔮 [DEBUG] 预测成功:', actualData.success);
                console.log('🔮 [DEBUG] 预测Token数量:', actualData.data?.length || 0);
                console.log('🔮 [DEBUG] 轮次ID:', actualData.meta?.round_id);
                console.log('🔮 [DEBUG] 算法:', actualData.meta?.prediction_algorithm);

                if (actualData.success && Array.isArray(actualData.data)) {
                  predictionArray = actualData.data;
                  metaInfo = actualData.meta || null;
                }
              } else if (actualData.data && Array.isArray(actualData.data)) {
                // 可能是 {data: []} 格式
                console.log('🔮 [DEBUG] 检测到简单包装格式 {data: []}');
                predictionArray = actualData.data;
                metaInfo = actualData.meta || null;
              } else {
                console.warn('⚠️ [DEBUG] 未识别的预测数据格式:', actualData);
              }
            }

            // 更新预测数据
            if (predictionArray && Array.isArray(predictionArray) && predictionArray.length > 0) {
              currentAnalysis.value = [...predictionArray];
              analysisMeta.value = metaInfo;
              console.log('🔮 [DEBUG] 已更新预测分析数据，Token数量:', predictionArray.length);

              // 输出预测排名（如果有predicted_rank字段）
              const predictionsWithRank = predictionArray.filter((token) => token.predicted_rank !== undefined);
              if (predictionsWithRank.length > 0) {
                console.log(
                  '🔮 [DEBUG] 预测排名:',
                  predictionsWithRank.map((token) => `${token.symbol}: #${token.predicted_rank}`).join(', ')
                );
              } else {
                console.log('🔮 [DEBUG] 预测Token列表:', predictionArray.map((token) => token.symbol).join(', '));
              }
            } else {
              console.warn('⚠️ [DEBUG] 预测数据为空或格式不正确');
            }
          } catch (error) {
            console.error('❌ [DEBUG] 解析预测数据失败:', error);
            console.error('❌ [DEBUG] 原始数据:', rawEvent);
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

      isInitialized = true; // 标记为已初始化
      console.log('✅ [DEBUG] WebSocket连接成功建立');

      // 输出连接后的状态信息（仅用于调试，不再绑定额外事件）
      setTimeout(() => {
        console.log('🔍 [DEBUG] 连接建立后的状态检查:');
        if (window.Echo?.connector?.pusher) {
          console.log('🔍 [DEBUG] Pusher连接状态:', window.Echo.connector.pusher.connection.state);
          console.log('🔍 [DEBUG] 已订阅的频道:', Object.keys(window.Echo.connector.pusher.channels.channels));

          // 检查频道详情（仅输出信息，不再绑定事件）
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

  // ==================== 断开连接（完善清理机制） ====================
  const disconnectWebSocket = () => {
    console.log('🔌 [DEBUG] 断开WebSocket连接');

    if (gameUpdatesChannel) {
      try {
        window.Echo.leaveChannel('game-updates');
        gameUpdatesChannel = null;
        console.log('🔌 [DEBUG] 已断开 game-updates 频道');
      } catch (error) {
        console.error('❌ [DEBUG] 断开 game-updates 频道失败:', error);
      }
    }

    if (predictionsChannel) {
      try {
        window.Echo.leaveChannel('predictions');
        predictionsChannel = null;
        console.log('🔌 [DEBUG] 已断开 predictions 频道');
      } catch (error) {
        console.error('❌ [DEBUG] 断开 predictions 频道失败:', error);
      }
    }

    isInitialized = false; // 重置初始化状态
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

  // ==================== 初始化（防重复调用） ====================
  const initialize = async () => {
    if (isInitialized) {
      console.log('⚠️ [DEBUG] store已经初始化，跳过重复初始化');
      return;
    }

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
    console.log('🧪 [DEBUG] 初始化状态:', isInitialized);
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
          callbacks: Object.keys(channel.callbacks || {}),
          eventCallbacks: channel.callbacks
        });

        // 列出所有绑定的事件
        if (channel.callbacks) {
          Object.keys(channel.callbacks).forEach((eventName) => {
            console.log(
              `🧪 [DEBUG] 频道 ${channelName} 绑定的事件: ${eventName} (${channel.callbacks[eventName].length} listeners)`
            );
          });
        }
      });
    }
    console.log('🧪 [DEBUG] ========== 连接测试结束 ==========');
  };

  const testEventBinding = () => {
    console.log('🧪 [DEBUG] ========== 测试事件绑定 ==========');

    if (window.Echo?.connector?.pusher) {
      const channels = window.Echo.connector.pusher.channels.channels;

      // 测试绑定一个临时事件监听器
      if (channels['game-updates']) {
        console.log('🧪 [DEBUG] 测试绑定 test.event 到 game-updates 频道');
        channels['game-updates'].bind('test.event', (data: any) => {
          console.log('🧪 [DEBUG] 收到测试事件:', data);
        });
      }

      if (channels['predictions']) {
        console.log('🧪 [DEBUG] 测试绑定 test.prediction 到 predictions 频道');
        channels['predictions'].bind('test.prediction', (data: any) => {
          console.log('🧪 [DEBUG] 收到测试预测事件:', data);
        });
      }

      // 重新检查绑定的事件
      setTimeout(() => {
        Object.entries(channels).forEach(([channelName, channel]: [string, any]) => {
          console.log(`🧪 [DEBUG] 重新检查频道 ${channelName} 的绑定事件:`);
          if (channel.callbacks) {
            Object.keys(channel.callbacks).forEach((eventName) => {
              console.log(`🧪 [DEBUG] - ${eventName} (${channel.callbacks[eventName].length} listeners)`);
            });
          }
        });
      }, 1000);
    }

    console.log('🧪 [DEBUG] ========== 测试事件绑定结束 ==========');
  };

  return {
    // ==================== 状态导出 ====================
    websocketStatus,
    isConnected,

    // ==================== 数据状态导出 ====================
    currentAnalysis,
    analysisMeta,
    predictionHistory,
    latestGameData,
    analysisLoading,
    historyLoading,
    analysisError,
    historyError,

    // ==================== 计算属性导出 ====================
    hasCurrentAnalysis,
    totalHistoryRounds,
    currentRoundId,
    currentGameStatus,
    currentGameTokens,
    currentGameTokensWithRanks,
    tokenRankings,
    gameTimeInfo,
    bettingStats,
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
    testEventBinding,

    // ==================== 兼容性方法导出 ====================
    fetchCurrentAnalysis,
    fetchPredictionHistory,
    fetchInitialData,
    refreshAllPredictionData,
    clearErrors
  };
});
