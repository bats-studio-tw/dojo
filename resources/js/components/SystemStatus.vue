<template>
  <div class="space-y-6">
    <!-- 状态统计卡片 -->
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
            <n-button
              @click="$emit('reconnectToken')"
              :disabled="autoBettingStatus.is_running"
              type="tertiary"
              size="tiny"
            >
              重新验证
            </n-button>
          </div>
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

      <!-- 当前分析数据 -->
      <div class="border border-purple-500/30 rounded-lg bg-purple-500/10 p-4">
        <div class="mb-2 flex items-center space-x-2">
          <span class="text-lg">🎯</span>
          <span class="text-sm text-purple-400 font-medium">当前分析数据</span>
        </div>

        <div v-if="currentAnalysis" class="text-sm text-gray-300 space-y-2">
          <div class="flex justify-between">
            <span>当前轮次:</span>
            <span class="text-purple-400 font-mono">{{ currentAnalysis.meta?.round_id || '未知' }}</span>
          </div>
          <div class="flex justify-between">
            <span>游戏数量:</span>
            <span class="text-purple-400">{{ currentAnalysis.predictions?.length || 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span>数据状态:</span>
            <n-tag :type="getStatusTagType(currentAnalysis.meta?.status)" size="small">
              {{ currentAnalysis.meta?.status || '未知' }}
            </n-tag>
          </div>
          <div class="flex justify-between">
            <span>更新时间:</span>
            <span class="text-xs text-purple-400">
              {{
                currentAnalysis.meta?.timestamp
                  ? new Date(currentAnalysis.meta.timestamp).toLocaleTimeString()
                  : '无效时间'
              }}
            </span>
          </div>
        </div>
        <div v-else class="text-center text-gray-400">
          <NEmpty description="暂无分析数据" />
        </div>
      </div>
    </div>

    <!-- 配置同步状态提示 -->
    <div class="text-center">
      <div v-if="currentUID" class="mb-2">
        <span
          class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs text-green-800 font-medium"
        >
          ☁️ 配置已云端同步 (UID: {{ currentUID.slice(0, 8) }}...)
        </span>
      </div>
      <div v-else class="mb-2">
        <span
          class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs text-yellow-800 font-medium"
        >
          💾 配置本地存储 - 完成Token验证后可云端同步
        </span>
      </div>
    </div>

    <!-- 控制按钮 -->
    <div class="flex justify-center space-x-4">
      <n-button
        v-if="!autoBettingStatus.is_running"
        @click="$emit('startAutoBetting')"
        :loading="toggleLoading"
        type="success"
        size="large"
        class="shadow-green-500/25 shadow-lg hover:shadow-green-500/40"
      >
        <template #icon><span>▶️</span></template>
        启动自动下注
      </n-button>

      <n-button
        v-else
        @click="$emit('stopAutoBetting')"
        :loading="toggleLoading"
        type="error"
        size="large"
        class="shadow-lg shadow-red-500/25 hover:shadow-red-500/40"
      >
        <template #icon><span>⏹️</span></template>
        停止自动下注
      </n-button>

      <n-button
        @click="$emit('executeAutoBetting')"
        :loading="executeLoading"
        :disabled="!autoBettingStatus.is_running"
        type="warning"
        size="large"
        class="shadow-lg shadow-orange-500/25 hover:shadow-orange-500/40"
      >
        <template #icon><span>🎯</span></template>
        手动执行一次
      </n-button>

      <n-button
        @click="$emit('refreshAnalysis')"
        :loading="analysisLoading"
        type="info"
        size="large"
        class="shadow-blue-500/25 shadow-lg hover:shadow-blue-500/40"
      >
        <template #icon><span>🔄</span></template>
        刷新数据
      </n-button>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { NEmpty } from 'naive-ui';

  interface Props {
    userInfo: any;
    autoBettingStatus: any;
    currentAnalysis: any;
    currentUID: string;
    toggleLoading: boolean;
    executeLoading: boolean;
    analysisLoading: boolean;
  }

  interface Emits {
    (e: 'reconnectToken'): void;
    (e: 'startAutoBetting'): void;
    (e: 'stopAutoBetting'): void;
    (e: 'executeAutoBetting'): void;
    (e: 'refreshAnalysis'): void;
  }

  defineProps<Props>();
  defineEmits<Emits>();

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
</script>
