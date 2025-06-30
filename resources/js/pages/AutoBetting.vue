<template>
  <DefaultLayout>
    <Head title="自动下注控制中心" />

    <!-- 身份验证模态框 -->
    <WalletSetup :visible="!isTokenValidated" @validated="onTokenValidated" />

    <div
      v-if="isTokenValidated"
      class="min-h-screen from-slate-900 via-purple-900 to-slate-900 bg-gradient-to-br p-3 sm:p-6"
    >
      <div class="mx-auto max-w-7xl">
        <!-- 导航栏 -->
        <div class="mb-6 flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <router-link
              to="/"
              class="flex items-center rounded-lg bg-slate-600 px-4 py-2 text-white transition-colors duration-200 space-x-2 hover:bg-slate-700"
            >
              <span>📊</span>
              <span>返回数据面板</span>
            </router-link>
            <!-- WebSocket状态指示器 -->
            <div class="flex items-center rounded-lg px-3 py-2 text-sm space-x-2" :class="getWebSocketStatusClass()">
              <span>{{ getWebSocketStatusIcon() }}</span>
              <span>{{ websocketStatus.message }}</span>
              <button v-if="!isConnected" @click="reconnectWebSocket()" class="text-xs underline">重连</button>
            </div>
          </div>

          <div class="flex-1 text-center">
            <h1 class="text-2xl text-white font-bold">🤖 自动下注控制中心</h1>
            <p class="text-gray-300">基于AI预测的智能下注系统</p>
            <!-- 配置同步状态提示 -->
            <div v-if="currentUID" class="mt-2">
              <span
                class="inline-flex items-center border border-green-500/30 rounded-full bg-green-500/20 px-2.5 py-0.5 text-xs text-green-400 font-medium"
              >
                ☁️ 配置已云端同步 (UID: {{ currentUID.slice(0, 8) }}...)
              </span>
            </div>
            <div v-else class="mt-2">
              <span
                class="inline-flex items-center border border-yellow-500/30 rounded-full bg-yellow-500/20 px-2.5 py-0.5 text-xs text-yellow-400 font-medium"
              >
                💾 配置本地存储 - 完成Token验证后可云端同步
              </span>
            </div>
          </div>

          <div class="flex items-center space-x-3">
            <!-- 当前策略显示 -->
            <div class="text-right">
              <div class="text-xs text-gray-400">当前策略</div>
              <div class="text-sm text-white font-medium">{{ currentStrategyName }}</div>
            </div>
            <!-- 自动下注状态 -->
            <div class="flex items-center rounded-lg px-3 py-2 text-sm space-x-2" :class="getAutoBettingStatusClass()">
              <span>{{ getAutoBettingStatusIcon() }}</span>
              <span>{{ autoBettingStatus.is_running ? '运行中' : '已停止' }}</span>
            </div>
          </div>
        </div>

        <!-- 系统状态面板 -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="📊 系统状态概览"
          size="large"
        >
          <div class="grid grid-cols-1 gap-4 lg:grid-cols-4 md:grid-cols-2 sm:grid-cols-2">
            <!-- 用户信息卡片 -->
            <div
              class="prediction-stat-card border-blue-500/30 from-blue-500/10 to-indigo-600/5 bg-gradient-to-br hover:border-blue-400/50 hover:shadow-blue-500/20"
            >
              <div class="stat-icon">👤</div>
              <div class="stat-content">
                <div class="stat-label text-blue-300">用户余额</div>
                <div class="stat-value text-blue-400">${{ (userInfo?.ojoValue || 0).toFixed(2) }}</div>
                <div class="stat-desc text-blue-200/70">可用于下注</div>
              </div>
            </div>

            <!-- 策略状态卡片 -->
            <div
              class="prediction-stat-card border-purple-500/30 from-purple-500/10 to-indigo-600/5 bg-gradient-to-br hover:border-purple-400/50 hover:shadow-purple-500/20"
            >
              <div class="stat-icon">🎯</div>
              <div class="stat-content">
                <div class="stat-label text-purple-300">策略匹配</div>
                <div class="stat-value text-purple-400">
                  {{ strategyValidation?.total_matched || 0 }}
                </div>
                <div class="stat-desc text-purple-200/70">符合条件的Token</div>
              </div>
            </div>

            <!-- 预测数据状态 -->
            <div
              class="prediction-stat-card border-green-500/30 from-green-500/10 to-emerald-600/5 bg-gradient-to-br hover:border-green-400/50 hover:shadow-green-500/20"
            >
              <div class="stat-icon">🔮</div>
              <div class="stat-content">
                <div class="stat-label text-green-300">AI预测数据</div>
                <div class="stat-value text-green-400">
                  {{ currentAnalysis.length }}
                </div>
                <div class="stat-desc text-green-200/70">个Token分析</div>
              </div>
            </div>

            <!-- 轮次信息 -->
            <div
              class="prediction-stat-card border-orange-500/30 from-orange-500/10 to-red-600/5 bg-gradient-to-br hover:border-orange-400/50 hover:shadow-orange-500/20"
            >
              <div class="stat-icon">🎲</div>
              <div class="stat-content">
                <div class="stat-label text-orange-300">当前轮次</div>
                <div class="stat-value text-lg text-orange-400">
                  {{ currentRoundId || 'N/A' }}
                </div>
                <div class="stat-desc text-orange-200/70">
                  <NTag :type="getStatusTagType(currentGameStatus)" size="small">
                    {{ getStatusText(currentGameStatus) }}
                  </NTag>
                </div>
              </div>
            </div>
          </div>
        </NCard>

        <!-- 当前预测分析面板 -->
        <NCard
          v-if="currentAnalysis.length > 0"
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="🔮 当前轮次AI预测"
          size="large"
        >
          <template #header-extra>
            <div class="flex items-center space-x-3">
              <n-button :loading="analysisLoading" @click="fetchAnalysisData" type="primary" size="small">
                🔄 刷新预测
              </n-button>
              <n-button
                v-if="strategyValidation?.matches.length"
                :loading="executeLoading"
                @click="executeStrategyBetting"
                type="warning"
                size="small"
              >
                ⚡ 执行策略下注
              </n-button>
            </div>
          </template>

          <div class="space-y-4">
            <!-- 策略匹配结果 -->
            <div v-if="strategyValidation" class="grid grid-cols-1 gap-3 lg:grid-cols-3 md:grid-cols-2">
              <div
                class="prediction-stat-card border-emerald-500/30 from-emerald-500/10 to-green-600/5 bg-gradient-to-br hover:border-emerald-400/50 hover:shadow-emerald-500/20"
              >
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                  <div class="stat-label text-emerald-300">策略匹配</div>
                  <div class="stat-value text-emerald-400">{{ strategyValidation.total_matched }}</div>
                  <div class="stat-desc text-emerald-200/70">个符合条件</div>
                </div>
              </div>

              <div
                class="prediction-stat-card border-cyan-500/30 from-cyan-500/10 to-blue-600/5 bg-gradient-to-br hover:border-cyan-400/50 hover:shadow-cyan-500/20"
              >
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                  <div class="stat-label text-cyan-300">所需金额</div>
                  <div class="stat-value text-cyan-400">${{ strategyValidation.required_balance.toFixed(0) }}</div>
                  <div class="stat-desc text-cyan-200/70">总下注金额</div>
                </div>
              </div>

              <div
                class="prediction-stat-card hover:shadow-lg"
                :class="
                  strategyValidation.balance_sufficient
                    ? 'border-green-500/30 from-green-500/10 to-emerald-600/5 bg-gradient-to-br hover:border-green-400/50 hover:shadow-green-500/20'
                    : 'border-red-500/30 from-red-500/10 to-pink-600/5 bg-gradient-to-br hover:border-red-400/50 hover:shadow-red-500/20'
                "
              >
                <div class="stat-icon">{{ strategyValidation.balance_sufficient ? '✅' : '❌' }}</div>
                <div class="stat-content">
                  <div
                    class="stat-label"
                    :class="strategyValidation.balance_sufficient ? 'text-green-300' : 'text-red-300'"
                  >
                    余额状态
                  </div>
                  <div
                    class="stat-value"
                    :class="strategyValidation.balance_sufficient ? 'text-green-400' : 'text-red-400'"
                  >
                    {{ strategyValidation.balance_sufficient ? '充足' : '不足' }}
                  </div>
                  <div
                    class="stat-desc"
                    :class="strategyValidation.balance_sufficient ? 'text-green-200/70' : 'text-red-200/70'"
                  >
                    余额: ${{ strategyValidation.actual_balance.toFixed(0) }}
                  </div>
                </div>
              </div>
            </div>

            <!-- 匹配的Token展示 -->
            <div v-if="strategyValidation?.matches.length" class="space-y-3">
              <h3 class="text-white font-medium">符合策略条件的Token:</h3>
              <div class="grid grid-cols-1 gap-3 lg:grid-cols-3 md:grid-cols-2 xl:grid-cols-5">
                <div
                  v-for="(match, index) in strategyValidation.matches"
                  :key="`match-${index}-${match.symbol}`"
                  class="relative overflow-hidden border rounded-lg p-3 transition-all duration-300 hover:shadow-lg"
                  :class="getMatchCardClass(index)"
                >
                  <div class="mb-2 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                      <div class="text-lg">{{ getPredictionIcon(match.predicted_rank - 1) }}</div>
                      <div class="text-sm text-white font-bold">{{ match.symbol }}</div>
                    </div>
                    <div class="text-xs text-gray-400">#{{ match.predicted_rank }}</div>
                  </div>

                  <div class="text-xs space-y-1">
                    <div class="flex justify-between">
                      <span class="text-gray-400">下注金额:</span>
                      <span class="text-green-400 font-bold">${{ match.bet_amount }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-400">置信度:</span>
                      <span class="text-blue-400 font-bold">{{ (match.confidence || 0).toFixed(1) }}%</span>
                    </div>
                    <div v-if="match.score" class="flex justify-between">
                      <span class="text-gray-400">预测分数:</span>
                      <span class="text-purple-400 font-bold">{{ (match.score || 0).toFixed(1) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 无匹配时的提示 -->
            <NEmpty v-else description="当前无符合策略条件的Token" class="py-8" />
          </div>
        </NCard>

        <!-- 标签页导航 -->
        <NTabs v-model:value="activeTab" type="card" size="large" class="auto-betting-tabs">
          <!-- 智能控制中心标签页 -->
          <NTabPane name="control" tab="🎛️ 智能控制中心">
            <SmartControlCenter
              :user-info="userInfo"
              :auto-betting-status="autoBettingStatus"
              :current-analysis="currentAnalysis"
              :debug-info="debugInfo"
              :toggle-loading="toggleLoading"
              :execute-loading="executeLoading"
              :analysis-loading="analysisLoading"
              :diagnostics-loading="diagnosticsLoading"
              :strategy-name="currentStrategyName"
              :confidence-threshold="config.confidence_threshold"
              :risk-level="config.max_bet_percentage"
              :config="config"
              :selected-template="selectedTemplate"
              :custom-strategy-mode="customStrategyMode"
              :config-saving="configSaving"
              :config-sync-status="configSyncStatus"
              :strategy-templates="strategyTemplates"
              :strategy-templates-with-custom="getStrategyTemplatesWithCustom()"
              :strategy-validation="strategyValidation"
              :is-running="autoBettingStatus.is_running"
              :has-u-i-d="!!currentUID"
              @start-auto-betting="startAutoBetting"
              @stop-auto-betting="stopAutoBetting"
              @execute-manual-betting="executeManualBetting"
              @fetch-analysis-data="fetchAnalysisData"
              @reconnect-token="reconnectToken"
              @clear-bet-results="clearBetResults"
              @apply-strategy-template="applyStrategyTemplate"
              @switch-to-custom-mode="switchToCustomMode"
              @reset-to-template-mode="resetToTemplateMode"
              @execute-strategy-betting="executeStrategyBetting"
              @manual-save-config="manualSaveConfig"
              @run-api-diagnostics="runApiDiagnostics"
            />
          </NTabPane>

          <!-- 历史与分析标签页 -->
          <NTabPane name="history" tab="📊 历史与分析">
            <HistoryAnalysisTab
              :exact-rate="predictionStats.calculateRoundBasedStats.value.exactRate"
              :total-rounds="predictionStats.calculatePortfolioStats.value.totalRounds"
              :all-stats="predictionStats.calculateRankBasedStats.value"
              :recent-stats="predictionStats.calculateRecentRankBasedStats.value"
              :recent-rounds-count="recentRoundsCount"
              :max-rounds="predictionStore.totalHistoryRounds"
              :history-loading="predictionStore.historyLoading"
              :prediction-comparison-data="predictionStats.getPredictionComparisonData.value"
              @refresh-prediction-history="predictionStore.fetchPredictionHistory"
              @update:recent-rounds-count="updateRecentRoundsCount"
            />
          </NTabPane>

          <!-- 系统诊断标签页 -->
          <NTabPane name="diagnostics" tab="🔬 系统诊断">
            <DiagnosticsTab
              :debug-info="debugInfo"
              :is-monitoring-rounds="isMonitoringRounds"
              :last-known-round-id="lastKnownRoundId"
              :auto-betting-status="autoBettingStatus"
              :strategy-validation="strategyValidation"
              :diagnostics-loading="diagnosticsLoading"
              @run-api-diagnostics="runApiDiagnostics"
              @clear-bet-results="clearBetResults"
            />
          </NTabPane>
        </NTabs>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
  import { NTabs, NTabPane, NEmpty, NTag } from 'naive-ui';
  import { Head } from '@inertiajs/vue3';
  import { storeToRefs } from 'pinia';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import WalletSetup from '@/components/WalletSetup.vue';
  import SmartControlCenter from '@/components/SmartControlCenter.vue';
  import HistoryAnalysisTab from '@/components/HistoryAnalysisTab.vue';
  import DiagnosticsTab from '@/components/DiagnosticsTab.vue';

  // 导入composables和stores
  import { useAutoBettingConfig, strategyTemplates } from '@/composables/useAutoBettingConfig';
  import { useAutoBettingControl } from '@/composables/useAutoBettingControl';
  import { useGamePredictionStore } from '@/stores/gamePrediction';
  import { usePredictionStats } from '@/composables/usePredictionStats';
  import type { StrategyValidation } from '@/types/autoBetting';
  import { handleError, createConfirmDialog, handleAsyncOperation } from '@/utils/errorHandler';
  import { autoBettingApi } from '@/utils/api';

  // 初始化composables和stores
  const configComposable = useAutoBettingConfig();
  const controlComposable = useAutoBettingControl();
  const predictionStore = useGamePredictionStore();

  // 从store中获取响应式数据
  const { predictionHistory, currentAnalysis, currentRoundId, currentGameStatus, websocketStatus, isConnected } =
    storeToRefs(predictionStore);

  // 从composables中解构状态和方法
  const {
    config,
    selectedTemplate,
    customStrategyMode,
    configSaving,
    configSyncStatus,
    getStrategyTemplatesWithCustom,
    detectCurrentStrategy,
    applyStrategyTemplate,
    switchToCustomMode,
    resetToTemplateMode,
    manualSaveConfig,
    initializeConfig
  } = configComposable;

  const {
    isTokenValidated,
    currentUID,
    userInfo,
    autoBettingStatus,
    lastKnownRoundId,
    isMonitoringRounds,
    debugInfo,
    toggleLoading,
    executeLoading,
    analysisLoading,
    diagnosticsLoading,
    startAutoBetting,
    stopAutoBetting,
    executeAutoBetting,
    fetchAnalysisData,
    runApiDiagnostics,
    reconnectToken,
    onTokenValidated,
    restoreAuthState,
    executeSingleBet,
    loadStatus
  } = controlComposable;

  // 从store中获取WebSocket重连方法
  const { reconnectWebSocket } = predictionStore;

  // 标签页状态
  const activeTab = ref('control');

  // 预测统计相关
  const recentRoundsCount = ref(50);
  const predictionStats = usePredictionStats(predictionHistory, recentRoundsCount);

  // 策略验证状态
  const strategyValidation = ref<StrategyValidation | null>(null);

  // ==================== 工具函数 ====================

  // 状态文本转换
  const getStatusText = (status: string) => {
    const statusMap = {
      bet: '🟢 投注中',
      lock: '🟡 已锁定',
      settling: '🟠 结算中',
      settled: '🔵 已结算',
      unknown: '❓ 未知'
    };
    return statusMap[status as keyof typeof statusMap] || '❓ 未知';
  };

  // 状态标签类型
  const getStatusTagType = (status: string) => {
    switch (status) {
      case 'bet':
        return 'success';
      case 'settling':
        return 'warning';
      case 'settled':
        return 'info';
      default:
        return 'default';
    }
  };

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

  // 预测图标
  const getPredictionIcon = (index: number) => {
    if (index === 0) return '🥇';
    if (index === 1) return '🥈';
    if (index === 2) return '🥉';
    return '📊';
  };

  // 匹配卡片样式
  const getMatchCardClass = (index: number) => {
    const colors = [
      'border-yellow-400/30 bg-gradient-to-br from-yellow-500/10 to-amber-600/5 hover:border-yellow-400/50 hover:shadow-yellow-500/20',
      'border-slate-400/30 bg-gradient-to-br from-slate-500/10 to-gray-600/5 hover:border-slate-400/50 hover:shadow-slate-500/20',
      'border-orange-400/30 bg-gradient-to-br from-orange-500/10 to-red-600/5 hover:border-orange-400/50 hover:shadow-orange-500/20',
      'border-blue-400/30 bg-gradient-to-br from-blue-500/10 to-indigo-600/5 hover:border-blue-400/50 hover:shadow-blue-500/20',
      'border-purple-400/30 bg-gradient-to-br from-purple-500/10 to-pink-600/5 hover:border-purple-400/50 hover:shadow-purple-500/20'
    ];
    return colors[index % colors.length];
  };

  // ==================== 计算属性 ====================

  // 当前策略名称计算属性
  const currentStrategyName = computed(() => {
    if (customStrategyMode.value) {
      return '自定义策略';
    }
    if (selectedTemplate.value && strategyTemplates[selectedTemplate.value as keyof typeof strategyTemplates]) {
      return strategyTemplates[selectedTemplate.value as keyof typeof strategyTemplates].name;
    }
    return '未选择策略';
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
      predicted_rank: rawPrediction.predicted_rank
    };
  };

  // 评估预测是否符合策略条件
  const evaluatePredictionMatch = (prediction: any): boolean => {
    if (config.strategy === 'rank_betting') {
      return config.rank_betting_enabled_ranks.includes(prediction.predicted_rank);
    }

    if (prediction.confidence < config.confidence_threshold) return false;
    if (prediction.score < config.score_gap_threshold) return false;
    if (prediction.sample_count < config.min_sample_count) return false;
    if (prediction.historical_accuracy < config.historical_accuracy_threshold) return false;

    return true;
  };

  // 计算下注金额
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const calculateBetAmount = (prediction: any): number => {
    let betAmount = config.bet_amount;
    const walletBalance = userInfo.value?.ojoValue || 0;

    if (walletBalance > 0) {
      betAmount = Math.min(betAmount, walletBalance * (config.max_bet_percentage / 100));
    }

    betAmount = Math.max(betAmount, 200);
    return Math.round(betAmount);
  };

  // 验证当前策略
  const validateCurrentStrategy = () => {
    debugInfo.strategyValidationCount++;
    debugInfo.lastValidationTime = new Date().toLocaleTimeString();

    if (!currentAnalysis.value || currentAnalysis.value.length === 0) {
      strategyValidation.value = null;
      return;
    }

    const predictions = currentAnalysis.value;
    const matches: any[] = [];
    let totalMatchedValue = 0;

    predictions.forEach((rawPrediction: any) => {
      const prediction = mapPredictionData(rawPrediction);
      const isMatch = evaluatePredictionMatch(prediction);

      if (isMatch) {
        const betAmount = calculateBetAmount(prediction);
        matches.push({
          ...prediction,
          bet_amount: betAmount
        });
        totalMatchedValue += betAmount;
      }
    });

    const actualBalance = userInfo.value?.ojoValue || 0;
    const balanceInsufficient = totalMatchedValue > actualBalance;

    strategyValidation.value = {
      matches,
      total_matched: matches.length,
      balance_sufficient: !balanceInsufficient,
      required_balance: totalMatchedValue,
      actual_balance: actualBalance
    };
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

  // ==================== 自动下注逻辑 ====================

  let autoBettingTimer: number | null = null;

  // 自动下注逻辑
  const performAutoBetting = async () => {
    if (!autoBettingStatus.value.is_running) return;
    if (!config.jwt_token) return;
    if (!currentAnalysis.value || currentAnalysis.value.length === 0) return;

    const roundId = currentRoundId.value;
    if (!roundId) return;

    try {
      // 检查是否已经在该轮次下过注
      const roundBetCheck = await autoBettingApi.checkRoundBet(currentUID.value, roundId);
      if (roundBetCheck.data.success && roundBetCheck.data.data.has_bet) {
        console.log(`轮次 ${roundId} 已下注，跳过`);
        return;
      }
    } catch (error) {
      console.warn('检查轮次下注记录失败:', error);
    }

    validateCurrentStrategy();

    if (!strategyValidation.value?.matches.length) {
      console.log('当前无符合条件的下注目标');
      return;
    }

    if (!strategyValidation.value?.balance_sufficient) {
      console.warn('余额不足，暂停自动下注');
      window.$message?.warning('余额不足，暂停自动下注');
      return;
    }

    try {
      console.log(`🤖 自动下注：检测到 ${strategyValidation.value.matches.length} 个符合条件的目标`);
      window.$message?.info(`🤖 自动下注：检测到 ${strategyValidation.value.matches.length} 个符合条件的目标`);

      let successCount = 0;
      let failCount = 0;

      for (const match of strategyValidation.value.matches) {
        try {
          const betSuccess = await executeSingleBet(roundId, match.symbol, match.bet_amount, config.jwt_token);
          if (betSuccess) {
            successCount++;
            debugInfo.lastBetResults.push({
              time: new Date().toLocaleTimeString(),
              symbol: match.symbol,
              amount: match.bet_amount,
              success: true
            });
          } else {
            failCount++;
            debugInfo.lastBetResults.push({
              time: new Date().toLocaleTimeString(),
              symbol: match.symbol,
              amount: match.bet_amount,
              success: false
            });
          }

          await new Promise((resolve) => setTimeout(resolve, 500));
        } catch (error) {
          console.error(`自动下注失败 ${match.symbol}:`, error);
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

      await loadStatus();
      validateCurrentStrategy();

      if (successCount > 0) {
        console.log(`🎯 自动下注完成：成功 ${successCount} 个，失败 ${failCount} 个`);
        window.$message?.success(`🎯 自动下注完成：成功 ${successCount} 个，失败 ${failCount} 个`);
      } else if (failCount > 0) {
        console.error(`❌ 自动下注失败：失败 ${failCount} 个`);
        window.$message?.error(`❌ 自动下注失败：失败 ${failCount} 个`);
      }
    } catch (error) {
      console.error('自动下注执行失败:', error);
      window.$message?.error('自动下注执行失败');
    }
  };

  // 启动自动下注定时器
  const startAutoBettingTimer = () => {
    if (autoBettingTimer) {
      clearInterval(autoBettingTimer);
    }

    autoBettingTimer = setInterval(async () => {
      await performAutoBetting();
    }, 15000); // 15秒间隔

    console.log('🤖 自动下注定时器已启动 - 每15秒检查一次');
  };

  // 停止自动下注定时器
  const stopAutoBettingTimer = () => {
    if (autoBettingTimer) {
      clearInterval(autoBettingTimer);
      autoBettingTimer = null;
      console.log('🛑 自动下注定时器已停止');
    }
  };

  // ==================== 监听器设置 ====================

  // 监听器引用，用于清理
  const configWatcher = watch(
    config,
    () => {
      configComposable.autoSaveConfig(currentUID.value);
      validateCurrentStrategy();

      const detectedStrategy = detectCurrentStrategy();
      if (selectedTemplate.value !== detectedStrategy) {
        selectedTemplate.value = detectedStrategy;
        if (detectedStrategy === 'custom') {
          customStrategyMode.value = true;
        } else {
          customStrategyMode.value = false;
        }
      }
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

  // 监听自动下注状态变化
  const autoBettingStatusWatcher = watch(
    () => autoBettingStatus.value.is_running,
    (isRunning) => {
      if (isRunning) {
        startAutoBettingTimer();
        window.$message?.success('🤖 自动下注监控已启动，系统将自动检查条件并执行下注');
      } else {
        stopAutoBettingTimer();
        window.$message?.info('🛑 自动下注监控已停止');
      }
    },
    { immediate: true }
  );

  // ==================== 生命周期钩子 ====================

  // 组件挂载时初始化
  onMounted(async () => {
    await initializeConfig();
    const restored = await restoreAuthState();

    if (restored) {
      if (!isMonitoringRounds.value) {
        isMonitoringRounds.value = true;
      }
    }

    console.log('🤖 自动下注页面已加载，使用WebSocket实时数据模式');
  });

  // 组件卸载时清理资源
  onUnmounted(() => {
    if (configWatcher) configWatcher();
    if (analysisWatcher) analysisWatcher();
    if (autoBettingStatusWatcher) autoBettingStatusWatcher();

    if (autoBettingTimer) {
      clearInterval(autoBettingTimer);
      autoBettingTimer = null;
    }

    isMonitoringRounds.value = false;
    debugInfo.lastBetResults = [];

    console.log('🧹 自动下注页面已卸载，已清理所有定时器和监听器');
  });
</script>

<style scoped>
  .prediction-stat-card {
    @apply relative overflow-hidden border rounded-xl p-4 transition-all duration-300 hover:shadow-lg sm:p-6;
  }

  .stat-icon {
    @apply absolute right-2 top-2 text-xl opacity-20 sm:text-2xl;
  }

  .stat-content {
    @apply relative;
  }

  .stat-label {
    @apply text-xs font-medium sm:text-sm;
  }

  .stat-value {
    @apply mt-2 text-2xl font-bold sm:text-3xl;
  }

  .stat-value span {
    @apply text-base sm:text-lg;
  }

  .stat-desc {
    @apply mt-2 text-xs;
  }

  :deep(.auto-betting-tabs .n-tabs-nav) {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
  }

  :deep(.auto-betting-tabs .n-tabs-tab) {
    border-radius: 6px;
    margin: 2px;
  }

  :deep(.auto-betting-tabs .n-tabs-tab.n-tabs-tab--active) {
    background: rgba(59, 130, 246, 0.2);
    color: rgb(96, 165, 250);
  }
</style>
