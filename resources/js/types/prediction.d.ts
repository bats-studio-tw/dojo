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

// 游戏状态类型
export type GameStatus = 'waiting' | 'running' | 'finished' | 'paused';

// 代币排名信息
export interface TokenWithRank {
  symbol: string;
  rank: number;
  score: number;
  price?: number;
  change_24h?: number;
}

// 预测分析结果
export interface PredictionAnalysis {
  round_id: string;
  predictions: Array<{
    token: string;
    rank: number;
    score: number;
    confidence: number;
  }>;
  meta: {
    strategy: string;
    timestamp: string;
    accuracy?: number;
  };
}

// 分析元数据
export interface AnalysisMeta {
  strategy: string;
  timestamp: string;
  accuracy?: number;
  total_predictions?: number;
  last_updated?: string;
}

// 混合预测结果
export interface HybridPrediction {
  token: string;
  rank: number;
  score: number;
  momentum_score: number;
  elo_score: number;
  volume_score: number;
  confidence: number;
  timestamp: string;
}
