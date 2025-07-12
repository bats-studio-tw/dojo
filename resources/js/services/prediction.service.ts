import axios from 'axios';
import type {
  ActionResponse,
  PredictionResultDTO,
  StrategyDTO,
  PredictionRequest,
  BacktestRequest,
  BacktestResult
} from '@/types/prediction';

// 创建预测API客户端
const predictionApi = axios.create({
  baseURL: '/api/v2',
  timeout: 30000, // 预测可能需要更长时间
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
});

// 请求拦截器 - 添加CSRF令牌
predictionApi.interceptors.request.use(
  (config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
      config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// 响应拦截器 - 统一错误处理
predictionApi.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    console.error('预测API错误:', error);
    if (error.response?.status === 401) {
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export const predictionService = {
  /**
   * 执行预测
   * @param strategyTag 策略标签
   * @param symbols 代币符号数组
   * @returns 预测结果数组
   */
  async fetchPrediction(strategyTag: string, symbols: string[]): Promise<PredictionResultDTO[]> {
    try {
      const response = await predictionApi.post<ActionResponse<PredictionResultDTO[]>>('/predictions', {
        strategy_tag: strategyTag,
        symbols: symbols
      });

      if (response.data.success) {
        return response.data.data;
      }

      throw new Error(response.data.message || '预测执行失败');
    } catch (error) {
      console.error('执行预测失败:', error);
      throw error;
    }
  },

  /**
   * 获取可用策略列表
   * @returns 策略列表
   */
  async getAvailableStrategies(): Promise<StrategyDTO[]> {
    try {
      const response = await predictionApi.get<ActionResponse<StrategyDTO[]>>('/strategies');

      if (response.data.success) {
        return response.data.data;
      }

      throw new Error(response.data.message || '获取策略列表失败');
    } catch (error) {
      console.error('获取策略列表失败:', error);
      throw error;
    }
  },

  /**
   * 执行回测
   * @param request 回测请求参数
   * @returns 回测结果
   */
  async runBacktest(request: BacktestRequest): Promise<BacktestResult> {
    try {
      const response = await predictionApi.post<ActionResponse<BacktestResult>>('/backtest', request);

      if (response.data.success) {
        return response.data.data;
      }

      throw new Error(response.data.message || '回测执行失败');
    } catch (error) {
      console.error('执行回测失败:', error);
      throw error;
    }
  },

  /**
   * 获取预测历史
   * @param options 查询选项
   * @returns 历史预测结果
   */
  async getPredictionHistory(options?: {
    strategy_tag?: string;
    start_date?: string;
    end_date?: string;
    limit?: number;
  }): Promise<PredictionResultDTO[]> {
    try {
      const response = await predictionApi.get<ActionResponse<PredictionResultDTO[]>>('/predictions/history', {
        params: options
      });

      if (response.data.success) {
        return response.data.data;
      }

      throw new Error(response.data.message || '获取预测历史失败');
    } catch (error) {
      console.error('获取预测历史失败:', error);
      throw error;
    }
  },

  /**
   * 获取策略性能统计
   * @param strategyTag 策略标签
   * @param days 天数限制
   * @returns 性能统计数据
   */
  async getStrategyPerformance(
    strategyTag: string,
    days?: number
  ): Promise<{
    total_predictions: number;
    accuracy_rate: number;
    avg_score: number;
    best_performance: number;
    worst_performance: number;
  }> {
    try {
      const response = await predictionApi.get<ActionResponse<any>>('/strategies/performance', {
        params: { strategy_tag: strategyTag, days }
      });

      if (response.data.success) {
        return response.data.data;
      }

      throw new Error(response.data.message || '获取策略性能失败');
    } catch (error) {
      console.error('获取策略性能失败:', error);
      throw error;
    }
  }
};
