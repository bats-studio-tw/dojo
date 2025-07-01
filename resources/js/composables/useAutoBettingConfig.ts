import { ref, reactive } from 'vue';
import { autoBettingApi } from '@/utils/api';

/**
 * 🔧 数据单位统一说明 (2025-01-06)
 *
 * 为提高代码可读性和一致性，所有百分比相关的配置项已统一为0-100数值：
 * - historical_accuracy_threshold: 0-1 → 0-100 (如 0.12 → 12)
 * - kelly_fraction: 0-1 → 0-100 (如 0.25 → 25)
 * - min_win_rate_threshold: 0-1 → 0-100 (如 0.58 → 58)
 * - min_top3_rate_threshold: 0-1 → 0-100 (如 0.7 → 70)
 * - max_volatility_threshold: 0-1 → 0-100 (如 0.8 → 80)
 *
 * 在实际计算中使用时，需要除以100转换为小数进行数学运算。
 */

export interface AutoBettingConfig {
  jwt_token: string;
  bet_amount: number;
  daily_stop_loss_percentage: number;
  confidence_threshold: number;
  score_gap_threshold: number;
  min_total_games: number;
  strategy: 'single_bet' | 'multi_bet' | 'hedge_bet' | 'rank_betting';
  historical_accuracy_threshold: number;
  min_sample_count: number;
  max_bet_percentage: number;
  enable_trend_analysis: boolean;
  enable_volume_filter: boolean;
  stop_loss_consecutive: number;
  enable_kelly_criterion: boolean;
  kelly_fraction: number;
  enable_martingale: boolean;
  martingale_multiplier: number;
  max_martingale_steps: number;
  enable_time_filter: boolean;
  allowed_hours_start: number;
  allowed_hours_end: number;
  enable_volatility_filter: boolean;
  max_volatility_threshold: number;
  min_liquidity_threshold: number;
  is_active: boolean;
  rank_betting_enabled_ranks: number[];
  rank_betting_amount_per_rank: number;
  rank_betting_different_amounts: boolean;
  rank_betting_rank1_amount: number;
  rank_betting_rank2_amount: number;
  rank_betting_rank3_amount: number;
  rank_betting_max_ranks: number;

  // 🆕 新增历史表现过滤器
  enable_win_rate_filter: boolean;
  min_win_rate_threshold: number;
  enable_top3_rate_filter: boolean;
  min_top3_rate_threshold: number;
  enable_avg_rank_filter: boolean;
  max_avg_rank_threshold: number;
  enable_stability_filter: boolean;
  max_stability_threshold: number;

  // 🆕 新增评分过滤器
  enable_absolute_score_filter: boolean;
  min_absolute_score_threshold: number;
  enable_relative_score_filter: boolean;
  min_relative_score_threshold: number;
  enable_h2h_score_filter: boolean;
  min_h2h_score_threshold: number;

  // 🆕 新增市场动态过滤器
  enable_change_5m_filter: boolean;
  min_change_5m_threshold: number;
  max_change_5m_threshold: number;
  enable_change_1h_filter: boolean;
  min_change_1h_threshold: number;
  max_change_1h_threshold: number;
  enable_change_4h_filter: boolean;
  min_change_4h_threshold: number;
  max_change_4h_threshold: number;
  enable_change_24h_filter: boolean;
  min_change_24h_threshold: number;
  max_change_24h_threshold: number;
}

/**
 * 优化后的默认配置 - 基于实际市场数据
 * 🔧 所有百分比相关配置项统一使用0-100数值
 */
