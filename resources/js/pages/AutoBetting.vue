<template>
  <DefaultLayout>
    <Head title="自动下注控制" />

    <!-- 身份验证模态框 -->
    <WalletSetup :visible="!isTokenValidated" @validated="onTokenValidated" />

    <div
      v-if="isTokenValidated"
      class="min-h-screen from-slate-900 via-purple-900 to-slate-900 bg-gradient-to-br p-3 sm:p-6"
    >
      <div class="mx-auto max-w-7xl">
        <!-- 导航栏 -->
        <div class="mb-6 flex items-center justify-between">
          <a
            href="/"
            class="flex items-center rounded-lg bg-slate-600 px-4 py-2 text-white transition-colors duration-200 space-x-2 hover:bg-slate-700"
          >
            <span>📊</span>
            <span>返回数据面板</span>
          </a>
          <div class="flex-1 text-center">
            <h1 class="text-3xl text-white font-bold">🤖 自动下注控制中心</h1>
            <p class="text-gray-300">基于数据驱动的智能下注系统</p>
          </div>
          <div class="w-32"></div>
        </div>

        <!-- 使用标签页组织主要功能 -->
        <n-tabs default-value="status" type="card" size="large" class="mb-6">
          <!-- 系统状态监控 -->
          <n-tab-pane name="status" tab="📈 系统状态">
            <div class="space-y-6">
              <!-- 系统状态 -->
              <SystemStatus
                :user-info="userInfo"
                :auto-betting-status="autoBettingStatus"
                :current-analysis="currentAnalysis"
                :current-u-i-d="currentUID"
                :toggle-loading="toggleLoading"
                :execute-loading="executeLoading"
                :analysis-loading="analysisLoading"
                @reconnect-token="reconnectToken"
                @start-auto-betting="startAutoBetting"
                @stop-auto-betting="stopAutoBetting"
                @execute-auto-betting="executeAutoBetting"
                @refresh-analysis="refreshAnalysis"
              />

              <!-- 调试面板 -->
              <DebugPanel
                :debug-info="debugInfo"
                :is-monitoring-rounds="isMonitoringRounds"
                :last-known-round-id="lastKnownRoundId"
                :auto-betting-status="autoBettingStatus"
                :strategy-validation="strategyValidation"
                :diagnostics-loading="diagnosticsLoading"
                @run-diagnostics="runApiDiagnostics"
              />
            </div>
          </n-tab-pane>

          <!-- 策略配置 -->
          <n-tab-pane name="strategy" tab="🎯 策略配置">
            <StrategyConfig
              :strategy-templates="strategyTemplates"
              :selected-template="selectedTemplate"
              :custom-strategy-mode="customStrategyMode"
              :strategy-validation="strategyValidation"
              :backtest-results="backtestResults"
              :backtest-loading="backtestLoading"
              :execute-loading="executeLoading"
              :has-history-data="!!predictionStore.predictionHistory?.length"
              @apply-template="applyStrategyTemplate"
              @switch-to-custom="switchToCustomMode"
              @reset-to-template="resetToTemplateMode"
              @execute-strategy="executeStrategyBetting"
              @run-backtest="runBacktest"
            />
          </n-tab-pane>

          <!-- 参数配置 -->
          <n-tab-pane name="config" tab="⚙️ 参数配置">
            <div class="space-y-6">
              <!-- 配置模式说明 -->
              <div
                v-if="customStrategyMode || selectedTemplate"
                class="border rounded-lg p-3"
                :class="
                  customStrategyMode ? 'border-orange-500/30 bg-orange-500/5' : 'border-blue-500/30 bg-blue-500/5'
                "
              >
                <div class="flex items-center space-x-2">
                  <span :class="customStrategyMode ? 'text-orange-400' : 'text-blue-400'">
                    {{ customStrategyMode ? '🎨' : '📋' }}
                  </span>
                  <span class="text-sm font-medium" :class="customStrategyMode ? 'text-orange-400' : 'text-blue-400'">
                    {{ customStrategyMode ? '自定义策略模式已激活' : `当前模板: ${getTemplateName(selectedTemplate)}` }}
                  </span>
                </div>
                <div class="mt-1 text-xs text-gray-400">
                  {{
                    customStrategyMode
                      ? '所有参数都可以自由调整，变更会实时应用到策略验证中。'
                      : '您可以在此基础上微调参数，或切换到自定义模式进行完全控制。'
                  }}
                </div>
              </div>

              <ConfigPanel
                :config="config"
                :auto-betting-running="autoBettingStatus.is_running"
                :config-saving="configSaving"
                @save-config="manualSaveConfig"
              />

              <!-- 指定排名下注配置 -->
              <div v-if="config.strategy === 'rank_betting'" class="border-t border-gray-600 pt-6">
                <h4 class="mb-4 text-lg text-white font-semibold">🎯 指定排名下注设置</h4>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                  <!-- 排名选择 -->
                  <div class="space-y-3">
                    <label class="text-sm text-gray-300 font-medium">选择要下注的排名</label>
                    <div class="flex flex-wrap gap-2">
                      <n-checkbox
                        v-for="rank in [1, 2, 3, 4, 5]"
                        :key="rank"
                        :checked="config.rank_betting_enabled_ranks.includes(rank)"
                        @update:checked="(checked: boolean) => toggleRankBetting(rank, checked)"
                        :disabled="autoBettingStatus.is_running"
                      >
                        <span class="text-sm text-gray-300">第{{ rank }}名</span>
                      </n-checkbox>
                    </div>
                    <div class="text-xs text-gray-400">
                      已选择: {{ config.rank_betting_enabled_ranks.join('、') }}名
                    </div>
                  </div>

                  <!-- 金额设置 -->
                  <div class="space-y-3">
                    <n-checkbox
                      v-model:checked="config.rank_betting_different_amounts"
                      :disabled="autoBettingStatus.is_running"
                    >
                      <span class="text-sm text-gray-300">为不同排名设置不同金额</span>
                    </n-checkbox>

                    <!-- 统一金额 -->
                    <div v-if="!config.rank_betting_different_amounts" class="space-y-2">
                      <label class="text-sm text-gray-300 font-medium">每个排名下注金额</label>
                      <n-input-number
                        v-model:value="config.rank_betting_amount_per_rank"
                        :min="200"
                        :max="1000"
                        :step="100"
                        :disabled="autoBettingStatus.is_running"
                        class="w-full"
                      />
                    </div>
                  </div>
                </div>

                <!-- 排名下注预览 -->
                <div class="mt-4 border border-blue-500/30 rounded-lg bg-blue-500/5 p-3">
                  <div class="mb-2 text-sm text-blue-400 font-medium">📋 下注预览</div>
                  <div class="text-xs text-gray-300 space-y-1">
                    <div v-for="rank in config.rank_betting_enabled_ranks" :key="rank">
                      第{{ rank }}名: ${{ getRankBettingAmount(rank).toFixed(2) }}
                    </div>
                    <div class="mt-2 border-t border-blue-500/30 pt-1">
                      <strong>每局总金额: ${{ getTotalRankBettingAmount().toFixed(2) }}</strong>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 配置同步状态 -->
              <div v-if="configSyncStatus" class="text-center text-xs text-gray-400">
                <div v-if="configSyncStatus.type === 'success'" class="text-green-400">
                  ✅ {{ configSyncStatus.message }}
                </div>
                <div v-else-if="configSyncStatus.type === 'error'" class="text-red-400">
                  ❌ {{ configSyncStatus.message }}
                </div>
                <div v-else class="text-blue-400">ℹ️ {{ configSyncStatus.message }}</div>
              </div>
            </div>
          </n-tab-pane>

          <!-- 数据分析 -->
          <n-tab-pane name="analysis" tab="📊 数据分析">
            <div class="space-y-6">
              <!-- 当前预测展示 -->
              <PredictionDisplay
                :analysis-data="predictionStore.currentAnalysis"
                :analysis-meta="predictionStore.analysisMeta"
                :loading="predictionStore.analysisLoading"
                @refresh="predictionStore.fetchCurrentAnalysis"
              />

              <!-- 预测统计分析 -->
              <PredictionStats
                :exact-rate="predictionStats.calculateRoundBasedStats.value.exactRate"
                :total-rounds="predictionStats.calculatePortfolioStats.value.totalRounds"
                :all-stats="predictionStats.calculateRankBasedStats.value"
                :recent-stats="predictionStats.calculateRecentRankBasedStats.value"
                v-model:recent-rounds-count="recentRoundsCount"
                :max-rounds="predictionStore.totalHistoryRounds"
                :loading="predictionStore.historyLoading"
                @refresh="predictionStore.fetchPredictionHistory"
              />

              <!-- 预测历史对比表格 -->
              <PredictionHistoryTable
                :prediction-data="predictionStats.getPredictionComparisonData.value"
                :loading="predictionStore.historyLoading"
                @refresh="predictionStore.fetchPredictionHistory"
              />
            </div>
          </n-tab-pane>
        </n-tabs>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { ref, onMounted, watch, reactive, computed } from 'vue';

  import { Head } from '@inertiajs/vue3';
  import { getUserInfo, autoBettingApi, gameApi } from '@/utils/api';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import WalletSetup from '@/components/WalletSetup.vue';
  import PredictionDisplay from '@/components/PredictionDisplay.vue';
  import PredictionStats from '@/components/PredictionStats.vue';
  import PredictionHistoryTable from '@/components/PredictionHistoryTable.vue';
  import SystemStatus from '@/components/SystemStatus.vue';
  import DebugPanel from '@/components/DebugPanel.vue';
  import StrategyConfig from '@/components/StrategyConfig.vue';
  import ConfigPanel from '@/components/ConfigPanel.vue';
  import type { UserInfo } from '@/types';
  import { useGamePredictionStore } from '@/stores/gamePrediction';
  import { usePredictionStats } from '@/composables/usePredictionStats';

  // 身份验证状态
  const isTokenValidated = ref(false);
  const currentUID = ref('');
  const userInfo = ref<UserInfo | null>(null);

  // 使用预测相关Store和Composables
  const predictionStore = useGamePredictionStore();
  const recentRoundsCount = ref(50);

  // 使用预测统计composable
  const predictionStats = usePredictionStats(
    computed(() => predictionStore.predictionHistory),
    recentRoundsCount
  );

  // 预设策略模板
  const strategyTemplates = {
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
      confidence_threshold: 0, // 无条件限制
      score_gap_threshold: 0.0,
      min_total_games: 1, // 只要有预测就下注
      historical_accuracy_threshold: 0.0,
      min_sample_count: 1,
      max_bet_percentage: 30,
      strategy: 'rank_betting' as const,
      enable_trend_analysis: false,
      enable_volume_filter: false,
      stop_loss_consecutive: 10 // 较高的容错次数
    }
  };

  // 自动下注配置 - 使用reactive进行深度响应
  const config = reactive({
    jwt_token: '',
    bet_amount: 200,
    daily_stop_loss_percentage: 15,

    // 基础策略参数
    confidence_threshold: 88,
    score_gap_threshold: 6.0,
    min_total_games: 25,
    strategy: 'single_bet' as 'single_bet' | 'multi_bet' | 'hedge_bet' | 'rank_betting',

    // 高级策略参数
    historical_accuracy_threshold: 0.7,
    min_sample_count: 40,
    max_bet_percentage: 15,
    enable_trend_analysis: true,
    enable_volume_filter: true,
    stop_loss_consecutive: 4,

    // 资金管理参数
    enable_kelly_criterion: false,
    kelly_fraction: 0.25,
    enable_martingale: false,
    martingale_multiplier: 2.0,
    max_martingale_steps: 3,

    // 时间过滤参数
    enable_time_filter: false,
    allowed_hours_start: 9,
    allowed_hours_end: 21,

    // 市场条件过滤
    enable_volatility_filter: false,
    max_volatility_threshold: 0.8,
    min_liquidity_threshold: 1000000,

    is_active: false,

    // 指定排名下注相关配置
    rank_betting_enabled_ranks: [1, 2, 3], // 默认下注前三名
    rank_betting_amount_per_rank: 200, // 每个排名的下注金额
    rank_betting_different_amounts: false, // 是否为不同排名设置不同金额
    rank_betting_rank1_amount: 200, // 第1名下注金额
    rank_betting_rank2_amount: 200, // 第2名下注金额
    rank_betting_rank3_amount: 200, // 第3名下注金额
    rank_betting_max_ranks: 5 // 最多支持前几名（1-5名）
  });

  // 配置同步状态
  const configSaving = ref(false);
  const configSyncStatus = ref<{ type: 'success' | 'error' | 'info'; message: string } | null>(null);

  // 策略相关状态
  const selectedTemplate = ref('');
  const customStrategyMode = ref(false);
  const strategyValidation = ref<{
    matches: any[];
    total_matched: number;
    estimated_profit: number;
    risk_level: string;
    success_probability: number;
    balance_sufficient: boolean;
    required_balance: number;
    actual_balance: number;
  } | null>(null);

  // 策略回测状态
  const backtestLoading = ref(false);
  const backtestResults = ref<{
    total_rounds: number;
    win_rate: number;
    total_profit: number;
    avg_profit_per_round: number;
    max_drawdown: number;
    sharp_ratio: number;
    best_strategy: string;
  } | null>(null);

  // 自动下注状态
  const autoBettingStatus = ref({
    is_running: false,
    current_round_id: null,
    last_bet_at: null,
    total_bets: 0,
    total_profit_loss: 0,
    today_profit_loss: 0,
    consecutive_losses: 0,
    last_error: null
  });

  // 当前分析数据 (复用Dashboard的接口类型)
  const currentAnalysis = ref<any>(null);

  // 轮次监控状态
  const lastKnownRoundId = ref<string | null>(null);
  const isMonitoringRounds = ref(false);

  // 加载状态
  const statusLoading = ref(false);
  const toggleLoading = ref(false);
  const executeLoading = ref(false);
  const analysisLoading = ref(false);
  const diagnosticsLoading = ref(false);

  // 调试信息状态
  const debugInfo = reactive({
    showDebugPanel: false,
    roundCheckCount: 0,
    lastRoundCheckTime: '',
    autoTriggerCount: 0,
    lastAutoTriggerTime: '',
    lastExecutionTime: '',
    strategyValidationCount: 0,
    lastValidationTime: '',
    lastBetResults: [] as Array<{
      time: string;
      symbol: string;
      amount: number;
      success: boolean;
      error?: string;
    }>
  });

  // 防抖器用于自动保存
  let saveConfigTimeout: number | null = null;

  // 辅助方法
  const getTemplateName = (templateKey: string): string => {
    return strategyTemplates[templateKey as keyof typeof strategyTemplates]?.name || templateKey;
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

  // 调试日志功能
  const addDebugLog = (level: 'info' | 'warn' | 'error' | 'success', message: string) => {
    const time = new Date().toLocaleTimeString();

    // 输出到控制台
    const consoleMessage = `[AutoBetting ${time}] ${message}`;
    switch (level) {
      case 'error':
        console.error(consoleMessage);
        break;
      case 'warn':
        console.warn(consoleMessage);
        break;
      case 'success':
      case 'info':
      default:
        console.log(consoleMessage);
        break;
    }
  };

  // 数据映射函数：将API返回的数据格式转换为策略验证期望的格式
  const mapPredictionData = (rawPrediction: any): any => {
    return {
      ...rawPrediction,
      // 映射字段名
      confidence: rawPrediction.rank_confidence || rawPrediction.confidence || 0,
      score: rawPrediction.predicted_final_value || rawPrediction.score || 0,
      sample_count: rawPrediction.total_games || rawPrediction.sample_count || 0,
      historical_accuracy: (rawPrediction.win_rate || 0) / 100, // 转换为0-1范围
      // 保留原有字段
      symbol: rawPrediction.symbol,
      predicted_rank: rawPrediction.predicted_rank
    };
  };

  // 评估预测是否符合策略条件
  const evaluatePredictionMatch = (prediction: any): boolean => {
    // 如果是指定排名下注策略，使用不同的逻辑
    if (config.strategy === 'rank_betting') {
      // 指定排名下注：只检查预测排名是否在启用的排名列表中
      return config.rank_betting_enabled_ranks.includes(prediction.predicted_rank);
    }

    // 传统策略的条件检查
    if (prediction.confidence < config.confidence_threshold) return false;
    if (prediction.score < config.score_gap_threshold) return false;
    if (prediction.sample_count < config.min_sample_count) return false;
    if (prediction.historical_accuracy < config.historical_accuracy_threshold) return false;

    // 时间过滤
    if (config.enable_time_filter) {
      const currentHour = new Date().getHours();
      if (currentHour < config.allowed_hours_start || currentHour > config.allowed_hours_end) {
        return false;
      }
    }

    // 趋势分析过滤
    if (config.enable_trend_analysis && prediction.trend_score) {
      if (prediction.trend_score < 0.6) return false;
    }

    // 成交量过滤
    if (config.enable_volume_filter && prediction.volume_score) {
      if (prediction.volume_score < 0.5) return false;
    }

    // 波动率过滤
    if (config.enable_volatility_filter && prediction.volatility) {
      if (prediction.volatility > config.max_volatility_threshold) return false;
    }

    return true;
  };

  // 计算下注金额
  const calculateBetAmount = (prediction: any): number => {
    // 如果是指定排名下注策略，使用排名专用的金额计算
    if (config.strategy === 'rank_betting') {
      return getRankBettingAmount(prediction.predicted_rank);
    }

    // 传统策略的金额计算
    let betAmount = config.bet_amount;
    const walletBalance = userInfo.value?.ojoValue || 0;

    // Kelly准则计算
    if (config.enable_kelly_criterion && walletBalance > 0) {
      const winProbability = prediction.confidence / 100;
      const odds = 1.95; // 假设赔率
      const kellyFraction = (winProbability * odds - 1) / (odds - 1);
      betAmount = Math.min(
        walletBalance * kellyFraction * config.kelly_fraction,
        walletBalance * (config.max_bet_percentage / 100)
      );
    }

    // 确保不超过最大下注比例
    if (walletBalance > 0) {
      betAmount = Math.min(betAmount, walletBalance * (config.max_bet_percentage / 100));
    }

    // 最小下注金额
    betAmount = Math.max(betAmount, 200);

    return Math.round(betAmount);
  };

  // 验证当前策略
  const validateCurrentStrategy = () => {
    debugInfo.strategyValidationCount++;
    debugInfo.lastValidationTime = new Date().toLocaleTimeString();

    if (!currentAnalysis.value?.predictions) {
      strategyValidation.value = null;
      addDebugLog('warn', '❌ 没有预测数据，跳过策略验证');
      return;
    }

    addDebugLog('info', `🎯 开始策略验证 - 共有${currentAnalysis.value.predictions.length}个预测数据`);

    const predictions = currentAnalysis.value.predictions;
    const matches: any[] = [];
    let totalMatchedValue = 0;
    let estimatedProfit = 0;

    // 如果是指定排名下注策略，使用简化的匹配逻辑
    if (config.strategy === 'rank_betting') {
      predictions.forEach((rawPrediction: any) => {
        const prediction = mapPredictionData(rawPrediction);
        const predictedRank = prediction.predicted_rank;

        // 检查预测排名是否在启用的排名列表中
        const isMatch = config.rank_betting_enabled_ranks.includes(predictedRank);

        addDebugLog(
          'info',
          `🎲 排名下注检查 ${prediction.symbol}: 预测排名=${predictedRank}, 启用排名=[${config.rank_betting_enabled_ranks.join(',')}], 匹配=${isMatch ? '✅' : '❌'}`
        );

        if (isMatch) {
          const betAmount = getRankBettingAmount(predictedRank);
          matches.push({
            ...prediction,
            bet_amount: betAmount,
            expected_return: betAmount * 1.95 // 简化的期望回报计算
          });
          totalMatchedValue += betAmount;
          estimatedProfit += betAmount * 0.95 - betAmount; // 假设95%回报率
        }
      });
    } else {
      // 传统策略的复杂匹配逻辑
      predictions.forEach((rawPrediction: any) => {
        // 映射数据格式
        const prediction = mapPredictionData(rawPrediction);
        const isMatch = evaluatePredictionMatch(prediction);

        addDebugLog(
          'info',
          `🎲 检查 ${prediction.symbol}: confidence=${prediction.confidence}%, score=${prediction.score}, 样本=${prediction.sample_count}, 胜率=${(prediction.historical_accuracy * 100).toFixed(1)}%, 匹配=${isMatch ? '✅' : '❌'}`
        );

        if (isMatch) {
          const betAmount = calculateBetAmount(prediction);
          matches.push({
            ...prediction,
            bet_amount: betAmount,
            expected_return: betAmount * (prediction.confidence / 100) * 1.95 // 假设95%回报率
          });
          totalMatchedValue += betAmount;
          estimatedProfit += betAmount * (prediction.confidence / 100) * 0.95 - betAmount;
        }
      });
    }

    const successProbability =
      matches.length > 0 ? matches.reduce((sum, m) => sum + (m.confidence || 70), 0) / matches.length / 100 : 0;

    let riskLevel = 'low';
    const walletBalance = userInfo.value?.ojoValue || 0;
    if (walletBalance > 0) {
      if (totalMatchedValue > walletBalance * 0.2) riskLevel = 'high';
      else if (totalMatchedValue > walletBalance * 0.1) riskLevel = 'medium';
    }

    // 检查实际余额是否足够
    const actualBalance = userInfo.value?.ojoValue || 0;
    const balanceInsufficient = totalMatchedValue > actualBalance;

    strategyValidation.value = {
      matches,
      total_matched: matches.length,
      estimated_profit: estimatedProfit,
      risk_level: riskLevel,
      success_probability: successProbability,
      balance_sufficient: !balanceInsufficient,
      required_balance: totalMatchedValue,
      actual_balance: actualBalance
    };

    // 记录策略验证结果
    if (config.strategy === 'rank_betting') {
      addDebugLog(
        'success',
        `🎯 排名下注策略验证完成: ${matches.length}个匹配, 总金额$${totalMatchedValue.toFixed(2)}`
      );
    } else {
      addDebugLog('success', `🎯 传统策略验证完成: ${matches.length}个匹配, 总金额$${totalMatchedValue.toFixed(2)}`);
    }
  };

  // 应用策略模板
  const applyStrategyTemplate = (templateKey: string) => {
    if (!strategyTemplates[templateKey as keyof typeof strategyTemplates]) return;

    const template = strategyTemplates[templateKey as keyof typeof strategyTemplates];

    // 应用模板参数到配置
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
    validateCurrentStrategy();
  };

  // 切换到自定义策略模式
  const switchToCustomMode = () => {
    customStrategyMode.value = true;
    selectedTemplate.value = '';
    window.$message?.info('已切换到自定义策略模式，现在可以手动调整所有参数');
  };

  // 重置为模板模式
  const resetToTemplateMode = () => {
    customStrategyMode.value = false;
    selectedTemplate.value = '';
    window.$message?.info('已返回模板模式，请选择一个预设策略模板');
  };

  // 从云端加载配置
  const loadConfigFromCloud = async (): Promise<boolean> => {
    if (!currentUID.value) return false;

    try {
      const response = await autoBettingApi.getConfig(currentUID.value);
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
  const saveConfigToCloud = async (): Promise<boolean> => {
    if (!currentUID.value) return false;

    try {
      const response = await autoBettingApi.saveConfig(currentUID.value, config);
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
        Object.assign(config, {
          jwt_token: '',
          bet_amount: 200,
          daily_stop_loss_percentage: 15,
          confidence_threshold: 88,
          score_gap_threshold: 6.0,
          min_total_games: 25,
          strategy: 'single_bet' as 'single_bet' | 'multi_bet' | 'hedge_bet' | 'rank_betting',
          is_active: false
        });
        configSyncStatus.value = { type: 'error', message: '本地配置损坏，已重置为默认配置' };
      }
    }
  };

  // 保存配置到localStorage
  const saveConfigToLocalStorage = () => {
    try {
      localStorage.setItem('autoBettingConfig', JSON.stringify(config));
      if (!currentUID.value) {
        configSyncStatus.value = { type: 'success', message: '配置已保存到本地存储' };
      }
    } catch (error) {
      console.error('保存本地配置失败:', error);
      configSyncStatus.value = { type: 'error', message: '保存本地配置失败' };
    }
  };

  // 自动保存配置（带防抖）
  const autoSaveConfig = async () => {
    if (saveConfigTimeout) {
      clearTimeout(saveConfigTimeout);
    }

    saveConfigTimeout = setTimeout(async () => {
      // 总是保存到localStorage作为备份
      saveConfigToLocalStorage();

      // 如果有UID，也保存到云端
      if (currentUID.value) {
        await saveConfigToCloud();
      }
    }, 1000); // 1秒防抖
  };

  // 手动保存配置
  const manualSaveConfig = async () => {
    configSaving.value = true;

    try {
      // 总是保存到localStorage
      saveConfigToLocalStorage();

      // 如果有UID，也保存到云端
      if (currentUID.value) {
        await saveConfigToCloud();
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

  // 监听配置变化，自动保存
  watch(
    config,
    () => {
      autoSaveConfig();
      validateCurrentStrategy();
    },
    { deep: true, flush: 'post' }
  );

  // 监听当前分析数据变化，自动验证策略
  watch(
    currentAnalysis,
    () => {
      validateCurrentStrategy();
    },
    { deep: true }
  );

  // 执行单次下注的通用方法
  const executeSingleBet = async (
    roundId: string,
    tokenSymbol: string,
    amount: number,
    jwtToken: string
  ): Promise<boolean> => {
    try {
      // 第一步：获取betId
      const betIdResponse = await gameApi.getBetId(roundId, jwtToken);

      if (!betIdResponse.data.success) {
        console.error('获取betId失败:', betIdResponse.data);
        return false;
      }

      const betId = betIdResponse.data.data;

      // 第二步：执行下注
      const betResponse = await gameApi.placeBet(roundId, betId, tokenSymbol, amount, jwtToken);

      if (betResponse.data.success) {
        // 记录下注结果到后端
        await autoBettingApi.recordResult({
          uid: currentUID.value,
          round_id: roundId,
          token_symbol: tokenSymbol,
          amount,
          bet_id: betId,
          success: true,
          result_data: betResponse.data.data
        });

        // 下注成功后重新获取用户信息更新余额
        try {
          const userInfoResponse = await getUserInfo(jwtToken);
          if (userInfoResponse.success && userInfoResponse.obj) {
            userInfo.value = userInfoResponse.obj;
            localStorage.setItem('userInfo', JSON.stringify(userInfo.value));
            console.log('下注后更新余额:', userInfo.value.ojoValue);
          }
        } catch (error) {
          console.warn('下注后更新用户信息失败:', error);
        }

        return true;
      } else {
        console.error('下注失败:', betResponse.data);
        // 记录失败结果
        await autoBettingApi.recordResult({
          uid: currentUID.value,
          round_id: roundId,
          token_symbol: tokenSymbol,
          amount,
          bet_id: betId,
          success: false,
          result_data: betResponse.data
        });
        return false;
      }
    } catch (error) {
      console.error('下注过程出错:', error);
      return false;
    }
  };

  // 检查指定轮次是否已经下过注
  const checkRoundAlreadyBet = async (roundId: string): Promise<boolean> => {
    if (!currentUID.value) {
      addDebugLog('warn', '❌ 缺少用户UID，无法检查重复下注');
      return false;
    }

    try {
      addDebugLog('info', `🔍 检查轮次 ${roundId} 是否已下注...`);
      const response = await autoBettingApi.checkRoundBet(currentUID.value, roundId);

      if (response.data.success) {
        const hasAlreadyBet = response.data.data.has_bet;
        const betCount = response.data.data.bet_count;

        if (hasAlreadyBet) {
          addDebugLog('warn', `⚠️ 轮次 ${roundId} 已存在 ${betCount} 个下注记录，跳过重复下注`);
          return true;
        } else {
          addDebugLog('info', `✅ 轮次 ${roundId} 未发现下注记录，可以继续下注`);
          return false;
        }
      } else {
        addDebugLog('error', `❌ 检查重复下注失败: ${response.data.message}`);
        // 出错时为安全起见，假设已经下过注
        return true;
      }
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : String(error);
      addDebugLog('error', `❌ 检查重复下注异常: ${errorMsg}`);
      // 出错时为安全起见，假设已经下过注
      return true;
    }
  };

  // 监控游戏轮次变化并触发完整的自动下注流程
  const checkRoundChange = async () => {
    debugInfo.roundCheckCount++;
    debugInfo.lastRoundCheckTime = new Date().toLocaleTimeString();

    if (!isTokenValidated.value || !config.jwt_token) {
      addDebugLog('warn', '❌ Token未验证或JWT Token为空，跳过轮次检查');
      return;
    }

    try {
      addDebugLog('info', '📡 开始获取分析数据...');
      const response = await gameApi.getCurrentAnalysis();

      // 详细记录响应数据
      addDebugLog('info', `📡 API响应状态: ${response.status}`);
      addDebugLog(
        'info',
        `📊 响应数据结构: success=${response.data?.success}, hasData=${!!response.data?.data}, hasMeta=${!!response.data?.data?.meta}`
      );

      if (response.data?.meta?.round_id) {
        addDebugLog('info', `🎮 获取到轮次ID: ${response.data.meta.round_id}`);
      }

      if (response.data?.data && Array.isArray(response.data.data)) {
        addDebugLog('info', `🎯 获取到预测数据: ${response.data.data.length}个`);
      } else {
        addDebugLog('warn', '❌ 响应中没有预测数据 (data字段为空或非数组)');
      }

      if (response.data.success && response.data.meta?.round_id) {
        const currentRoundId = response.data.meta.round_id;
        const isNewRound = lastKnownRoundId.value && lastKnownRoundId.value !== currentRoundId;

        // 检测到轮次变化（新游戏开始）
        if (isNewRound) {
          addDebugLog('success', `🎮 检测到新轮次开始: ${lastKnownRoundId.value} → ${currentRoundId}`);

          // 第1步：检查该轮次是否已经下过注（重复下注防呆）
          const alreadyBet = await checkRoundAlreadyBet(currentRoundId);
          if (alreadyBet) {
            addDebugLog('warn', `🚫 轮次 ${currentRoundId} 已存在下注记录，跳过自动下注流程`);

            // 仍然更新数据和余额，但不执行下注
            currentAnalysis.value = {
              predictions: response.data.data,
              meta: response.data.meta
            };
            lastKnownRoundId.value = currentRoundId;

            // 更新余额
            try {
              const userInfoResponse = await getUserInfo(config.jwt_token);
              if (userInfoResponse.success && userInfoResponse.obj) {
                const oldBalance = userInfo.value?.ojoValue || 0;
                userInfo.value = userInfoResponse.obj;
                localStorage.setItem('userInfo', JSON.stringify(userInfo.value));

                const newBalance = userInfo.value.ojoValue;
                const balanceChange = newBalance - oldBalance;

                if (Math.abs(balanceChange) > 0.01) {
                  addDebugLog(
                    'success',
                    `🎲 游戏结算完成！余额变化: ${balanceChange >= 0 ? '+' : ''}$${balanceChange.toFixed(2)}`
                  );
                  window.$message?.info(
                    `🎲 游戏结算完成！余额变化: ${balanceChange >= 0 ? '+' : ''}$${balanceChange.toFixed(2)}`
                  );
                }
              }
            } catch (error) {
              addDebugLog('error', `更新用户信息失败: ${error instanceof Error ? error.message : String(error)}`);
            }

            return; // 提前退出，不执行下注逻辑
          }

          // 第2步：更新余额（结算上一轮的盈亏）
          try {
            const userInfoResponse = await getUserInfo(config.jwt_token);
            if (userInfoResponse.success && userInfoResponse.obj) {
              const oldBalance = userInfo.value?.ojoValue || 0;
              userInfo.value = userInfoResponse.obj;
              localStorage.setItem('userInfo', JSON.stringify(userInfo.value));

              const newBalance = userInfo.value.ojoValue;
              const balanceChange = newBalance - oldBalance;

              addDebugLog(
                'info',
                `💰 余额结算更新: $${oldBalance.toFixed(2)} → $${newBalance.toFixed(2)} (${balanceChange >= 0 ? '+' : ''}${balanceChange.toFixed(2)})`
              );

              // 如果有余额变化，显示提示
              if (Math.abs(balanceChange) > 0.01) {
                addDebugLog(
                  'success',
                  `🎲 游戏结算完成！余额变化: ${balanceChange >= 0 ? '+' : ''}$${balanceChange.toFixed(2)}`
                );
                window.$message?.info(
                  `🎲 游戏结算完成！余额变化: ${balanceChange >= 0 ? '+' : ''}$${balanceChange.toFixed(2)}`
                );
              }
            }
          } catch (error) {
            addDebugLog(
              'error',
              `轮次变化时更新用户信息失败: ${error instanceof Error ? error.message : String(error)}`
            );
          }

          // 第3步：更新分析数据
          currentAnalysis.value = {
            predictions: response.data.data,
            meta: response.data.meta
          };
          addDebugLog('info', '📊 更新分析数据完成');

          // 第4步：如果自动下注已启动，触发自动下注流程
          if (autoBettingStatus.value.is_running) {
            debugInfo.autoTriggerCount++;
            debugInfo.lastAutoTriggerTime = new Date().toLocaleTimeString();
            addDebugLog('info', '🤖 自动下注已启动，新轮次开始，正在触发自动下注...');

            // 延迟一点时间让数据更新完成，然后内联执行自动下注
            setTimeout(async () => {
              try {
                // 重新验证策略
                validateCurrentStrategy();
                addDebugLog('info', '🎯 重新验证策略完成');

                // 等待一小段时间让策略验证完成
                await new Promise((resolve) => setTimeout(resolve, 200));

                if (strategyValidation.value?.matches.length && strategyValidation.value?.balance_sufficient) {
                  const totalBetAmount = strategyValidation.value.required_balance;
                  addDebugLog(
                    'success',
                    `🎯 发现符合条件的下注机会: ${strategyValidation.value.matches.length}个游戏，总金额: $${totalBetAmount.toFixed(2)}`
                  );
                  window.$message?.info(
                    `🤖 自动下注触发：发现${strategyValidation.value.matches.length}个符合条件的游戏`
                  );

                  debugInfo.lastExecutionTime = new Date().toLocaleTimeString();
                  addDebugLog('info', `📋 开始执行${strategyValidation.value.matches.length}个下注...`);

                  // 实际执行自动下注逻辑
                  let successCount = 0;
                  let failCount = 0;

                  for (const match of strategyValidation.value.matches) {
                    try {
                      addDebugLog('info', `🎯 正在下注 ${match.symbol}: $${match.bet_amount}`);
                      const betSuccess = await executeSingleBet(
                        currentRoundId,
                        match.symbol,
                        match.bet_amount,
                        config.jwt_token
                      );
                      if (betSuccess) {
                        successCount++;
                        addDebugLog('success', `✅ ${match.symbol} 下注成功: $${match.bet_amount}`);

                        // 记录下注结果到调试信息
                        debugInfo.lastBetResults.push({
                          time: new Date().toLocaleTimeString(),
                          symbol: match.symbol,
                          amount: match.bet_amount,
                          success: true
                        });
                      } else {
                        failCount++;
                        addDebugLog('error', `❌ ${match.symbol} 下注失败: $${match.bet_amount}`);

                        debugInfo.lastBetResults.push({
                          time: new Date().toLocaleTimeString(),
                          symbol: match.symbol,
                          amount: match.bet_amount,
                          success: false,
                          error: 'API调用失败'
                        });
                      }
                    } catch (error) {
                      failCount++;
                      const errorMsg = error instanceof Error ? error.message : String(error);
                      addDebugLog('error', `❌ ${match.symbol} 下注异常: ${errorMsg}`);

                      debugInfo.lastBetResults.push({
                        time: new Date().toLocaleTimeString(),
                        symbol: match.symbol,
                        amount: match.bet_amount,
                        success: false,
                        error: errorMsg
                      });
                    }
                  }

                  // 限制下注结果记录数量
                  if (debugInfo.lastBetResults.length > 20) {
                    debugInfo.lastBetResults = debugInfo.lastBetResults.slice(-15);
                  }

                  // 显示执行结果
                  if (successCount > 0) {
                    addDebugLog('success', `🎉 自动下注完成：成功 ${successCount} 个，失败 ${failCount} 个`);
                    window.$message?.success(`🤖 自动下注完成：成功 ${successCount} 个，失败 ${failCount} 个`);
                  } else {
                    addDebugLog('error', `❌ 自动下注全部失败：${failCount} 个`);
                    window.$message?.error(`❌ 自动下注全部失败：${failCount} 个`);
                  }

                  // 重新加载状态和余额
                  setTimeout(async () => {
                    try {
                      const statusResponse = await autoBettingApi.getStatus(currentUID.value);
                      if (statusResponse.data.success) {
                        autoBettingStatus.value = statusResponse.data.data;
                        addDebugLog('info', '📊 下注后刷新状态完成');
                      }
                    } catch (error) {
                      addDebugLog('error', `刷新状态失败: ${error instanceof Error ? error.message : String(error)}`);
                    }
                  }, 500);

                  // 更新用户余额
                  try {
                    const userInfoResponse = await getUserInfo(config.jwt_token);
                    if (userInfoResponse.success && userInfoResponse.obj) {
                      userInfo.value = userInfoResponse.obj;
                      localStorage.setItem('userInfo', JSON.stringify(userInfo.value));
                      addDebugLog('info', `💰 下注后更新余额: $${userInfo.value.ojoValue.toFixed(2)}`);
                    }
                  } catch (error) {
                    addDebugLog(
                      'error',
                      `下注后更新用户信息失败: ${error instanceof Error ? error.message : String(error)}`
                    );
                  }
                } else if (strategyValidation.value?.matches.length && !strategyValidation.value?.balance_sufficient) {
                  addDebugLog(
                    'warn',
                    `💸 发现${strategyValidation.value.matches.length}个下注机会，但余额不足($${strategyValidation.value.required_balance.toFixed(2)})`
                  );
                  window.$message?.warning(
                    `💸 发现${strategyValidation.value.matches.length}个下注机会，但余额不足($${strategyValidation.value.required_balance.toFixed(2)})`
                  );
                } else {
                  addDebugLog('info', '📊 当前轮次暂无符合策略的下注机会');
                  window.$message?.info('📊 当前轮次暂无符合策略的下注机会');
                }
              } catch (error) {
                const errorMsg = error instanceof Error ? error.message : String(error);
                addDebugLog('error', `自动下注流程执行失败: ${errorMsg}`);
                window.$message?.error('自动下注流程执行失败');
              }
            }, 1000);
          } else {
            addDebugLog('info', '⏸️ 自动下注未启动，仅更新数据');
          }
        } else {
          // 非新轮次的常规检查
          addDebugLog('info', `🔄 常规检查 - 轮次: ${currentRoundId}`);
        }

        // 更新已知轮次ID和分析数据
        lastKnownRoundId.value = currentRoundId;
        if (!isNewRound) {
          currentAnalysis.value = {
            predictions: response.data.data,
            meta: response.data.meta
          };
        }
      } else {
        // 更详细的错误信息
        if (!response.data) {
          addDebugLog('error', '❌ API响应为空');
        } else if (!response.data.success) {
          addDebugLog('error', `❌ API返回失败: ${response.data.message || '未知原因'}`);
        } else if (!response.data.data) {
          addDebugLog('error', '❌ API响应中缺少data字段');
        } else if (!Array.isArray(response.data.data)) {
          addDebugLog('error', '❌ API响应中data字段不是数组');
        } else if (!response.data.meta) {
          addDebugLog('error', '❌ API响应中缺少meta字段');
        } else if (!response.data.meta.round_id) {
          addDebugLog('error', '❌ API响应中缺少round_id字段');
        } else {
          addDebugLog('error', '❌ 获取分析数据失败或数据格式错误 (未知原因)');
        }

        // 记录完整的响应数据用于调试
        addDebugLog('info', `🔍 完整响应数据: ${JSON.stringify(response.data, null, 2).slice(0, 500)}...`);
      }
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : String(error);
      addDebugLog('error', `📡 API调用异常: ${errorMsg}`);

      // 记录更多错误信息
      if (error instanceof Error) {
        addDebugLog('error', `📡 错误堆栈: ${error.stack?.slice(0, 200)}...`);
      }

      // 检查网络连接
      addDebugLog('info', '🌐 检查网络连接状态...');
      if (navigator.onLine) {
        addDebugLog('info', '✅ 网络连接正常');
      } else {
        addDebugLog('error', '❌ 网络连接异常');
      }
    }
  };

  // 获取分析数据
  const fetchAnalysisData = async () => {
    addDebugLog('info', '📡 手动刷新分析数据...');
    analysisLoading.value = true;
    try {
      const response = await gameApi.getCurrentAnalysis();

      // 详细记录响应
      addDebugLog('info', `📡 手动刷新API响应: status=${response.status}, success=${response.data?.success}`);

      if (response.data.success) {
        currentAnalysis.value = {
          predictions: response.data.data,
          meta: response.data.meta
        };
        addDebugLog('success', '✅ 手动刷新分析数据成功');

        // 初始化轮次监控
        if (response.data.meta?.round_id && !lastKnownRoundId.value) {
          lastKnownRoundId.value = response.data.meta.round_id;
          addDebugLog('info', `🎮 初始化轮次监控: ${lastKnownRoundId.value}`);
        }

        // 记录获取到的数据信息
        if (response.data.data && Array.isArray(response.data.data)) {
          addDebugLog('info', `🎯 手动刷新获取到${response.data.data.length}个预测`);
        }
      } else {
        addDebugLog('error', `❌ 手动刷新失败: ${response.data.message || '未知原因'}`);
      }
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : String(error);
      addDebugLog('error', `❌ 手动刷新异常: ${errorMsg}`);
    } finally {
      analysisLoading.value = false;
    }
  };

  // API调用函数
  const loadStatus = async () => {
    if (!currentUID.value) return;

    statusLoading.value = true;
    try {
      const response = await autoBettingApi.getStatus(currentUID.value);
      if (response.data.success) {
        autoBettingStatus.value = response.data.data;
      } else {
        window.$message?.error(response.data.message || '加载状态失败');
      }
    } catch (error) {
      console.error('加载状态失败:', error);
    } finally {
      statusLoading.value = false;
    }
  };

  const startAutoBetting = async () => {
    addDebugLog('info', '🎬 用户点击启动自动下注');
    toggleLoading.value = true;
    try {
      const response = await autoBettingApi.toggle('start', currentUID.value);
      if (response.data.success) {
        addDebugLog('success', '✅ 自动下注启动成功');
        window.$message?.success('自动下注已启动');
        await loadStatus();
      } else {
        addDebugLog('error', `❌ 自动下注启动失败: ${response.data.message || '未知错误'}`);
        window.$message?.error(response.data.message || '启动失败');
      }
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : String(error);
      addDebugLog('error', `❌ 启动自动下注时出错: ${errorMsg}`);
      window.$message?.error('启动失败');
    } finally {
      toggleLoading.value = false;
    }
  };

  const stopAutoBetting = async () => {
    addDebugLog('info', '🛑 用户点击停止自动下注');
    toggleLoading.value = true;
    try {
      const response = await autoBettingApi.toggle('stop', currentUID.value);
      if (response.data.success) {
        addDebugLog('success', '✅ 自动下注停止成功');
        window.$message?.success('自动下注已停止');
        await loadStatus();
      } else {
        addDebugLog('error', `❌ 自动下注停止失败: ${response.data.message || '未知错误'}`);
        window.$message?.error(response.data.message || '停止失败');
      }
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : String(error);
      addDebugLog('error', `❌ 停止自动下注时出错: ${errorMsg}`);
      window.$message?.error('停止失败');
    } finally {
      toggleLoading.value = false;
    }
  };

  const executeAutoBetting = async () => {
    executeLoading.value = true;
    try {
      // 先获取下注建议
      const response = await autoBettingApi.execute(currentUID.value, config);
      if (response.data.success) {
        const { recommended_bets, round_id, jwt_token } = response.data.data;

        // 检查实际余额是否足够
        const totalBetAmount = recommended_bets.reduce((sum: number, bet: any) => sum + bet.bet_amount, 0);
        const actualBalance = userInfo.value?.ojoValue || 0;

        if (totalBetAmount > actualBalance) {
          window.$message?.error(`余额不足！需要 $${totalBetAmount.toFixed(2)}，当前余额 $${actualBalance.toFixed(2)}`);
          return;
        }

        window.$message?.info('开始执行自动下注...');

        let successCount = 0;
        let failCount = 0;

        // 对每个推荐下注执行API调用
        for (const bet of recommended_bets) {
          try {
            const betSuccess = await executeSingleBet(round_id, bet.symbol, bet.bet_amount, jwt_token);
            if (betSuccess) {
              successCount++;
            } else {
              failCount++;
            }
          } catch (error) {
            console.error(`下注失败 ${bet.symbol}:`, error);
            failCount++;
          }
        }

        if (successCount > 0) {
          window.$message?.success(`自动下注完成：成功 ${successCount} 个，失败 ${failCount} 个`);
        } else {
          window.$message?.error('自动下注全部失败');
        }

        await loadStatus();
      } else {
        window.$message?.error(response.data.message || '获取下注建议失败');
      }
    } catch (error) {
      console.error('执行自动下注失败:', error);
      window.$message?.error('执行自动下注失败');
    } finally {
      executeLoading.value = false;
    }
  };

  // 执行策略下注
  const executeStrategyBetting = async () => {
    if (!strategyValidation.value?.matches.length) {
      window.$message?.warning('没有符合条件的游戏可以下注');
      return;
    }

    // 检查余额是否足够
    if (!strategyValidation.value?.balance_sufficient) {
      window.$message?.error(
        `余额不足！需要 $${strategyValidation.value?.required_balance.toFixed(2)}，当前余额 $${strategyValidation.value?.actual_balance.toFixed(2)}`
      );
      return;
    }

    executeLoading.value = true;
    try {
      window.$message?.info('开始执行策略下注...');

      let successCount = 0;
      let failCount = 0;
      const roundId = currentAnalysis.value?.meta?.round_id;

      if (!roundId) {
        window.$message?.error('无法获取当前轮次ID');
        return;
      }

      // 对每个符合条件的游戏执行下注
      for (const match of strategyValidation.value.matches) {
        try {
          const betSuccess = await executeSingleBet(roundId, match.symbol, match.bet_amount, config.jwt_token);
          if (betSuccess) {
            successCount++;
          } else {
            failCount++;
          }
        } catch (error) {
          console.error(`策略下注失败 ${match.symbol}:`, error);
          failCount++;
        }
      }

      if (successCount > 0) {
        window.$message?.success(`策略下注完成：成功 ${successCount} 个，失败 ${failCount} 个`);
      } else {
        window.$message?.error('策略下注全部失败');
      }

      await loadStatus();
      validateCurrentStrategy(); // 重新验证策略
    } catch (error) {
      console.error('执行策略下注失败:', error);
      window.$message?.error('执行策略下注失败');
    } finally {
      executeLoading.value = false;
    }
  };

  // 策略回测
  const runBacktest = async () => {
    if (!predictionStore.predictionHistory?.length) {
      window.$message?.warning('没有足够的历史数据进行回测');
      return;
    }

    backtestLoading.value = true;
    try {
      window.$message?.info('正在运行策略回测...');

      // 模拟回测逻辑
      const history = predictionStore.predictionHistory.slice(0, 50); // 使用最近50轮数据
      let totalProfit = 0;
      let winCount = 0;
      let totalBets = 0;
      const profits: number[] = [];
      let runningProfit = 0;
      let maxDrawdown = 0;
      let peakProfit = 0;

      for (const round of history) {
        if (!round.predictions?.length) continue;

        const matches: any[] = [];
        // 模拟当前策略在历史数据上的表现
        round.predictions.forEach((rawPrediction: any) => {
          const prediction = mapPredictionData(rawPrediction);
          const isMatch = evaluatePredictionMatch(prediction);
          if (isMatch) {
            const betAmount = calculateBetAmount(prediction);
            matches.push({
              ...prediction,
              bet_amount: betAmount
            });
          }
        });

        if (matches.length === 0) continue;

        // 模拟下注结果
        for (const match of matches) {
          totalBets++;
          const betAmount = match.bet_amount;

          // 简化的结果模拟：基于历史准确率计算
          const actualAccuracy = match.historical_accuracy || 0.7;
          const isWin = Math.random() < actualAccuracy;

          if (isWin) {
            const profit = betAmount * 0.95; // 假设95%回报率
            totalProfit += profit;
            runningProfit += profit;
            winCount++;
          } else {
            totalProfit -= betAmount;
            runningProfit -= betAmount;
          }

          profits.push(runningProfit);

          if (runningProfit > peakProfit) {
            peakProfit = runningProfit;
          }

          const currentDrawdown = peakProfit - runningProfit;
          if (currentDrawdown > maxDrawdown) {
            maxDrawdown = currentDrawdown;
          }
        }
      }

      const winRate = totalBets > 0 ? winCount / totalBets : 0;
      const avgProfitPerRound = history.length > 0 ? totalProfit / history.length : 0;

      // 计算夏普比率 (简化版)
      const returns = profits.map((profit, index) => (index > 0 ? profit - profits[index - 1] : profit));
      const avgReturn = returns.reduce((sum, ret) => sum + ret, 0) / returns.length;
      const returnVariance = returns.reduce((sum, ret) => sum + Math.pow(ret - avgReturn, 2), 0) / returns.length;
      const sharpRatio = returnVariance > 0 ? avgReturn / Math.sqrt(returnVariance) : 0;

      // 确定最佳策略
      let bestStrategy = '当前策略';
      if (winRate > 0.7) bestStrategy = '优秀策略';
      else if (winRate > 0.6) bestStrategy = '良好策略';
      else if (winRate < 0.5) bestStrategy = '需要优化';

      backtestResults.value = {
        total_rounds: history.length,
        win_rate: winRate,
        total_profit: totalProfit,
        avg_profit_per_round: avgProfitPerRound,
        max_drawdown: maxDrawdown,
        sharp_ratio: sharpRatio,
        best_strategy: bestStrategy
      };

      window.$message?.success('策略回测完成');
    } catch (error) {
      console.error('回测失败:', error);
      window.$message?.error('策略回测失败');
    } finally {
      backtestLoading.value = false;
    }
  };

  const refreshAnalysis = () => fetchAnalysisData();

  // API连接诊断工具
  const runApiDiagnostics = async () => {
    addDebugLog('info', '🔬 开始运行API连接诊断...');
    diagnosticsLoading.value = true;

    try {
      // 1. 测试基本API连接
      addDebugLog('info', '📡 测试基本API连接...');
      try {
        const basicResponse = await fetch('/api/game/current-analysis');
        addDebugLog('info', `📡 基本连接状态: ${basicResponse.status} ${basicResponse.statusText}`);

        if (basicResponse.ok) {
          const responseText = await basicResponse.text();
          addDebugLog('info', `📡 响应长度: ${responseText.length} 字符`);

          try {
            const data = JSON.parse(responseText);
            addDebugLog('info', `📊 JSON解析成功: success=${data.success}, message=${data.message || '无'}`);

            if (data.success && data.data) {
              addDebugLog('success', `✅ API响应正常: 获取到${data.data.length || 0}条数据`);

              if (data.meta) {
                addDebugLog('info', `🎮 元数据: round_id=${data.meta.round_id}, status=${data.meta.status}`);
              }
            } else {
              addDebugLog('warn', `⚠️ API返回失败: ${data.message || '未知原因'}`);
            }
          } catch (jsonError) {
            addDebugLog(
              'error',
              `❌ JSON解析失败: ${jsonError instanceof Error ? jsonError.message : String(jsonError)}`
            );
            addDebugLog('info', `🔍 原始响应前200字符: ${responseText.slice(0, 200)}...`);
          }
        } else {
          addDebugLog('error', `❌ HTTP错误: ${basicResponse.status} ${basicResponse.statusText}`);
        }
      } catch (fetchError) {
        addDebugLog('error', `❌ 连接失败: ${fetchError instanceof Error ? fetchError.message : String(fetchError)}`);
      }

      // 2. 测试缓存状态
      addDebugLog('info', '🗄️ 检查游戏缓存状态...');
      try {
        const cacheResponse = await fetch('/api/game/current-analysis');
        if (cacheResponse.ok) {
          const data = await cacheResponse.json();
          if (data.meta?.source) {
            addDebugLog('info', `📦 数据源: ${data.meta.source}`);
            if (data.meta.source === 'cached_prediction') {
              addDebugLog('success', '✅ 使用缓存预测数据');
            } else if (data.meta.source === 'realtime_calculation') {
              addDebugLog('warn', '⚠️ 使用实时计算 (缓存可能为空)');
            }
          }

          if (data.meta?.current_tokens) {
            addDebugLog('info', `🎯 当前轮次代币: ${data.meta.current_tokens.join(', ')}`);
          }
        }
      } catch (error) {
        addDebugLog('error', `❌ 缓存检查失败: ${error instanceof Error ? error.message : String(error)}`);
      }

      // 3. 测试预测服务
      addDebugLog('info', '🧠 检查预测服务状态...');
      const hasCurrentAnalysis = !!currentAnalysis.value;
      const hasPredictions = !!currentAnalysis.value?.predictions?.length;

      addDebugLog('info', `📊 本地分析数据: ${hasCurrentAnalysis ? '存在' : '不存在'}`);
      addDebugLog(
        'info',
        `🎯 本地预测数据: ${hasPredictions ? `${currentAnalysis.value.predictions.length}条` : '不存在'}`
      );

      // 测试数据映射
      if (hasPredictions && currentAnalysis.value.predictions.length > 0) {
        const samplePrediction = currentAnalysis.value.predictions[0];
        const mappedPrediction = mapPredictionData(samplePrediction);
        addDebugLog(
          'info',
          `🔄 数据映射测试 - 原始字段: rank_confidence=${samplePrediction.rank_confidence}, win_rate=${samplePrediction.win_rate}`
        );
        addDebugLog(
          'info',
          `🔄 数据映射测试 - 映射后: confidence=${mappedPrediction.confidence}, historical_accuracy=${mappedPrediction.historical_accuracy}`
        );
      }

      // 4. 检查数据库状态
      addDebugLog('info', '🗃️ 检查历史数据状态...');
      try {
        const historyResponse = await fetch('/api/game/history');
        if (historyResponse.ok) {
          const historyData = await historyResponse.json();
          if (historyData.success && historyData.data) {
            addDebugLog('success', `✅ 历史数据正常: ${historyData.data.length}条记录`);
          } else {
            addDebugLog('warn', '⚠️ 历史数据为空或异常');
          }
        }
      } catch (error) {
        addDebugLog('error', `❌ 历史数据检查失败: ${error instanceof Error ? error.message : String(error)}`);
      }

      // 5. 网络状态检查
      addDebugLog('info', '🌐 检查网络状态...');
      addDebugLog('info', `📶 在线状态: ${navigator.onLine ? '在线' : '离线'}`);
      addDebugLog('info', `🔗 当前域名: ${window.location.hostname}`);
      addDebugLog('info', `🚪 当前端口: ${window.location.port || '默认'}`);

      // 6. Laravel相关检查
      addDebugLog('info', '🎭 检查Laravel环境...');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      addDebugLog('info', `🔐 CSRF Token: ${csrfToken ? '存在' : '不存在'}`);

      addDebugLog('success', '🔬 API诊断完成！请查看上述日志分析问题原因');
      window.$message?.success('API诊断完成，请查看调试日志');
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : String(error);
      addDebugLog('error', `❌ 诊断过程出错: ${errorMsg}`);
      window.$message?.error('诊断过程出错');
    } finally {
      diagnosticsLoading.value = false;
    }
  };

  // 重新验证Token
  const reconnectToken = () => {
    // 清除所有保存的验证状态
    localStorage.removeItem('tokenValidated');
    localStorage.removeItem('currentUID');
    localStorage.removeItem('tokenSetupData');
    localStorage.removeItem('userInfo');

    // 重置状态
    isTokenValidated.value = false;
    currentUID.value = '';
    userInfo.value = null;
    config.jwt_token = '';

    window.$message?.info('已清除验证状态，请重新验证');
  };

  // Token验证成功回调
  const onTokenValidated = async (data: {
    uid: string;
    jwt_token: string;
    user_stats: any;
    today_stats: any;
    user_info: UserInfo;
  }) => {
    console.log('接收到Token验证成功事件:', data);

    currentUID.value = data.uid;
    config.jwt_token = data.jwt_token;
    userInfo.value = data.user_info;
    isTokenValidated.value = true;

    console.log('设置状态:', {
      currentUID: currentUID.value,
      isTokenValidated: isTokenValidated.value,
      userInfo: userInfo.value
    });

    // 保存验证状态到localStorage
    localStorage.setItem('tokenValidated', 'true');
    localStorage.setItem('currentUID', data.uid);
    if (data.user_info) {
      localStorage.setItem('userInfo', JSON.stringify(data.user_info));
    }

    // 有了UID后，优先从云端加载配置
    const cloudLoaded = await loadConfigFromCloud();
    if (!cloudLoaded) {
      // 云端加载失败，使用本地配置并同步到云端
      await saveConfigToCloud();
    }

    // 刷新状态和数据
    loadStatus();
    fetchAnalysisData();

    // 启动游戏轮次监控
    if (!isMonitoringRounds.value) {
      isMonitoringRounds.value = true;
      setInterval(() => {
        checkRoundChange();
      }, 3000); // 3秒检查一次轮次变化
    }

    // 初始化预测数据
    predictionStore.refreshAllPredictionData();

    console.log('Token验证完成，界面应该切换了');
  };

  onMounted(async () => {
    addDebugLog('info', '🚀 AutoBetting组件初始化开始');

    // 默认显示调试面板
    debugInfo.showDebugPanel = true;

    // 先从localStorage读取配置
    loadConfigFromLocalStorage();
    addDebugLog('info', '📥 从本地存储加载配置完成');

    // 检查Token验证状态
    const savedTokenValidated = localStorage.getItem('tokenValidated');
    const savedUID = localStorage.getItem('currentUID');
    const savedTokenData = localStorage.getItem('tokenSetupData');

    console.log('检查保存的验证状态:', {
      savedTokenValidated,
      savedUID,
      savedTokenData
    });

    if (savedTokenValidated === 'true' && savedUID && savedTokenData) {
      try {
        const tokenData = JSON.parse(savedTokenData);
        currentUID.value = savedUID;
        config.jwt_token = tokenData.jwt_token;
        isTokenValidated.value = true;

        // 恢复用户信息
        const savedUserInfo = localStorage.getItem('userInfo');
        if (savedUserInfo) {
          try {
            userInfo.value = JSON.parse(savedUserInfo);
          } catch (error) {
            console.error('恢复用户信息失败:', error);
          }
        }

        // 重新获取最新的用户信息
        if (tokenData.jwt_token) {
          try {
            const userInfoResponse = await getUserInfo(tokenData.jwt_token);
            userInfo.value = userInfoResponse.obj || userInfoResponse;
            localStorage.setItem('userInfo', JSON.stringify(userInfo.value));
          } catch (error) {
            console.warn('获取最新用户信息失败:', error);
          }
        }

        console.log('自动恢复Token验证状态:', {
          currentUID: currentUID.value,
          isTokenValidated: isTokenValidated.value,
          userInfo: userInfo.value
        });

        // 有UID后，尝试从云端同步配置
        await loadConfigFromCloud();

        loadStatus();
        fetchAnalysisData();

        // 初始化预测数据
        predictionStore.refreshAllPredictionData();

        // 启动游戏轮次监控（较高频率检查轮次变化）
        if (!isMonitoringRounds.value) {
          isMonitoringRounds.value = true;
          setInterval(() => {
            checkRoundChange();
          }, 3000); // 3秒检查一次轮次变化
        }

        // 定时刷新状态和分析数据（较低频率）
        setInterval(() => {
          loadStatus();
          // 定期刷新预测数据
          predictionStore.fetchCurrentAnalysis();
        }, 10000); // 10秒刷新一次状态

        // 预测历史数据刷新频率较低
        setInterval(() => {
          predictionStore.fetchPredictionHistory();
        }, 30000);
      } catch (error) {
        console.error('恢复验证状态失败:', error);
        // 清除错误的保存状态
        localStorage.removeItem('tokenValidated');
        localStorage.removeItem('currentUID');
        localStorage.removeItem('tokenSetupData');
        localStorage.removeItem('userInfo');
      }
    }

    // 无论是否有验证状态，都初始化基础预测数据展示
    predictionStore.refreshAllPredictionData();
  });
</script>

<style scoped>
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
