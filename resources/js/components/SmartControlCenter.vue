<template>
  <div class="space-y-6">
    <!-- 🤖 自动下注状态面板 (整合自页面) -->
    <NCard
      class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
      title="🤖 自动下注状态"
      size="large"
    >
      <template #header-extra>
        <div class="flex items-center space-x-3">
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

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-4 md:grid-cols-2 sm:grid-cols-2">
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
      </div>

      <!-- 匹配的Token展示 -->
      <div v-if="strategyValidation?.matches.length" class="mt-6 space-y-3">
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
      <NEmpty v-else-if="currentAnalysis.length > 0" description="当前无符合策略条件的Token" class="mt-6 py-8" />

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

    <!-- 🔮 AI预测排名面板 (使用组件) -->
    <AIPredictionRanking
      :current-analysis="currentAnalysis"
      :analysis-meta="analysisMeta"
      :current-round-id="currentRoundId"
      :current-game-status="currentGameStatus"
      :current-game-tokens-with-ranks="currentGameTokensWithRanks"
      :analysis-loading="analysisLoading"
      @refresh-analysis="refreshAnalysis"
    />

    <!-- 主要工作区域：左侧策略配置，右侧快速配置 -->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <!-- 左侧：策略选择和配置区域 -->
      <div class="space-y-6">
        <!-- 策略模板选择 -->
        <NCard
          class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="🎯 智能策略选择"
          size="large"
        >
          <div class="space-y-4">
            <!-- 策略网格布局 -->
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div
                v-for="(template, key) in strategyTemplatesWithCustom"
                :key="key"
                class="cursor-pointer border border-gray-500/30 rounded-lg bg-gray-500/10 p-3 transition-all duration-200 hover:border-blue-400/60 hover:bg-blue-500/10"
                :class="{
                  'border-blue-400 bg-blue-500/20': selectedTemplate === String(key),
                  'border-orange-400 bg-orange-500/20': String(key) === 'custom' && selectedTemplate === String(key)
                }"
                @click="applyStrategyTemplate(String(key))"
              >
                <div class="mb-2 flex items-center justify-between">
                  <span class="flex items-center text-sm text-white font-medium space-x-1">
                    <span v-if="String(key) === 'custom'">🎨</span>
                    <span v-else>📋</span>
                    <span>{{ template.name }}</span>
                  </span>
                  <n-tag
                    :type="
                      selectedTemplate === String(key) ? (String(key) === 'custom' ? 'warning' : 'primary') : 'default'
                    "
                    size="small"
                  >
                    {{ String(key) === 'custom' ? '自定义' : template.confidence_threshold + '%' }}
                  </n-tag>
                </div>
                <div class="text-xs text-gray-400">{{ template.description }}</div>
                <div v-if="String(key) !== 'custom'" class="mt-2 flex flex-wrap gap-1">
                  <span class="rounded bg-gray-600 px-1.5 py-0.5 text-xs text-gray-300">
                    风险: {{ template.max_bet_percentage }}%
                  </span>
                  <span class="rounded bg-gray-600 px-1.5 py-0.5 text-xs text-gray-300">
                    {{
                      template.strategy === 'single_bet'
                        ? '单项'
                        : template.strategy === 'multi_bet'
                          ? '多项'
                          : template.strategy === 'hedge_bet'
                            ? '对冲'
                            : '指定排名'
                    }}
                  </span>
                </div>
                <div v-else class="mt-2">
                  <span class="rounded bg-orange-600 px-1.5 py-0.5 text-xs text-orange-200">完全可定制</span>
                </div>
              </div>
            </div>
          </div>
        </NCard>
      </div>

      <!-- 右侧：快速配置面板 -->
      <div class="space-y-6">
        <!-- 快速配置面板 -->
        <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="⚙️ 快速配置">
          <div class="space-y-4">
            <!-- 基础配置 -->
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="text-xs text-gray-300 font-medium">下注金额</label>
                <n-input-number
                  v-model:value="props.config.bet_amount"
                  :min="200"
                  :max="2000"
                  :step="50"
                  :disabled="isRunning"
                  size="small"
                />
              </div>
              <div class="space-y-2">
                <label class="text-xs text-gray-300 font-medium">风险比例(%)</label>
                <n-input-number
                  v-model:value="props.config.max_bet_percentage"
                  :min="5"
                  :max="50"
                  :step="1"
                  :disabled="isRunning"
                  size="small"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="text-xs text-gray-300 font-medium">置信度(%)</label>
                <n-input-number
                  v-model:value="props.config.confidence_threshold"
                  :min="70"
                  :max="99"
                  :step="1"
                  :disabled="isRunning || props.config.strategy === 'rank_betting'"
                  size="small"
                />
              </div>
              <div class="space-y-2">
                <label class="text-xs text-gray-300 font-medium">下注策略</label>
                <n-select
                  v-model:value="props.config.strategy"
                  :options="[
                    { label: '单项', value: 'single_bet' },
                    { label: '多项', value: 'multi_bet' },
                    { label: '对冲', value: 'hedge_bet' },
                    { label: '排名', value: 'rank_betting' }
                  ]"
                  :disabled="isRunning"
                  size="small"
                />
              </div>
            </div>

            <!-- 指定排名下注配置 -->
            <div v-if="props.config.strategy === 'rank_betting'" class="border-t border-gray-600 pt-4">
              <label class="mb-2 block text-xs text-gray-300 font-medium">选择排名</label>
              <div class="grid grid-cols-5 gap-2">
                <div
                  v-for="rank in [1, 2, 3, 4, 5]"
                  :key="rank"
                  class="cursor-pointer border-2 rounded p-2 text-center text-xs transition-all duration-200"
                  :class="
                    props.config.rank_betting_enabled_ranks.includes(rank)
                      ? 'border-blue-400 bg-blue-500/20 text-blue-400'
                      : 'border-gray-500/30 bg-gray-500/10 text-gray-400 hover:border-gray-400/60'
                  "
                  @click="toggleRankBetting(rank, !props.config.rank_betting_enabled_ranks.includes(rank))"
                >
                  <div class="font-bold">TOP{{ rank }}</div>
                </div>
              </div>
            </div>

            <!-- 保存按钮 -->
            <div class="text-center">
              <n-button @click="manualSaveConfig" :disabled="isRunning" :loading="configSaving" type="primary">
                <template #icon>
                  <span>💾</span>
                </template>
                {{ hasUID ? '保存到云端' : '本地保存' }}
              </n-button>
            </div>
          </div>
        </NCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, watch } from 'vue';
  import { NEmpty, NTag } from 'naive-ui';
  import AIPredictionRanking from '@/components/AIPredictionRanking.vue';
  import type { AutoBettingStatus, DebugInfo } from '@/composables/useAutoBettingControl';
  import type { AutoBettingConfig } from '@/composables/useAutoBettingConfig';
  import api from '@/utils/api';

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
    confidenceThreshold: number;
    riskLevel: number;
    config: AutoBettingConfig;
    selectedTemplate: string;
    customStrategyMode: boolean;
    configSaving: boolean;
    configSyncStatus: { type: 'success' | 'error' | 'info'; message: string } | null;
    strategyTemplates: any;
    strategyTemplatesWithCustom: any;
    strategyValidation: any;

    isRunning: boolean;
    hasUID: boolean;
  }

  const props = defineProps<Props>();

  // Emits
  const emit = defineEmits<{
    startAutoBetting: [];
    stopAutoBetting: [];
    executeManualBetting: [];
    applyStrategyTemplate: [key: string];
    executeStrategyBetting: [];
    manualSaveConfig: [];
    refreshAnalysis: [];
  }>();

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

  // 统一卡片样式
  const getUnifiedCardClass = (index: number) => {
    if (index === 0)
      return 'border-yellow-400/30 bg-gradient-to-br from-yellow-500/10 to-amber-600/5 hover:border-yellow-400/50 hover:shadow-yellow-500/20';
    if (index === 1)
      return 'border-slate-400/30 bg-gradient-to-br from-slate-500/10 to-gray-600/5 hover:border-slate-400/50 hover:shadow-slate-500/20';
    if (index === 2)
      return 'border-orange-400/30 bg-gradient-to-br from-orange-500/10 to-red-600/5 hover:border-orange-400/50 hover:shadow-orange-500/20';
    if (index === 3)
      return 'border-blue-400/30 bg-gradient-to-br from-blue-500/10 to-indigo-600/5 hover:border-blue-400/50 hover:shadow-blue-500/20';
    return 'border-purple-400/30 bg-gradient-to-br from-purple-500/10 to-pink-600/5 hover:border-purple-400/50 hover:shadow-purple-500/20';
  };

  // 分数文本样式
  const getScoreTextClass = (index: number) => {
    if (index === 0) return 'text-yellow-400';
    if (index === 1) return 'text-slate-400';
    if (index === 2) return 'text-orange-400';
    if (index === 3) return 'text-blue-400';
    return 'text-purple-400';
  };

  // 格式化价格变化
  const formatPriceChange = (change: number | null) => {
    if (change === null || change === undefined) return { text: '-', color: 'text-gray-500' };
    const value = change.toFixed(2);
    if (change > 0) {
      return { text: `+${value}%`, color: 'text-green-400' };
    } else if (change < 0) {
      return { text: `${value}%`, color: 'text-red-400' };
    } else {
      return { text: '0.00%', color: 'text-gray-400' };
    }
  };

  // ==================== 计算属性 ====================

  // 预测Token按排名排序
  const sortedPredictionsByRank = computed(() => {
    return [...props.currentAnalysis].sort((a, b) => a.predicted_rank - b.predicted_rank);
  });

  // 获取Token当前排名
  const getTokenCurrentRank = (symbol: string) => {
    const token = props.currentGameTokensWithRanks.find((t) => t.symbol === symbol);
    return token?.rank || null;
  };

  // 获取Token当前价格变化
  const getTokenCurrentChange = (symbol: string) => {
    const token = props.currentGameTokensWithRanks.find((t) => t.symbol === symbol);
    return token?.priceChange || null;
  };

  // 排名下注相关方法 - 直接操作props中的config
  const toggleRankBetting = (rank: number, checked: boolean) => {
    if (checked) {
      if (!props.config.rank_betting_enabled_ranks.includes(rank)) {
        props.config.rank_betting_enabled_ranks.push(rank);
        props.config.rank_betting_enabled_ranks.sort((a: number, b: number) => a - b);
      }
    } else {
      const index = props.config.rank_betting_enabled_ranks.indexOf(rank);
      if (index > -1) {
        props.config.rank_betting_enabled_ranks.splice(index, 1);
      }
    }
  };

  // ==================== 本地状态管理 ====================

  // 本地加载状态（独立于props中的analysisLoading）
  const localAnalysisLoading = computed(() => props.analysisLoading);

  // ==================== 数据获取函数 ====================

  // 获取初始预测数据
  const fetchInitialPredictionData = async () => {
    console.log('🔮 SmartControlCenter: 获取初始预测数据...');
    try {
      const response = await api.get('/game/current-analysis');
      if (response.data.success) {
        console.log(`✅ SmartControlCenter: 成功获取初始预测数据: ${response.data.data?.length || 0} 个Token`);
        // 通知父组件更新数据，这里我们通过emit通知父组件刷新
        emit('refreshAnalysis');
      } else {
        console.warn('⚠️ SmartControlCenter: 获取初始预测数据失败:', response.data.message);
      }
    } catch (error) {
      console.error('❌ SmartControlCenter: 获取初始预测数据失败:', error);
    }
  };

  // 刷新分析数据
  const refreshAnalysis = () => {
    console.log('🔄 SmartControlCenter: 手动刷新分析数据');
    emit('refreshAnalysis');
  };

  // ==================== 生命周期钩子 ====================

  onMounted(() => {
    console.log('🎛️ SmartControlCenter: 组件已挂载');

    // 检查是否有预测数据，如果没有则主动获取
    if (!props.currentAnalysis || props.currentAnalysis.length === 0) {
      console.log('🔮 SmartControlCenter: 未检测到预测数据，主动获取中...');
      fetchInitialPredictionData();
    } else {
      console.log(`✅ SmartControlCenter: 已有预测数据: ${props.currentAnalysis.length} 个Token`);
    }
  });

  // 监听预测数据变化，当数据清空时主动重新获取
  watch(
    () => props.currentAnalysis,
    (newAnalysis, oldAnalysis) => {
      // 如果从有数据变为无数据，或者一直没有数据，则主动获取
      if ((!newAnalysis || newAnalysis.length === 0) && (!oldAnalysis || oldAnalysis.length === 0)) {
        console.log('🔮 SmartControlCenter: 检测到预测数据缺失，尝试获取...');
        fetchInitialPredictionData();
      }
    },
    { immediate: false }
  );

  // Methods
  const startAutoBetting = () => emit('startAutoBetting');
  const stopAutoBetting = () => emit('stopAutoBetting');
  const executeManualBetting = () => emit('executeManualBetting');
  const applyStrategyTemplate = (key: string) => emit('applyStrategyTemplate', key);
  const executeStrategyBetting = () => emit('executeStrategyBetting');
  const manualSaveConfig = () => emit('manualSaveConfig');
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
