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
            <!-- 配置同步状态提示 -->
            <div v-if="currentUID" class="mt-2">
              <span
                class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs text-green-800 font-medium"
              >
                ☁️ 配置已云端同步 (UID: {{ currentUID.slice(0, 8) }}...)
              </span>
            </div>
            <div v-else class="mt-2">
              <span
                class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs text-yellow-800 font-medium"
              >
                💾 配置本地存储 - 完成Token验证后可云端同步
              </span>
            </div>
          </div>
          <div class="w-32"></div>
        </div>

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
  import { NTabs, NTabPane } from 'naive-ui';
  import { Head } from '@inertiajs/vue3';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import WalletSetup from '@/components/WalletSetup.vue';
  import SmartControlCenter from '@/components/SmartControlCenter.vue';
  import HistoryAnalysisTab from '@/components/HistoryAnalysisTab.vue';
  import DiagnosticsTab from '@/components/DiagnosticsTab.vue';

  // 导入composables
  import { useAutoBettingConfig, strategyTemplates } from '@/composables/useAutoBettingConfig';
  import { useAutoBettingControl } from '@/composables/useAutoBettingControl';
  import { useGamePredictionStore } from '@/stores/gamePrediction';
  import { usePredictionStats } from '@/composables/usePredictionStats';
  import type { StrategyValidation } from '@/types/autoBetting';
  import { handleError, createConfirmDialog, handleAsyncOperation } from '@/utils/errorHandler';

  // 初始化composables
  const configComposable = useAutoBettingConfig();
  const controlComposable = useAutoBettingControl();
  const predictionStore = useGamePredictionStore();

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
    currentAnalysis,
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

  // 标签页状态
  const activeTab = ref('control');

  // 预测统计相关
  const recentRoundsCount = ref(50);
  const predictionStats = usePredictionStats(
    computed(() => predictionStore.predictionHistory),
    recentRoundsCount
  );

  // 策略验证状态
  const strategyValidation = ref<StrategyValidation | null>(null);

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

    if (!currentAnalysis.value?.predictions) {
      strategyValidation.value = null;
      return;
    }

    const predictions = currentAnalysis.value.predictions;
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

    // 添加确认对话框
    createConfirmDialog(
      '确认执行策略下注',
      `将下注 ${strategyValidation.value.matches.length} 个游戏，总金额 $${strategyValidation.value.required_balance.toFixed(2)}。是否继续？`,
      async () => {
        const result = await handleAsyncOperation(
          async () => {
            let successCount = 0;
            let failCount = 0;
            const roundId = currentAnalysis.value?.meta?.round_id;

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
            successMessage: `策略下注完成：成功 ${0} 个`
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

  // 定时器引用，用于清理
  let analysisRefreshTimer: number | null = null;
  let predictionRefreshTimer: number | null = null;

  // 监听器引用，用于清理
  const configWatcher = watch(
    config,
    () => {
      configComposable.autoSaveConfig(currentUID.value);
      validateCurrentStrategy();

      // 自动检测策略
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

  // 组件挂载时初始化
  onMounted(async () => {
    // 初始化配置
    await initializeConfig();

    // 尝试恢复认证状态
    const restored = await restoreAuthState();

    // 如果恢复成功，初始化预测数据和监控
    if (restored) {
      predictionStore.refreshAllPredictionData();

      // 启动游戏轮次监控
      if (!isMonitoringRounds.value) {
        isMonitoringRounds.value = true;
      }
    }

    // 无论是否有验证状态，都初始化基础预测数据展示
    predictionStore.refreshAllPredictionData();

    // 设置自动刷新定时器 - 添加名次预测自动刷新功能
    // 当前分析数据每2秒刷新一次（名次预测）
    analysisRefreshTimer = setInterval(() => {
      fetchAnalysisData();
    }, 2000);

    // 预测历史数据每15秒刷新一次
    predictionRefreshTimer = setInterval(() => {
      predictionStore.refreshAllPredictionData();
    }, 15000);
  });

  // 组件卸载时清理资源
  onUnmounted(() => {
    // 停止监听器
    if (configWatcher) configWatcher();
    if (analysisWatcher) analysisWatcher();

    // 清理定时器
    if (analysisRefreshTimer) {
      clearInterval(analysisRefreshTimer);
      analysisRefreshTimer = null;
    }
    if (predictionRefreshTimer) {
      clearInterval(predictionRefreshTimer);
      predictionRefreshTimer = null;
    }

    // 停止游戏轮次监控
    isMonitoringRounds.value = false;

    // 清理调试信息
    debugInfo.lastBetResults = [];
  });
</script>

<style scoped>
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
