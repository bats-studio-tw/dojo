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

export const strategyTemplates = {
  conservative: {
    name: '保守型策略',
    description: '高置信度、低风险、小额下注',
    confidence_threshold: 92,
    score_gap_threshold: 8.0,
    min_total_games: 30,
    historical_accuracy_threshold: 0.75,
    min_sample_count: 50,
    max_bet_percentage: 10,
    strategy: 'single_bet' as const,
    enable_trend_analysis: true,
    enable_volume_filter: true,
    stop_loss_consecutive: 3
  },
  aggressive: {
    name: '进取型策略',
    description: '中等置信度、高收益、较大金额',
    confidence_threshold: 85,
    score_gap_threshold: 5.0,
    min_total_games: 20,
    historical_accuracy_threshold: 0.65,
    min_sample_count: 30,
    max_bet_percentage: 25,
    strategy: 'multi_bet' as const,
    enable_trend_analysis: true,
    enable_volume_filter: false,
    stop_loss_consecutive: 5
  },
  balanced: {
    name: '平衡型策略',
    description: '均衡风险收益，适合长期使用',
    confidence_threshold: 88,
    score_gap_threshold: 6.0,
    min_total_games: 25,
    historical_accuracy_threshold: 0.7,
    min_sample_count: 40,
    max_bet_percentage: 15,
    strategy: 'hedge_bet' as const,
    enable_trend_analysis: true,
    enable_volume_filter: true,
    stop_loss_consecutive: 4
  },
  scalping: {
    name: '频繁交易策略',
    description: '低阈值、高频次、快速获利',
    confidence_threshold: 82,
    score_gap_threshold: 4.0,
    min_total_games: 15,
    historical_accuracy_threshold: 0.6,
    min_sample_count: 20,
    max_bet_percentage: 20,
    strategy: 'multi_bet' as const,
    enable_trend_analysis: false,
    enable_volume_filter: false,
    stop_loss_consecutive: 6
  },
  trend_following: {
    name: '趋势跟随策略',
    description: '基于趋势分析的中长期策略',
    confidence_threshold: 90,
    score_gap_threshold: 7.0,
    min_total_games: 35,
    historical_accuracy_threshold: 0.8,
    min_sample_count: 60,
    max_bet_percentage: 12,
    strategy: 'single_bet' as const,
    enable_trend_analysis: true,
    enable_volume_filter: true,
    stop_loss_consecutive: 2
  },
  rank_betting: {
    name: '指定排名下注',
    description: '每局自动下注预测前几名，无其他条件限制',
    confidence_threshold: 0,
    score_gap_threshold: 0.0,
    min_total_games: 1,
    historical_accuracy_threshold: 0.0,
    min_sample_count: 1,
    max_bet_percentage: 30,
    strategy: 'rank_betting' as const,
    enable_trend_analysis: false,
    enable_volume_filter: false,
    stop_loss_consecutive: 10
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

    // 🆕 新增历史表现过滤器
    enable_win_rate_filter: false,
    min_win_rate_threshold: 0.7,
    enable_top3_rate_filter: false,
    min_top3_rate_threshold: 0.5,
    enable_avg_rank_filter: false,
    max_avg_rank_threshold: 3.0,
    enable_stability_filter: false,
    max_stability_threshold: 0.8,

    // 🆕 新增评分过滤器
    enable_absolute_score_filter: false,
    min_absolute_score_threshold: 0.7,
    enable_relative_score_filter: false,
    min_relative_score_threshold: 0.5,
    enable_h2h_score_filter: false,
    min_h2h_score_threshold: 0.7,
    enable_risk_adjusted_filter: false,
    min_risk_adjusted_threshold: 0.6,

    // 🆕 新增市场动态过滤器
    enable_change_5m_filter: false,
    min_change_5m_threshold: 0.0,
    max_change_5m_threshold: 0.0,
    enable_change_1h_filter: false,
    min_change_1h_threshold: 0.0,
    max_change_1h_threshold: 0.0,
    enable_change_4h_filter: false,
    min_change_4h_threshold: 0.0,
    max_change_4h_threshold: 0.0,
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
      name: '自定义策略',
      description: '完全自定义的策略配置，可手动调整所有参数',
      confidence_threshold: config.confidence_threshold,
      score_gap_threshold: config.score_gap_threshold,
      min_total_games: config.min_total_games,
      historical_accuracy_threshold: config.historical_accuracy_threshold,
      min_sample_count: config.min_sample_count,
      max_bet_percentage: config.max_bet_percentage,
      strategy: config.strategy,
      enable_trend_analysis: config.enable_trend_analysis,
      enable_volume_filter: config.enable_volume_filter,
      stop_loss_consecutive: config.stop_loss_consecutive
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
        config.confidence_threshold === template.confidence_threshold &&
        config.score_gap_threshold === template.score_gap_threshold &&
        config.min_total_games === template.min_total_games &&
        config.historical_accuracy_threshold === template.historical_accuracy_threshold &&
        config.min_sample_count === template.min_sample_count &&
        config.max_bet_percentage === template.max_bet_percentage &&
        config.strategy === template.strategy &&
        config.enable_trend_analysis === template.enable_trend_analysis &&
        config.enable_volume_filter === template.enable_volume_filter &&
        config.stop_loss_consecutive === template.stop_loss_consecutive;

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

    Object.assign(config, {
      confidence_threshold: template.confidence_threshold,
      score_gap_threshold: template.score_gap_threshold,
      min_total_games: template.min_total_games,
      historical_accuracy_threshold: template.historical_accuracy_threshold,
      min_sample_count: template.min_sample_count,
      max_bet_percentage: template.max_bet_percentage,
      strategy: template.strategy,
      enable_trend_analysis: template.enable_trend_analysis,
      enable_volume_filter: template.enable_volume_filter,
      stop_loss_consecutive: template.stop_loss_consecutive
    });

    selectedTemplate.value = templateKey;
    customStrategyMode.value = false;
    window.$message?.success(`已应用${template.name}`);
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
