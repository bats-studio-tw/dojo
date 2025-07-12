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

// 回测结果
export interface BacktestResult {
  win_rate: number;
  profit_rate: number;
  sharpe_ratio: number;
  max_drawdown: number;
  max_profit: number;
  max_loss: number;
  avg_profit_loss_ratio: number;
  total_rounds: number;
  strategy_tag: string;
  config_snapshot: Record<string, any>;
}

// 回测请求参数
export interface BacktestRequest {
  strategy_tag: string;
  start_date?: string;
  end_date?: string;
  rounds?: number;
}
