import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/utils/api';

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
  round_id: string | null | undefined;
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

// Hybrid预测数据接口
export interface HybridPrediction {
  symbol: string;
  predicted_rank: number;
  mom_score?: number;
  elo_prob?: number;
  final_score?: number;
  confidence?: number;
  [key: string]: any; // 允许其他字段
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

  // Hybrid预测数据状态管理
  const hybridPredictions = ref<any[]>([]);
  const hybridAnalysisMeta = ref<AnalysisMeta | null>(null);

  // 加载状态（空实现）
  const analysisLoading = ref(false);
  const historyLoading = ref(false);
  const hybridAnalysisLoading = ref(false);

  // 错误状态（空实现）
  const analysisError = ref<string | null>(null);
  const historyError = ref<string | null>(null);
  const hybridAnalysisError = ref<string | null>(null);

  // ==================== 计算属性 ====================
  const isConnected = computed(() => websocketStatus.value.status === 'connected');

  // ==================== 为了兼容性添加的计算属性 ====================
  const hasCurrentAnalysis = computed(() => currentAnalysis.value.length > 0);
  const totalHistoryRounds = computed(() => predictionHistory.value.length);
  const hasHybridPredictions = computed(() => hybridPredictions.value.length > 0);

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

  // ==================== API调用方法 ====================
  const fetchCurrentAnalysis = async () => {
    analysisLoading.value = true;
    analysisError.value = null;

    try {
      console.log('🔄 开始获取当前分析数据...');
      const response = await api.get('/v2/predictions/current-analysis');
      console.log('📡 API响应状态:', response.status);
      console.log('📡 API响应数据:', response.data);

      if (response.data.success) {
        const rawData = response.data.data || [];
        console.log('📊 原始数据长度:', rawData.length);
        console.log('📊 原始数据样本:', rawData.slice(0, 3));

        // 数据映射：将API返回的数据转换为TokenAnalysis格式
        const mappedData = rawData.map((item: any) => ({
          symbol: item.symbol,
          name: item.symbol, // 使用symbol作为name
          change_5m: null,
          change_1h: null,
          change_4h: null,
          change_24h: null,
          volume_24h: '0',
          market_cap: null,
          logo: null,
          prediction_score: item.prediction_score || 0,
          win_rate: 0, // 默认值
          top3_rate: 0, // 默认值
          avg_rank: 3, // 默认值
          total_games: 0, // 默认值
          wins: 0, // 默认值
          top3: 0, // 默认值
          predicted_rank: item.predicted_rank || 999,
          // 映射可选字段
          absolute_score: item.elo_score || 0,
          relative_score: item.h2h_score || 0,
          h2h_score: item.h2h_score || 0,
          risk_adjusted_score: item.risk_adjusted_score || 0,
          rank_confidence: item.confidence || 0,
          final_prediction_score: item.volume_score || 0,
          market_momentum_score: item.momentum_score || 0,
          value_stddev: item.value_stddev || 0,
          recent_avg_value: item.recent_avg_value || 0,
          avg_value: item.avg_value || 0
        }));

        console.log('🔄 映射后数据长度:', mappedData.length);
        console.log('🔄 映射后数据样本:', mappedData.slice(0, 3));
        console.log('🔄 设置currentAnalysis.value:', mappedData);

        currentAnalysis.value = mappedData;
        analysisMeta.value = response.data.meta || null;

        console.log('✅ 数据设置完成，currentAnalysis.value长度:', currentAnalysis.value.length);
        console.log('✅ analysisMeta.value:', analysisMeta.value);
      } else {
        console.error('❌ API返回失败:', response.data.message);
        throw new Error(response.data.message || '获取当前分析数据失败');
      }
    } catch (error) {
      console.error('❌ 获取当前分析数据失败:', error);
      analysisError.value = error instanceof Error ? error.message : String(error);
      throw error;
    } finally {
      analysisLoading.value = false;
    }
  };

