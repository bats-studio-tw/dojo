<template>
  <div class="space-y-6">
    <!-- 系统状态监控卡片 -->
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
          :disabled="!autoBettingStatus.is_running"
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
    <div class="mb-6">
      <PredictionDisplay
        :analysis-data="currentAnalysis"
        :analysis-meta="currentAnalysis?.meta"
        :loading="analysisLoading"
        @refresh="fetchAnalysisData"
      />
    </div>

    <!-- 近期活动日志 -->
    <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="📊 近期活动日志" size="large">
      <div class="space-y-3">
        <!-- 最近下注结果 -->
        <div v-if="debugInfo.lastBetResults.length > 0">
          <div class="mb-3 flex items-center justify-between">
            <span class="text-sm text-white font-medium">🎯 最近下注记录</span>
            <n-button @click="clearBetResults" type="tertiary" size="tiny">清空记录</n-button>
          </div>

          <div class="max-h-48 overflow-y-auto rounded bg-black/30 p-3 space-y-2">
            <div
              v-for="(bet, index) in debugInfo.lastBetResults.slice(-5).reverse()"
              :key="index"
              class="border border-gray-600/50 rounded-lg bg-gray-700/30 p-3"
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
                  <span class="text-white font-medium">{{ bet.symbol }}</span>
                  <span class="text-gray-400">${{ bet.amount }}</span>
                </div>
                <span class="text-xs text-gray-500">{{ bet.time }}</span>
              </div>
              <div v-if="!bet.success && bet.error" class="mt-1 text-xs text-red-400">错误: {{ bet.error }}</div>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8 text-gray-400">
          <div class="text-2xl mb-2">📝</div>
          <div class="text-sm">暂无下注记录</div>
        </div>

        <!-- 系统状态指示器 -->
        <div class="border-t border-gray-600 pt-4">
          <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="text-center">
              <div class="text-xs text-gray-400 mb-1">轮次检查</div>
              <div class="text-sm text-blue-400">{{ debugInfo.roundCheckCount }}次</div>
            </div>
            <div class="text-center">
              <div class="text-xs text-gray-400 mb-1">自动触发</div>
              <div class="text-sm text-green-400">{{ debugInfo.autoTriggerCount }}次</div>
            </div>
            <div class="text-center">
              <div class="text-xs text-gray-400 mb-1">策略验证</div>
              <div class="text-sm text-purple-400">{{ debugInfo.strategyValidationCount }}次</div>
            </div>
            <div class="text-center">
              <div class="text-xs text-gray-400 mb-1">最后检查</div>
              <div class="text-xs text-gray-500">{{ debugInfo.lastRoundCheckTime || '未检查' }}</div>
            </div>
          </div>
        </div>
      </div>
    </NCard>
  </div>
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import { NEmpty } from 'naive-ui';
  import PredictionDisplay from './PredictionDisplay.vue';
  import type { UserInfo } from '@/types';
  import type { AutoBettingStatus, DebugInfo } from '@/composables/useAutoBettingControl';

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
  }>();

  // Methods
  const startAutoBetting = () => emit('startAutoBetting');
  const stopAutoBetting = () => emit('stopAutoBetting');
  const executeManualBetting = () => emit('executeManualBetting');
  const fetchAnalysisData = () => emit('fetchAnalysisData');
  const reconnectToken = () => emit('reconnectToken');
  const clearBetResults = () => emit('clearBetResults');
</script>
