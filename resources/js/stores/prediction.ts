import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { predictionService } from '@/services/prediction.service';
import type {
  PredictionResultDTO,
  StrategyDTO,
  PredictionState,
  BacktestRequest,
  BacktestResult
} from '@/types/prediction';

export const usePredictionStore = defineStore('prediction', () => {
  // 状态
  const isLoading = ref(false);
  const results = ref<PredictionResultDTO[]>([]);
  const error = ref<string | null>(null);
  const currentStrategy = ref<string | null>(null);
  const availableStrategies = ref<StrategyDTO[]>([]);
  const backtestResults = ref<BacktestResult | null>(null);
  const predictionHistory = ref<PredictionResultDTO[]>([]);
  const isConnected = ref(false);

  // 计算属性
  const hasResults = computed(() => results.value.length > 0);
  const sortedResults = computed(() => [...results.value].sort((a, b) => a.predict_rank - b.predict_rank));
  const topPrediction = computed(() => results.value.find((r) => r.predict_rank === 1));
  const currentStrategyInfo = computed(() => availableStrategies.value.find((s) => s.tag === currentStrategy.value));

  // Actions
  /**
   * 执行预测
   */
  const runPrediction = async (strategyTag: string, symbols: string[]) => {
    isLoading.value = true;
    error.value = null;

    try {
      results.value = await predictionService.fetchPrediction(strategyTag, symbols);
      currentStrategy.value = strategyTag;
    } catch (err: any) {
      error.value = err.message || '预测执行失败';
      results.value = [];
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * 获取可用策略列表
   */
  const fetchStrategies = async () => {
    try {
      availableStrategies.value = await predictionService.getAvailableStrategies();
    } catch (err: any) {
      error.value = err.message || '获取策略列表失败';
      availableStrategies.value = [];
    }
  };

  /**
   * 执行回测
   */
  const runBacktest = async (request: BacktestRequest) => {
    isLoading.value = true;
    error.value = null;

    try {
      backtestResults.value = await predictionService.runBacktest(request);
    } catch (err: any) {
      error.value = err.message || '回测执行失败';
      backtestResults.value = null;
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * 获取预测历史
   */
  const fetchPredictionHistory = async (options?: {
    strategy_tag?: string;
    start_date?: string;
    end_date?: string;
    limit?: number;
  }) => {
    try {
      predictionHistory.value = await predictionService.getPredictionHistory(options);
    } catch (err: any) {
      error.value = err.message || '获取预测历史失败';
      predictionHistory.value = [];
    }
  };

  /**
   * 新增的 Action，用于处理 WebSocket 推送的实时预测数据
   */
  const addRealtimePrediction = (newPrediction: PredictionResultDTO) => {
    // 检查是否已存在相同的预测记录
    const exists = results.value.some((p) => p.id === newPrediction.id);
    if (!exists) {
      // 将新预测数据插入到数组顶部
      results.value.unshift(newPrediction);

      // 保持数组大小，只保留最近100条记录
      if (results.value.length > 100) {
        results.value = results.value.slice(0, 100);
      }

      console.log('🔮 实时预测数据已更新:', {
        symbol: newPrediction.token,
        rank: newPrediction.predict_rank,
        score: newPrediction.predict_score
      });
    }
  };

  /**
   * 设置 WebSocket 连接状态
   */
  const setConnectionStatus = (connected: boolean) => {
    isConnected.value = connected;
  };

  /**
   * 清除错误
   */
  const clearError = () => {
    error.value = null;
  };

  /**
   * 清除结果
   */
  const clearResults = () => {
    results.value = [];
    currentStrategy.value = null;
  };

  /**
   * 设置当前策略
   */
  const setCurrentStrategy = (strategyTag: string) => {
    currentStrategy.value = strategyTag;
  };

  return {
    // 状态
    isLoading,
    results,
    error,
    currentStrategy,
    availableStrategies,
    backtestResults,
    predictionHistory,
    isConnected,

    // 计算属性
    hasResults,
    sortedResults,
    topPrediction,
    currentStrategyInfo,

    // Actions
    runPrediction,
    fetchStrategies,
    runBacktest,
    fetchPredictionHistory,
    addRealtimePrediction,
    setConnectionStatus,
    clearError,
    clearResults,
    setCurrentStrategy
  };
});