  const fetchPredictionHistory = async () => {
    historyLoading.value = true;
    historyError.value = null;

    try {
      console.log('🔄 获取预测历史数据...');
      const response = await api.get('/v2/predictions/history');
      if (response.data.success) {
        // 更新store中的预测历史数据
        predictionHistory.value = response.data.data || [];
      } else {
        window.$message?.error(response.data.message || '获取预测历史数据失败');
      }
    } catch (error) {
      console.error('❌ 获取预测历史数据失败:', error);
      historyError.value = error instanceof Error ? error.message : String(error);
      window.$message?.error('获取预测历史数据失败');
      // 不抛出错误，让调用者可以继续运行
    } finally {
      historyLoading.value = false;
    }
  };

  const fetchHybridAnalysis = async () => {
    hybridAnalysisLoading.value = true;
    hybridAnalysisError.value = null;

    try {
      const response = await api.get('/v2/predictions/hybrid');
      if (response.data.success) {
        const rawData = response.data.data || [];

        // 数据验证和去重
        const validatedPredictions = rawData.filter((prediction: any) => {
          // 验证必要字段
          return (
            prediction &&
            typeof prediction === 'object' &&
            prediction.symbol &&
            typeof prediction.symbol === 'string' &&
            typeof prediction.predicted_rank === 'number' &&
            prediction.predicted_rank > 0
          );
        });

        // 基于symbol去重，保留排名最高的记录
        const uniquePredictions = new Map<string, HybridPrediction>();
        validatedPredictions.forEach((prediction: HybridPrediction) => {
          const symbol = prediction.symbol.toUpperCase();
          if (
            !uniquePredictions.has(symbol) ||
            prediction.predicted_rank < uniquePredictions.get(symbol)!.predicted_rank
          ) {
            uniquePredictions.set(symbol, prediction);
          }
        });

        const finalPredictions = Array.from(uniquePredictions.values());

        if (finalPredictions.length > 0) {
          hybridPredictions.value = finalPredictions;
          hybridAnalysisMeta.value = response.data.meta || null;
        } else {
          hybridPredictions.value = [];
          hybridAnalysisMeta.value = null;
        }
      } else {
        hybridPredictions.value = [];
        hybridAnalysisMeta.value = null;
      }
    } catch (error) {
      console.error('❌ 获取Hybrid分析数据失败:', error);
      hybridAnalysisError.value = error instanceof Error ? error.message : String(error);
      hybridPredictions.value = [];
      hybridAnalysisMeta.value = null;
      // 不抛出错误，让调用者可以继续运行
    } finally {
      hybridAnalysisLoading.value = false;
    }
  };

  const fetchInitialData = async () => {
    await Promise.all([
      fetchCurrentAnalysis().catch(console.error),
      fetchPredictionHistory().catch(console.error),
      fetchHybridAnalysis().catch(console.error)
    ]);
  };

  const refreshAllPredictionData = async () => {
    await fetchInitialData();
  };

  const clearErrors = () => {
    analysisError.value = null;
    historyError.value = null;
    hybridAnalysisError.value = null;
  };

  // ==================== 实时数据更新方法 ====================

  /**
   * 更新游戏数据
   */
  const updateGameData = (gameData: GameData) => {
    latestGameData.value = gameData;

    // 同时更新分析元数据
    if (gameData.rdId) {
      const { status, ...restGameData } = gameData;
      analysisMeta.value = {
        round_id: gameData.rdId,
        status: status || 'unknown',
        updated_at: new Date().toISOString(),
        ...restGameData
      };
    }
  };

