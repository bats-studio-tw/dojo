import { ref, reactive } from 'vue';
import { autoBettingApi } from '@/utils/api';

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
 */
export const optimizedDefaultConfig: Omit<AutoBettingConfig, 'jwt_token'> = {
  // 🎯 基础交易参数 - 基于实际数据优化
  bet_amount: 200,
  daily_stop_loss_percentage: 15,
  confidence_threshold: 85, // 基于实际数据，选择NEAR 85%级别
  score_gap_threshold: 65.0, // 选择中等质量机会，如NXPC 64.4级别
  min_total_games: 4, // 适应样本不足现实
  historical_accuracy_threshold: 0.12, // 12%，适应实际情况
  min_sample_count: 6, // 适中样本数要求
  max_bet_percentage: 15,
  strategy: 'single_bet' as const,

  // 🔧 高级功能设置
  enable_trend_analysis: false, // 默认关闭，避免过度过滤
  enable_volume_filter: false, // 默认关闭，交易量数据不稳定
  stop_loss_consecutive: 5, // 适中的止损容忍度
  enable_kelly_criterion: false,
  kelly_fraction: 0.25,
  enable_martingale: false,
  martingale_multiplier: 2.0,
  max_martingale_steps: 3,
  enable_time_filter: false,
  allowed_hours_start: 9,
  allowed_hours_end: 21,
  enable_volatility_filter: false,
  max_volatility_threshold: 0.8,
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
  min_win_rate_threshold: 0.12, // 12%，适应实际数据
  enable_top3_rate_filter: true, // 默认开启，重要指标
  min_top3_rate_threshold: 0.58, // 58%，参考ETH 58.8%
  enable_avg_rank_filter: false, // 默认关闭
  max_avg_rank_threshold: 4.0,
  enable_stability_filter: false, // 默认关闭
  max_stability_threshold: 2.5,

  // 🎯 评分过滤器 - 基于实际分数范围
  enable_absolute_score_filter: true, // 默认开启，核心过滤器
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
  max_change_4h_threshold: 10.0,
  enable_change_24h_filter: false, // 默认关闭，变化范围很大
  min_change_24h_threshold: -12.0, // 适应VANA +16.81%等情况
  max_change_24h_threshold: 18.0
};

/**
 * 策略模板 - 四大交易原型
 * 重新设计为四个具有鲜明特色和根本差异的策略原型
 */
