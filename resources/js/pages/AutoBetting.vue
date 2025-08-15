<template>
  <DefaultLayout>
    <Head title="自动下注控制中心" />

    <!-- 身份验证模态框 -->
    <WalletSetup :visible="!isTokenValidated" @validated="handleTokenValidated" />

    <div v-if="isTokenValidated" class="min-h-screen from-slate-900 via-slate-800 to-slate-900 bg-gradient-to-br">
      <!-- 顶部状态栏 -->
      <div class="status-bar">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <!-- 左侧：标题和配置状态 -->
            <div class="flex-1">
              <div class="flex items-center gap-3">
                <div class="icon-container float-animation h-10 w-10 flex items-center justify-center rounded-lg">
                  <span class="text-xl">🤖</span>
                </div>
                <div>
                  <h1 class="gradient-text text-xl font-bold sm:text-2xl">自动下注控制中心</h1>
                </div>
              </div>

              <!-- 配置同步状态 -->
              <div class="mt-3">
                <div
                  v-if="currentUID"
                  class="status-indicator inline-flex items-center gap-2 border border-green-500/20 rounded-full bg-green-500/5 px-3 py-1.5"
                >
                  <div class="pulse-dot h-2 w-2 rounded-full bg-green-400"></div>
                  <span class="text-xs text-green-400 font-medium">
                    配置已云端同步 ({{ currentUID.slice(0, 8) }}...)
                  </span>
                </div>
                <div
                  v-else
                  class="status-indicator inline-flex items-center gap-2 border border-yellow-500/20 rounded-full bg-yellow-500/5 px-3 py-1.5"
                >
                  <div class="pulse-dot h-2 w-2 rounded-full bg-yellow-400"></div>
                  <span class="text-xs text-yellow-400 font-medium">配置本地存储 - 完成验证后可云端同步</span>
                </div>
              </div>
            </div>

            <!-- 右侧：状态指示器 -->
            <div class="flex flex-wrap gap-3 lg:flex-nowrap">
              <!-- WebSocket状态 -->
              <div
                class="status-indicator flex items-center gap-2 border rounded-lg px-3 py-2 text-sm transition-all duration-300"
                :class="getWebSocketStatusClass()"
              >
                <span>{{ getWebSocketStatusIcon() }}</span>
                <span>{{ websocketStatus.message }}</span>
                <button
                  v-if="!isConnected"
                  @click="reconnectWebSocket()"
                  class="text-xs underline opacity-80 transition-opacity hover:opacity-100"
                >
                  重连
                </button>
              </div>

              <!-- 自动下注状态 -->
              <div
                class="status-indicator flex items-center gap-2 border rounded-lg px-3 py-2 text-sm transition-all duration-300"
                :class="getAutoBettingStatusClass()"
              >
                <span>{{ getAutoBettingStatusIcon() }}</span>
                <span>{{ autoBettingStatus.is_running ? '运行中' : '已停止' }}</span>
              </div>

              <!-- 当前策略 -->
              <div
                class="status-indicator border border-blue-500/20 rounded-lg bg-blue-500/5 px-3 py-2 transition-all duration-300 hover:bg-blue-500/10"
              >
                <div class="text-xs text-blue-400">当前策略</div>
                <div class="text-sm text-blue-300 font-medium">{{ currentStrategyName }}</div>
              </div>

              <!-- 用户信息 -->
              <div
                v-if="userInfo"
                class="status-indicator border border-blue-500/20 rounded-lg bg-blue-500/5 px-3 py-2 transition-all duration-300 hover:bg-blue-500/10"
              >
                <div class="text-xs text-blue-400">用户ID</div>
                <div class="flex items-center gap-2">
                  <span class="text-sm text-blue-300 font-mono">{{ userInfo.uid.slice(0, 8) }}...</span>
                  <div class="flex gap-1">
                    <NButton
                      @click="reconnectToken"
                      :disabled="autoBettingStatus.is_running"
                      type="tertiary"
                      size="tiny"
                      class="transition-all duration-200 !h-5 !text-xs"
                    >
                      登出
                    </NButton>
                    <NButton
                      @click="refreshUserInfo"
                      :loading="userInfoRefreshing"
                      :disabled="autoBettingStatus.is_running"
                      type="tertiary"
                      size="tiny"
                      class="transition-all duration-200 !h-5 !text-xs"
                    >
                      刷新用戶信息
                    </NButton>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 主要内容区域 -->
      <div class="mx-auto max-w-7xl p-4 sm:p-6">
        <!-- 标签页导航 -->
        <div class="mb-6">
          <NTabs v-model:value="activeTab" type="line" size="large" class="modern-tabs">
            <!-- 智能控制中心标签页 -->
            <NTabPane name="control">
              <template #tab>
                <div class="flex items-center gap-2">
                  <span class="text-lg">🎛️</span>
                  <span>智能控制中心</span>
                </div>
              </template>

              <div class="border border-white/10 rounded-xl bg-black/20 p-6 backdrop-blur-md">
                <SmartControlCenter
                  :auto-betting-status="autoBettingStatus"
                  :current-analysis="currentAnalysis"
                  :analysis-meta="analysisMeta"
                  :current-round-id="currentRoundId"
                  :current-game-status="currentGameStatus"
                  :current-game-tokens-with-ranks="currentGameTokensWithRanks"
                  :debug-info="debugInfo"
                  :toggle-loading="toggleLoading"
                  :execute-loading="executeLoading"
                  :analysis-loading="analysisLoading"
                  :diagnostics-loading="diagnosticsLoading"
                  :strategy-name="currentStrategyName"
                  :config="config"
                  :config-saving="configSaving"
                  :config-loading="configLoading"
                  :config-sync-status="configSyncStatus"
                  :strategy-validation="strategyValidation"
                  :is-running="autoBettingStatus.is_running"
                  :has-u-i-d="!!currentUID"
                  :user-info="userInfo"
                  :hybrid-predictions="hybridPredictions"
                  :hybrid-analysis-meta="hybridAnalysisMeta"
                  :hybrid-analysis-loading="hybridAnalysisLoading"
                  :refresh-hybrid-analysis="fetchHybridAnalysis"
                  @start-auto-betting="startAutoBetting"
                  @stop-auto-betting="stopAutoBetting"
                  @clear-bet-results="clearBetResults"
                  @manual-save-config="handleManualSaveConfig"
                  @run-api-diagnostics="runApiDiagnostics"
                  @refresh-analysis="refreshAnalysis"
                  @update-config="handleConfigUpdate"
                />
              </div>
            </NTabPane>

            <!-- 历史与分析标签页 -->
            <NTabPane name="history">
              <template #tab>
                <div class="flex items-center gap-2">
                  <span class="text-lg">📊</span>
                  <span>历史与分析</span>
                </div>
              </template>

              <div class="border border-white/10 rounded-xl bg-black/20 p-6 backdrop-blur-md">
                <HistoryAnalysisTab
                  :exact-rate="predictionStats.calculateRoundBasedStats.value?.exactRate || 0"
                  :total-rounds="predictionStats.calculatePortfolioStats.value?.totalRounds || 0"
                  :all-stats="
                    predictionStats.calculateRankBasedStats.value || {
                      rank1: {
                        total: 0,
                        breakeven: 0,
                        loss: 0,
                        firstPlace: 0,
                        breakevenRate: 0,
                        lossRate: 0,
                        firstPlaceRate: 0
                      },
                      rank2: {
                        total: 0,
                        breakeven: 0,
                        loss: 0,
                        firstPlace: 0,
                        breakevenRate: 0,
                        lossRate: 0,
                        firstPlaceRate: 0
                      },
                      rank3: {
                        total: 0,
                        breakeven: 0,
                        loss: 0,
                        firstPlace: 0,
                        breakevenRate: 0,
                        lossRate: 0,
                        firstPlaceRate: 0
                      }
                    }
                  "
                  :recent-stats="
                    predictionStats.calculateRecentRankBasedStats.value || {
                      rank1: {
                        total: 0,
                        breakeven: 0,
                        loss: 0,
                        firstPlace: 0,
                        breakevenRate: 0,
                        lossRate: 0,
                        firstPlaceRate: 0
                      },
                      rank2: {
                        total: 0,
                        breakeven: 0,
                        loss: 0,
                        firstPlace: 0,
                        breakevenRate: 0,
                        lossRate: 0,
                        firstPlaceRate: 0
                      },
                      rank3: {
                        total: 0,
                        breakeven: 0,
                        loss: 0,
                        firstPlace: 0,
                        breakevenRate: 0,
                        lossRate: 0,
                        firstPlaceRate: 0
                      }
                    }
                  "
                  :recent-rounds-count="recentRoundsCount"
                  :max-rounds="predictionHistory.length || 0"
                  :history-loading="predictionStore.historyLoading"
                  :prediction-comparison-data="predictionStats.getPredictionComparisonData.value || []"
                  :momentum-stats="
                    momentumStats.stats.value || {
                      momentumAccuracy: 0,
                      totalRounds: 0,
                      allStats: {
                        rank1: {
                          total: 0,
                          breakeven: 0,
                          loss: 0,
                          firstPlace: 0,
                          breakevenRate: 0,
                          lossRate: 0,
                          firstPlaceRate: 0
                        },
                        rank2: {
                          total: 0,
                          breakeven: 0,
                          loss: 0,
                          firstPlace: 0,
                          breakevenRate: 0,
                          lossRate: 0,
                          firstPlaceRate: 0
                        },
                        rank3: {
                          total: 0,
                          breakeven: 0,
                          loss: 0,
                          firstPlace: 0,
                          breakevenRate: 0,
                          lossRate: 0,
                          firstPlaceRate: 0
                        }
                      },
                      recentStats: {
                        rank1: {
                          total: 0,
                          breakeven: 0,
                          loss: 0,
                          firstPlace: 0,
                          breakevenRate: 0,
                          lossRate: 0,
                          firstPlaceRate: 0
                        },
                        rank2: {
                          total: 0,
                          breakeven: 0,
                          loss: 0,
                          firstPlace: 0,
                          breakevenRate: 0,
                          lossRate: 0,
                          firstPlaceRate: 0
                        },
                        rank3: {
                          total: 0,
                          breakeven: 0,
                          loss: 0,
                          firstPlace: 0,
                          breakevenRate: 0,
                          lossRate: 0,
                          firstPlaceRate: 0
                        }
                      },
                      averageMomentumScore: 0,
                      averageConfidence: 0
                    }
                  "
                  :momentum-loading="momentumHistoryLoading"
                  :momentum-recent-rounds-count="momentumRecentRoundsCount"
                  :momentum-max-rounds="Math.max(500, momentumPredictionHistory.length || 0)"
                  @refresh-prediction-history="refreshPredictionHistory"
                  @refresh-momentum-history="refreshMomentumHistory"
                  @update:recent-rounds-count="updateRecentRoundsCount"
                  @update:momentum-recent-rounds-count="updateMomentumRecentRoundsCount"
                />
              </div>
            </NTabPane>
          </NTabs>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
  import { NTabs, NTabPane, NButton } from 'naive-ui';
  import { Head } from '@inertiajs/vue3';
  import { storeToRefs } from 'pinia';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import WalletSetup from '@/components/WalletSetup.vue';
  import SmartControlCenter from '@/components/SmartControlCenter.vue';
  import HistoryAnalysisTab from '@/components/HistoryAnalysisTab.vue';
  // 导入composables和stores
  import { useAutoBettingConfig } from '@/composables/useAutoBettingConfig';
  import type { AutoBettingConfig } from '@/composables/useAutoBettingConfig';
  import { useAutoBettingControl } from '@/composables/useAutoBettingControl';
  import { useGamePredictionStore } from '@/stores/gamePrediction';
  import { usePredictionStats } from '@/composables/usePredictionStats';
  import { useMomentumPredictionStats } from '@/composables/useMomentumPredictionStats';
  import { useConditionBuilder } from '@/composables/useConditionBuilder';
  import type { StrategyValidation } from '@/types/autoBetting';
  import type { UserInfo } from '@/types';
  import type { MomentumPredictionHistoryRound } from '@/composables/useMomentumPredictionStats';

  import { autoBettingApi, gameApi, getUserInfo, networkUtils, jwtTokenUtils } from '@/utils/api';
  import { canBet } from '@/utils/statusUtils';
  import { websocketManager } from '@/utils/websocketManager';

  // 初始化composables和stores
  const configComposable = useAutoBettingConfig();
  const controlComposable = useAutoBettingControl();
  const predictionStore = useGamePredictionStore();
  const { evaluateDynamicConditions } = useConditionBuilder();

  // 从store中获取响应式数据
  const {
    predictionHistory,
    currentAnalysis,
    analysisMeta,
    currentRoundId,
    currentGameStatus,
    currentGameTokensWithRanks,
    analysisLoading,
    hybridPredictions,
    hybridAnalysisMeta,
    hybridAnalysisLoading
  } = storeToRefs(predictionStore);

  // 使用新的WebSocket管理器
  const websocketStatus = websocketManager.websocketStatus;
  const isConnected = websocketManager.isConnected;

  // 从store中获取方法
  const { fetchHybridAnalysis } = predictionStore;

  // 从store中获取方法
  // const { reconnectWebSocket } = predictionStore; // 已在下面定义

  // 从composables中解构状态和方法
  const { config, configSaving, configLoading, configSyncStatus, manualSaveConfig, initializeConfig } =
    configComposable;

  const {
    isTokenValidated,
    currentUID,
    userInfo,
    autoBettingStatus,
    isMonitoringRounds,
    debugInfo,
    toggleLoading,
    executeLoading,
    diagnosticsLoading,
    startAutoBetting,
    stopAutoBetting,
    runApiDiagnostics,
    reconnectToken,
    restoreAuthState,
    executeSingleBet,
    loadStatus
  } = controlComposable;

  // 🔧 自定义Token验证处理函数 - 修复JWT Token同步问题
  const handleTokenValidated = async (data: {
    uid: string;
    jwt_token: string;
    user_stats: any;
    today_stats: any;
    user_info: UserInfo;
  }) => {
    console.log('🔑 开始处理Token验证...');

    // 🔧 关键修复：先同步JWT Token到配置中
    if (data.jwt_token) {
      console.log('🔑 [handleTokenValidated] 开始同步JWT Token到配置:', `${data.jwt_token.slice(0, 20)}...`);
      console.log(
        '🔑 [handleTokenValidated] 同步前 config.jwt_token:',
        config.jwt_token ? `${config.jwt_token.slice(0, 20)}...` : 'null'
      );

      config.jwt_token = data.jwt_token;

      console.log('✅ [handleTokenValidated] JWT Token已同步到自动下注配置中:', `${data.jwt_token.slice(0, 20)}...`);
      console.log(
        '✅ [handleTokenValidated] 同步后 config.jwt_token:',
        config.jwt_token ? `${config.jwt_token.slice(0, 20)}...` : 'null'
      );
    }

    // 然后调用原始的验证回调
    await controlComposable.onTokenValidated(data);

    // 🔧 关键修复：Token验证成功后，重新加载该用户的配置
    if (data.uid) {
      console.log('🔄 [handleTokenValidated] Token验证成功，重新加载用户配置...');
      console.log(
        '🔑 [handleTokenValidated] 加载云端配置前 config.jwt_token:',
        config.jwt_token ? `${config.jwt_token.slice(0, 20)}...` : 'null'
      );

      await configComposable.loadConfigFromCloud(data.uid);

      console.log(
        '🔑 [handleTokenValidated] 加载云端配置后 config.jwt_token:',
        config.jwt_token ? `${config.jwt_token.slice(0, 20)}...` : 'null'
      );
    }

    console.log('✅ Token验证和配置同步完成');
  };

  // 使用新的WebSocket管理器重连方法
  const reconnectWebSocket = () => {
    websocketManager.manualReconnect();
  };

  // 🔧 修复：处理SmartControlCenter的config更新
  const handleConfigUpdate = (newConfig: AutoBettingConfig) => {
    console.log(
      '🔄 [AutoBetting] handleConfigUpdate called with:',
      JSON.stringify(newConfig.dynamic_conditions, null, 2)
    );
    // 更新本地config
    Object.assign(config, newConfig);

    // 触发自动保存
    configComposable.autoSaveConfig(currentUID.value);
  };

  // 🔧 修复：处理手动保存配置
  const handleManualSaveConfig = () => {
    console.log('💾 [AutoBetting] 处理手动保存配置请求...', { uid: currentUID.value });
    manualSaveConfig(currentUID.value);
  };

  // 标签页状态
  const activeTab = ref('control');

  // 预测统计相关
  const recentRoundsCount = ref(50);
  const predictionStats = usePredictionStats(predictionHistory, recentRoundsCount);

  // 动能预测历史数据
  const momentumPredictionHistory = ref<MomentumPredictionHistoryRound[]>([]);
  const momentumHistoryLoading = ref(false);
  const momentumRecentRoundsCount = ref(50);
  const momentumStats = useMomentumPredictionStats(momentumPredictionHistory, momentumRecentRoundsCount);

  // 策略验证状态
  const strategyValidation = ref<StrategyValidation | null>(null);

  // 🔧 新增：用户信息刷新状态
  const userInfoRefreshing = ref(false);

  // 🔧 新增：防抖机制，避免短时间内重复执行
  const isExecuting = ref(false);
  const executionTimeout = ref<NodeJS.Timeout | null>(null);

  // ==================== 工具函数 ====================

  // WebSocket状态样式
  const getWebSocketStatusClass = () => {
    const status = websocketStatus.value.status;
    switch (status) {
      case 'connected':
        return 'bg-green-500/10 border border-green-500/20 text-green-400';
      case 'connecting':
        return 'bg-yellow-500/10 border border-yellow-500/20 text-yellow-400';
      case 'disconnected':
        return 'bg-gray-500/10 border border-gray-500/20 text-gray-400';
      case 'error':
        return 'bg-red-500/10 border border-red-500/20 text-red-400';
      default:
        return 'bg-gray-500/10 border border-gray-500/20 text-gray-400';
    }
  };

  const getWebSocketStatusIcon = () => {
    const status = websocketStatus.value.status;
    switch (status) {
      case 'connected':
        return '🟢';
      case 'connecting':
        return '🟡';
      case 'disconnected':
        return '⚪';
      case 'error':
        return '🔴';
      default:
        return '⚪';
    }
  };

  // 自动下注状态样式
  const getAutoBettingStatusClass = () => {
    return autoBettingStatus.value.is_running
      ? 'bg-green-500/10 border border-green-500/20 text-green-400'
      : 'bg-gray-500/10 border border-gray-500/20 text-gray-400';
  };

  const getAutoBettingStatusIcon = () => {
    return autoBettingStatus.value.is_running ? '🤖' : '⏹️';
  };

  // ==================== 计算属性 ====================

  // 当前策略名称计算属性
  const currentStrategyName = computed(() => {
    // 根据动态条件判断策略类型
    if (config.dynamic_conditions && config.dynamic_conditions.length > 0) {
      const conditions = config.dynamic_conditions;

      // 检查是否为智能排名策略
      if (conditions.length === 1 && conditions[0].type === 'h2h_rank') {
        return '智能排名策略';
      }

      // 检查是否为实战模式
      if (
        conditions.length === 4 &&
        conditions.every((c) => ['confidence', 'score', 'sample_count', 'win_rate'].includes(c.type))
      ) {
        return '实战模式策略';
      }

      return '自定义策略';
    }

    return '默认策略';
  });

  // ==================== 核心逻辑函数 ====================

  // 数据映射函数
  const mapPredictionData = (rawPrediction: any): any => {
    return {
      ...rawPrediction,
      confidence: rawPrediction.rank_confidence || rawPrediction.confidence || 0,
      score: rawPrediction.predicted_final_value || rawPrediction.score || 0,
      sample_count: rawPrediction.total_games || rawPrediction.sample_count || 0,
      win_rate: rawPrediction.win_rate || 0, // 🔧 修复：保持百分比格式，不除以100
      top3_rate: rawPrediction.top3_rate || 0, // 🔧 修复：保持百分比格式
      symbol: rawPrediction.symbol,
      predicted_rank: rawPrediction.predicted_rank,
      // 🆕 复合型策略需要的数据
      momentum_rank: rawPrediction.momentum_rank || rawPrediction.predicted_rank || 999
    };
  };

  // 计算下注金额 - 硬编码模式
  const calculateBetAmount = (): number => {
    // 🎯 根据betting_mode硬编码下注金额
    if (config.betting_mode === 'real') {
      return 200; // 真实模式固定200
    } else {
      return 200; // 模拟模式也固定200
    }
  };

  // 验证当前策略
  const validateCurrentStrategy = () => {
    debugInfo.strategyValidationCount++;
    debugInfo.lastValidationTime = new Date().toLocaleTimeString();

    // 🔧 修复：使用与SmartControlCenter.vue相同的数据源（合并动能数据）
    const h2hData = currentAnalysis.value || [];
    const momentumData = hybridPredictions.value || [];

    // 合并数据，与SmartControlCenter.vue保持一致
    let predictions: any[] = [];
    if (momentumData.length > 0 && h2hData.length > 0) {
      predictions = h2hData.map((h2hToken: any) => {
        const momentumToken = momentumData.find((m: any) => m.symbol?.toUpperCase() === h2hToken.symbol?.toUpperCase());
        return {
          ...h2hToken,
          momentum_rank: momentumToken?.predicted_rank ?? null,
          mom_score: momentumToken?.mom_score ?? null,
          final_score: momentumToken?.final_score ?? null,
          elo_prob: momentumToken?.elo_prob ?? null
        };
      });
    } else if (momentumData.length > 0 && h2hData.length === 0) {
      predictions = momentumData;
    } else {
      predictions = h2hData;
    }

    console.log(`📊 策略验证：使用 ${predictions.length} 个合并后的分析数据`);

    if (!predictions || predictions.length === 0) {
      console.log(`⚠️ 策略验证：无可用预测数据`);
      strategyValidation.value = null;
      return;
    }

    const allMatches: any[] = [];

    // 找出所有符合条件的预测
    predictions.forEach((rawPrediction: any) => {
      const prediction = mapPredictionData(rawPrediction);

      // 🔧 修复：使用与SmartControlCenter.vue完全相同的评估逻辑
      let isMatch = false;

      // 使用动态条件评估
      if ((config.dynamic_conditions || []).length > 0) {
        console.log(`🔍 [AutoBetting] 评估Token ${prediction.symbol} 的条件匹配:`, {
          symbol: prediction.symbol,
          predicted_rank: prediction.predicted_rank,
          momentum_rank: prediction.momentum_rank,
          win_rate: prediction.win_rate,
          conditions: config.dynamic_conditions
        });

        isMatch = evaluateDynamicConditions(prediction, config.dynamic_conditions || []);

        console.log(`🔍 [AutoBetting] Token ${prediction.symbol} 最终匹配结果:`, isMatch);
      } else {
        // 如果没有动态条件，默认通过
        isMatch = true;
      }

      // 🔧 调试：输出条件评估详情
      console.log(`🔍 [策略验证] Token ${prediction.symbol} 条件评估:`, {
        symbol: prediction.symbol,
        predicted_rank: prediction.predicted_rank,
        win_rate: prediction.win_rate,
        top3_rate: prediction.top3_rate,
        isMatch,
        conditions: config.dynamic_conditions?.map((c) => ({
          type: c.type,
          operator: c.operator,
          value: c.value,
          logic: c.logic
        }))
      });

      if (isMatch) {
        const betAmount = calculateBetAmount();
        allMatches.push({
          ...prediction,
          bet_amount: betAmount
        });
      }
    });

    console.log(`📊 策略验证：从 ${predictions.length} 个预测中找到 ${allMatches.length} 个符合条件的Token`);

    // 使用所有符合条件的Token
    const finalMatches = allMatches;
    console.log(`🚀 动态条件策略：选择所有 ${finalMatches.length} 个符合条件的Token`);

    // 计算总下注金额
    const totalMatchedValue = finalMatches.reduce((sum, match) => sum + match.bet_amount, 0);

    // 🎯 根据下注模式选择正确的余额
    const bettingMode = config.betting_mode || 'dummy';
    let actualBalance = 0;
    let balanceType = '';

    if (bettingMode === 'real') {
      actualBalance = userInfo.value?.ojoValue || 0;
      balanceType = 'OJO代币';
    } else {
      actualBalance = userInfo.value?.available || 0;
      balanceType = '模拟代币';
    }

    const balanceInsufficient = totalMatchedValue > actualBalance;

    strategyValidation.value = {
      matches: finalMatches,
      total_matched: finalMatches.length,
      balance_sufficient: !balanceInsufficient,
      required_balance: totalMatchedValue,
      actual_balance: actualBalance
    };

    console.log(
      `📊 策略验证完成：${finalMatches.length} 个目标，需要${balanceType} $${totalMatchedValue.toFixed(2)}，实际余额 $${actualBalance.toFixed(2)}`
    );
  };

  // ==================== 用户操作函数 ====================

  // 清空下注结果
  const clearBetResults = () => {
    debugInfo.lastBetResults = [];
  };

  // 🔧 新增：刷新用户信息方法
  const refreshUserInfo = async () => {
    if (!config.jwt_token) {
      window.$message?.warning('没有JWT Token，无法刷新用户信息');
      return;
    }

    // 🔧 新增：Token一致性检查
    const tokenCheck = jwtTokenUtils.checkSystemTokenConsistency(config.jwt_token);
    console.log('🔑 [refreshUserInfo] Token一致性检查:', tokenCheck.report);
    if (!tokenCheck.isConsistent) {
      console.warn('⚠️ [refreshUserInfo] Token一致性问题:', tokenCheck.issues);
    }

    userInfoRefreshing.value = true;
    try {
      console.log('🔄 开始刷新用户信息...');
      const userInfoResponse = await getUserInfo(config.jwt_token);

      if (userInfoResponse.success && userInfoResponse.obj) {
        userInfo.value = userInfoResponse.obj;
        localStorage.setItem('userInfo', JSON.stringify(userInfo.value));
        window.$message?.success('用户信息刷新成功');
        console.log('✅ 用户信息刷新成功:', {
          ojoValue: userInfo.value?.ojoValue,
          available: userInfo.value?.available
        });
      } else {
        throw new Error('获取用户信息失败');
      }
    } catch (error: any) {
      console.error('刷新用户信息失败:', error.message);

      // 根据错误类型显示不同的提示
      if (networkUtils.isAuthError(error)) {
        window.$message?.error('JWT Token已过期，请重新验证');
      } else if (networkUtils.isNetworkError(error)) {
        window.$message?.warning('网络连接不稳定，请稍后重试');
      } else {
        window.$message?.error('刷新用户信息失败，请稍后重试');
      }
    } finally {
      userInfoRefreshing.value = false;
    }
  };

  // 更新最近轮次数量
  const updateRecentRoundsCount = (value: number) => {
    recentRoundsCount.value = value;
  };

  // 更新动能预测最近局数
  const updateMomentumRecentRoundsCount = (value: number) => {
    momentumRecentRoundsCount.value = value;
  };

  // 获取 Hybrid-Edge 動能預測數據
  const fetchHybridPredictions = async () => {
    try {
      await fetchHybridAnalysis(true);
    } catch (error) {
      console.error('❌ 获取 Hybrid-Edge 預測數據失败:', error);
    }
  };

  // 刷新分析数据
  const refreshAnalysis = async () => {
    try {
      // 使用store的方法来获取数据，确保数据正确更新到store中，强制刷新
      await predictionStore.fetchCurrentAnalysis(true);

      // 同时刷新 Hybrid-Edge 預測數據，强制刷新
      await fetchHybridPredictions();

      // 刷新后重新验证策略
      if (!isExecuting.value) {
        validateCurrentStrategy();
      }
    } catch (error) {
      console.error('❌ 刷新预测数据失败:', error);
      window.$message?.error('刷新预测数据失败');
    }
  };

  // 刷新预测历史数据
  const refreshPredictionHistory = async () => {
    await predictionStore.fetchPredictionHistory();
  };

  // 刷新动能预测历史数据
  const refreshMomentumHistory = async () => {
    momentumHistoryLoading.value = true;
    try {
      // 🔧 修复：增加limit参数，获取更多历史数据
      const response = await gameApi.getMomentumPredictionHistory({ limit: 300 });
      if (response.data.success) {
        momentumPredictionHistory.value = response.data.data || [];
        console.log(`📊 获取到 ${momentumPredictionHistory.value.length} 局动能预测历史数据`);
      } else {
        window.$message?.error(response.data.message || '获取动能预测历史数据失败');
      }
    } catch (error) {
      console.error('❌ 获取动能预测历史数据失败:', error);
      window.$message?.error('获取动能预测历史数据失败');
    } finally {
      momentumHistoryLoading.value = false;
    }
  };

  // 🔌 设置WebSocket频道监听
  const setupWebSocketListeners = () => {
    // 监听游戏数据更新
    websocketManager.listenToGameUpdates((event: any) => {
      // 更新游戏状态和轮次信息
      if (event.data) {
        const gameData = event.data;

        // 使用store的更新方法
        predictionStore.updateGameData(gameData);

        // 🔧 移除：不再在这里触发策略验证，避免重复触发
        // 策略验证现在由响应式监听器统一处理
        // if (gameData.status === 'bet') {
        //   validateCurrentStrategy();
        // }
      }
    });

    // 监听预测数据更新
    websocketManager.listenToPredictions((event: any) => {
      console.log('📡 收到预测数据更新:', event);

      // 更新预测数据 - 根据后端广播的数据结构
      if (event.data && Array.isArray(event.data)) {
        // 需要对WebSocket数据进行与API相同的数据映射
        const mappedData = event.data.map((item: any) => ({
          symbol: item.symbol,
          name: item.symbol, // 使用symbol作为name
          change_5m: item.change_5m,
          change_1h: item.change_1h,
          change_4h: item.change_4h,
          change_24h: item.change_24h,
          volume_24h: '0', // API中没有这个字段，保持默认值
          market_cap: null, // API中没有这个字段，保持默认值
          logo: null, // API中没有这个字段，保持默认值
          prediction_score: item.predicted_final_value || item.h2h_score || 0,
          win_rate: item.win_rate || 0, // 🔧 修复：保持百分比格式
          top3_rate: item.top3_rate || 0, // 🔧 修复：保持百分比格式
          avg_rank: item.avg_rank || 3,
          total_games: item.total_games || 0,
          wins: item.wins || 0,
          top3: item.top3 || 0,
          predicted_rank: item.predicted_rank || 999,
          // 映射可选字段
          absolute_score: item.absolute_score || 0,
          relative_score: item.relative_score || 0,
          h2h_score: item.h2h_score || 0,
          risk_adjusted_score: item.risk_adjusted_score || 0,
          rank_confidence: item.rank_confidence || 0,
          final_prediction_score: item.predicted_final_value || 0,
          market_momentum_score: item.market_momentum_score || 0,
          value_stddev: item.value_stddev || 0,
          recent_avg_value: item.avg_value || 0,
          avg_value: item.avg_value || 0
        }));

        console.log('🔄 WebSocket数据映射完成:', mappedData.slice(0, 3));

        // 更新store中的currentAnalysis
        currentAnalysis.value = mappedData;
        analysisMeta.value = event.meta || null;

        // 🔧 移除：不再在这里触发策略验证，避免重复触发
        // validateCurrentStrategy();
      } else if (event.prediction) {
        // 兼容旧的单个预测数据格式
        const predictionData = event.prediction;

        // 使用store的更新方法
        predictionStore.updatePredictionData(predictionData);

        // 🔧 移除：不再在这里触发策略验证，避免重复触发
        // validateCurrentStrategy();
      }
    });

    // 监听Hybrid预测数据更新
    websocketManager.listenToHybridPredictions((event: any) => {
      console.log('📡 收到Hybrid预测数据更新:', event);

      // 更新Hybrid预测数据
      if (event.data && Array.isArray(event.data)) {
        // 使用store的更新方法
        predictionStore.updateHybridPredictions(event.data, event.meta);

        // 🔧 移除：不再在这里触发策略验证，避免重复触发
        // validateCurrentStrategy();
      }
    });
  };

  // ==================== 响应式自动下注逻辑 ====================

  // 记录已处理的轮次，避免重复下注
  const processedRounds = ref<Set<string>>(new Set());

  // 检查所有自动下注条件
  const checkAutoBettingConditions = (): { canProceed: boolean; reason?: string } => {
    if (!autoBettingStatus.value.is_running) {
      return { canProceed: false, reason: '自动下注未运行' };
    }

    if (!canBet(currentGameStatus.value || '')) {
      return { canProceed: false, reason: `游戏状态不允许下注 (${currentGameStatus.value})` };
    }

    if (!config.jwt_token) {
      return { canProceed: false, reason: '缺少JWT Token' };
    }

    if (!currentRoundId.value) {
      return { canProceed: false, reason: '无当前轮次ID' };
    }

    if (!currentUID.value) {
      return { canProceed: false, reason: '用户未认证' };
    }

    // 🔧 修复：放宽数据检查条件，允许在数据不足时继续执行，在executeAutoBettingLogic中处理
    // 检查数据源 - 但不作为阻止条件
    if (!currentAnalysis.value || currentAnalysis.value.length === 0) {
      console.log('⚠️ 无分析数据，将在执行时处理');
    }

    return { canProceed: true };
  };

  // 执行自动下注的核心逻辑
  const executeAutoBettingLogic = async () => {
    const timestamp = new Date().toLocaleTimeString();
    const roundId = currentRoundId.value!;

    // 🔧 防抖检查：如果正在执行，则跳过
    if (isExecuting.value) {
      console.log(`🚫 [${timestamp}] 自动下注逻辑正在执行中，跳过重复调用`);
      return;
    }

    // 🔧 增强：检查是否已处理此轮次（本地缓存）
    if (processedRounds.value.has(roundId)) {
      console.log(`🚫 [${timestamp}] 轮次 ${roundId} 已在本地缓存中，跳过`);
      return;
    }

    // 🔧 新增：立即标记为已处理，防止并发执行
    processedRounds.value.add(roundId);
    isExecuting.value = true;
    console.log(`🎯 [${timestamp}] 开始自动下注逻辑 - 轮次: ${roundId}`);

    try {
      // 检查API中是否已下注（双重保险）
      const roundBetCheck = await autoBettingApi.checkRoundBet(currentUID.value, roundId);
      if (roundBetCheck.data.success && roundBetCheck.data.data.has_bet) {
        console.log(`🚫 [${timestamp}] 轮次 ${roundId} 已在API中记录下注，跳过`);
        isExecuting.value = false;
        return;
      }
    } catch (error) {
      console.warn(`⚠️ [${timestamp}] 检查轮次下注记录失败:`, error);
      // 🔧 新增：如果API检查失败，从本地缓存中移除，允许重试
      processedRounds.value.delete(roundId);
      isExecuting.value = false;
      return;
    }

    // 🔧 修复：在执行策略验证前检查数据可用性
    const hasAnalysisData = currentAnalysis.value && currentAnalysis.value.length > 0;
    const hasHybridData = hybridPredictions.value && hybridPredictions.value.length > 0;

    console.log(
      `📊 [${timestamp}] 数据状态检查: analysis=${hasAnalysisData ? currentAnalysis.value.length : 0}, hybrid=${hasHybridData ? hybridPredictions.value.length : 0}`
    );

    // 验证策略条件
    validateCurrentStrategy();

    if (!strategyValidation.value?.matches.length) {
      console.log(`❌ [${timestamp}] 无符合条件的下注目标`);
      console.log(`📊 [${timestamp}] 策略验证结果:`, strategyValidation.value);
      isExecuting.value = false;
      return;
    }

    if (!strategyValidation.value?.balance_sufficient) {
      console.warn(`💰 [${timestamp}] 余额不足，跳过此轮下注`);
      window.$message?.warning('余额不足，跳过此轮自动下注');
      isExecuting.value = false;
      return;
    }

    console.log(`🤖 [${timestamp}] 自动下注：检测到 ${strategyValidation.value.matches.length} 个符合条件的目标`);
    window.$message?.info(`🤖 自动下注：检测到 ${strategyValidation.value.matches.length} 个符合条件的目标`);

    // 🔧 新增：执行下注前的Token一致性检查
    const tokenCheck = jwtTokenUtils.checkSystemTokenConsistency(config.jwt_token);
    console.log(`🔑 [${timestamp}] 下注前Token一致性检查:`, tokenCheck.report);
    if (!tokenCheck.isConsistent) {
      console.error(`❌ [${timestamp}] Token一致性问题，停止自动下注:`, tokenCheck.issues);
      window.$message?.error('JWT Token不一致，请重新验证');
      isExecuting.value = false;
      return;
    }

    let successCount = 0;
    let failCount = 0;

    // 执行下注
    for (const match of strategyValidation.value.matches) {
      console.log(`🎲 [${timestamp}] 开始下注: ${match.symbol}, 金额: ${match.bet_amount}`);
      try {
        const betSuccess = await executeSingleBet(
          roundId,
          match.symbol,
          match.bet_amount,
          config.jwt_token,
          config.betting_mode
        );
        if (betSuccess) {
          successCount++;
          console.log(`✅ [${timestamp}] 下注成功: ${match.symbol}`);
          debugInfo.lastBetResults.push({
            time: new Date().toLocaleTimeString(),
            symbol: match.symbol,
            amount: match.bet_amount,
            success: true
          });
        } else {
          failCount++;
          console.log(`❌ [${timestamp}] 下注失败: ${match.symbol}`);
          debugInfo.lastBetResults.push({
            time: new Date().toLocaleTimeString(),
            symbol: match.symbol,
            amount: match.bet_amount,
            success: false
          });
        }

        // 下注间隔
        await new Promise((resolve) => setTimeout(resolve, 500));
      } catch (error) {
        console.error(`💥 [${timestamp}] 下注异常 ${match.symbol}:`, error);
        failCount++;
        debugInfo.lastBetResults.push({
          time: new Date().toLocaleTimeString(),
          symbol: match.symbol,
          amount: match.bet_amount,
          success: false,
          error: error instanceof Error ? error.message : String(error)
        });
      }
    }

    // 更新状态和验证
    await loadStatus();
    // 🔧 修复：避免在函数结束时重复验证策略，因为此时isExecuting还是true
    // validateCurrentStrategy();

    // 显示结果
    if (successCount > 0) {
      console.log(`🎯 [${timestamp}] 自动下注完成：成功 ${successCount} 个，失败 ${failCount} 个`);
      window.$message?.success(`🎯 自动下注完成：成功 ${successCount} 个，失败 ${failCount} 个`);
    } else if (failCount > 0) {
      console.error(`❌ [${timestamp}] 自动下注失败：失败 ${failCount} 个`);
      window.$message?.error(`❌ 自动下注失败：失败 ${failCount} 个`);
    }

    // 清理旧的处理记录（保留最近20个轮次）
    if (processedRounds.value.size > 20) {
      const sortedRounds = Array.from(processedRounds.value).sort();
      processedRounds.value = new Set(sortedRounds.slice(-20));
    }

    // 🔧 新增：重置执行状态
    isExecuting.value = false;
  };

  // ==================== 监听器设置 ====================

  // 监听器引用，用于清理
  const configWatcher = watch(
    config,
    () => {
      configComposable.autoSaveConfig(currentUID.value);
      // 🔧 修复：避免在自动下注执行期间重复验证策略
      if (!isExecuting.value) {
        validateCurrentStrategy();
      }
    },
    { deep: true, flush: 'post' }
  );

  const analysisWatcher = watch(
    currentAnalysis,
    () => {
      // 🔧 修复：避免在自动下注执行期间重复验证策略
      if (!isExecuting.value) {
        validateCurrentStrategy();
      }
    },
    { deep: true }
  );

  // 🎯 核心：响应式自动下注监听器 - 替代定时器轮询
  const autoBettingTriggerWatcher = watch(
    [
      () => autoBettingStatus.value.is_running,
      currentGameStatus,
      currentRoundId,
      currentAnalysis,
      hybridPredictions,
      () => config.jwt_token,
      currentUID
    ],
    async (
      [isRunning, gameStatus, roundId, analysis, hybridData, jwtToken, uid],
      [prevIsRunning, prevGameStatus, prevRoundId]
    ) => {
      // 🔧 当自动下注开启/关闭时的状态提示
      if (isRunning !== prevIsRunning) {
        if (isRunning) {
          window.$message?.success('🤖 自动下注监控已启动，系统将响应式地检查条件并执行下注');
          console.log('🤖 响应式自动下注监控已启动');
        } else {
          window.$message?.info('🛑 自动下注监控已停止');
          console.log('🛑 响应式自动下注监控已停止');
          return;
        }
      }

      // 🔧 新增：检查轮次变化，只有在新轮次开始时才执行下注
      const isNewRound = roundId && roundId !== prevRoundId;
      const isGameStatusChanged = gameStatus !== prevGameStatus;

      // 💰 新增：当游戏状态变为bet时，获取最新用户信息
      if (isGameStatusChanged && gameStatus === 'bet' && jwtToken) {
        console.log('💰 游戏状态变为投注中，获取最新用户信息...');
        try {
          const userInfoResponse = await getUserInfo(jwtToken);
          if (userInfoResponse.success && userInfoResponse.obj) {
            userInfo.value = userInfoResponse.obj;
            localStorage.setItem('userInfo', JSON.stringify(userInfo.value));
            console.log('✅ 用户信息已更新:', {
              ojoValue: userInfo.value?.ojoValue,
              available: userInfo.value?.available
            });
          }
        } catch (error) {
          console.warn('获取最新用户信息失败:', error);
        }
      }

      // 检查基础条件
      const conditions = checkAutoBettingConditions();
      if (!conditions.canProceed) {
        // 只有在游戏状态从非bet变为bet时才打印日志，避免过多输出
        if (gameStatus === 'bet' && roundId && isRunning) {
          console.log(`⏸️ 自动下注条件不满足: ${conditions.reason}`);
        }
        return;
      }

      // 🎯 关键触发条件：游戏状态为bet且有轮次数据
      // 🔧 修复：只有在新轮次开始或游戏状态变为bet时才执行下注
      if (gameStatus === 'bet' && roundId && isRunning && jwtToken && uid) {
        // 🔧 新增：更严格的触发条件检查
        const shouldExecute = isNewRound || (isGameStatusChanged && gameStatus === 'bet');

        if (shouldExecute) {
          console.log(`🚀 触发自动下注检查 - 轮次: ${roundId}, 状态: ${gameStatus}`);
          console.log(`📊 当前数据状态: analysis=${analysis?.length || 0}, hybrid=${hybridData?.length || 0}`);
          console.log(`🔄 触发原因: ${isNewRound ? '新轮次' : '游戏状态变化'}`);

          // 🔧 新增：防抖机制，避免短时间内重复执行
          if (executionTimeout.value) {
            clearTimeout(executionTimeout.value);
          }

          executionTimeout.value = setTimeout(async () => {
            await executeAutoBettingLogic();
            executionTimeout.value = null;
          }, 100); // 100ms防抖延迟
        } else {
          // 🔧 新增：调试日志，说明为什么跳过
          console.log(`⏭️ 跳过自动下注检查 - 轮次: ${roundId}, 原因: 非新轮次且游戏状态未变化`);
        }
      }
    },
    {
      immediate: false, // 不立即执行，等数据准备好
      deep: false, // 不需要深度监听
      flush: 'post' // 在DOM更新后执行
    }
  );

  // ==================== 生命周期钩子 ====================

  // 组件挂载时初始化
  onMounted(async () => {
    console.log('🚀 AutoBetting: 页面开始初始化...');

    // 恢复认证状态
    await restoreAuthState();

    // 初始化配置 - 传递当前UID
    await initializeConfig(currentUID.value);

    // 从localStorage恢复JWT Token到配置中
    console.log('🔑 [onMounted] 开始恢复JWT Token...');
    console.log(
      '🔑 [onMounted] 当前 config.jwt_token:',
      config.jwt_token ? `${config.jwt_token.slice(0, 20)}...` : 'null'
    );

    const savedTokenData = localStorage.getItem('tokenSetupData');
    if (savedTokenData) {
      try {
        const tokenData = JSON.parse(savedTokenData);
        console.log(
          '🔑 [onMounted] localStorage中的Token:',
          tokenData.jwt_token ? `${tokenData.jwt_token.slice(0, 20)}...` : 'null'
        );

        if (tokenData.jwt_token && !config.jwt_token) {
          config.jwt_token = tokenData.jwt_token;
          console.log('✅ [onMounted] JWT Token已从localStorage恢复到配置中');
        } else if (config.jwt_token) {
          console.log('⚠️ [onMounted] config.jwt_token已存在，跳过恢复');
        }

        console.log(
          '🔑 [onMounted] 恢复后 config.jwt_token:',
          config.jwt_token ? `${config.jwt_token.slice(0, 20)}...` : 'null'
        );
      } catch (error) {
        console.warn('恢复JWT Token失败:', error);
      }
    } else {
      console.log('⚠️ [onMounted] localStorage中没有找到tokenSetupData');
    }

    if (!isMonitoringRounds.value) {
      isMonitoringRounds.value = true;
    }

    // 🔧 确保WebSocket管理器已初始化
    console.log('🔌 检查WebSocket管理器状态...');
    if (!websocketManager.isInitialized) {
      console.log('🔌 初始化WebSocket管理器...');
      websocketManager.initialize();
    } else {
      console.log('🔌 WebSocket管理器已初始化');
    }

    // 🔧 优化：使用store的方法获取初始数据，并添加调试日志
    console.log('📡 AutoBetting: 开始获取初始数据...');
    await predictionStore.fetchInitialData();
    console.log('✅ AutoBetting: 初始数据获取完成');

    // 获取动能预测历史数据
    await refreshMomentumHistory();

    // 设置WebSocket频道监听
    console.log('🔌 设置WebSocket频道监听...');
    setupWebSocketListeners();

    console.log('🎉 AutoBetting: 页面初始化完成');
    console.log('📊 当前WebSocket状态:', websocketStatus.value);
    console.log('📊 当前自动下注状态:', autoBettingStatus.value);
  });

  // 组件卸载时清理资源
  onUnmounted(() => {
    if (configWatcher) configWatcher();
    if (analysisWatcher) analysisWatcher();
    if (autoBettingTriggerWatcher) autoBettingTriggerWatcher();

    isMonitoringRounds.value = false;
    debugInfo.lastBetResults = [];
    processedRounds.value.clear();

    // 🔧 新增：清理防抖定时器
    if (executionTimeout.value) {
      clearTimeout(executionTimeout.value);
      executionTimeout.value = null;
    }
    isExecuting.value = false;
  });