export const optimizedDefaultConfig: Omit<AutoBettingConfig, 'jwt_token'> = {
  // 🎯 基础交易参数 - 基于实际数据优化
  bet_amount: 200,
  daily_stop_loss_percentage: 15,
  confidence_threshold: 80, // 降低至80%，适应NEAR 82%的情况
  score_gap_threshold: 60, // 降低至60，更宽松的分数要求
  min_total_games: 1, // 降低至1，适应样本不足现实
  historical_accuracy_threshold: 10, // 10%，更宽松的历史准确率要求 (统一为0-100)
  min_sample_count: 1, // 降低至1，适应样本不足现实
  max_bet_percentage: 15,
  strategy: 'single_bet' as const,

  // 🔧 高级功能设置
  enable_trend_analysis: false, // 默认关闭，避免过度过滤
  enable_volume_filter: false, // 默认关闭，交易量数据不稳定
  stop_loss_consecutive: 5, // 适中的止损容忍度
  enable_kelly_criterion: false,
  kelly_fraction: 25, // 25% (统一为0-100)
  enable_martingale: false,
  martingale_multiplier: 2.0,
  max_martingale_steps: 3,
  enable_time_filter: false,
  allowed_hours_start: 9,
  allowed_hours_end: 21,
  enable_volatility_filter: false,
  max_volatility_threshold: 80, // 80% (统一为0-100)
  min_liquidity_threshold: 1000000,
  is_active: false,

  // 🎯 排名下注设置
  rank_betting_enabled_ranks: [1, 2, 3],
  rank_betting_amount_per_rank: 200,
  rank_betting_different_amounts: false,
  rank_betting_rank1_amount: 200,
  rank_betting_rank2_amount: 200,
  rank_betting_rank3_amount: 200,
  rank_betting_max_ranks: 5,

  // 📊 历史表现过滤器 - 基于实际数据优化
  enable_win_rate_filter: false, // 默认关闭，胜率数据经常不足
  min_win_rate_threshold: 12, // 12%，适应实际数据 (统一为0-100)
  enable_top3_rate_filter: false, // 🔧 修复：默认关闭，避免过度过滤
  min_top3_rate_threshold: 58, // 58%，参考ETH 58.8% (统一为0-100)
  enable_avg_rank_filter: false, // 默认关闭
  max_avg_rank_threshold: 4.0,
  enable_stability_filter: false, // 默认关闭
  max_stability_threshold: 2.5,

  // 🎯 评分过滤器 - 基于实际分数范围
  enable_absolute_score_filter: false, // 🔧 修复：默认关闭，避免过度过滤
  min_absolute_score_threshold: 58.0, // 58，参考NXPC 59.2级别
  enable_relative_score_filter: false, // 默认关闭
  min_relative_score_threshold: 52.0, // 52，参考NXPC 53.2级别
  enable_h2h_score_filter: false, // 默认关闭
  min_h2h_score_threshold: 52.0,

  // 📈 市场动态过滤器 - 基于实际波动范围
  enable_change_5m_filter: false, // 默认关闭，数据经常缺失
  min_change_5m_threshold: -1.0, // 适应实际波动
  max_change_5m_threshold: 1.0,
  enable_change_1h_filter: false, // 默认关闭，变化范围很大
  min_change_1h_threshold: -3.0, // 参考实际数据
  max_change_1h_threshold: 4.0,
  enable_change_4h_filter: false, // 默认关闭
  min_change_4h_threshold: -8.0,
  max_change_4h_threshold: 10,
  enable_change_24h_filter: false, // 默认关闭，变化范围很大
  min_change_24h_threshold: -12.0, // 适应VANA +16.81%等情况
  max_change_24h_threshold: 18.0
};

/**
 * ⭐ 2025-07-01 版：基於 6/26–7/01 回測結果重塑的五大策略模板
 * baseline = 61.1 %（actual_rank ≤ 3）
 * — realistic：寬鬆入門；— rock：穩健守成；
 * — sniper：極致精挑；— momentum_rider：追短線動能；
 * — all_rounder：鎖定 ≈66 % 命中率的量質折衷。
 */
