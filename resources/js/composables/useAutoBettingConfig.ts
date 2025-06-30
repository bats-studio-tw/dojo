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
  enable_risk_adjusted_filter: boolean;
  min_risk_adjusted_threshold: number;

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
  // 策略一：磐石型 (The Rock) - 极致保守，追求确定性
  rock: {
    name: '🗿 磐石型 (The Rock)',
    description: '极致的风险规避，只相信确定性。追求低频率、高质量的交易，只在AI非常有把握时出手。',
    confidence_threshold: 95, // 极高置信度要求
    score_gap_threshold: 15.0, // 极高分数差距要求
    min_total_games: 80, // 需要大量历史数据
    historical_accuracy_threshold: 0.85, // 极高历史准确率
    min_sample_count: 100, // 需要充足样本
    max_bet_percentage: 5, // 极低风险比例
    strategy: 'single_bet' as const, // 只做单项下注
    enable_trend_analysis: true,
    enable_volume_filter: true,
    stop_loss_consecutive: 1, // 一次失败就暂停
    // 🗿 磐石型：最严苛的历史表现过滤器
    enable_win_rate_filter: true,
    min_win_rate_threshold: 0.85, // 胜率必须≥85%
    enable_top3_rate_filter: true,
    min_top3_rate_threshold: 0.9, // 保本率必须≥90%
    enable_avg_rank_filter: true,
    max_avg_rank_threshold: 1.5, // 平均排名必须≤1.5
    enable_stability_filter: true,
    max_stability_threshold: 0.2, // 极低波动性
    // 🗿 磐石型：最严苛的评分过滤器
    enable_absolute_score_filter: true,
    min_absolute_score_threshold: 0.9, // 绝对分数≥90%
    enable_relative_score_filter: true,
    min_relative_score_threshold: 0.85, // 相对分数≥85%
    enable_h2h_score_filter: true,
    min_h2h_score_threshold: 0.85, // H2H分数≥85%
    enable_risk_adjusted_filter: true,
    min_risk_adjusted_threshold: 0.9, // 风险调整分数≥90%
    // 🗿 磐石型：极度保守的市场动态过滤器
    enable_change_5m_filter: true,
    min_change_5m_threshold: -0.01,
    max_change_5m_threshold: 0.03, // 只接受温和上涨
    enable_change_1h_filter: true,
    min_change_1h_threshold: -0.02,
    max_change_1h_threshold: 0.05,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0.0,
    max_change_4h_threshold: 0.0,
    enable_change_24h_filter: false,
    min_change_24h_threshold: 0.0,
    max_change_24h_threshold: 0.0
  },

  // 策略二：狙击手型 (The Sniper) - 专注于尋找斷層優勢
  sniper: {
    name: '🎯 狙击手型 (The Sniper)',
    description: '专注于寻找领先者与追赶者有巨大分数差距的机会。核心是「相对优势」而非「绝对强度」。',
    confidence_threshold: 85, // 置信度要求可以稍放宽
    score_gap_threshold: 25.0, // **核心指标：极高的分数差距**
    min_total_games: 30, // 历史数据要求适中
    historical_accuracy_threshold: 0.7, // 历史准确率要求适中
    min_sample_count: 40, // 样本要求适中
    max_bet_percentage: 15, // 中等风险比例
    strategy: 'single_bet' as const, // 专注单项精准狙击
    enable_trend_analysis: true,
    enable_volume_filter: true,
    stop_loss_consecutive: 3, // 允许少量试错
    // 🎯 狙击手型：关注相对优势的历史表现过滤器
    enable_win_rate_filter: true,
    min_win_rate_threshold: 0.7, // 胜率要求适中
    enable_top3_rate_filter: true,
    min_top3_rate_threshold: 0.65, // 保本率要求适中
    enable_avg_rank_filter: true,
    max_avg_rank_threshold: 2.0, // 平均排名要求较高
    enable_stability_filter: false, // 不强调稳定性
    max_stability_threshold: 1.0,
    // 🎯 狙击手型：重视相对强势的评分过滤器
    enable_absolute_score_filter: false, // 不强调绝对分数
    min_absolute_score_threshold: 0.7,
    enable_relative_score_filter: true, // **核心：重视相对分数**
    min_relative_score_threshold: 0.8, // 相对分数要求很高
    enable_h2h_score_filter: true,
    min_h2h_score_threshold: 0.75, // H2H分数要求较高
    enable_risk_adjusted_filter: true,
    min_risk_adjusted_threshold: 0.75,
    // 🎯 狙击手型：适度的市场动态过滤器
    enable_change_5m_filter: true,
    min_change_5m_threshold: 0.0,
    max_change_5m_threshold: 0.08, // 允许一定的市场波动
    enable_change_1h_filter: true,
    min_change_1h_threshold: 0.0,
    max_change_1h_threshold: 0.12,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0.0,
    max_change_4h_threshold: 0.0,
    enable_change_24h_filter: false,
    min_change_24h_threshold: 0.0,
    max_change_24h_threshold: 0.0
  },

  // 策略三：动量骑士型 (The Momentum Rider) - 让利润奔跑
  momentum_rider: {
    name: '🏇 动量骑士型 (The Momentum Rider)',
    description: '利用连胜来动态放大投注，在判断正确的趋势中快速扩大战果。积极的趋势跟随策略。',
    confidence_threshold: 88, // 均衡的入场条件
    score_gap_threshold: 8.0, // 均衡的分数差距要求
    min_total_games: 25, // 入场门槛不高
    historical_accuracy_threshold: 0.65, // 历史准确率要求适中
    min_sample_count: 30, // 样本要求适中
    max_bet_percentage: 30, // **核心：更高的投注比例上限**
    strategy: 'multi_bet' as const, // 允许多项下注
    enable_trend_analysis: true, // **核心：启用趋势分析**
    enable_volume_filter: false, // 不限制交易量
    stop_loss_consecutive: 2, // **核心：严格的止损控制**
    // 🏇 动量骑士型：动态的历史表现过滤器
    enable_win_rate_filter: true,
    min_win_rate_threshold: 0.6, // 胜率要求不高
    enable_top3_rate_filter: true,
    min_top3_rate_threshold: 0.55, // 保本率要求不高
    enable_avg_rank_filter: false, // 不限制平均排名
    max_avg_rank_threshold: 3.0,
    enable_stability_filter: false, // **核心：不要求稳定性**
    max_stability_threshold: 2.0,
    // 🏇 动量骑士型：平衡的评分过滤器
    enable_absolute_score_filter: true,
    min_absolute_score_threshold: 0.7,
    enable_relative_score_filter: true,
    min_relative_score_threshold: 0.65,
    enable_h2h_score_filter: true,
    min_h2h_score_threshold: 0.65,
    enable_risk_adjusted_filter: true,
    min_risk_adjusted_threshold: 0.65,
    // 🏇 动量骑士型：**核心：追逐短期动量的市场过滤器**
    enable_change_5m_filter: true,
    min_change_5m_threshold: 0.01, // **核心：要求正向动量**
    max_change_5m_threshold: 0.15, // 允许较大涨幅
    enable_change_1h_filter: true,
    min_change_1h_threshold: 0.02, // **核心：要求正向动量**
    max_change_1h_threshold: 0.2, // 允许较大涨幅
    enable_change_4h_filter: true,
    min_change_4h_threshold: 0.01,
    max_change_4h_threshold: 0.25,
    enable_change_24h_filter: false,
    min_change_24h_threshold: 0.0,
    max_change_24h_threshold: 0.0
  },

  // 策略四：全能平衡型 (The All-Rounder) - 穩健的基準選擇
  all_rounder: {
    name: '⚖️ 全能平衡型 (The All-Rounder)',
    description: '综合各项指标的平衡策略，在风险和机会之间取得良好平衡。是稳健用户的理想起点。',
    confidence_threshold: 90, // 各项指标均为中上水平
    score_gap_threshold: 10.0, // 中上水平的分数差距
    min_total_games: 40, // 适中的历史数据要求
    historical_accuracy_threshold: 0.75, // 中上水平的历史准确率
    min_sample_count: 50, // 适中的样本要求
    max_bet_percentage: 18, // 适中的风险比例
    strategy: 'single_bet' as const, // 专注于单项优质下注
    enable_trend_analysis: true,
    enable_volume_filter: true,
    stop_loss_consecutive: 3, // 适中的止损设置
    // ⚖️ 全能平衡型：平衡的历史表现过滤器
    enable_win_rate_filter: true,
    min_win_rate_threshold: 0.7, // 平衡的胜率要求
    enable_top3_rate_filter: true,
    min_top3_rate_threshold: 0.65, // 平衡的保本率要求
    enable_avg_rank_filter: true,
    max_avg_rank_threshold: 2.5, // 平衡的排名要求
    enable_stability_filter: true,
    max_stability_threshold: 0.6, // 适度的稳定性要求
    // ⚖️ 全能平衡型：全面的评分过滤器
    enable_absolute_score_filter: true,
    min_absolute_score_threshold: 0.75, // 平衡的绝对分数要求
    enable_relative_score_filter: true,
    min_relative_score_threshold: 0.7, // 平衡的相对分数要求
    enable_h2h_score_filter: true,
    min_h2h_score_threshold: 0.7, // 平衡的H2H分数要求
    enable_risk_adjusted_filter: true,
    min_risk_adjusted_threshold: 0.75, // 平衡的风险调整分数要求
    // ⚖️ 全能平衡型：适度的市场动态过滤器
    enable_change_5m_filter: true,
    min_change_5m_threshold: -0.02, // 允许小幅下跌
    max_change_5m_threshold: 0.08, // 允许适度上涨
    enable_change_1h_filter: true,
    min_change_1h_threshold: -0.05, // 允许适度下跌
    max_change_1h_threshold: 0.12, // 允许适度上涨
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0.0,
    max_change_4h_threshold: 0.0,
    enable_change_24h_filter: false,
    min_change_24h_threshold: 0.0,
    max_change_24h_threshold: 0.0
  }
};

