export interface StrategyValidation {
  matches: StrategyMatch[];
  total_matched: number;
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
  win_rate: number;
  sample_count: number;
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