export const strategyTemplates = {
  /** 🎯 實戰模式 (Market Reality) — 基線 61 % */
  realistic: {
    name: '🎯 實戰模式 (Market Reality)',
    description: '新手模板：條件最寬，確保每日都有機會可下。',
    // ---- 核心下注 ----
    strategy: 'single_bet' as const,
    bet_amount: 200,
    daily_stop_loss_percentage: 12,
    max_bet_percentage: 10,
    // ---- AI 門檻 ----
    confidence_threshold: 85,
    score_gap_threshold: 65,
    min_total_games: 1,
    historical_accuracy_threshold: 10, // 10% (统一为0-100)
    min_sample_count: 10,
    // ---- 風控 ----
    stop_loss_consecutive: 6,
    // ---- 遺漏參數設為默認值 ----
    enable_trend_analysis: false,
    enable_volume_filter: false,
    enable_kelly_criterion: false,
    kelly_fraction: 25, // 25% (统一为0-100)
    enable_martingale: false,
    martingale_multiplier: 2.0,
    max_martingale_steps: 3,
    enable_time_filter: false,
    allowed_hours_start: 9,
    allowed_hours_end: 21,
    enable_volatility_filter: false,
    max_volatility_threshold: 80, // 80% (统一为0-100)
    min_liquidity_threshold: 1000000,
    is_active: false,
    rank_betting_enabled_ranks: [1, 2, 3],
    rank_betting_amount_per_rank: 200,
    rank_betting_different_amounts: false,
    rank_betting_rank1_amount: 200,
    rank_betting_rank2_amount: 200,
    rank_betting_rank3_amount: 200,
    rank_betting_max_ranks: 5,
    // 歷史表現過濾器
    enable_win_rate_filter: false,
    min_win_rate_threshold: 0,
    enable_top3_rate_filter: false,
    min_top3_rate_threshold: 0,
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 0,
    enable_stability_filter: false,
    max_stability_threshold: 0,
    // 評分過濾器
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0,
    // 市場動態過濾器
    enable_change_5m_filter: false,
    min_change_5m_threshold: 0,
    max_change_5m_threshold: 0,
    enable_change_1h_filter: false,
    min_change_1h_threshold: 0,
    max_change_1h_threshold: 0,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0,
    max_change_4h_threshold: 0,
    enable_change_24h_filter: false,
    min_change_24h_threshold: 0,
    max_change_24h_threshold: 0
  },

  /** 🗿 磐石型 (The Rock) — 回測 63.2 % */
  rock: {
    name: '🗿 磐石型 (The Rock)',
    description: '保守穩健：樣本大、波動小，追求穩定累積。',
    strategy: 'single_bet' as const,
    bet_amount: 300,
    daily_stop_loss_percentage: 8,
    max_bet_percentage: 8,
    confidence_threshold: 90,
    score_gap_threshold: 75,
    min_total_games: 30,
    historical_accuracy_threshold: 50, // 50% (统一为0-100)
    min_sample_count: 10,
    // 歷史表現
    enable_top3_rate_filter: true,
    min_top3_rate_threshold: 70, // 70% (统一为0-100)
    enable_avg_rank_filter: true,
    max_avg_rank_threshold: 2.5,
    // 波動
    enable_stability_filter: true,
    max_stability_threshold: 0.9, // 0.9 (标准差小数格式)
    // 僅保留 24 h 正動能
    enable_change_24h_filter: true,
    min_change_24h_threshold: 0,
    max_change_24h_threshold: 20,
    // 風控
    stop_loss_consecutive: 5,
    // ---- 遺漏參數設為默認值 ----
    enable_trend_analysis: false,
    enable_volume_filter: false,
    enable_kelly_criterion: false,
    kelly_fraction: 25, // 25% (统一为0-100)
    enable_martingale: false,
    martingale_multiplier: 2.0,
    max_martingale_steps: 3,
    enable_time_filter: false,
    allowed_hours_start: 9,
    allowed_hours_end: 21,
    enable_volatility_filter: false,
    max_volatility_threshold: 80, // 80% (统一为0-100)
    min_liquidity_threshold: 1000000,
    is_active: false,
    rank_betting_enabled_ranks: [1, 2, 3],
    rank_betting_amount_per_rank: 300,
    rank_betting_different_amounts: false,
    rank_betting_rank1_amount: 300,
    rank_betting_rank2_amount: 300,
    rank_betting_rank3_amount: 300,
    rank_betting_max_ranks: 5,
    // 其他歷史表現過濾器
    enable_win_rate_filter: false,
    min_win_rate_threshold: 0,
    // 評分過濾器
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0,
    // 其他市場動態過濾器
    enable_change_5m_filter: false,
    min_change_5m_threshold: 0,
    max_change_5m_threshold: 0,
    enable_change_1h_filter: false,
    min_change_1h_threshold: 0,
    max_change_1h_threshold: 0,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0,
    max_change_4h_threshold: 0
  },

  /** 🎯 狙擊手型 (Elite Sniper 75) — 回測 75 %+ */
  sniper: {
    name: '🎯 狙擊手型 (Elite Sniper 75)',
    description: '極限精挑：日機會 ≲15 檔，但命中率 ≥75 %。',
    strategy: 'single_bet' as const,
    bet_amount: 200,
    daily_stop_loss_percentage: 10,
    max_bet_percentage: 5,
    // AI 分數
    confidence_threshold: 97,
    score_gap_threshold: 15,
    min_total_games: 20,
    historical_accuracy_threshold: 65, // 65% (统一为0-100)
    min_sample_count: 5,
    // 分數雙閥
    enable_absolute_score_filter: true,
    min_absolute_score_threshold: 97,
    // 波動 & 動能
    enable_stability_filter: true,
    max_stability_threshold: 0.03, // 0.03 (标准差小数格式)
    enable_change_24h_filter: true,
    min_change_24h_threshold: 2.0,
    max_change_24h_threshold: 100, // 設定上限值
    // 風控
    enable_kelly_criterion: true,
    kelly_fraction: 50, // 50% (统一为0-100)
    stop_loss_consecutive: 4,
    // ---- 遺漏參數設為默認值 ----
    enable_trend_analysis: false,
    enable_volume_filter: false,
    enable_martingale: false,
    martingale_multiplier: 2.0,
    max_martingale_steps: 3,
    enable_time_filter: false,
    allowed_hours_start: 9,
    allowed_hours_end: 21,
    enable_volatility_filter: false,
    max_volatility_threshold: 80, // 80% (统一为0-100)
    min_liquidity_threshold: 1000000,
    is_active: false,
    rank_betting_enabled_ranks: [1, 2, 3],
    rank_betting_amount_per_rank: 200,
    rank_betting_different_amounts: false,
    rank_betting_rank1_amount: 200,
    rank_betting_rank2_amount: 200,
    rank_betting_rank3_amount: 200,
    rank_betting_max_ranks: 5,
    // 其他歷史表現過濾器
    enable_win_rate_filter: false,
    min_win_rate_threshold: 0,
    enable_top3_rate_filter: false,
    min_top3_rate_threshold: 0,
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 0,
    // 其他評分過濾器
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0,
    // 其他市場動態過濾器
    enable_change_5m_filter: false,
    min_change_5m_threshold: 0,
    max_change_5m_threshold: 0,
    enable_change_1h_filter: false,
    min_change_1h_threshold: 0,
    max_change_1h_threshold: 0,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0,
    max_change_4h_threshold: 0
  },

  /** 🏇 動量騎士型 (Momentum Rider) — 回測 61.9 % */
  momentum_rider: {
    name: '🏇 動量騎士型 (Momentum Rider)',
    description: '積極多倉：追 5 m / 1 h / 24 h 正動能，配簡易馬丁格爾。',
    strategy: 'multi_bet' as const,
    bet_amount: 250,
    daily_stop_loss_percentage: 15,
    max_bet_percentage: 30,
    confidence_threshold: 85,
    score_gap_threshold: 20,
    min_total_games: 5,
    historical_accuracy_threshold: 30, // 30% (统一为0-100)
    min_sample_count: 3,
    // 動能濾器
    enable_change_5m_filter: true,
    min_change_5m_threshold: 5, // 5% (统一为0-100)
    max_change_5m_threshold: 100, // 設定上限值
    enable_change_1h_filter: true,
    min_change_1h_threshold: 20, // 20% (统一为0-100)
    max_change_1h_threshold: 100, // 設定上限值
    enable_change_24h_filter: true,
    min_change_24h_threshold: 50, // 50% (统一为0-100)
    max_change_24h_threshold: 100, // 設定上限值
    // 波動
    enable_stability_filter: true,
    max_stability_threshold: 1.2, // 1.2 (标准差小数格式)
    // 馬丁格爾
    enable_martingale: true,
    martingale_multiplier: 2.0,
    max_martingale_steps: 2,
    stop_loss_consecutive: 8,
    // ---- 遺漏參數設為默認值 ----
    enable_trend_analysis: false,
    enable_volume_filter: false,
    enable_kelly_criterion: false,
    kelly_fraction: 25, // 25% (统一为0-100)
    enable_time_filter: false,
    allowed_hours_start: 9,
    allowed_hours_end: 21,
    enable_volatility_filter: false,
    max_volatility_threshold: 80, // 80% (统一为0-100)
    min_liquidity_threshold: 1000000,
    is_active: false,
    rank_betting_enabled_ranks: [1, 2, 3],
    rank_betting_amount_per_rank: 250,
    rank_betting_different_amounts: false,
    rank_betting_rank1_amount: 250,
    rank_betting_rank2_amount: 250,
    rank_betting_rank3_amount: 250,
    rank_betting_max_ranks: 5,
    // 歷史表現過濾器
    enable_win_rate_filter: false,
    min_win_rate_threshold: 0,
    enable_top3_rate_filter: false,
    min_top3_rate_threshold: 0,
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 0,
    // 評分過濾器
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0,
    // 其他市場動態過濾器
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0,
    max_change_4h_threshold: 0
  },

  /** ⚖️ 全能平衡型 (Precision 66) — 回測 66.2 % */
  all_rounder: {
    name: '⚖️ 全能平衡型 (Precision 66)',
    description: '量質折衷：每日 ≈80 機會，命中率約 66 %。',
    strategy: 'multi_bet' as const,
    bet_amount: 220,
    daily_stop_loss_percentage: 12,
    max_bet_percentage: 18,
    confidence_threshold: 88,
    score_gap_threshold: 50,
    min_total_games: 10,
    historical_accuracy_threshold: 60, // 60% (统一为0-100)
    min_sample_count: 5,
    // 歷史表現
    enable_top3_rate_filter: true,
    min_top3_rate_threshold: 60, // 60% (统一为0-100)
    // 波動
    enable_stability_filter: true,
    max_stability_threshold: 0.14, // 0.14 (标准差小数格式)
    // 動能
    enable_change_1h_filter: true,
    min_change_1h_threshold: 5, // 5% (统一为0-100)
    max_change_1h_threshold: 100, // 設定上限值
    enable_change_24h_filter: true,
    min_change_24h_threshold: 100, // 100% (统一为0-100)
    max_change_24h_threshold: 100, // 設定上限值
    // 風控
    enable_kelly_criterion: true,
    kelly_fraction: 60, // 60% (统一为0-100)
    stop_loss_consecutive: 6,
    // ---- 遺漏參數設為默認值 ----
    enable_trend_analysis: false,
    enable_volume_filter: false,
    enable_martingale: false,
    martingale_multiplier: 2.0,
    max_martingale_steps: 3,
    enable_time_filter: false,
    allowed_hours_start: 9,
    allowed_hours_end: 21,
    enable_volatility_filter: false,
    max_volatility_threshold: 80, // 80% (统一为0-100)
    min_liquidity_threshold: 1000000,
    is_active: false,
    rank_betting_enabled_ranks: [1, 2, 3],
    rank_betting_amount_per_rank: 220,
    rank_betting_different_amounts: false,
    rank_betting_rank1_amount: 220,
    rank_betting_rank2_amount: 220,
    rank_betting_rank3_amount: 220,
    rank_betting_max_ranks: 5,
    // 其他歷史表現過濾器
    enable_win_rate_filter: false,
    min_win_rate_threshold: 0,
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 0,
    // 評分過濾器
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0,
    // 其他市場動態過濾器
    enable_change_5m_filter: false,
    min_change_5m_threshold: 0,
    max_change_5m_threshold: 0,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0,
    max_change_4h_threshold: 0
  },

  // 🎯 智能排名策略 - 保持宽松设置
  rank_betting_smart: {
    name: '🎯 智能排名策略',
    description: '基于排名下注，参数要求最宽松。选择TOP1、TOP2等排名进行下注。',
    confidence_threshold: 0, // 排名下注不使用置信度
    score_gap_threshold: 0, // 排名下注不使用分数差距
    min_total_games: 1, // 最低要求
    historical_accuracy_threshold: 0, // 排名下注不使用历史准确率 (统一为0-100)
    min_sample_count: 1, // 最低要求
    max_bet_percentage: 25, // 适中的风险比例
    strategy: 'rank_betting' as const, // **核心：排名下注策略**
    bet_amount: 200,
    daily_stop_loss_percentage: 15,
    enable_trend_analysis: false,
    enable_volume_filter: false,
    stop_loss_consecutive: 8, // 允许较多连续失败
    enable_kelly_criterion: false,
    kelly_fraction: 25, // 25% (统一为0-100)
    enable_martingale: false,
    martingale_multiplier: 2.0,
    max_martingale_steps: 3,
    enable_time_filter: false,
    allowed_hours_start: 9,
    allowed_hours_end: 21,
    enable_volatility_filter: false,
    max_volatility_threshold: 80, // 80% (统一为0-100)
    min_liquidity_threshold: 1000000,
    is_active: false,
    rank_betting_enabled_ranks: [1, 2, 3],
    rank_betting_amount_per_rank: 200,
    rank_betting_different_amounts: false,
    rank_betting_rank1_amount: 200,
    rank_betting_rank2_amount: 200,
    rank_betting_rank3_amount: 200,
    rank_betting_max_ranks: 5,
    // 🎯 智能排名策略：关闭所有过滤器
    enable_win_rate_filter: false,
    min_win_rate_threshold: 0,
    enable_top3_rate_filter: false,
    min_top3_rate_threshold: 0,
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 0,
    enable_stability_filter: false,
    max_stability_threshold: 0,
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0,
    enable_change_5m_filter: false,
    min_change_5m_threshold: 0,
    max_change_5m_threshold: 0,
    enable_change_1h_filter: false,
    min_change_1h_threshold: 0,
    max_change_1h_threshold: 0,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0,
    max_change_4h_threshold: 0,
    enable_change_24h_filter: false,
    min_change_24h_threshold: 0,
    max_change_24h_threshold: 0
  }
};

