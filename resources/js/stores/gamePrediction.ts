import { defineStore } from 'pinia';
import { ref, computed, triggerRef } from 'vue';
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

  // 🆕 新增计算属性 - 提供更方便的数据访问
  const currentRoundId = computed(() => {
    // 优先从游戏数据获取轮次ID
    if (latestGameData.value?.rdId) {
      return latestGameData.value.rdId;
    }
    // 备用：从分析元数据获取
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

  // 提供兼容的数据结构 - 为了向后兼容
  const currentAnalysisFormatted = computed(() => {
    return {
      predictions: currentAnalysis.value,
      meta: analysisMeta.value,
      game_data: latestGameData.value,
      round_id: currentRoundId.value,
      status: currentGameStatus.value
    };
  });

  // 检查是否可以下注
  const canBet = computed(() => {
    const status = currentGameStatus.value;
    return status === 'bet';
  });

  // 检查是否已结算
  const isSettled = computed(() => {
    const status = currentGameStatus.value;
    return status === 'settled';
  });

  // 检查是否正在结算
  const isSettling = computed(() => {
    const status = currentGameStatus.value;
    return status === 'settling';
  });

  // 检查是否已锁定
  const isLocked = computed(() => {
    const status = currentGameStatus.value;
    return status === 'lock';
  });

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
        console.log('🎮 数据类型:', data.type);
        console.log('🎮 游戏数据:', data.data);
        console.log('🎮 轮次ID:', data.data?.rdId);
        console.log('🎮 游戏状态:', data.data?.status);

        // 强制响应式更新 - 使用深拷贝确保Vue检测到变化
        latestGameData.value = { ...data.data };

        console.log('✅ 已更新latestGameData:', {
          rdId: latestGameData.value?.rdId,
          status: latestGameData.value?.status,
          tokenCount: latestGameData.value?.token ? Object.keys(latestGameData.value.token).length : 0
        });

        // 🔥 强制触发Vue响应式更新
        triggerRef(latestGameData);
        console.log(
          '🔥 已强制触发响应式更新 - 当前轮次:',
          latestGameData.value?.rdId,
          '状态:',
          latestGameData.value?.status
        );

        // 如果是结算数据，刷新预测历史
        if (data.type === 'settlement') {
          console.log('📊 检测到结算数据，刷新预测历史');
          fetchPredictionHistory();
        }
      });

      // 监听预测数据更新 - 实时更新current-analysis
      console.log('🔗 设置predictions频道监听器...');
      predictionsChannel = window.Echo.channel('predictions')
        .listen('prediction.updated', (receivedData: any) => {
          console.log('🧠 收到预测数据更新（原始）:', receivedData);

          try {
            // 解析数据 - WebSocket可能发送字符串格式的data
            let data = receivedData;
            if (typeof receivedData === 'string') {
              data = JSON.parse(receivedData);
            } else if (receivedData.data && typeof receivedData.data === 'string') {
              // 如果data字段是字符串，需要解析
              data = JSON.parse(receivedData.data);
            }

            console.log('🧠 解析后的预测数据:', data);
            console.log('🧠 数据结构分析:', {
              hasSuccess: !!data.success,
              hasData: !!data.data,
              hasMetaData: !!data.meta,
              dataIsArray: Array.isArray(data.data),
              dataLength: data.data?.length,
              dataKeys: data.data ? Object.keys(data.data) : null
            });

            // 检查是否是与current-analysis API相同的数据结构
            if (data.success && data.data && data.meta) {
              console.log('🧠 匹配完整数据结构，开始处理...');

              // 新的完整数据结构（与current-analysis API一致）
              currentAnalysis.value = [...data.data]; // 使用展开运算符确保响应式更新
              analysisMeta.value = { ...data.meta };

              // 🔥 强制触发Vue响应式更新
              triggerRef(currentAnalysis);
              triggerRef(analysisMeta);

              console.log(`✅ 已更新预测分析数据（完整结构）: ${data.data.length} 个代币`);
              console.log('📊 更新的轮次信息:', data.meta.round_id, '状态:', data.meta.status);
              console.log('📊 更新后的currentAnalysis数量:', currentAnalysis.value.length);
              console.log('📊 更新后的第一个代币:', currentAnalysis.value[0]?.symbol || '无');
            } else if (data.data && Array.isArray(data.data)) {
              console.log('🧠 匹配兼容数据结构，开始处理...');
              // 旧的简单数据结构（向后兼容）
              currentAnalysis.value = [...data.data];

              // 更新分析元数据
              if (data.round_id || data.meta?.round_id) {
                analysisMeta.value = {
                  round_id: data.round_id || data.meta?.round_id,
                  status: data.meta?.status || analysisMeta.value?.status || 'unknown',
                  updated_at: data.timestamp || data.meta?.timestamp || new Date().toISOString(),
                  source: 'websocket'
                };
              }

              // 🔥 强制触发Vue响应式更新
              triggerRef(currentAnalysis);
              triggerRef(analysisMeta);

              console.log(`✅ 已更新预测分析数据（兼容模式）: ${data.data.length} 个代币`);
              console.log('📊 更新后的currentAnalysis数量:', currentAnalysis.value.length);
              console.log('📊 更新后的第一个代币:', currentAnalysis.value[0]?.symbol || '无');
            } else {
              console.warn('⚠️ 收到无效的预测数据格式:', data);
              console.warn('⚠️ 预期格式: {success: true, data: Array, meta: Object} 或 {data: Array}');
              console.warn('⚠️ 实际数据结构:', Object.keys(data));
              console.warn('⚠️ 原始数据:', receivedData);
            }
          } catch (error) {
            console.error('❌ 解析预测数据失败:', error);
            console.error('❌ 原始数据:', receivedData);
          }
        })
        .subscribed(() => {
          console.log('✅ 已成功订阅predictions频道');
        })
        .error((error: any) => {
          console.error('❌ predictions频道订阅错误:', error);
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

      // 额外的调试信息
      console.log('🔍 WebSocket调试信息:');
      console.log('  - Echo实例:', window.Echo);
      console.log('  - gameUpdatesChannel:', gameUpdatesChannel);
      console.log('  - predictionsChannel:', predictionsChannel);
      console.log('  - Pusher连接状态:', window.Echo?.connector?.pusher?.connection?.state);

      // 监听Pusher原始事件用于调试
      if (window.Echo?.connector?.pusher) {
        console.log('🔍 添加Pusher原始事件监听器用于调试...');
        window.Echo.connector.pusher.bind('pusher:subscription_succeeded', (data: any) => {
          console.log('🔍 Pusher频道订阅成功:', data);
        });

        // 监听所有predictions频道的原始事件
        const pusherChannel = window.Echo.connector.pusher.channels.channels['predictions'];
        if (pusherChannel) {
          console.log('🔍 找到predictions频道，添加原始事件监听...');
          pusherChannel.bind('prediction.updated', (data: any) => {
            console.log('🔍 Pusher原始事件 prediction.updated:', data);
          });
        }
      }
    } catch (error) {
      console.error('❌ WebSocket连接失败:', error);
      websocketStatus.value = {
        ...websocketStatus.value,
        status: 'error',
        message: '连接失败',
        lastError: error instanceof Error ? error.message : String(error)
      };

      // 尝试重连
      if (websocketStatus.value.reconnectAttempts < maxReconnectAttempts) {
        websocketStatus.value.reconnectAttempts++;
        const delay = Math.min(1000 * Math.pow(2, websocketStatus.value.reconnectAttempts), 30000); // 指数退避，最大30秒

        websocketStatus.value = {
          ...websocketStatus.value,
          status: 'disconnected',
          message: `${delay / 1000}秒后尝试第${websocketStatus.value.reconnectAttempts}次重连...`
        };

        console.log(`🔄 计划在${delay / 1000}秒后进行第${websocketStatus.value.reconnectAttempts}次重连`);

        reconnectTimer = window.setTimeout(() => {
          initializeWebSocket();
        }, delay);
      } else {
        websocketStatus.value = {
          ...websocketStatus.value,
          status: 'error',
          message: `重连失败，已达到最大重连次数(${maxReconnectAttempts})`
        };
      }
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
