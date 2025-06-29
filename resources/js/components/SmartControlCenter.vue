<template>
  <div class="space-y-6">
    <!-- 顶部系统状态监控区域 -->
    <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="📈 系统状态概览" size="large">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <!-- 用户信息卡片 -->
        <div class="border border-blue-500/30 rounded-lg bg-blue-500/10 p-4">
          <div class="mb-2 flex items-center space-x-2">
            <span class="text-lg">👤</span>
            <span class="text-sm text-blue-400 font-medium">用户信息</span>
          </div>

          <div v-if="userInfo" class="text-sm text-gray-300 space-y-2">
            <div class="flex justify-between">
              <span>用户ID:</span>
              <span class="text-xs text-blue-400 font-mono">{{ userInfo.uid }}</span>
            </div>
            <div class="flex justify-between">
              <span>可用余额:</span>
              <span class="text-green-400 font-semibold">${{ userInfo.ojoValue.toFixed(2) }}</span>
            </div>
            <div class="flex justify-between">
              <span>排名:</span>
              <span class="text-blue-400">{{ userInfo.rankPercent }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span>状态:</span>
              <n-button @click="reconnectToken" :disabled="autoBettingStatus.is_running" type="tertiary" size="tiny">
                重新验证
              </n-button>
            </div>
          </div>
          <div v-else class="text-center text-gray-400">
            <NEmpty description="未获取用户信息" />
          </div>
        </div>

        <!-- 自动下注状态 -->
        <div class="border border-green-500/30 rounded-lg bg-green-500/10 p-4">
          <div class="mb-2 flex items-center space-x-2">
            <span class="text-lg">⚙️</span>
            <span class="text-sm text-green-400 font-medium">自动下注状态</span>
          </div>

          <div class="text-sm text-gray-300 space-y-2">
            <div class="flex items-center justify-between">
              <span>运行状态:</span>
              <n-tag :type="autoBettingStatus.is_running ? 'success' : 'default'" size="small">
                {{ autoBettingStatus.is_running ? '运行中' : '已停止' }}
              </n-tag>
            </div>
            <div class="flex justify-between">
              <span>总下注次数:</span>
              <span class="text-green-400 font-semibold">{{ autoBettingStatus.total_bets }}</span>
            </div>
            <div class="flex justify-between">
              <span>总盈亏:</span>
              <span
                class="font-semibold"
                :class="autoBettingStatus.total_profit_loss >= 0 ? 'text-green-400' : 'text-red-400'"
              >
                ${{ autoBettingStatus.total_profit_loss.toFixed(2) }}
              </span>
            </div>
            <div class="flex justify-between">
              <span>今日盈亏:</span>
              <span
                class="font-semibold"
                :class="autoBettingStatus.today_profit_loss >= 0 ? 'text-green-400' : 'text-red-400'"
              >
                ${{ autoBettingStatus.today_profit_loss.toFixed(2) }}
              </span>
            </div>
          </div>
        </div>

        <!-- 当前策略摘要 -->
        <div class="border border-purple-500/30 rounded-lg bg-purple-500/10 p-4">
          <div class="mb-2 flex items-center space-x-2">
            <span class="text-lg">🎯</span>
            <span class="text-sm text-purple-400 font-medium">当前策略</span>
          </div>

          <div class="text-sm text-gray-300 space-y-2">
            <div class="flex justify-between">
              <span>策略名称:</span>
              <span class="text-purple-400 font-medium">{{ strategyName }}</span>
            </div>
            <div class="flex justify-between">
              <span>置信度阈值:</span>
              <span class="text-purple-400">{{ confidenceThreshold }}%</span>
            </div>
            <div class="flex justify-between">
              <span>风险水平:</span>
              <span class="text-purple-400">{{ riskLevel }}%</span>
            </div>
            <div class="flex justify-between">
              <span>当前轮次:</span>
              <span class="text-xs text-purple-400 font-mono">
                {{ currentAnalysis?.meta?.round_id || '未知' }}
              </span>
            </div>
          </div>
        </div>
      </div>

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

        <n-button
          @click="fetchAnalysisData"
          :loading="analysisLoading"
          type="info"
          size="large"
          class="shadow-blue-500/25 shadow-lg hover:shadow-blue-500/40"
        >
          <template #icon>
            <span>🔄</span>
          </template>
          刷新数据
        </n-button>
      </div>
    </NCard>
    <!-- 当前预测展示 -->
    <PredictionDisplay
      :analysis-data="currentAnalysis?.predictions || []"
      :analysis-meta="currentAnalysis?.meta"
      :loading="analysisLoading"
      @refresh="fetchAnalysisData"
    />

    <!-- 主要工作区域：左侧策略配置，右侧预测和控制 -->
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
            <!-- 策略模式状态指示器 -->
            <div class="mb-4 flex items-center justify-between">
              <div class="flex items-center space-x-2">
                <n-tag :type="customStrategyMode ? 'warning' : 'success'" size="small">
                  {{ customStrategyMode ? '🎨 自定义模式' : '📋 模板模式' }}
                </n-tag>
              </div>
              <n-button
                @click="customStrategyMode ? resetToTemplateMode() : switchToCustomMode()"
                :type="customStrategyMode ? 'default' : 'primary'"
                size="small"
              >
                {{ customStrategyMode ? '返回模板' : '自定义设置' }}
              </n-button>
            </div>

            <!-- 策略模板选择 -->
            <div v-if="!customStrategyMode">
              <div class="grid grid-cols-1 gap-3">
                <div
                  v-for="(template, key) in strategyTemplates"
                  :key="key"
                  class="cursor-pointer border border-gray-500/30 rounded-lg bg-gray-500/10 p-3 transition-all duration-200 hover:border-blue-400/60 hover:bg-blue-500/10"
                  :class="{
                    'border-blue-400 bg-blue-500/20': selectedTemplate === String(key)
                  }"
                  @click="applyStrategyTemplate(String(key))"
                >
                  <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm text-white font-medium">{{ template.name }}</span>
                    <n-tag :type="selectedTemplate === String(key) ? 'primary' : 'default'" size="small">
                      {{ template.confidence_threshold }}%
                    </n-tag>
                  </div>
                  <div class="text-xs text-gray-400">{{ template.description }}</div>
                  <div class="mt-2 flex flex-wrap gap-1">
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
                </div>
              </div>
            </div>

            <!-- 自定义模式提示 -->
            <div v-else class="space-y-3">
              <div class="border border-orange-500/30 rounded-lg bg-orange-500/10 p-4">
                <div class="mb-2 flex items-center space-x-2">
                  <span class="text-orange-400">🎨</span>
                  <span class="text-sm text-orange-400 font-medium">自定义策略模式</span>
                </div>
                <div class="text-xs text-gray-300">
                  您现在处于自定义模式，可以手动调整所有参数。预设模板功能已禁用，所有参数变更将实时应用。
                </div>
              </div>
            </div>
          </div>
        </NCard>
      </div>

      <!-- 右侧：预测展示和验证结果 -->
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
        <!-- 实时策略验证 -->
        <NCard
          v-if="strategyValidation"
          class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="📊 策略验证结果"
          size="large"
        >
          <div class="space-y-4">
            <!-- 符合条件的游戏数量 -->
            <div class="border border-green-500/30 rounded-lg bg-green-500/10 p-4 text-center">
              <div class="text-sm text-green-400">符合条件的游戏</div>
              <div class="text-3xl text-white font-bold">{{ strategyValidation.total_matched }}</div>
              <div class="text-xs text-gray-400">个游戏符合当前策略</div>
            </div>

            <!-- 符合条件的游戏列表 -->
            <div v-if="strategyValidation.matches && strategyValidation.matches.length > 0" class="space-y-2">
              <div class="text-sm text-gray-300 font-medium">符合条件的游戏详情：</div>
              <div class="max-h-48 overflow-y-auto space-y-2">
                <div
                  v-for="match in strategyValidation.matches"
                  :key="match.symbol"
                  class="flex items-center justify-between border border-gray-600/30 rounded-lg bg-gray-800/50 p-3"
                >
                  <div class="flex items-center space-x-3">
                    <span class="text-white font-medium">{{ match.symbol }}</span>
                    <span class="text-xs text-gray-400">TOP{{ match.predicted_rank }}</span>
                    <span class="text-xs text-blue-400">{{ match.confidence }}%</span>
                  </div>
                  <div class="text-right">
                    <div class="text-sm text-green-400">${{ match.bet_amount }}</div>
                    <div class="text-xs text-gray-500">{{ match.sample_count }}局</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 一键执行按钮 -->
            <div class="text-center">
              <n-button
                v-if="strategyValidation.matches && strategyValidation.matches.length > 0"
                @click="executeStrategyBetting"
                :loading="executeLoading"
                :disabled="!strategyValidation.balance_sufficient"
                :type="strategyValidation.balance_sufficient ? 'success' : 'error'"
                size="large"
                class="shadow-green-500/25 shadow-lg hover:shadow-green-500/40"
              >
                <template #icon>
                  <span>{{ strategyValidation.balance_sufficient ? '🚀' : '⚠️' }}</span>
                </template>
                {{
                  strategyValidation.balance_sufficient
                    ? `一键执行策略下注 (${strategyValidation.matches.length}个)`
                    : '余额不足，无法执行'
                }}
              </n-button>
              <div v-else class="text-center text-gray-400">
                <NEmpty description="当前没有符合策略条件的游戏" />
              </div>
            </div>
          </div>
        </NCard>

        <!-- 近期活动日志 -->
        <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="📊 近期活动日志">
          <div class="space-y-3">
            <!-- 最近下注结果 -->
            <div v-if="debugInfo.lastBetResults && debugInfo.lastBetResults.length > 0">
              <div class="mb-3 flex items-center justify-between">
                <span class="text-sm text-white font-medium">🎯 最近下注记录</span>
                <n-button @click="clearBetResults" type="tertiary" size="tiny">清空记录</n-button>
              </div>

              <div class="max-h-40 overflow-y-auto rounded bg-black/30 p-3 space-y-2">
                <div
                  v-for="(bet, index) in debugInfo.lastBetResults.slice(-3).reverse()"
                  :key="index"
                  class="border border-gray-600/50 rounded-lg bg-gray-700/30 p-2"
                  :class="{
                    'border-green-500/50 bg-green-500/10': bet.success,
                    'border-red-500/50 bg-red-500/10': !bet.success
                  }"
                >
                  <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                      <span :class="bet.success ? 'text-green-400' : 'text-red-400'">
                        {{ bet.success ? '✅' : '❌' }}
                      </span>
                      <span class="text-sm text-white font-medium">{{ bet.symbol }}</span>
                      <span class="text-xs text-gray-400">${{ bet.amount }}</span>
                    </div>
                    <span class="text-xs text-gray-500">{{ bet.time }}</span>
                  </div>
                  <div v-if="!bet.success && bet.error" class="mt-1 text-xs text-red-400">错误: {{ bet.error }}</div>
                </div>
              </div>
            </div>

            <div v-else class="py-4 text-center text-gray-400">
              <div class="mb-1 text-xl">📝</div>
              <div class="text-sm">暂无下注记录</div>
            </div>
          </div>
        </NCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { NEmpty } from 'naive-ui';
  import PredictionDisplay from './PredictionDisplay.vue';
  import type { UserInfo } from '@/types';
  import type { AutoBettingStatus, DebugInfo } from '@/composables/useAutoBettingControl';
  import type { AutoBettingConfig } from '@/composables/useAutoBettingConfig';

  // Props
  interface Props {
    userInfo: UserInfo | null;
    autoBettingStatus: AutoBettingStatus;
    currentAnalysis: any;
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
    fetchAnalysisData: [];
    reconnectToken: [];
    clearBetResults: [];
    applyStrategyTemplate: [key: string];
    switchToCustomMode: [];
    resetToTemplateMode: [];
    executeStrategyBetting: [];

    manualSaveConfig: [];
  }>();

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

  // Methods
  const startAutoBetting = () => emit('startAutoBetting');
  const stopAutoBetting = () => emit('stopAutoBetting');
  const executeManualBetting = () => emit('executeManualBetting');
  const fetchAnalysisData = () => emit('fetchAnalysisData');
  const reconnectToken = () => emit('reconnectToken');
  const clearBetResults = () => emit('clearBetResults');
  const applyStrategyTemplate = (key: string) => emit('applyStrategyTemplate', key);
  const switchToCustomMode = () => emit('switchToCustomMode');
  const resetToTemplateMode = () => emit('resetToTemplateMode');
  const executeStrategyBetting = () => emit('executeStrategyBetting');

  const manualSaveConfig = () => emit('manualSaveConfig');
</script>