export const useAutoBettingConfig = () => {
  // 配置状态 - 使用优化后的默认配置
  const config = reactive<AutoBettingConfig>({
    jwt_token: '',
    ...optimizedDefaultConfig
  });

  // 策略模式状态
  const selectedTemplate = ref<string>('');
  const customStrategyMode = ref(false);
  const configSaving = ref(false);
  const configSyncStatus = ref<{ type: 'success' | 'error' | 'info'; message: string } | null>(null);

  // 计算属性：添加自定义策略到模板列表（动态更新）
  const getStrategyTemplatesWithCustom = () => ({
    ...strategyTemplates,
    custom: {
      name: '🎨 自定义策略',
      description: '完全自定义的策略配置，可手动调整所有参数',
      // 基础参数
      confidence_threshold: config.confidence_threshold,
      score_gap_threshold: config.score_gap_threshold,
      min_total_games: config.min_total_games,
      historical_accuracy_threshold: config.historical_accuracy_threshold,
      min_sample_count: config.min_sample_count,
      max_bet_percentage: config.max_bet_percentage,
      strategy: config.strategy,
      enable_trend_analysis: config.enable_trend_analysis,
      enable_volume_filter: config.enable_volume_filter,
      stop_loss_consecutive: config.stop_loss_consecutive,

      // 🔧 新增：历史表现过滤器参数
      enable_win_rate_filter: config.enable_win_rate_filter,
      min_win_rate_threshold: config.min_win_rate_threshold,
      enable_top3_rate_filter: config.enable_top3_rate_filter,
      min_top3_rate_threshold: config.min_top3_rate_threshold,
      enable_avg_rank_filter: config.enable_avg_rank_filter,
      max_avg_rank_threshold: config.max_avg_rank_threshold,
      enable_stability_filter: config.enable_stability_filter,
      max_stability_threshold: config.max_stability_threshold,

      // 🔧 新增：评分过滤器参数
      enable_absolute_score_filter: config.enable_absolute_score_filter,
      min_absolute_score_threshold: config.min_absolute_score_threshold,
      enable_relative_score_filter: config.enable_relative_score_filter,
      min_relative_score_threshold: config.min_relative_score_threshold,
      enable_h2h_score_filter: config.enable_h2h_score_filter,
      min_h2h_score_threshold: config.min_h2h_score_threshold,

      // 🔧 新增：市场动态过滤器参数
      enable_change_5m_filter: config.enable_change_5m_filter,
      min_change_5m_threshold: config.min_change_5m_threshold,
      max_change_5m_threshold: config.max_change_5m_threshold,
      enable_change_1h_filter: config.enable_change_1h_filter,
      min_change_1h_threshold: config.min_change_1h_threshold,
      max_change_1h_threshold: config.max_change_1h_threshold,
      enable_change_4h_filter: config.enable_change_4h_filter,
      min_change_4h_threshold: config.min_change_4h_threshold,
      max_change_4h_threshold: config.max_change_4h_threshold,
      enable_change_24h_filter: config.enable_change_24h_filter,
      min_change_24h_threshold: config.min_change_24h_threshold,
      max_change_24h_threshold: config.max_change_24h_threshold
    }
  });

  // 防抖器
  let saveConfigTimeout: number | null = null;

  // 从云端加载配置
  const loadConfigFromCloud = async (uid: string): Promise<boolean> => {
    if (!uid) return false;

    try {
      const response = await autoBettingApi.getConfig(uid);
      if (response.data.success) {
        Object.assign(config, response.data.data);
        configSyncStatus.value = { type: 'success', message: '已从云端加载配置' };
        return true;
      } else {
        configSyncStatus.value = { type: 'error', message: '加载云端配置失败' };
        return false;
      }
    } catch (error) {
      console.error('加载云端配置失败:', error);
      configSyncStatus.value = { type: 'error', message: '网络错误，无法加载云端配置' };
      return false;
    }
  };

  // 保存配置到云端
  const saveConfigToCloud = async (uid: string): Promise<boolean> => {
    if (!uid) return false;

    try {
      const response = await autoBettingApi.saveConfig(uid, config);
      if (response.data.success) {
        configSyncStatus.value = { type: 'success', message: '配置已保存到云端' };
        return true;
      } else {
        configSyncStatus.value = { type: 'error', message: '保存云端配置失败' };
        return false;
      }
    } catch (error) {
      console.error('保存云端配置失败:', error);
      configSyncStatus.value = { type: 'error', message: '网络错误，无法保存到云端' };
      return false;
    }
  };

  // 从localStorage加载配置
  const loadConfigFromLocalStorage = () => {
    const savedConfig = localStorage.getItem('autoBettingConfig');
    if (savedConfig) {
      try {
        const parsed = JSON.parse(savedConfig);
        Object.assign(config, { ...parsed });
        configSyncStatus.value = { type: 'info', message: '已从本地存储加载配置' };
      } catch (error) {
        console.error('加载本地配置失败:', error);
        configSyncStatus.value = { type: 'error', message: '本地配置损坏，已重置为默认配置' };
      }
    }
  };

  // 保存配置到localStorage
  const saveConfigToLocalStorage = () => {
    try {
      localStorage.setItem('autoBettingConfig', JSON.stringify(config));
      configSyncStatus.value = { type: 'success', message: '配置已保存到本地存储' };
    } catch (error) {
      console.error('保存本地配置失败:', error);
      configSyncStatus.value = { type: 'error', message: '保存本地配置失败' };
    }
  };

  // 自动保存配置（带防抖）
  const autoSaveConfig = async (uid?: string) => {
    if (saveConfigTimeout) {
      clearTimeout(saveConfigTimeout);
    }

    saveConfigTimeout = setTimeout(async () => {
      saveConfigToLocalStorage();
      if (uid) {
        await saveConfigToCloud(uid);
      }
    }, 1000);
  };

  // 手动保存配置
  const manualSaveConfig = async (uid?: string) => {
    configSaving.value = true;

    try {
      saveConfigToLocalStorage();

      if (uid) {
        await saveConfigToCloud(uid);
        window.$message?.success('配置已保存到云端');
      } else {
        window.$message?.success('配置已保存到本地');
      }
    } catch (err) {
      console.error('保存配置失败:', err);
      window.$message?.error('保存配置失败');
    } finally {
      configSaving.value = false;
    }
  };

  // 检测当前配置是否匹配某个预设策略
  const detectCurrentStrategy = (): string => {
    for (const [key, template] of Object.entries(strategyTemplates)) {
      const matches =
        // 基础参数匹配
        config.confidence_threshold === template.confidence_threshold &&
        config.score_gap_threshold === template.score_gap_threshold &&
        config.min_total_games === template.min_total_games &&
        config.historical_accuracy_threshold === template.historical_accuracy_threshold &&
        config.min_sample_count === template.min_sample_count &&
        config.max_bet_percentage === template.max_bet_percentage &&
        config.strategy === template.strategy &&
        config.enable_trend_analysis === template.enable_trend_analysis &&
        config.enable_volume_filter === template.enable_volume_filter &&
        config.stop_loss_consecutive === template.stop_loss_consecutive &&
        // 🔧 新增：历史表现过滤器参数匹配
        config.enable_win_rate_filter === template.enable_win_rate_filter &&
        config.min_win_rate_threshold === template.min_win_rate_threshold &&
        config.enable_top3_rate_filter === template.enable_top3_rate_filter &&
        config.min_top3_rate_threshold === template.min_top3_rate_threshold &&
        config.enable_avg_rank_filter === template.enable_avg_rank_filter &&
        config.max_avg_rank_threshold === template.max_avg_rank_threshold &&
        config.enable_stability_filter === template.enable_stability_filter &&
        config.max_stability_threshold === template.max_stability_threshold &&
        // 🔧 新增：评分过滤器参数匹配
        config.enable_absolute_score_filter === template.enable_absolute_score_filter &&
        config.min_absolute_score_threshold === template.min_absolute_score_threshold &&
        config.enable_relative_score_filter === template.enable_relative_score_filter &&
        config.min_relative_score_threshold === template.min_relative_score_threshold &&
        config.enable_h2h_score_filter === template.enable_h2h_score_filter &&
        config.min_h2h_score_threshold === template.min_h2h_score_threshold &&
        // 🔧 新增：市场动态过滤器参数匹配
        config.enable_change_5m_filter === template.enable_change_5m_filter &&
        config.min_change_5m_threshold === template.min_change_5m_threshold &&
        config.max_change_5m_threshold === template.max_change_5m_threshold &&
        config.enable_change_1h_filter === template.enable_change_1h_filter &&
        config.min_change_1h_threshold === template.min_change_1h_threshold &&
        config.max_change_1h_threshold === template.max_change_1h_threshold &&
        config.enable_change_4h_filter === template.enable_change_4h_filter &&
        config.min_change_4h_threshold === template.min_change_4h_threshold &&
        config.max_change_4h_threshold === template.max_change_4h_threshold &&
        config.enable_change_24h_filter === template.enable_change_24h_filter &&
        config.min_change_24h_threshold === template.min_change_24h_threshold &&
        config.max_change_24h_threshold === template.max_change_24h_threshold;

      if (matches) {
        return key;
      }
    }
    return 'custom';
  };

  // 应用策略模板
  const applyStrategyTemplate = (templateKey: string) => {
    if (templateKey === 'custom') {
      selectedTemplate.value = 'custom';
      customStrategyMode.value = true;
      window.$message?.info('已选择自定义策略，可手动调整所有参数');
      return;
    }

    const template = strategyTemplates[templateKey as keyof typeof strategyTemplates];
    if (!template) return;

    // 🔧 修复：同步所有策略模板参数，包括新增的过滤器参数
    Object.assign(config, {
      // 基础参数
      confidence_threshold: template.confidence_threshold,
      score_gap_threshold: template.score_gap_threshold,
      min_total_games: template.min_total_games,
      historical_accuracy_threshold: template.historical_accuracy_threshold,
      min_sample_count: template.min_sample_count,
      max_bet_percentage: template.max_bet_percentage,
      strategy: template.strategy,
      enable_trend_analysis: template.enable_trend_analysis,
      enable_volume_filter: template.enable_volume_filter,
      stop_loss_consecutive: template.stop_loss_consecutive,

      // 🆕 历史表现过滤器参数
      enable_win_rate_filter: template.enable_win_rate_filter,
      min_win_rate_threshold: template.min_win_rate_threshold,
      enable_top3_rate_filter: template.enable_top3_rate_filter,
      min_top3_rate_threshold: template.min_top3_rate_threshold,
      enable_avg_rank_filter: template.enable_avg_rank_filter,
      max_avg_rank_threshold: template.max_avg_rank_threshold,
      enable_stability_filter: template.enable_stability_filter,
      max_stability_threshold: template.max_stability_threshold,

      // 🆕 评分过滤器参数
      enable_absolute_score_filter: template.enable_absolute_score_filter,
      min_absolute_score_threshold: template.min_absolute_score_threshold,
      enable_relative_score_filter: template.enable_relative_score_filter,
      min_relative_score_threshold: template.min_relative_score_threshold,
      enable_h2h_score_filter: template.enable_h2h_score_filter,
      min_h2h_score_threshold: template.min_h2h_score_threshold,

      // 🆕 市场动态过滤器参数
      enable_change_5m_filter: template.enable_change_5m_filter,
      min_change_5m_threshold: template.min_change_5m_threshold,
      max_change_5m_threshold: template.max_change_5m_threshold,
      enable_change_1h_filter: template.enable_change_1h_filter,
      min_change_1h_threshold: template.min_change_1h_threshold,
      max_change_1h_threshold: template.max_change_1h_threshold,
      enable_change_4h_filter: template.enable_change_4h_filter,
      min_change_4h_threshold: template.min_change_4h_threshold,
      max_change_4h_threshold: template.max_change_4h_threshold,
      enable_change_24h_filter: template.enable_change_24h_filter,
      min_change_24h_threshold: template.min_change_24h_threshold,
      max_change_24h_threshold: template.max_change_24h_threshold
    });

    selectedTemplate.value = templateKey;
    customStrategyMode.value = false;
    window.$message?.success(`已应用${template.name}，所有参数已同步到配置中`);
  };

  // 🎨 切换到自定义策略模式（自动重置为优化默认配置）
  const switchToCustomMode = () => {
    // 先应用优化后的默认配置
    Object.assign(config, {
      jwt_token: config.jwt_token, // 保留JWT令牌
      ...optimizedDefaultConfig
    });

    // 切换到自定义模式
    customStrategyMode.value = true;
    selectedTemplate.value = '';

    window.$message?.success('🎨 已进入自定义策略模式，配置已重置为优化后的默认值，您可以自由调整所有参数');
    console.log('🎨 自定义模式已激活，配置已重置为:', optimizedDefaultConfig);
  };

  // 🎨 切换到自定义策略模式（保持当前配置）
  const switchToCustomModeKeepConfig = () => {
    customStrategyMode.value = true;
    selectedTemplate.value = '';

    window.$message?.info('🎨 已进入自定义策略模式，保持当前配置不变');
  };

  // 🔄 重置到优化后的默认配置
  const resetToDefaultConfig = () => {
    Object.assign(config, {
      jwt_token: config.jwt_token, // 保留JWT令牌
      ...optimizedDefaultConfig
    });

    // 重置状态
    customStrategyMode.value = false;
    selectedTemplate.value = '';

    window.$message?.success('✨ 已重置为优化后的默认配置，基于实际市场数据优化');
    console.log('🔄 配置已重置为优化后的默认值:', optimizedDefaultConfig);
  };

  // 🔄 重置为模板模式（保持当前配置不变）
  const resetToTemplateMode = () => {
    customStrategyMode.value = false;
    selectedTemplate.value = '';
    window.$message?.info('📋 已返回模板模式，请选择一个预设策略模板');
  };

  // 🔄 完全重置（包括JWT令牌）
  const resetAllConfig = () => {
    Object.assign(config, {
      jwt_token: '',
      ...optimizedDefaultConfig
    });

    // 重置状态
    customStrategyMode.value = false;
    selectedTemplate.value = '';

    // 清除本地存储
    localStorage.removeItem('autoBettingConfig');

    window.$message?.warning('🗑️ 已完全重置所有配置，包括JWT令牌和本地存储');
    console.log('🔄 所有配置已完全重置');
  };

  // 🔄 重置为保守模式（快速应用磐石型策略）
  const resetToConservativeMode = () => {
    applyStrategyTemplate('rock');
    window.$message?.success('🗿 已快速重置为保守模式（磐石型策略）');
  };

  // 🔄 重置为积极模式（快速应用动量骑士型策略）
  const resetToAggressiveMode = () => {
    applyStrategyTemplate('momentum_rider');
    window.$message?.success('🏇 已快速重置为积极模式（动量骑士型策略）');
  };

  // 指定排名下注相关方法
  const toggleRankBetting = (rank: number, checked: boolean) => {
    if (checked) {
      if (!config.rank_betting_enabled_ranks.includes(rank)) {
        config.rank_betting_enabled_ranks.push(rank);
        config.rank_betting_enabled_ranks.sort((a, b) => a - b);
      }
    } else {
      const index = config.rank_betting_enabled_ranks.indexOf(rank);
      if (index > -1) {
        config.rank_betting_enabled_ranks.splice(index, 1);
      }
    }
  };

  const getRankBettingAmount = (rank: number): number => {
    if (!config.rank_betting_different_amounts) {
      return config.rank_betting_amount_per_rank || 200;
    }

    switch (rank) {
      case 1:
        return config.rank_betting_rank1_amount || 200;
      case 2:
        return config.rank_betting_rank2_amount || 200;
      case 3:
        return config.rank_betting_rank3_amount || 200;
      default:
        return config.rank_betting_amount_per_rank || 200;
    }
  };

  const getTotalRankBettingAmount = (): number => {
    return config.rank_betting_enabled_ranks.reduce((total, rank) => {
      return total + getRankBettingAmount(rank);
    }, 0);
  };

  // 初始化配置
  const initializeConfig = async (uid?: string) => {
    loadConfigFromLocalStorage();
    if (uid) {
      await loadConfigFromCloud(uid);
    }
  };

  return {
    // 状态
    config,
    selectedTemplate,
    customStrategyMode,
    configSaving,
    configSyncStatus,
    strategyTemplates,
    getStrategyTemplatesWithCustom,
    optimizedDefaultConfig,

    // 方法
    loadConfigFromCloud,
    saveConfigToCloud,
    loadConfigFromLocalStorage,
    saveConfigToLocalStorage,
    autoSaveConfig,
    manualSaveConfig,
    applyStrategyTemplate,
    switchToCustomMode,
    resetToTemplateMode,
    detectCurrentStrategy,
    toggleRankBetting,
    getRankBettingAmount,
    getTotalRankBettingAmount,
    initializeConfig,

    // 🔄 重置方法
    resetToDefaultConfig,
    resetAllConfig,
    resetToConservativeMode,
    resetToAggressiveMode,

    // 🎨 自定义模式方法
    switchToCustomModeKeepConfig
  };
};