</script>

<style scoped>
  /* 极简线条风格标签页 */
  :deep(.modern-tabs .n-tabs-nav) {
    background: transparent;
    border-radius: 8px;
    padding: 0 4px;
    border: none;
    box-shadow: none;
    position: relative;
    overflow: visible;
    min-height: 44px;
  }

  :deep(.modern-tabs .n-tabs-tab) {
    background: transparent;
    border: none;
    border-radius: 6px 6px 0 0;
    margin: 0 8px;
    color: #64748b;
    font-weight: 500;
    font-size: 16px;
    padding: 10px 20px 8px 20px;
    transition:
      color 0.2s,
      font-weight 0.2s;
    position: relative;
    min-width: 100px;
    box-shadow: none;
    outline: none;
    cursor: pointer;
  }

  :deep(.modern-tabs .n-tabs-tab:hover) {
    color: #2563eb;
  }

  :deep(.modern-tabs .n-tabs-tab.n-tabs-tab--active) {
    color: #2563eb;
    font-weight: 700;
  }

  :deep(.modern-tabs .n-tabs-tab::after) {
    content: '';
    display: block;
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%) scaleX(0);
    width: 60%;
    height: 3px;
    border-radius: 2px;
    background: linear-gradient(90deg, #2563eb 0%, #a78bfa 100%);
    transition:
      transform 0.25s cubic-bezier(0.4, 0, 0.2, 1),
      opacity 0.2s;
    opacity: 0;
    z-index: 1;
  }

  :deep(.modern-tabs .n-tabs-tab:hover::after) {
    transform: translateX(-50%) scaleX(1);
    opacity: 0.5;
  }

  :deep(.modern-tabs .n-tabs-tab.n-tabs-tab--active::after) {
    transform: translateX(-50%) scaleX(1);
    opacity: 1;
  }

  :deep(.modern-tabs .n-tabs-tab-pane) {
    padding: 0;
  }

  :deep(.modern-tabs .n-tabs-content) {
    margin-top: 8px;
  }

  /* 响应式优化 */
  @media (max-width: 1024px) {
    :deep(.modern-tabs .n-tabs-tab) {
      font-size: 15px;
      padding: 8px 12px 6px 12px;
      min-width: 80px;
      margin: 0 4px;
    }
  }
  @media (max-width: 768px) {
    :deep(.modern-tabs .n-tabs-tab) {
      font-size: 14px;
      padding: 6px 8px 4px 8px;
      min-width: 60px;
      margin: 0 2px;
    }
  }
  @media (max-width: 480px) {
    :deep(.modern-tabs .n-tabs-tab) {
      font-size: 13px;
      padding: 4px 4px 2px 4px;
      min-width: 40px;
      margin: 0 1px;
    }
  }

  /* 状态指示器动画效果 */
  .status-indicator {
    position: relative;
    overflow: hidden;
  }

  .status-indicator::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s;
  }

  .status-indicator:hover::before {
    left: 100%;
  }

  /* 玻璃态效果增强 */
  .glass-effect {
    backdrop-filter: blur(16px) saturate(180%);
    background: rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.125);
  }

  /* 渐变文字效果 */
  .gradient-text {
    background: linear-gradient(135deg, #60a5fa, #a78bfa, #f472b6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  /* 悬浮动画 */
  .float-animation {
    animation: float 3s ease-in-out infinite;
  }

  @keyframes float {
    0%,
    100% {
      transform: translateY(0px);
    }
    50% {
      transform: translateY(-5px);
    }
  }

  /* 脉冲动画 */
  .pulse-dot {
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0%,
    100% {
      opacity: 1;
    }
    50% {
      opacity: 0.5;
    }
  }

  /* 卡片容器增强 */
  .content-card {
    background: rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
  }

  .content-card:hover {
    border-color: rgba(59, 130, 246, 0.3);
    box-shadow:
      0 10px 25px -5px rgba(0, 0, 0, 0.1),
      0 10px 10px -5px rgba(59, 130, 246, 0.04);
  }

  /* 状态栏美化 */
  .status-bar {
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.1));
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  /* 图标容器美化 */
  .icon-container {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    box-shadow:
      0 4px 6px -1px rgba(59, 130, 246, 0.25),
      0 2px 4px -1px rgba(59, 130, 246, 0.06);
    transition: all 0.3s ease;
  }

  .icon-container:hover {
    transform: scale(1.05);
    box-shadow:
      0 8px 25px -5px rgba(59, 130, 246, 0.25),
      0 10px 10px -5px rgba(59, 130, 246, 0.04);
  }

  :deep(.modern-tabs .n-tabs-bar) {
    display: none !important;
  }
</style>
