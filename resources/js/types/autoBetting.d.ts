export interface StrategyValidation {
  matches: StrategyMatch[];
  total_matched: number;
  estimated_profit: number;
  risk_level: 'low' | 'medium' | 'high';
  success_probability: number;
  balance_sufficient: boolean;
  required_balance: number;
  actual_balance: number;
}

export interface StrategyMatch {
  symbol: string;
  predicted_rank: number;
  confidence: number;
  score: number;
  bet_amount: number;
  expected_return: number;
  historical_accuracy: number;
  sample_count: number;
}

export interface BacktestResults {
  total_rounds: number;
  win_rate: number;
  total_profit: number;
  avg_profit_per_round: number;
  max_drawdown: number;
  sharp_ratio: number;
  best_strategy: string;
}

export interface PredictionAnalysis {
  predictions: any[];
  meta: {
    round_id: string;
    status: string;
    updated_at: string;
  } | null;
}

export interface ConfigSyncStatus {
  type: 'success' | 'error' | 'info';
  message: string;
}
