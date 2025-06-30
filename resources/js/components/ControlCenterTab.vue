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
    <div class="mb-6">
      <AIPredictionRanking
        :current-analysis="currentAnalysis?.predictions || []"
        :analysis-meta="currentAnalysis?.meta"
        :current-round-id="currentAnalysis?.meta?.round_id || null"
        :current-game-status="currentAnalysis?.meta?.status || 'unknown'"
        :current-game-tokens-with-ranks="[]"
        :analysis-loading="analysisLoading"
        @refresh-analysis="fetchAnalysisData"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
  import { NEmpty } from 'naive-ui';
  import AIPredictionRanking from './AIPredictionRanking.vue';
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
  }

  defineProps<Props>();

  // Emits
  const emit = defineEmits<{
    startAutoBetting: [];
    stopAutoBetting: [];
    executeManualBetting: [];
    fetchAnalysisData: [];
    reconnectToken: [];
  }>();

  // Methods
  const startAutoBetting = () => emit('startAutoBetting');
  const stopAutoBetting = () => emit('stopAutoBetting');
  const executeManualBetting = () => emit('executeManualBetting');
  const fetchAnalysisData = () => emit('fetchAnalysisData');
  const reconnectToken = () => emit('reconnectToken');
</script>