  /**
   * 更新预测数据
   */
  const updatePredictionData = (predictionData: any) => {
    if (!currentAnalysis.value) {
      currentAnalysis.value = [];
    }

    // 查找并更新现有预测，或添加新预测
    const existingIndex = currentAnalysis.value.findIndex(
      (item: TokenAnalysis) => item.symbol === predictionData.token
    );

    if (existingIndex >= 0) {
      // 更新现有预测
      currentAnalysis.value[existingIndex] = {
        ...currentAnalysis.value[existingIndex],
        predicted_rank: predictionData.predict_rank,
        prediction_score: predictionData.predict_score,
        rank_confidence: predictionData.confidence || 0,
        // 使用可选字段，如果存在的话
        ...(predictionData.elo_score && { absolute_score: predictionData.elo_score }),
        ...(predictionData.momentum_score && { market_momentum_score: predictionData.momentum_score }),
        ...(predictionData.volume_score && { final_prediction_score: predictionData.volume_score })
      };
    } else {
      // 添加新预测
      currentAnalysis.value.push({
        symbol: predictionData.token,
        name: predictionData.token,
        predicted_rank: predictionData.predict_rank,
        prediction_score: predictionData.predict_score,
        rank_confidence: predictionData.confidence || 0,
        win_rate: 0,
        top3_rate: 0,
        avg_rank: 3,
        total_games: 0,
        wins: 0,
        top3: 0,
        change_5m: null,
        change_1h: null,
        change_4h: null,
        change_24h: null,
        volume_24h: '0',
        market_cap: null,
        logo: null,
        // 使用可选字段，如果存在的话
        ...(predictionData.elo_score && { absolute_score: predictionData.elo_score }),
        ...(predictionData.momentum_score && { market_momentum_score: predictionData.momentum_score }),
        ...(predictionData.volume_score && { final_prediction_score: predictionData.volume_score })
      });
    }
  };

  /**
   * 更新Hybrid预测数据
   */
  const updateHybridPredictions = (data: any[], meta?: any) => {
    if (!Array.isArray(data)) {
      console.warn('⚠️ Hybrid预测数据格式错误，期望数组');
      return;
    }

    // 数据验证和去重
    const validatedPredictions = data.filter((prediction: any) => {
      return (
        prediction &&
        typeof prediction === 'object' &&
        prediction.symbol &&
        typeof prediction.symbol === 'string' &&
        typeof prediction.predicted_rank === 'number' &&
        prediction.predicted_rank > 0
      );
    });

    // 基于symbol去重，保留排名最高的记录
    const uniquePredictions = new Map<string, HybridPrediction>();
    validatedPredictions.forEach((prediction: HybridPrediction) => {
      const symbol = prediction.symbol.toUpperCase();
      if (!uniquePredictions.has(symbol) || prediction.predicted_rank < uniquePredictions.get(symbol)!.predicted_rank) {
        uniquePredictions.set(symbol, prediction);
      }
    });

    const finalPredictions = Array.from(uniquePredictions.values());

    if (finalPredictions.length > 0) {
      hybridPredictions.value = finalPredictions;
      hybridAnalysisMeta.value = {
        round_id: meta?.round_id || currentRoundId.value || '',
        status: meta?.status || 'unknown',
        updated_at: new Date().toISOString(),
        ...meta
      };
    }
  };

  // ==================== 初始化 ====================
  const initialize = async () => {
    // WebSocket初始化已移至独立的WebSocket管理器
  };

  // ==================== 清理 ====================
  const cleanup = () => {
    // WebSocket清理已移至独立的WebSocket管理器
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

    // ==================== Hybrid预测数据状态导出 ====================
    hybridPredictions,
    hybridAnalysisMeta,
    hybridAnalysisLoading,
    hybridAnalysisError,

    // ==================== 计算属性导出 ====================
    hasCurrentAnalysis,
    totalHistoryRounds,
    hasHybridPredictions,
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
    initialize,
    cleanup,

    // ==================== 兼容性方法导出 ====================
    fetchCurrentAnalysis,
    fetchPredictionHistory,
    fetchHybridAnalysis,
    fetchInitialData,
    refreshAllPredictionData,
    clearErrors,

    // ==================== 实时数据更新方法导出 ====================
    updateGameData,
    updatePredictionData,
    updateHybridPredictions
  };
});
