<template>
  <div class="space-y-6">
    <!-- 🎯 统一预测展示区域 -->
    <div class="space-y-6">
      <!-- 🔮 AI预测排名面板 -->
      <AIPredictionRanking
        :current-analysis="currentAnalysis"
        :analysis-meta="analysisMeta"
        :current-round-id="currentRoundId"
        :current-game-status="currentGameStatus"
        :current-game-tokens-with-ranks="currentGameTokensWithRanks"
        :analysis-loading="analysisLoading"
        @refresh-analysis="refreshAnalysis"
      />

      <!-- ⚡ AI动能预测排名面板 -->
      <MomentumPredictionDisplay
        :hybrid-predictions="hybridPredictions || []"
        :analysis-meta="hybridAnalysisMeta"
        :current-round-id="currentRoundId"
        :current-game-status="currentGameStatus"
        :current-game-tokens-with-ranks="currentGameTokensWithRanks"
        :analysis-loading="hybridAnalysisLoading"
        @refresh-analysis="refreshHybridAnalysis"
      />
    </div>

    <!-- 🤖 自动下注状态面板 -->
    <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="🤖 自动下注状态" size="large">
      <template #header-extra>
        <div class="flex items-center space-x-3">
          <n-button
            v-if="strategyValidation?.matches.length"
            :loading="executeLoading"
            @click="executeStrategyBetting"
            type="primary"
            size="small"
          >
            ⚡ 执行策略下注
          </n-button>
        </div>
      </template>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 md:grid-cols-3 sm:grid-cols-2">
        <!-- 用户余额 -->
        <div
          class="prediction-stat-card border-blue-500/30 from-blue-500/10 to-indigo-600/5 bg-gradient-to-br hover:border-blue-400/50 hover:shadow-blue-500/20"
        >
          <div class="stat-icon">👤</div>
          <div class="stat-content">
            <div class="stat-label text-blue-300">用户余额</div>
            <div class="stat-value text-blue-400">${{ (strategyValidation?.actual_balance || 0).toFixed(2) }}</div>
            <div class="stat-desc text-blue-200/70">可用于下注</div>
          </div>
        </div>

        <!-- 策略匹配 -->
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

        <!-- 下注金额 -->
        <div
          class="prediction-stat-card border-cyan-500/30 from-cyan-500/10 to-blue-600/5 bg-gradient-to-br hover:border-cyan-400/50 hover:shadow-cyan-500/20"
        >
          <div class="stat-icon">💰</div>
          <div class="stat-content">
            <div class="stat-label text-cyan-300">所需金额</div>
            <div class="stat-value text-cyan-400">${{ (strategyValidation?.required_balance || 0).toFixed(0) }}</div>
            <div class="stat-desc text-cyan-200/70">总下注金额</div>
          </div>
        </div>

        <!-- 余额状态 -->
        <div
          class="prediction-stat-card hover:shadow-lg"
          :class="
            (strategyValidation?.balance_sufficient ?? true)
              ? 'border-green-500/30 from-green-500/10 to-emerald-600/5 bg-gradient-to-br hover:border-green-400/50 hover:shadow-green-500/20'
              : 'border-red-500/30 from-red-500/10 to-pink-600/5 bg-gradient-to-br hover:border-red-400/50 hover:shadow-red-500/20'
          "
        >
          <div class="stat-icon">{{ (strategyValidation?.balance_sufficient ?? true) ? '✅' : '❌' }}</div>
          <div class="stat-content">
            <div
              class="stat-label"
              :class="(strategyValidation?.balance_sufficient ?? true) ? 'text-green-300' : 'text-red-300'"
            >
              余额状态
            </div>
            <div
              class="stat-value"
              :class="(strategyValidation?.balance_sufficient ?? true) ? 'text-green-400' : 'text-red-400'"
            >
              {{ (strategyValidation?.balance_sufficient ?? true) ? '充足' : '不足' }}
            </div>
            <div
              class="stat-desc"
              :class="(strategyValidation?.balance_sufficient ?? true) ? 'text-green-200/70' : 'text-red-200/70'"
            >
              实际余额: ${{ (strategyValidation?.actual_balance || 0).toFixed(0) }}
            </div>
          </div>
        </div>

        <!-- 总下注次数 -->
        <div
          class="prediction-stat-card border-amber-500/30 from-amber-500/10 to-yellow-600/5 bg-gradient-to-br hover:border-amber-400/50 hover:shadow-amber-500/20"
        >
          <div class="stat-icon">📊</div>
          <div class="stat-content">
            <div class="stat-label text-amber-300">总下注次数</div>
            <div class="stat-value text-amber-400">{{ autoBettingStatus.total_bets || 0 }}</div>
            <div class="stat-desc text-amber-200/70">历史累计</div>
          </div>
        </div>
      </div>

      <!-- Token详细匹配分析 -->
      <div v-if="currentAnalysis && currentAnalysis.length > 0" class="mt-6 space-y-4">
        <!-- 匹配概览 -->
        <div class="flex items-center justify-between">
          <h3 class="text-white font-medium">Token匹配分析 (共{{ currentAnalysis.length }}个)</h3>
          <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-400">符合条件:</span>
            <span class="font-bold" :class="strategyValidation?.matches.length ? 'text-green-400' : 'text-red-400'">
              {{ strategyValidation?.matches.length || 0 }}个
            </span>
          </div>
        </div>

        <!-- Token分析网格 -->
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-5 md:grid-cols-3 xl:grid-cols-5">
          <div
            v-for="(token, index) in displayAnalysisData.slice(0, 5)"
            :key="`analysis-${index}-${token.symbol}`"
            class="border rounded-lg p-3 transition-all duration-200 hover:shadow-lg"
            :class="getTokenDebugClass(token)"
          >
            <!-- Token头部 - 横向紧凑布局 -->
            <div class="mb-3">
              <!-- 第一行：图标 + Token符号 + 排名 -->
              <div class="mb-2 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                  <span class="text-lg">{{ getPredictionIcon(index + 1) }}</span>
                  <span class="text-sm text-white font-bold">{{ token.symbol }}</span>
                  <span class="text-xs text-gray-400">#{{ token.predicted_rank || index + 1 }}</span>
                </div>
              </div>
              <!-- 第二行：匹配状态 + 下注金额 -->
              <div class="flex items-center justify-between">
                <span class="text-xs font-medium" :class="isTokenMatching(token) ? 'text-green-400' : 'text-red-400'">
                  {{ isTokenMatching(token) ? '✅ 匹配' : '❌ 不匹配' }}
                </span>
                <span v-if="isTokenMatching(token)" class="text-xs text-green-400 font-medium">
                  下注 ${{ config.bet_amount }}
                </span>
              </div>
              <!-- 🆕 复合型策略：显示两种排名 -->
              <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-blue-300">AI: #{{ token.predicted_rank || '-' }}</span>
                <span class="text-green-300">
                  动能:
                  <template v-if="token.momentum_rank !== undefined && token.momentum_rank !== null">
                    #{{ token.momentum_rank }}
                  </template>
                  <template v-else>-</template>
                </span>
              </div>
            </div>

            <!-- 核心指标 -->
            <div class="text-xs space-y-2">
              <div class="flex justify-between">
                <span class="text-gray-400">置信度:</span>
                <span class="text-blue-400">{{ getTokenConfidence(token).toFixed(1) }}%</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">分数:</span>
                <span class="text-blue-400">
                  {{ getTokenScore(token).toFixed(1) }}
                </span>
              </div>
              <!-- 🔧 新增：显示动能分数 -->
              <div v-if="token.mom_score !== null && token.mom_score !== undefined" class="flex justify-between">
                <span class="text-gray-400">动能分数:</span>
                <span class="text-green-400">{{ token.mom_score.toFixed(1) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">样本数:</span>
                <span class="text-blue-400">
                  {{ getTokenSampleCount(token) }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">胜率:</span>
                <span class="text-blue-400">{{ (getTokenHistoricalAccuracy(token) * 100).toFixed(1) }}%</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 显示更多提示 -->
        <div v-if="displayAnalysisData.length > 5" class="mt-4 text-center">
          <span class="text-xs text-gray-400">显示前5个Token，总共{{ displayAnalysisData.length }}个</span>
        </div>
      </div>

      <!-- 无数据时的提示 -->
      <NEmpty v-else description="暂无预测数据" class="mt-6 py-8" />

      <!-- 核心控制按钮 -->
      <div class="mt-6 flex justify-center space-x-4">
        <n-button
          v-if="!autoBettingStatus.is_running"
          @click="startAutoBetting"
          :loading="toggleLoading"
          type="success"
          size="large"
          class="shadow-green-500/25 shadow-lg hover:shadow-green-500/40"
        >
          <template #icon>
            <span>▶️</span>
          </template>
          启动自动下注
        </n-button>

        <n-button
          v-else
          @click="stopAutoBetting"
          :loading="toggleLoading"
          type="error"
          size="large"
          class="shadow-lg shadow-red-500/25 hover:shadow-red-500/40"
        >
          <template #icon>
            <span>⏹️</span>
          </template>
          停止自动下注
        </n-button>

        <n-button
          @click="executeManualBetting"
          :loading="executeLoading"
          type="warning"
          size="large"
          class="shadow-lg shadow-orange-500/25 hover:shadow-orange-500/40"
        >
          <template #icon>
            <span>🎯</span>
          </template>
          手动执行一次
        </n-button>
      </div>
    </NCard>

    <!-- 🎛️ 智能控制中心：策略配置区域 -->
    <StrategyConfigPanel
      :config="localConfig"
      :is-running="isRunning"
      :config-saving="configSaving"
      :config-loading="configLoading"
      :has-u-i-d="hasUID"
      @update:config="onUpdateConfig"
      @save-config="manualSaveConfig"
    />
  </div>
</template>

<script setup lang="ts">
  import { onMounted, watch, computed, onUnmounted, ref } from 'vue';
  import { NEmpty } from 'naive-ui';
  import AIPredictionRanking from '@/components/AIPredictionRanking.vue';
  import MomentumPredictionDisplay from '@/components/MomentumPredictionDisplay.vue';
  import StrategyConfigPanel from '@/components/StrategyConfigPanel.vue';
  import type { AutoBettingStatus, DebugInfo } from '@/composables/useAutoBettingControl';
  import type { AutoBettingConfig } from '@/composables/useAutoBettingConfig';
  import { usePredictionDisplay } from '@/composables/usePredictionDisplay';
  import { useGamePredictionStore } from '@/stores/gamePrediction';

  // Props
  interface Props {
    autoBettingStatus: AutoBettingStatus;
    currentAnalysis: any;
    analysisMeta: any;
    currentRoundId: string | null;
    currentGameStatus: string;
    currentGameTokensWithRanks: any[];
    debugInfo: DebugInfo;
    toggleLoading: boolean;
    executeLoading: boolean;
    analysisLoading: boolean;

    strategyName: string;
    config: AutoBettingConfig;
    configSaving: boolean;
    configLoading: boolean; // 新增：配置加载状态
    configSyncStatus: { type: 'success' | 'error' | 'info'; message: string } | null;
    strategyValidation: any;

    isRunning: boolean;
    hasUID: boolean;
    hybridPredictions?: any[]; // 新增：Hybrid-Edge v1.0 動能預測數據
    hybridAnalysisMeta?: any; // 新增：Hybrid预测元数据
    hybridAnalysisLoading?: boolean; // 新增：Hybrid预测加载状态
    refreshHybridAnalysis?: () => void; // 新增：刷新Hybrid预测方法
  }

  const props = withDefaults(defineProps<Props>(), {
    hybridAnalysisLoading: false
  });

  // Emits
  const emit = defineEmits<{
    startAutoBetting: [];
    stopAutoBetting: [];
    executeManualBetting: [];
    applyStrategyTemplate: [key: string];
    executeStrategyBetting: [];
    manualSaveConfig: [];
    refreshAnalysis: [];
    updateConfig: [config: AutoBettingConfig];
  }>();

  // 🔧 修复：创建本地config副本，避免直接v-model到props
  const localConfig = ref<AutoBettingConfig>(JSON.parse(JSON.stringify(props.config)));

  // 🔧 修复：监听props.config变化，同步到本地副本
  watch(
    () => props.config,
    (newConfig) => {
      localConfig.value = JSON.parse(JSON.stringify(newConfig));
    },
    { deep: true }
  );

  // 🔧 修复：处理本地config更新
  const onUpdateConfig = (config: AutoBettingConfig) => {
    console.log(
      '🔄 [SmartControlCenter] onUpdateConfig called with:',
      JSON.stringify(config.dynamic_conditions, null, 2)
    );
    localConfig.value = JSON.parse(JSON.stringify(config));
    // 同步回父组件
    emit('updateConfig', config);
  };

  // ==================== 动态条件构建器 ====================
  import { useConditionBuilder } from '@/composables/useConditionBuilder';

  const { evaluateDynamicConditions } = useConditionBuilder();

  // ==================== 工具函数 ====================

  const { getPredictionIcon } = usePredictionDisplay();

  // ==================== 计算属性 ====================

  // ==================== 本地状态管理 ====================

  // ==================== 数据获取函数 ====================

  // 获取初始预测数据
  const fetchInitialPredictionData = async () => {
    console.log('🔮 SmartControlCenter: 获取初始预测数据...');

    // 🔧 优化：检查store的初始化状态
    const predictionStore = useGamePredictionStore();
    if (predictionStore.isInitialized) {
      console.log('📦 SmartControlCenter: Store已初始化，跳过重复请求');
      return;
    }

    // 🔧 关键修复：如果store正在初始化，等待完成而不是重复请求
    if (predictionStore.initializationPromise) {
      console.log('⏳ SmartControlCenter: Store正在初始化，等待完成...');
      await predictionStore.initializationPromise;
      return;
    }

    // 🔧 关键修复：使用store的方法而不是直接调用API
    try {
      await predictionStore.fetchCurrentAnalysis();
      console.log(`✅ SmartControlCenter: 通过store成功获取初始预测数据`);
    } catch (error) {
      console.error('❌ SmartControlCenter: 获取初始预测数据失败:', error);
    }
  };

  // 刷新分析数据
  const refreshAnalysis = () => {
    console.log('🔄 SmartControlCenter: 手动刷新分析数据');
    emit('refreshAnalysis');
  };

  // 刷新动能预测数据
  const refreshHybridAnalysis = () => {
    console.log('⚡ SmartControlCenter: 手动刷新动能预测数据');
    if (props.refreshHybridAnalysis) {
      props.refreshHybridAnalysis();
    } else {
      emit('refreshAnalysis'); // 如果没有专门的动能刷新方法，使用通用刷新
    }
  };

  // ==================== 生命周期钩子 ====================

  onMounted(() => {
    console.log('🎛️ SmartControlCenter: 组件已挂载');

    // 🔧 优化：检查store的初始化状态
    const predictionStore = useGamePredictionStore();

    // 🔧 关键修复：增加更长的延迟，确保父组件的数据获取完成
    setTimeout(() => {
      // 如果store已初始化，直接检查数据
      if (predictionStore.isInitialized) {
        if (!props.currentAnalysis || props.currentAnalysis.length === 0) {
          console.log('🔮 SmartControlCenter: Store已初始化但无数据，主动获取中...');
          fetchInitialPredictionData();
        } else {
          console.log(`✅ SmartControlCenter: Store已初始化且有数据: ${props.currentAnalysis.length} 个Token`);
        }
      } else {
        // 如果store未初始化，等待初始化完成后再检查
        console.log('⏳ SmartControlCenter: Store未初始化，等待初始化完成...');
        let waitCount = 0;
        const maxWaitCount = 50; // 最大等待5秒 (50 * 100ms)
        const checkData = () => {
          if (predictionStore.isInitialized) {
            if (!props.currentAnalysis || props.currentAnalysis.length === 0) {
              console.log('🔮 SmartControlCenter: Store初始化完成但无数据，主动获取中...');
              fetchInitialPredictionData();
            } else {
              console.log(`✅ SmartControlCenter: Store初始化完成且有数据: ${props.currentAnalysis.length} 个Token`);
            }
          } else {
            // 继续等待，但增加最大等待时间限制
            waitCount++;
            if (waitCount < maxWaitCount) {
              setTimeout(checkData, 100);
            } else {
              console.warn('⚠️ SmartControlCenter: 等待store初始化超时，跳过数据获取');
            }
          }
        };
        checkData();
      }
    }, 500); // 🔧 关键修复：延迟500ms，确保父组件的数据获取完成
  });

  // 🔧 优化：监听预测数据变化，当数据清空时主动重新获取
  // 但增加防抖机制，避免频繁触发
  let dataCheckTimeout: NodeJS.Timeout | null = null;
  watch(
    () => props.currentAnalysis,
    (newAnalysis, oldAnalysis) => {
      // 清除之前的定时器
      if (dataCheckTimeout) {
        clearTimeout(dataCheckTimeout);
      }

      // 延迟检查，避免与父组件的数据获取冲突
      dataCheckTimeout = setTimeout(() => {
        // 如果从有数据变为无数据，或者一直没有数据，则主动获取
        if ((!newAnalysis || newAnalysis.length === 0) && (!oldAnalysis || oldAnalysis.length === 0)) {
          console.log('🔮 SmartControlCenter: 检测到预测数据缺失，尝试获取...');
          fetchInitialPredictionData();
        }
      }, 200); // 延迟200ms，确保父组件的数据获取完成
    },
    { immediate: false }
  );

  // 组件卸载时清理定时器
  onUnmounted(() => {
    if (dataCheckTimeout) {
      clearTimeout(dataCheckTimeout);
    }
  });

  // ==================== 调试面板状态和函数 ====================

  // 数据映射函数（复制自AutoBetting.vue）
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

  // 🔧 评估预测是否符合策略条件 - 使用动态条件
  const evaluatePredictionMatch = (prediction: any): boolean => {
    // 使用动态条件评估
    if ((localConfig.value.dynamic_conditions || []).length > 0) {
      return evaluateDynamicConditions(prediction, localConfig.value.dynamic_conditions || []);
    }

    // 如果没有动态条件，默认通过
    return true;
  };

  // 调试工具函数
  const getTokenConfidence = (token: any): number => {
    return token.rank_confidence || token.confidence || 0;
  };

  const getTokenScore = (token: any): number => {
    // 🔧 修复：优先使用动能分数，如果没有则使用其他分数
    return token.mom_score || token.final_score || token.predicted_final_value || token.score || 0;
  };

  const getTokenSampleCount = (token: any): number => {
    return token.total_games || token.sample_count || 0;
  };

  const getTokenHistoricalAccuracy = (token: any): number => {
    return (token.win_rate || 0) / 100;
  };

  const isTokenMatching = (token: any): boolean => {
    const prediction = mapPredictionData(token);
    return evaluatePredictionMatch(prediction);
  };

  const getTokenDebugClass = (token: any): string => {
    const isMatching = isTokenMatching(token);
    return isMatching ? 'border-green-500/30 bg-green-500/5' : 'border-red-500/30 bg-red-500/5';
  };

  // Methods
  const startAutoBetting = () => emit('startAutoBetting');
  const stopAutoBetting = () => emit('stopAutoBetting');
  const executeManualBetting = () => emit('executeManualBetting');
  const executeStrategyBetting = () => emit('executeStrategyBetting');
  const manualSaveConfig = () => {
    console.log('💾 [SmartControlCenter] 触发手动保存配置事件');
    emit('manualSaveConfig');
  };

  // [新增] 创建计算属性来动态选择数据源
  const displayAnalysisData = computed(() => {
    // 🔧 修复：始终尝试合并动能预测数据，不管是否有动能条件
    const h2hData = props.currentAnalysis || [];
    const momentumData = props.hybridPredictions || [];

    // 🔧 调试：输出数据状态
    console.log('🔍 [SmartControlCenter] 数据状态:', {
      h2hDataLength: h2hData.length,
      momentumDataLength: momentumData.length,
      momentumDataSample: momentumData.slice(0, 2)
    });

    // 如果有动能预测数据，合并到AI预测数据中
    if (momentumData.length > 0 && h2hData.length > 0) {
      const combinedData = h2hData.map((h2hToken: any) => {
        const momentumToken = momentumData.find((m: any) => m.symbol?.toUpperCase() === h2hToken.symbol?.toUpperCase());
        const combinedToken = {
          ...h2hToken,
          // 🔧 修复：添加动能相关字段
          momentum_rank: momentumToken?.predicted_rank ?? null,
          mom_score: momentumToken?.mom_score ?? null,
          final_score: momentumToken?.final_score ?? null,
          elo_prob: momentumToken?.elo_prob ?? null
        };

        // 🔧 调试：输出合并结果
        if (momentumToken) {
          console.log(`🔗 [SmartControlCenter] 合并Token ${h2hToken.symbol}:`, {
            original: h2hToken,
            momentum: momentumToken,
            combined: combinedToken
          });
        }

        return combinedToken;
      });
      return combinedData;
    }

    // 如果只有动能预测数据，使用动能数据
    if (momentumData.length > 0 && h2hData.length === 0) {
      return momentumData;
    }

    // 默认使用AI预测数据
    return h2hData;
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
</style>