export const useAutoBettingConfig = () => {
  // 配置状态
  const config = reactive<AutoBettingConfig>({
    jwt_token: '',
    bet_amount: 200,
    daily_stop_loss_percentage: 15,
    confidence_threshold: 88,
    score_gap_threshold: 6.0,
    min_total_games: 25,
    strategy: 'single_bet' as 'single_bet' | 'multi_bet' | 'hedge_bet' | 'rank_betting',
    historical_accuracy_threshold: 0.7,
    min_sample_count: 40,
    max_bet_percentage: 15,
    enable_trend_analysis: true,
    enable_volume_filter: true,
    stop_loss_consecutive: 4,
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

    // 🆕 新增历史表现过滤器 (默认使用AI优化策略的中等设置)
    enable_win_rate_filter: false,
    min_win_rate_threshold: 0.65,
    enable_top3_rate_filter: false,
    min_top3_rate_threshold: 0.6,
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 2.8,
    enable_stability_filter: false,
    max_stability_threshold: 0.7,

    // 🆕 新增评分过滤器 (默认使用AI优化策略的平衡设置)
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0.7,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0.65,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0.65,
    enable_risk_adjusted_filter: false,
    min_risk_adjusted_threshold: 0.7,

    // 🆕 新增市场动态过滤器 (默认禁用，用户可根据需要启用)
    enable_change_5m_filter: false,
    min_change_5m_threshold: -0.01,
    max_change_5m_threshold: 0.07,
    enable_change_1h_filter: false,
    min_change_1h_threshold: -0.03,
    max_change_1h_threshold: 0.1,
    enable_change_4h_filter: false,
    min_change_4h_threshold: -0.05,
    max_change_4h_threshold: 0.15,
    enable_change_24h_filter: false,
    min_change_24h_threshold: 0.0,
    max_change_24h_threshold: 0.0
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
      enable_risk_adjusted_filter: config.enable_risk_adjusted_filter,
      min_risk_adjusted_threshold: config.min_risk_adjusted_threshold,

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
        config.enable_risk_adjusted_filter === template.enable_risk_adjusted_filter &&
        config.min_risk_adjusted_threshold === template.min_risk_adjusted_threshold &&
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
      enable_risk_adjusted_filter: template.enable_risk_adjusted_filter,
      min_risk_adjusted_threshold: template.min_risk_adjusted_threshold,

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
