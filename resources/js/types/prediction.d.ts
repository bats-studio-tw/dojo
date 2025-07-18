// 预测系统相关类型定义

// 标准化API响应格式
export interface ActionResponse<T> {
  success: boolean;
  data: T;
  message: string;
  code: number;
}

// 预测结果DTO
export interface PredictionResultDTO {
  id: number;
  game_round_id: number;
  token: string;
  predict_rank: number;
  predict_score: number;
  elo_score: number;
  momentum_score: number;
  volume_score: number;
  norm_elo: number;
  norm_momentum: number;
  norm_volume: number;
  used_weights: Record<string, number>;
  used_normalization: Record<string, string>;
  strategy_tag: string;
  config_snapshot?: Record<string, any>;
  created_at: string;
}

// 策略选项DTO
export interface StrategyDTO {
  tag: string;
  name: string;
  description: string;
  weights?: Record<string, number>;
  normalization?: Record<string, string>;
}

// 预测请求参数
export interface PredictionRequest {
  strategy_tag: string;
  symbols: string[];
}

// 预测状态
export interface PredictionState {
  isLoading: boolean;
  results: PredictionResultDTO[];
  error: string | null;
  currentStrategy: string | null;
  availableStrategies: StrategyDTO[];
}

// A/B測試配置
export interface ABTestConfig {
  id: number;
  name: string;
  description?: string;
  strategies: Record<string, string>; // {strategy_name => strategy_tag}
  traffic_distribution: Record<string, number>; // {strategy_name => percentage}
  start_date: string;
  end_date: string;
  status: 'active' | 'stopped' | 'completed';
  created_by?: number;
  creator?: {
    id: number;
    name: string;
  };
  created_at: string;
  updated_at: string;
}

// A/B測試結果
export interface ABTestResult {
  id: number;
  ab_test_id: number;
  strategy: string;
  prediction_data: any;
  actual_result?: any;
  user_id?: number;
  round_id?: string;
  is_correct: boolean;
  created_at: string;
  updated_at: string;
}

// A/B測試報告結果
export interface ABTestReportResult {
  strategy: string;
  total_predictions: number;
  correct_predictions: number;
  accuracy_rate: number;
  unique_users: number;
  unique_rounds: number;
  traffic_percentage: number;
  daily_accuracy: Record<string, number>;
  hourly_accuracy: Record<string, number>;
}

// A/B測試報告摘要
export interface ABTestReportSummary {
  total_predictions: number;
  total_correct: number;
  overall_accuracy: number;
  best_strategy: string;
  best_accuracy: number;
  worst_strategy: string;
  worst_accuracy: number;
  strategy_count: number;
}

// A/B測試報告
export interface ABTestReport {
  test_config: ABTestConfig;
  results: ABTestReportResult[];
  summary: ABTestReportSummary;
  period: {
    start_date?: string;
    end_date?: string;
  };
}
