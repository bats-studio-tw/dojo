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
 * 策略模板 - 四大交易原型
 * 重新设计为四个具有鲜明特色和根本差异的策略原型
 */
export const strategyTemplates = {
  // 🆕 实战模式 - 专门针对真实市场情况优化
  realistic: {
    name: '🎯 实战模式 (Market Reality)',
    description: '基于真实市场数据优化的策略，适应低历史准确率和有限样本的现实情况。推荐新手使用。',
    confidence_threshold: 80, // 降低置信度要求
    score_gap_threshold: 5.0, // 降低分数差距要求
    min_total_games: 5, // 大幅降低最低游戏数要求
    historical_accuracy_threshold: 0.2, // 大幅降低历史准确率要求(20%)
    min_sample_count: 8, // 大幅降低样本数要求
    max_bet_percentage: 12, // 适中的风险比例
    strategy: 'single_bet' as const,
    enable_trend_analysis: false, // 关闭趋势分析，避免过度过滤
    enable_volume_filter: false, // 关闭交易量过滤
    stop_loss_consecutive: 5, // 允许更多连续失败
    // 🎯 实战模式：宽松的历史表现过滤器
    enable_win_rate_filter: false, // 关闭胜率过滤
    min_win_rate_threshold: 0.3,
    enable_top3_rate_filter: false, // 关闭保本率过滤
    min_top3_rate_threshold: 0.4,
    enable_avg_rank_filter: false, // 关闭平均排名过滤
    max_avg_rank_threshold: 4.0,
    enable_stability_filter: false, // 关闭稳定性过滤
    max_stability_threshold: 2.0,
    // 🎯 实战模式：关闭评分过滤器，专注置信度
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0.4,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0.4,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0.4,
    // 🎯 实战模式：关闭市场动态过滤器
    enable_change_5m_filter: false,
    min_change_5m_threshold: -0.2,
    max_change_5m_threshold: 0.2,
    enable_change_1h_filter: false,
    min_change_1h_threshold: -0.2,
    max_change_1h_threshold: 0.2,
    enable_change_4h_filter: false,
    min_change_4h_threshold: -0.2,
    max_change_4h_threshold: 0.2,
    enable_change_24h_filter: false,
    min_change_24h_threshold: -0.2,
    max_change_24h_threshold: 0.2
  },

  // 策略一：磐石型 (The Rock) - 调整为更现实的参数
  rock: {
    name: '🗿 磐石型 (The Rock)',
    description: '相对保守的策略，在可用机会中选择最优质的。适合追求稳定的用户。',
    confidence_threshold: 90, // 降低到90%
    score_gap_threshold: 8.0, // 降低分数差距要求
    min_total_games: 15, // 大幅降低最低游戏数
    historical_accuracy_threshold: 0.4, // 大幅降低到40%
    min_sample_count: 15, // 大幅降低样本数要求
    max_bet_percentage: 8, // 保持低风险
    strategy: 'single_bet' as const,
    enable_trend_analysis: true,
    enable_volume_filter: false, // 关闭交易量过滤
    stop_loss_consecutive: 3, // 降低止损要求
    // 🗿 磐石型：适度的历史表现过滤器
    enable_win_rate_filter: true,
    min_win_rate_threshold: 0.35, // 降低到35%
    enable_top3_rate_filter: false, // 关闭保本率过滤
    min_top3_rate_threshold: 0.5,
    enable_avg_rank_filter: false, // 关闭平均排名过滤
    max_avg_rank_threshold: 3.0,
    enable_stability_filter: false, // 关闭稳定性过滤
    max_stability_threshold: 1.0,
    // 🗿 磐石型：适度的评分过滤器
    enable_absolute_score_filter: true,
    min_absolute_score_threshold: 0.6, // 降低到60%
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0.6,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0.6,
    // 🗿 磐石型：关闭市场动态过滤器
    enable_change_5m_filter: false,
    min_change_5m_threshold: -0.1,
    max_change_5m_threshold: 0.1,
    enable_change_1h_filter: false,
    min_change_1h_threshold: -0.1,
    max_change_1h_threshold: 0.1,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0.0,
    max_change_4h_threshold: 0.0,
    enable_change_24h_filter: false,
    min_change_24h_threshold: 0.0,
    max_change_24h_threshold: 0.0
  },

  // 策略二：狙击手型 (The Sniper) - 调整为更现实的参数
  sniper: {
    name: '🎯 狙击手型 (The Sniper)',
    description: '专注于寻找高置信度和高分数差距的机会。追求精准出击。',
    confidence_threshold: 85, // 保持较高置信度要求
    score_gap_threshold: 15.0, // 保持高分数差距要求
    min_total_games: 10, // 降低最低游戏数
    historical_accuracy_threshold: 0.25, // 大幅降低到25%
    min_sample_count: 10, // 大幅降低样本数要求
    max_bet_percentage: 15, // 保持中等风险
    strategy: 'single_bet' as const,
    enable_trend_analysis: false, // 关闭趋势分析
    enable_volume_filter: false, // 关闭交易量过滤
    stop_loss_consecutive: 4, // 适中的止损设置
    // 🎯 狙击手型：专注分数差距的过滤器
    enable_win_rate_filter: false, // 关闭胜率过滤
    min_win_rate_threshold: 0.3,
    enable_top3_rate_filter: false, // 关闭保本率过滤
    min_top3_rate_threshold: 0.4,
    enable_avg_rank_filter: false, // 关闭平均排名过滤
    max_avg_rank_threshold: 4.0,
    enable_stability_filter: false, // 关闭稳定性过滤
    max_stability_threshold: 2.0,
    // 🎯 狙击手型：关闭评分过滤器，专注置信度和分数差距
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0.5,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0.5,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0.5,
    // 🎯 狙击手型：关闭市场动态过滤器
    enable_change_5m_filter: false,
    min_change_5m_threshold: -0.1,
    max_change_5m_threshold: 0.1,
    enable_change_1h_filter: false,
    min_change_1h_threshold: -0.1,
    max_change_1h_threshold: 0.1,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0.0,
    max_change_4h_threshold: 0.0,
    enable_change_24h_filter: false,
    min_change_24h_threshold: 0.0,
    max_change_24h_threshold: 0.0
  },

  // 策略三：动量骑士型 (The Momentum Rider) - 调整为更现实的参数
  momentum_rider: {
    name: '🏇 动量骑士型 (The Momentum Rider)',
    description: '积极捕捉机会，允许多项下注，适合有经验的用户。',
    confidence_threshold: 75, // 降低置信度要求
    score_gap_threshold: 3.0, // 大幅降低分数差距要求
    min_total_games: 8, // 大幅降低最低游戏数
    historical_accuracy_threshold: 0.15, // 大幅降低到15%
    min_sample_count: 8, // 大幅降低样本数要求
    max_bet_percentage: 25, // 保持较高风险比例
    strategy: 'multi_bet' as const,
    enable_trend_analysis: false, // 关闭趋势分析
    enable_volume_filter: false, // 关闭交易量过滤
    stop_loss_consecutive: 6, // 允许更多连续失败
    // 🏇 动量骑士型：宽松的过滤器设置
    enable_win_rate_filter: false, // 关闭所有历史表现过滤器
    min_win_rate_threshold: 0.2,
    enable_top3_rate_filter: false,
    min_top3_rate_threshold: 0.3,
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 5.0,
    enable_stability_filter: false,
    max_stability_threshold: 3.0,
    // 🏇 动量骑士型：关闭评分过滤器
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0.4,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0.4,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0.4,
    // 🏇 动量骑士型：关闭市场动态过滤器
    enable_change_5m_filter: false,
    min_change_5m_threshold: -0.2,
    max_change_5m_threshold: 0.2,
    enable_change_1h_filter: false,
    min_change_1h_threshold: -0.2,
    max_change_1h_threshold: 0.2,
    enable_change_4h_filter: false,
    min_change_4h_threshold: -0.2,
    max_change_4h_threshold: 0.2,
    enable_change_24h_filter: false,
    min_change_24h_threshold: -0.2,
    max_change_24h_threshold: 0.2
  },

  // 策略四：全能平衡型 (The All-Rounder) - 调整为更现实的参数
  all_rounder: {
    name: '⚖️ 全能平衡型 (The All-Rounder)',
    description: '平衡的策略配置，适合大多数用户的日常使用。',
    confidence_threshold: 80, // 降低置信度要求
    score_gap_threshold: 6.0, // 降低分数差距要求
    min_total_games: 10, // 大幅降低最低游戏数
    historical_accuracy_threshold: 0.3, // 大幅降低到30%
    min_sample_count: 12, // 大幅降低样本数要求
    max_bet_percentage: 18, // 保持适中风险
    strategy: 'single_bet' as const,
    enable_trend_analysis: false, // 关闭趋势分析
    enable_volume_filter: false, // 关闭交易量过滤
    stop_loss_consecutive: 4, // 适中的止损设置
    // ⚖️ 全能平衡型：适度的历史表现过滤器
    enable_win_rate_filter: true,
    min_win_rate_threshold: 0.25, // 降低到25%
    enable_top3_rate_filter: false, // 关闭保本率过滤
    min_top3_rate_threshold: 0.4,
    enable_avg_rank_filter: false, // 关闭平均排名过滤
    max_avg_rank_threshold: 4.0,
    enable_stability_filter: false, // 关闭稳定性过滤
    max_stability_threshold: 1.5,
    // ⚖️ 全能平衡型：适度的评分过滤器
    enable_absolute_score_filter: false, // 关闭绝对分数过滤
    min_absolute_score_threshold: 0.5,
    enable_relative_score_filter: false, // 关闭相对分数过滤
    min_relative_score_threshold: 0.5,
    enable_h2h_score_filter: false, // 关闭H2H分数过滤
    min_h2h_score_threshold: 0.5,
    // ⚖️ 全能平衡型：关闭市场动态过滤器
    enable_change_5m_filter: false,
    min_change_5m_threshold: -0.1,
    max_change_5m_threshold: 0.1,
    enable_change_1h_filter: false,
    min_change_1h_threshold: -0.1,
    max_change_1h_threshold: 0.1,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0.0,
    max_change_4h_threshold: 0.0,
    enable_change_24h_filter: false,
    min_change_24h_threshold: 0.0,
    max_change_24h_threshold: 0.0
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
  // 配置状态
  const config = reactive<AutoBettingConfig>({
    jwt_token: '',
    bet_amount: 200,
    daily_stop_loss_percentage: 15,
    confidence_threshold: 80, // 降低默认置信度要求
    score_gap_threshold: 4.0, // 降低默认分数差距要求
    min_total_games: 8, // 大幅降低最低游戏数要求
    strategy: 'single_bet' as 'single_bet' | 'multi_bet' | 'hedge_bet' | 'rank_betting',
    historical_accuracy_threshold: 0.25, // 大幅降低历史准确率要求到25%
    min_sample_count: 10, // 大幅降低样本数要求
    max_bet_percentage: 15,
    enable_trend_analysis: false, // 默认关闭趋势分析
    enable_volume_filter: false, // 默认关闭交易量过滤
    stop_loss_consecutive: 5, // 增加止损容忍度
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
    rank_betting_enabled_ranks: [1, 2, 3],
    rank_betting_amount_per_rank: 200,
    rank_betting_different_amounts: false,
    rank_betting_rank1_amount: 200,
    rank_betting_rank2_amount: 200,
    rank_betting_rank3_amount: 200,
    rank_betting_max_ranks: 5,

    // 🆕 新增历史表现过滤器 (默认全部关闭，避免过度过滤)
    enable_win_rate_filter: false,
    min_win_rate_threshold: 0.3, // 降低到30%
    enable_top3_rate_filter: false,
    min_top3_rate_threshold: 0.4, // 降低到40%
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 4.0, // 提高到4.0
    enable_stability_filter: false,
    max_stability_threshold: 2.0, // 提高到2.0

    // 🆕 新增评分过滤器 (默认全部关闭，专注置信度)
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0.5, // 降低到50%
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0.5, // 降低到50%
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0.5, // 降低到50%

    // 🆕 新增市场动态过滤器 (默认全部关闭)
    enable_change_5m_filter: false,
    min_change_5m_threshold: -0.1, // 扩大范围
    max_change_5m_threshold: 0.1,
    enable_change_1h_filter: false,
    min_change_1h_threshold: -0.1, // 扩大范围
    max_change_1h_threshold: 0.1,
    enable_change_4h_filter: false,
    min_change_4h_threshold: -0.2, // 扩大范围
    max_change_4h_threshold: 0.2,
    enable_change_24h_filter: false,
    min_change_24h_threshold: -0.2, // 扩大范围
    max_change_24h_threshold: 0.2
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

  // 切换到自定义策略模式
  const switchToCustomMode = () => {
    customStrategyMode.value = true;
    selectedTemplate.value = '';
    window.$message?.info('已切换到自定义策略模式');
  };

  // 重置为模板模式
  const resetToTemplateMode = () => {
    customStrategyMode.value = false;
    selectedTemplate.value = '';
    window.$message?.info('已返回模板模式，请选择一个预设策略模板');
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
    initializeConfig
  };
};