export const strategyTemplates = {
  // 🆕 实战模式 - 专门针对真实市场情况优化
  realistic: {
    name: '🎯 实战模式 (Market Reality)',
    description: '基于真实市场数据优化的策略，适应实际市场情况。推荐新手使用。',
    confidence_threshold: 85, // 根据实际数据调整到85%，选择NEAR 85%级别
    score_gap_threshold: 65.0, // 选择中等质量机会，如NXPC 64.4这个级别
    min_total_games: 3, // 进一步降低，适应样本不足现实
    historical_accuracy_threshold: 0.1, // 大幅降低到10%，适应实际情况
    min_sample_count: 5, // 进一步降低样本数要求
    max_bet_percentage: 12, // 适中的风险比例
    strategy: 'single_bet' as const,
    enable_trend_analysis: false, // 关闭趋势分析，避免过度过滤
    enable_volume_filter: false, // 关闭交易量过滤
    stop_loss_consecutive: 6, // 允许更多连续失败
    // 🎯 实战模式：宽松的历史表现过滤器
    enable_win_rate_filter: false, // 关闭胜率过滤，很多币种胜率数据不足
    min_win_rate_threshold: 0.1, // 大幅降低到10%
    enable_top3_rate_filter: true, // 开启保本率过滤，这是重要指标
    min_top3_rate_threshold: 0.55, // 设置为55%，参考NXPC 57.1%
    enable_avg_rank_filter: false, // 关闭平均排名过滤
    max_avg_rank_threshold: 5.0,
    enable_stability_filter: false, // 关闭稳定性过滤
    max_stability_threshold: 3.0,
    // 🎯 实战模式：适度开启评分过滤器
    enable_absolute_score_filter: true, // 开启绝对分数过滤
    min_absolute_score_threshold: 55.0, // 设置为55，参考NXPC 59.2
    enable_relative_score_filter: false,
    min_relative_score_threshold: 50.0,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 50.0,
    // 🎯 实战模式：适度开启市场动态过滤器
    enable_change_5m_filter: false, // 5分钟数据经常缺失
    min_change_5m_threshold: -1.0,
    max_change_5m_threshold: 1.0,
    enable_change_1h_filter: false, // 1小时变化范围很大，不设限制
    min_change_1h_threshold: -5.0,
    max_change_1h_threshold: 5.0,
    enable_change_4h_filter: false,
    min_change_4h_threshold: -10.0,
    max_change_4h_threshold: 10.0,
    enable_change_24h_filter: false, // 24小时变化范围很大，如VANA +16.81%
    min_change_24h_threshold: -15.0,
    max_change_24h_threshold: 20.0
  },

  // 策略一：磐石型 (The Rock) - 基于实际数据优化
  rock: {
    name: '🗿 磐石型 (The Rock)',
    description: '保守策略，选择最优质机会。要求高置信度、高保本率，适合追求稳定的用户。',
    confidence_threshold: 92, // 基于实际数据，选择NXPC 92%级别
    score_gap_threshold: 75.0, // 选择真正高质量机会，如AVAX 69.8以上级别
    min_total_games: 5, // 进一步降低，适应样本不足现实
    historical_accuracy_threshold: 0.15, // 降低到15%，适应实际情况
    min_sample_count: 8, // 进一步降低样本数要求
    max_bet_percentage: 8, // 保持低风险
    strategy: 'single_bet' as const,
    enable_trend_analysis: false, // 关闭，避免过度过滤
    enable_volume_filter: false, // 关闭交易量过滤
    stop_loss_consecutive: 4, // 适中的止损要求
    // 🗿 磐石型：严格的历史表现过滤器
    enable_win_rate_filter: false, // 关闭胜率过滤，数据经常不足
    min_win_rate_threshold: 0.15, // 大幅降低到15%
    enable_top3_rate_filter: true, // 开启保本率过滤，这是关键指标
    min_top3_rate_threshold: 0.7, // 设置为70%，参考AVAX 72.2%
    enable_avg_rank_filter: false, // 关闭平均排名过滤
    max_avg_rank_threshold: 2.5,
    enable_stability_filter: false, // 关闭稳定性过滤
    max_stability_threshold: 1.5,
    // 🗿 磐石型：严格的评分过滤器
    enable_absolute_score_filter: true, // 开启绝对分数过滤
    min_absolute_score_threshold: 70.0, // 设置为70，参考AVAX 74.0
    enable_relative_score_filter: true, // 开启相对分数过滤
    min_relative_score_threshold: 58.0, // 设置为58，参考AVAX 60.2
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 60.0,
    // 🗿 磐石型：谨慎的市场动态过滤器
    enable_change_5m_filter: false, // 5分钟数据经常缺失
    min_change_5m_threshold: -0.5,
    max_change_5m_threshold: 0.5,
    enable_change_1h_filter: true, // 开启1小时过滤，避免剧烈波动
    min_change_1h_threshold: -2.0, // 避免大幅下跌
    max_change_1h_threshold: 2.0, // 避免大幅上涨
    enable_change_4h_filter: false,
    min_change_4h_threshold: -5.0,
    max_change_4h_threshold: 5.0,
    enable_change_24h_filter: true, // 开启24小时过滤
    min_change_24h_threshold: -5.0, // 避免大幅波动
    max_change_24h_threshold: 10.0
  },

  // 策略二：狙击手型 (The Sniper) - 基于实际数据优化
  sniper: {
    name: '🎯 狙击手型 (The Sniper)',
    description: '专注顶级机会，要求极高置信度和分数。只选择如PEPE 92.6、UNI 85.5级别的机会。',
    confidence_threshold: 94, // 基于实际数据，选择AVAX 94%、UNI 95%级别
    score_gap_threshold: 90.0, // 只选择顶级机会，如UNI 85.5、PEPE 92.6级别
    min_total_games: 3, // 进一步降低，顶级机会稀少
    historical_accuracy_threshold: 0.05, // 极低要求，只要有记录即可
    min_sample_count: 3, // 顶级机会样本稀少
    max_bet_percentage: 20, // 提高风险比例，因为质量极高
    strategy: 'single_bet' as const,
    enable_trend_analysis: false, // 关闭趋势分析
    enable_volume_filter: false, // 关闭交易量过滤
    stop_loss_consecutive: 3, // 严格止损，因为追求精准
    // 🎯 狙击手型：顶级质量过滤器
    enable_win_rate_filter: false, // 关闭胜率过滤，顶级机会胜率可能很低
    min_win_rate_threshold: 0.05, // 极低要求
    enable_top3_rate_filter: true, // 开启保本率过滤，关键指标
    min_top3_rate_threshold: 0.8, // 设置为80%，参考PEPE 84.6%
    enable_avg_rank_filter: false, // 关闭平均排名过滤
    max_avg_rank_threshold: 2.0,
    enable_stability_filter: false, // 关闭稳定性过滤
    max_stability_threshold: 1.0,
    // 🎯 狙击手型：顶级评分过滤器
    enable_absolute_score_filter: true, // 开启绝对分数过滤
    min_absolute_score_threshold: 80.0, // 设置为80，参考UNI 79.6、PEPE 85.9
    enable_relative_score_filter: true, // 开启相对分数过滤
    min_relative_score_threshold: 62.0, // 设置为62，参考UNI 62.9、PEPE 65.9
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 65.0,
    // 🎯 狙击手型：严格的市场动态过滤器
    enable_change_5m_filter: false, // 5分钟数据经常缺失
    min_change_5m_threshold: -0.3,
    max_change_5m_threshold: 0.3,
    enable_change_1h_filter: true, // 开启1小时过滤，确保稳定
    min_change_1h_threshold: -1.0, // 避免大幅波动
    max_change_1h_threshold: 1.5,
    enable_change_4h_filter: false,
    min_change_4h_threshold: -3.0,
    max_change_4h_threshold: 3.0,
    enable_change_24h_filter: true, // 开启24小时过滤
    min_change_24h_threshold: -3.0, // 严格控制波动
    max_change_24h_threshold: 5.0
  },

  // 策略三：动量骑士型 (The Momentum Rider) - 基于实际数据优化
  momentum_rider: {
    name: '🏇 动量骑士型 (The Momentum Rider)',
    description: '积极捕捉机会，允许多项下注。适应市场变化，包容各种波动。适合有经验的用户。',
    confidence_threshold: 80, // 基于实际数据，选择AAVE 80%级别
    score_gap_threshold: 60.0, // 选择中上质量机会，如NEAR 60.1级别
    min_total_games: 2, // 最低要求，积极参与
    historical_accuracy_threshold: 0.05, // 极低要求，积极参与
    min_sample_count: 3, // 最低样本数要求
    max_bet_percentage: 30, // 提高风险比例，积极策略
    strategy: 'multi_bet' as const,
    enable_trend_analysis: false, // 关闭趋势分析
    enable_volume_filter: false, // 关闭交易量过滤
    stop_loss_consecutive: 8, // 允许更多连续失败，适应波动
    // 🏇 动量骑士型：宽松的过滤器设置
    enable_win_rate_filter: false, // 关闭胜率过滤，积极参与
    min_win_rate_threshold: 0.05, // 极低要求
    enable_top3_rate_filter: false, // 关闭保本率过滤，积极参与
    min_top3_rate_threshold: 0.4, // 较低要求
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 6.0,
    enable_stability_filter: false,
    max_stability_threshold: 5.0,
    // 🏇 动量骑士型：适度的评分过滤器
    enable_absolute_score_filter: true, // 开启最基本的分数过滤
    min_absolute_score_threshold: 50.0, // 设置为50，参考DOT 51.0级别
    enable_relative_score_filter: false,
    min_relative_score_threshold: 45.0,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 45.0,
    // 🏇 动量骑士型：包容的市场动态过滤器
    enable_change_5m_filter: false, // 5分钟数据经常缺失
    min_change_5m_threshold: -2.0,
    max_change_5m_threshold: 2.0,
    enable_change_1h_filter: false, // 不限制1小时变化，拥抱波动
    min_change_1h_threshold: -5.0,
    max_change_1h_threshold: 8.0,
    enable_change_4h_filter: false,
    min_change_4h_threshold: -10.0,
    max_change_4h_threshold: 15.0,
    enable_change_24h_filter: false, // 不限制24小时变化，拥抱所有机会
    min_change_24h_threshold: -20.0,
    max_change_24h_threshold: 25.0
  },

  // 策略四：全能平衡型 (The All-Rounder) - 基于实际数据优化
  all_rounder: {
    name: '⚖️ 全能平衡型 (The All-Rounder)',
    description: '平衡的策略配置，兼顾质量和机会数量。适合大多数用户的日常使用。',
    confidence_threshold: 87, // 基于实际数据，选择VANA 87%级别
    score_gap_threshold: 70.0, // 选择好质量机会，如AVAX 69.8级别以上
    min_total_games: 4, // 进一步降低，平衡要求
    historical_accuracy_threshold: 0.12, // 适中要求
    min_sample_count: 6, // 适中样本数要求
    max_bet_percentage: 18, // 保持适中风险
    strategy: 'single_bet' as const,
    enable_trend_analysis: false, // 关闭趋势分析
    enable_volume_filter: false, // 关闭交易量过滤
    stop_loss_consecutive: 5, // 适中的止损设置
    // ⚖️ 全能平衡型：平衡的历史表现过滤器
    enable_win_rate_filter: false, // 关闭胜率过滤，数据不稳定
    min_win_rate_threshold: 0.12, // 适中要求
    enable_top3_rate_filter: true, // 开启保本率过滤，重要指标
    min_top3_rate_threshold: 0.6, // 设置为60%，参考TRUMP 60.0%
    enable_avg_rank_filter: false, // 关闭平均排名过滤
    max_avg_rank_threshold: 3.5,
    enable_stability_filter: false, // 关闭稳定性过滤
    max_stability_threshold: 2.0,
    // ⚖️ 全能平衡型：平衡的评分过滤器
    enable_absolute_score_filter: true, // 开启绝对分数过滤
    min_absolute_score_threshold: 60.0, // 设置为60，参考ETH 60.5级别
    enable_relative_score_filter: true, // 开启相对分数过滤
    min_relative_score_threshold: 53.0, // 设置为53，参考ETH 53.8级别
    enable_h2h_score_filter: false, // 关闭H2H分数过滤
    min_h2h_score_threshold: 55.0,
    // ⚖️ 全能平衡型：适度的市场动态过滤器
    enable_change_5m_filter: false, // 5分钟数据经常缺失
    min_change_5m_threshold: -1.0,
    max_change_5m_threshold: 1.0,
    enable_change_1h_filter: false, // 不限制1小时变化，适应市场
    min_change_1h_threshold: -3.0,
    max_change_1h_threshold: 4.0,
    enable_change_4h_filter: false,
    min_change_4h_threshold: -8.0,
    max_change_4h_threshold: 10.0,
    enable_change_24h_filter: false, // 不限制24小时变化
    min_change_24h_threshold: -10.0,
    max_change_24h_threshold: 15.0
  },

  // 🎯 智能排名策略 - 保持宽松设置
  rank_betting_smart: {
    name: '🎯 智能排名策略',
    description: '基于排名下注，参数要求最宽松。选择TOP1、TOP2等排名进行下注。',
    confidence_threshold: 0, // 排名下注不使用置信度
    score_gap_threshold: 0.0, // 排名下注不使用分数差距
    min_total_games: 1, // 最低要求
    historical_accuracy_threshold: 0.0, // 排名下注不使用历史准确率
    min_sample_count: 1, // 最低要求
    max_bet_percentage: 25, // 适中的风险比例
    strategy: 'rank_betting' as const, // **核心：排名下注策略**
    enable_trend_analysis: false,
    enable_volume_filter: false,
    stop_loss_consecutive: 8, // 允许较多连续失败
    // 🎯 智能排名策略：关闭所有过滤器
    enable_win_rate_filter: false,
    min_win_rate_threshold: 0.0,
    enable_top3_rate_filter: false,
    min_top3_rate_threshold: 0.0,
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 10.0,
    enable_stability_filter: false,
    max_stability_threshold: 10.0,
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0.0,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0.0,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0.0,
    enable_change_5m_filter: false,
    min_change_5m_threshold: -1.0,
    max_change_5m_threshold: 1.0,
    enable_change_1h_filter: false,
    min_change_1h_threshold: -1.0,
    max_change_1h_threshold: 1.0,
    enable_change_4h_filter: false,
    min_change_4h_threshold: -1.0,
    max_change_4h_threshold: 1.0,
    enable_change_24h_filter: false,
    min_change_24h_threshold: -1.0,
    max_change_24h_threshold: 1.0
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
