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

export const useGamePredictionStore = defineStore('gamePrediction', () => {
  // 状态
  const currentAnalysis = ref<TokenAnalysis[]>([]);
  const analysisMeta = ref<AnalysisMeta | null>(null);
  const predictionHistory = ref<PredictionHistoryRound[]>([]);

  // 加载状态
  const analysisLoading = ref(false);
  const historyLoading = ref(false);

  // 错误状态
  const analysisError = ref<string | null>(null);
  const historyError = ref<string | null>(null);

  // 计算属性 - 当前轮次是否有有效数据
  const hasCurrentAnalysis = computed(() => currentAnalysis.value.length > 0);

  // 计算属性 - 历史数据总数
  const totalHistoryRounds = computed(() => predictionHistory.value.length);

  // 获取当前分析数据
  const fetchCurrentAnalysis = async () => {
    analysisLoading.value = true;
    analysisError.value = null;

    try {
      const response = await api.get('/game/current-analysis');
      if (response.data.success) {
        currentAnalysis.value = response.data.data || [];
        analysisMeta.value = response.data.meta || null;
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

  // 获取预测历史数据
  const fetchPredictionHistory = async () => {
    historyLoading.value = true;
    historyError.value = null;

    try {
      const response = await api.get('/game/prediction-history');
      if (response.data.success) {
        predictionHistory.value = response.data.data || [];
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

  // 刷新所有预测数据
  const refreshAllPredictionData = async () => {
    await Promise.all([fetchCurrentAnalysis(), fetchPredictionHistory()]);
  };

  // 清除错误状态
  const clearErrors = () => {
    analysisError.value = null;
    historyError.value = null;
  };

  return {
    // 状态
    currentAnalysis,
    analysisMeta,
    predictionHistory,

    // 加载状态
    analysisLoading,
    historyLoading,

    // 错误状态
    analysisError,
    historyError,

    // 计算属性
    hasCurrentAnalysis,
    totalHistoryRounds,

    // 方法
    fetchCurrentAnalysis,
    fetchPredictionHistory,
    refreshAllPredictionData,
    clearErrors
  };
});
