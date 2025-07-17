<template>
  <DefaultLayout>
    <Head title="自动下注控制中心" />

    <!-- 身份验证模态框 -->
    <WalletSetup :visible="!isTokenValidated" @validated="handleTokenValidated" />

    <div v-if="isTokenValidated" class="min-h-screen from-slate-900 via-purple-900 to-slate-900 bg-gradient-to-br">
      <!-- 🔧 调试面板 -->
      <div v-if="isDevMode" class="mb-4 border border-red-500/4 bg-red-900">
        <h3 class="mb-2 text-red-400 font-bold">🔧 调试面板 (开发模式)</h3>
        <div class="grid grid-cols-2 text-sm md:grid-cols-4">
          <div>
            <div class="text-red-300">WebSocket状态:</div>
            <div class="text-red-200">{{ websocketStatus.status }} - {{ websocketStatus.message }}</div>
          </div>
          <div>
            <div class="text-red-300">自动下注状态:</div>
            <div class="text-red-200">{{ autoBettingStatus.is_running ? '运行中' : '已停止' }}</div>
          </div>
          <div>
            <div class="text-red-300">当前游戏状态:</div>
            <div class="text-red-200">{{ currentGameStatus || '未知' }}</div>
          </div>
          <div>
            <div class="text-red-300">当前轮次ID:</div>
            <div class="text-red-200">{{ currentRoundId || '无' }}</div>
          </div>
          <div>
            <div class="text-red-300">分析数据:</div>
            <div class="text-red-200">{{ currentAnalysis?.length || 0 }} 个</div>
          </div>
          <div>
            <div class="text-red-300">动能预测:</div>
            <div class="text-red-200">{{ hybridPredictions?.length || 0 }} 个</div>
          </div>
          <div>
            <div class="text-red-300">JWT Token:</div>
            <div class="text-red-200">{{ config.jwt_token ? '已设置' : '未设置' }}</div>
          </div>
          <div>
            <div class="text-red-300">用户ID:</div>
            <div class="text-red-200">{{ currentUID || '未认证' }}</div>
          </div>
        </div>
      </div>

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
                  class="status-indicator inline-flex items-center gap-2 border border-green-500/20 rounded-full bg-green-500/10 px-3 py-1.5"
                >
                  <div class="pulse-dot h-2 w-2 rounded-full bg-green-400"></div>
                  <span class="text-xs text-green-400 font-medium">
                    配置已云端同步 ({{ currentUID.slice(0, 8) }}...)
                  </span>
                </div>
                <div
                  v-else
                  class="status-indicator inline-flex items-center gap-2 border border-yellow-500/20 rounded-full bg-yellow-500/10 px-3 py-1.5"
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
                class="status-indicator border border-blue-500/30 rounded-lg bg-blue-500/10 px-3 py-2 transition-all duration-300 hover:bg-blue-500/15"
              >
                <div class="text-xs text-blue-400">当前策略</div>
                <div class="text-sm text-blue-300 font-medium">{{ currentStrategyName }}</div>
              </div>

              <!-- 用户信息 -->
              <div
                v-if="userInfo"
                class="status-indicator border border-purple-500/30 rounded-lg bg-purple-500/10 px-3 py-2 transition-all duration-300 hover:bg-purple-500/15"
              >
                <div class="text-xs text-purple-400">用户ID</div>
                <div class="flex items-center gap-2">
                  <span class="text-sm text-purple-300 font-mono">{{ userInfo.uid.slice(0, 8) }}...</span>
                  <NButton
                    @click="reconnectToken"
                    :disabled="autoBettingStatus.is_running"
                    type="tertiary"
                    size="tiny"
                    class="transition-all duration-200 !h-5 !text-xs"
                  >
                    重新验证
                  </NButton>
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
                  :confidence-threshold="config.confidence_threshold"
                  :config="config"
                  :config-saving="configSaving"
                  :config-sync-status="configSyncStatus"
                  :strategy-validation="strategyValidation"
                  :is-running="autoBettingStatus.is_running"
                  :has-u-i-d="!!currentUID"
                  :hybrid-predictions="hybridPredictions"
                  :hybrid-analysis-meta="hybridAnalysisMeta"
                  :hybrid-analysis-loading="hybridAnalysisLoading"
                  :refresh-hybrid-analysis="fetchHybridAnalysis"
                  @start-auto-betting="startAutoBetting"
                  @stop-auto-betting="stopAutoBetting"
                  @execute-manual-betting="executeManualBetting"
                  @clear-bet-results="clearBetResults"
                  @execute-strategy-betting="executeStrategyBetting"
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
  import type { StrategyValidation } from '@/types/autoBetting';
  import type { UserInfo } from '@/types';
  import type { MomentumPredictionHistoryRound } from '@/composables/useMomentumPredictionStats';
  import { handleError, createConfirmDialog, handleAsyncOperation } from '@/utils/errorHandler';
  import { autoBettingApi, gameApi } from '@/utils/api';
  import { canBet } from '@/utils/statusUtils';
  import { websocketManager } from '@/utils/websocketManager';

  // 初始化composables和stores
  const configComposable = useAutoBettingConfig();
  const controlComposable = useAutoBettingControl();
  const predictionStore = useGamePredictionStore();

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
  const { config, configSaving, configSyncStatus, manualSaveConfig, initializeConfig } = configComposable;

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
    executeAutoBetting,
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
      config.jwt_token = data.jwt_token;
      console.log('✅ JWT Token已同步到自动下注配置中:', `${data.jwt_token.slice(0, 20)}...`);
      console.log('🔧 config.jwt_token现在是:', !!config.jwt_token);
    }

    // 然后调用原始的验证回调
    await controlComposable.onTokenValidated(data);

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

  // 🔧 开发模式检测
  const isDevMode = computed(() => import.meta.env.DEV);

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

  // ==================== 工具函数 ====================

  // WebSocket状态样式
  const getWebSocketStatusClass = () => {
    const status = websocketStatus.value.status;
    switch (status) {
      case 'connected':
        return 'bg-green-500/20 border border-green-500/30 text-green-400';
      case 'connecting':
        return 'bg-yellow-500/20 border border-yellow-500/30 text-yellow-400';
      case 'disconnected':
        return 'bg-gray-500/20 border border-gray-500/30 text-gray-400';
      case 'error':
        return 'bg-red-500/20 border border-red-500/30 text-red-400';
      default:
        return 'bg-gray-500/20 border border-gray-500/30 text-gray-400';
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
      ? 'bg-green-500/20 border border-green-500/30 text-green-400'
      : 'bg-gray-500/20 border border-gray-500/30 text-gray-400';
  };

  const getAutoBettingStatusIcon = () => {
    return autoBettingStatus.value.is_running ? '🤖' : '⏹️';
  };

  // ==================== 计算属性 ====================

  // 当前策略名称计算属性
  const currentStrategyName = computed(() => {
    // 根据策略类型返回对应的名称
    switch (config.strategy_type) {
      case 'h2h_breakeven':
        return 'H2H保本策略';
      case 'momentum':
        return '动能策略';
      case 'hybrid_rank':
        return '复合型策略';
      default:
        return '自定义策略';
    }
  });

  // ==================== 核心逻辑函数 ====================

  // 数据映射函数
  const mapPredictionData = (rawPrediction: any): any => {
    return {
      ...rawPrediction,
      confidence: rawPrediction.rank_confidence || rawPrediction.confidence || 0,
      score: rawPrediction.predicted_final_value || rawPrediction.score || 0,
      sample_count: rawPrediction.total_games || rawPrediction.sample_count || 0,
      historical_accuracy: (rawPrediction.win_rate || 0) / 100,
      symbol: rawPrediction.symbol,
      predicted_rank: rawPrediction.predicted_rank,
      // 🆕 复合型策略需要的数据
      momentum_rank: rawPrediction.momentum_rank || rawPrediction.predicted_rank || 999
    };
  };

  // 🔧 评估预测是否符合策略条件 - 支持多策略类型
  // 📊 数据单位统一 (2025-01-06): 所有百分比配置项已统一为0-100格式

  // 🆕 H2H策略评估逻辑
  const evaluateH2HPrediction = (prediction: any): boolean => {
    // 对于排名下注策略，首先检查排名是否在选中范围内
    if (config.strategy === 'rank_betting') {
      if (!config.rank_betting_enabled_ranks.includes(prediction.predicted_rank)) {
        return false;
      }
      // 即使是排名下注，也可以应用额外的过滤条件进行精细筛选
    } else {
      // 非排名下注策略的基础条件检查
      if (prediction.confidence < config.confidence_threshold) return false;
      if (prediction.score < config.score_gap_threshold) return false;
      if (prediction.sample_count < config.min_sample_count) return false;
      if (prediction.historical_accuracy * 100 < config.historical_accuracy_threshold) return false;
    }

    // 🔧 历史表现过滤器 - 逻辑验证：保留满足条件的Token
    // 胜率过滤器：如果胜率 < 门槛，则排除（保留胜率 >= 门槛的Token）
    if (config.enable_win_rate_filter && (prediction.win_rate || 0) < config.min_win_rate_threshold) return false;
    // 保本率过滤器：如果保本率 < 门槛，则排除（保留保本率 >= 门槛的Token）
    if (config.enable_top3_rate_filter && (prediction.top3_rate || 0) < config.min_top3_rate_threshold) return false;
    // 平均排名过滤器：如果平均排名 > 门槛，则排除（保留平均排名 <= 门槛的Token，排名越小越好）
    if (config.enable_avg_rank_filter && (prediction.avg_rank || 3) > config.max_avg_rank_threshold) return false;
    // 稳定性过滤器：如果波动性 > 门槛，则排除（保留波动性 <= 门槛的Token，波动越小越稳定）
    if (config.enable_stability_filter && (prediction.value_stddev || 0) > config.max_stability_threshold) return false;

    // 🔧 评分过滤器 - 逻辑验证：保留满足条件的Token
    // 绝对分数过滤器：如果绝对分数 < 门槛，则排除（保留绝对分数 >= 门槛的Token）
    if (config.enable_absolute_score_filter && (prediction.absolute_score || 0) < config.min_absolute_score_threshold)
      return false;
    // 相对分数过滤器：如果相对分数 < 门槛，则排除（保留相对分数 >= 门槛的Token）
    if (config.enable_relative_score_filter && (prediction.relative_score || 0) < config.min_relative_score_threshold)
      return false;
    // H2H分数过滤器：如果H2H分数 < 门槛，则排除（保留H2H分数 >= 门槛的Token）
    if (config.enable_h2h_score_filter && (prediction.h2h_score || 0) < config.min_h2h_score_threshold) return false;

    // 🔧 市场动态过滤器 - 范围检查逻辑正确
    if (config.enable_change_5m_filter) {
      const change5m = prediction.change_5m || 0;
      if (change5m < config.min_change_5m_threshold || change5m > config.max_change_5m_threshold) return false;
    }
    if (config.enable_change_1h_filter) {
      const change1h = prediction.change_1h || 0;
      if (change1h < config.min_change_1h_threshold || change1h > config.max_change_1h_threshold) return false;
    }
    if (config.enable_change_4h_filter) {
      const change4h = prediction.change_4h || 0;
      if (change4h < config.min_change_4h_threshold || change4h > config.max_change_4h_threshold) return false;
    }
    if (config.enable_change_24h_filter) {
      const change24h = prediction.change_24h || 0;
      if (change24h < config.min_change_24h_threshold || change24h > config.max_change_24h_threshold) return false;
    }

    return true;
  };

  // 🆕 动能策略评估逻辑
  const evaluateMomentumPrediction = (prediction: any): boolean => {
    // 动能策略使用不同的数据字段和评估标准
    const momentumScore = prediction.momentum_score || 0;
    const eloWinRate = prediction.elo_win_rate || 0;
    const confidence = prediction.confidence || 0;

    // 检查动能策略的三个核心条件
    if (momentumScore < config.min_momentum_score) return false;
    if (eloWinRate < config.min_elo_win_rate) return false;
    if (confidence < config.min_confidence) return false;

    return true;
  };

  // 🆕 复合型策略评估逻辑
  const evaluateHybridRankPrediction = (prediction: any): boolean => {
    // 获取AI预测排名和动能预测排名
    const h2hRank = prediction.predicted_rank || 999;
    const momentumRank = prediction.momentum_rank || 999;

    // 检查AI预测排名是否在选中范围内
    const h2hRankMatch = config.h2h_rank_enabled_ranks.includes(h2hRank);

    // 检查动能预测排名是否在选中范围内
    const momentumRankMatch = config.momentum_rank_enabled_ranks.includes(momentumRank);

    // 根据逻辑条件判断
    if (config.hybrid_rank_logic === 'and') {
      // "且"逻辑：必须同时满足两个条件
      return h2hRankMatch && momentumRankMatch;
    } else {
      // "或"逻辑：满足任一条件即可
      return h2hRankMatch || momentumRankMatch;
    }
  };

  // 🔧 评估预测是否符合策略条件 - 支持多策略类型
  const evaluatePredictionMatch = (prediction: any): boolean => {
    // 🆕 根据策略类型选择不同的评估逻辑
    if (config.strategy_type === 'momentum') {
      return evaluateMomentumPrediction(prediction);
    } else if (config.strategy_type === 'hybrid_rank') {
      return evaluateHybridRankPrediction(prediction);
    } else {
      return evaluateH2HPrediction(prediction);
    }
  };

  // 计算下注金额
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const calculateBetAmount = (prediction: any): number => {
    let betAmount = config.bet_amount;

    betAmount = Math.max(betAmount, 200);
    return Math.round(betAmount);
  };

  // 验证当前策略
  const validateCurrentStrategy = () => {
    debugInfo.strategyValidationCount++;
    debugInfo.lastValidationTime = new Date().toLocaleTimeString();

    // 🆕 根据策略类型选择数据源
    let predictions: any[] = [];
    if (config.strategy_type === 'momentum') {
      predictions = hybridPredictions.value || [];
      console.log(`📊 动能策略：使用 ${predictions.length} 个动能预测数据`);
    } else if (config.strategy_type === 'hybrid_rank') {
      // 🆕 复合型策略：需要同时有AI预测和动能预测数据
      const h2hData = currentAnalysis.value || [];
      const momentumData = hybridPredictions.value || [];

      console.log(`📊 复合型策略：AI预测数据 ${h2hData.length} 个，动能预测数据 ${momentumData.length} 个`);

      // 合并数据，确保每个Token都有两种预测的排名信息
      predictions = h2hData.map((h2hToken: any) => {
        const momentumToken = momentumData.find((m: any) => m.symbol === h2hToken.symbol);
        return {
          ...h2hToken,
          momentum_rank: momentumToken?.predicted_rank || 999
        };
      });
    } else {
      predictions = currentAnalysis.value || [];
      console.log(`📊 H2H策略：使用 ${predictions.length} 个分析数据`);
    }

    if (!predictions || predictions.length === 0) {
      console.log(`⚠️ 策略验证：无可用预测数据 (策略类型: ${config.strategy_type})`);
      strategyValidation.value = null;
      return;
    }

    const allMatches: any[] = [];
    let totalMatchedValue = 0;

    // 首先找出所有符合条件的预测
    predictions.forEach((rawPrediction: any) => {
      const prediction = mapPredictionData(rawPrediction);
      const isMatch = evaluatePredictionMatch(prediction);

      if (isMatch) {
        const betAmount = calculateBetAmount(prediction);
        allMatches.push({
          ...prediction,
          bet_amount: betAmount
        });
      }
    });

    console.log(`📊 策略验证：从 ${predictions.length} 个预测中找到 ${allMatches.length} 个符合条件的Token`);

    // 🔧 根据策略类型筛选最终的下注目标
    let finalMatches: any[] = [];

    if (config.strategy === 'single_bet') {
      // 🎯 单项下注：只选择一个最优的Token（通常是置信度最高或排名最高的）
      if (allMatches.length > 0) {
        // 按置信度排序，选择最优的一个
        const sortedByConfidence = [...allMatches].sort((a, b) => (b.confidence || 0) - (a.confidence || 0));
        finalMatches = [sortedByConfidence[0]];
        console.log(
          `🎯 单项策略：从 ${allMatches.length} 个符合条件的Token中选择最优: ${finalMatches[0].symbol} (置信度: ${finalMatches[0].confidence})`
        );
      } else {
        // 🚫 单项策略：没有符合条件的Token，不执行下注
        console.log(`🚫 单项策略：没有符合条件的Token，跳过下注`);
        finalMatches = [];
      }
    } else if (config.strategy === 'rank_betting') {
      // 🏆 排名下注：按预测排名过滤并排序
      const enabledRanks = config.rank_betting_enabled_ranks || [1, 2, 3];
      finalMatches = allMatches
        .filter((match) => enabledRanks.includes(match.predicted_rank))
        .sort((a, b) => (a.predicted_rank || 999) - (b.predicted_rank || 999));
      console.log(
        `🏆 排名策略：从 ${allMatches.length} 个符合条件的Token中选择排名 ${enabledRanks.join(',')} 的 ${finalMatches.length} 个Token`
      );
    } else {
      // 🚀 多项下注、对冲下注等：使用所有符合条件的Token
      finalMatches = allMatches;
      console.log(`🚀 ${config.strategy}策略：选择所有 ${finalMatches.length} 个符合条件的Token`);
    }

    // 计算总下注金额
    totalMatchedValue = finalMatches.reduce((sum, match) => sum + match.bet_amount, 0);

    const actualBalance = userInfo.value?.ojoValue || 0;
    const balanceInsufficient = totalMatchedValue > actualBalance;

    strategyValidation.value = {
      matches: finalMatches,
      total_matched: finalMatches.length,
      balance_sufficient: !balanceInsufficient,
      required_balance: totalMatchedValue,
      actual_balance: actualBalance
    };

    console.log(
      `📊 策略验证完成：${finalMatches.length} 个目标，需要余额 $${totalMatchedValue.toFixed(2)}，实际余额 $${actualBalance.toFixed(2)}`
    );
  };

  // ==================== 用户操作函数 ====================

  // 执行策略下注
  const executeStrategyBetting = async () => {
    if (!strategyValidation.value?.matches.length) {
      window.$message?.warning('没有符合条件的游戏可以下注');
      return;
    }

    if (!strategyValidation.value?.balance_sufficient) {
      window.$message?.error('余额不足，无法执行下注');
      return;
    }

    // 🔧 新增：检查游戏状态是否允许下注
    if (!canBet(currentGameStatus.value || '')) {
      window.$message?.error(`当前游戏状态不允许下注 (状态: ${currentGameStatus.value})`);
      return;
    }

    createConfirmDialog(
      '确认执行策略下注',
      `将下注 ${strategyValidation.value.matches.length} 个游戏，总金额 $${strategyValidation.value.required_balance.toFixed(2)}。是否继续？`,
      async () => {
        const result = await handleAsyncOperation(
          async () => {
            let successCount = 0;
            let failCount = 0;
            const roundId = currentRoundId.value;

            if (!roundId) {
              throw new Error('无法获取当前轮次ID');
            }

            for (const match of strategyValidation.value!.matches) {
              try {
                const betSuccess = await executeSingleBet(roundId, match.symbol, match.bet_amount, config.jwt_token);
                if (betSuccess) {
                  successCount++;
                } else {
                  failCount++;
                }
              } catch (error) {
                handleError(error, {
                  showToast: false,
                  fallbackMessage: `下注失败：${match.symbol}`
                });
                failCount++;
              }
            }

            await loadStatus();
            validateCurrentStrategy();

            return { successCount, failCount };
          },
          {
            loadingMessage: '正在执行策略下注...',
            successMessage: `策略下注完成`
          }
        );

        if (result) {
          if (result.successCount > 0) {
            window.$message?.success(`策略下注完成：成功 ${result.successCount} 个，失败 ${result.failCount} 个`);
          } else {
            window.$message?.error('策略下注全部失败');
          }
        }
      },
      {
        confirmText: '确认下注',
        cancelText: '取消',
        type: 'warning'
      }
    );
  };

  // 手动执行一次下注
  const executeManualBetting = async () => {
    // 🔧 新增：检查游戏状态是否允许下注
    if (!canBet(currentGameStatus.value || '')) {
      window.$message?.error(`当前游戏状态不允许下注 (状态: ${currentGameStatus.value})`);
      return;
    }

    await executeAutoBetting(config);
  };

  // 清空下注结果
  const clearBetResults = () => {
    debugInfo.lastBetResults = [];
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
      validateCurrentStrategy();
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

        // 如果游戏状态变为bet，触发策略验证
        if (gameData.status === 'bet') {
          validateCurrentStrategy();
        }
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
          win_rate: item.win_rate || 0,
          top3_rate: item.top3_rate || 0,
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

        // 触发策略验证
        validateCurrentStrategy();
      } else if (event.prediction) {
        // 兼容旧的单个预测数据格式
        const predictionData = event.prediction;

        // 使用store的更新方法
        predictionStore.updatePredictionData(predictionData);

        // 触发策略验证
        validateCurrentStrategy();
      }
    });

    // 监听Hybrid预测数据更新
    websocketManager.listenToHybridPredictions((event: any) => {
      console.log('📡 收到Hybrid预测数据更新:', event);

      // 更新Hybrid预测数据
      if (event.data && Array.isArray(event.data)) {
        // 使用store的更新方法
        predictionStore.updateHybridPredictions(event.data, event.meta);

        // 触发策略验证
        validateCurrentStrategy();
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
    // 🆕 根据策略类型检查数据源 - 但不作为阻止条件
    if (config.strategy_type === 'momentum') {
      if (!hybridPredictions.value || hybridPredictions.value.length === 0) {
        console.log('⚠️ 动能策略：无动能预测数据，将在执行时处理');
      }
    } else if (config.strategy_type === 'hybrid_rank') {
      if (
        !currentAnalysis.value ||
        currentAnalysis.value.length === 0 ||
        !hybridPredictions.value ||
        hybridPredictions.value.length === 0
      ) {
        console.log('⚠️ 复合型策略：缺少AI预测或动能预测数据，将在执行时处理');
      }
    } else {
      if (!currentAnalysis.value || currentAnalysis.value.length === 0) {
        console.log('⚠️ H2：无分析数据，将在执行时处理');
      }
    }

    return { canProceed: true };
  };

  // 执行自动下注的核心逻辑
  const executeAutoBettingLogic = async () => {
    const timestamp = new Date().toLocaleTimeString();
    const roundId = currentRoundId.value!;

    // 检查是否已处理此轮次
    if (processedRounds.value.has(roundId)) {
      console.log(`🚫 [${timestamp}] 轮次 ${roundId} 已处理过，跳过`);
      return;
    }

    console.log(`🎯 [${timestamp}] 开始自动下注逻辑 - 轮次: ${roundId}`);

    try {
      // 检查API中是否已下注（双重保险）
      const roundBetCheck = await autoBettingApi.checkRoundBet(currentUID.value, roundId);
      if (roundBetCheck.data.success && roundBetCheck.data.data.has_bet) {
        console.log(`🚫 [${timestamp}] 轮次 ${roundId} 已在API中记录下注，跳过`);
        processedRounds.value.add(roundId);
        return;
      }
    } catch (error) {
      console.warn(`⚠️ [${timestamp}] 检查轮次下注记录失败:`, error);
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
      console.log(`❌ [${timestamp}] 无符合条件的下注目标 (策略: ${config.strategy})`);
      console.log(`📊 [${timestamp}] 策略验证结果:`, strategyValidation.value);
      processedRounds.value.add(roundId);
      return;
    }

    if (!strategyValidation.value?.balance_sufficient) {
      console.warn(`💰 [${timestamp}] 余额不足，跳过此轮下注`);
      window.$message?.warning('余额不足，跳过此轮自动下注');
      processedRounds.value.add(roundId);
      return;
    }

    console.log(`🤖 [${timestamp}] 自动下注：检测到 ${strategyValidation.value.matches.length} 个符合条件的目标`);
    window.$message?.info(`🤖 自动下注：检测到 ${strategyValidation.value.matches.length} 个符合条件的目标`);

    let successCount = 0;
    let failCount = 0;

    // 执行下注
    for (const match of strategyValidation.value.matches) {
      console.log(`🎲 [${timestamp}] 开始下注: ${match.symbol}, 金额: ${match.bet_amount}`);
      try {
        const betSuccess = await executeSingleBet(roundId, match.symbol, match.bet_amount, config.jwt_token);
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

    // 标记此轮次已处理
    processedRounds.value.add(roundId);

    // 更新状态和验证
    await loadStatus();
    validateCurrentStrategy();

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
  };

  // ==================== 监听器设置 ====================

  // 监听器引用，用于清理
  const configWatcher = watch(
    config,
    () => {
      configComposable.autoSaveConfig(currentUID.value);
      validateCurrentStrategy();
    },
    { deep: true, flush: 'post' }
  );

  const analysisWatcher = watch(
    currentAnalysis,
    () => {
      validateCurrentStrategy();
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
    async ([isRunning, gameStatus, roundId, analysis, hybridData, jwtToken, uid], [prevIsRunning]) => {
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
      // 🔧 修复：放宽数据要求，只要有轮次ID和JWT Token就可以尝试执行
      if (gameStatus === 'bet' && roundId && isRunning && jwtToken && uid) {
        console.log(`🚀 触发自动下注检查 - 轮次: ${roundId}, 状态: ${gameStatus}`);
        console.log(`📊 当前数据状态: analysis=${analysis?.length || 0}, hybrid=${hybridData?.length || 0}`);
        await executeAutoBettingLogic();
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

    // 初始化配置
    await initializeConfig();

    // 从localStorage恢复JWT Token到配置中
    const savedTokenData = localStorage.getItem('tokenSetupData');
    if (savedTokenData) {
      try {
        const tokenData = JSON.parse(savedTokenData);
        if (tokenData.jwt_token && !config.jwt_token) {
          config.jwt_token = tokenData.jwt_token;
        }
      } catch (error) {
        console.warn('恢复JWT Token失败:', error);
      }
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
