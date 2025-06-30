<template>
  <div class="real-time-data-container">
    <!-- 连接状态指示器 -->
    <div class="mb-4 flex items-center gap-2">
      <div :class="['w-3 h-3 rounded-full', wsStatusConfig.bgColor.replace('bg-', 'bg-').replace('/20', '-500')]"></div>
      <span class="text-sm font-medium">WebSocket {{ connectionStatusText }}</span>
      <button
        @click="predictionStore.reconnectWebSocket()"
        class="ml-2 rounded bg-blue-500 px-2 py-1 text-xs text-white hover:bg-blue-600"
      >
        重新连接
      </button>
    </div>

    <!-- 实时游戏数据 -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- 游戏数据面板 -->
      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold">
          <div class="h-2 w-2 rounded-full bg-blue-500"></div>
          游戏数据
        </h3>

        <div v-if="latestGameData" class="space-y-3">
          <div class="flex justify-between">
            <span class="text-gray-600">轮次ID:</span>
            <span class="font-mono">{{ currentRoundId || 'N/A' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">状态:</span>
            <span :class="getStatusColor(currentGameStatus)">
              {{ currentGameStatus }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">代币数量:</span>
            <span>{{ currentGameTokens.length }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">更新时间:</span>
            <span class="text-sm text-gray-500">{{ formatTime(gameDataTimestamp) }}</span>
          </div>

          <!-- 代币列表 -->
          <div v-if="currentGameTokens.length > 0" class="mt-4">
            <h4 class="mb-2 text-sm text-gray-700 font-medium">当前代币:</h4>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="token in currentGameTokens"
                :key="token"
                class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-800"
              >
                {{ token }}
              </span>
            </div>
          </div>
        </div>

        <div v-else class="py-8 text-center text-gray-500">等待游戏数据...</div>
      </div>

      <!-- 预测数据面板 -->
      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold">
          <div class="h-2 w-2 rounded-full bg-green-500"></div>
          预测分析
        </h3>

        <div v-if="hasCurrentAnalysis" class="space-y-3">
          <div class="flex justify-between">
            <span class="text-gray-600">轮次ID:</span>
            <span class="font-mono">{{ currentRoundId || 'N/A' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">预测数量:</span>
            <span>{{ currentAnalysis.length }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">更新时间:</span>
            <span class="text-sm text-gray-500">{{ formatTime(predictionTimestamp) }}</span>
          </div>

          <!-- 预测排名 -->
          <div v-if="currentAnalysis.length > 0" class="mt-4">
            <h4 class="mb-2 text-sm text-gray-700 font-medium">预测排名:</h4>
            <div class="space-y-2">
              <div
                v-for="(prediction, index) in currentAnalysis.slice(0, 5)"
                :key="prediction.symbol"
                class="flex items-center justify-between rounded bg-gray-50 p-2"
              >
                <div class="flex items-center gap-2">
                  <span class="h-6 w-6 flex items-center justify-center rounded-full bg-blue-500 text-xs text-white">
                    {{ index + 1 }}
                  </span>
                  <span class="font-medium">{{ prediction.symbol }}</span>
                </div>
                <div class="text-right">
                  <div class="text-sm font-medium">
                    {{ prediction.risk_adjusted_score?.toFixed(1) || prediction.prediction_score?.toFixed(1) || 'N/A' }}
                  </div>
                  <div class="text-xs text-gray-500">
                    置信度: {{ prediction.rank_confidence?.toFixed(1) || prediction.win_rate?.toFixed(1) || 'N/A' }}%
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="py-8 text-center text-gray-500">等待预测数据...</div>
      </div>
    </div>

    <!-- 消息日志 -->
    <div class="mt-6 rounded-lg bg-gray-50 p-4">
      <h3 class="mb-3 text-lg font-semibold">实时消息日志</h3>
      <div class="max-h-48 overflow-y-auto space-y-1">
        <div
          v-for="(message, index) in recentMessages"
          :key="index"
          class="text-sm"
          :class="getMessageColor(message.type)"
        >
          <span class="text-gray-500">{{ formatTime(message.timestamp) }}</span>
          <span class="ml-2">{{ message.message }}</span>
        </div>
      </div>
      <div v-if="recentMessages.length === 0" class="py-4 text-center text-gray-500">暂无消息</div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
  import { useGamePredictionStore } from '@/stores/gamePrediction';
  import { getWebSocketStatusConfig, getGameStatusConfig } from '@/utils/statusUtils';
  import { storeToRefs } from 'pinia';

  // 使用预测数据store
  const predictionStore = useGamePredictionStore();

  // 从store中获取响应式数据
  const {
    latestGameData,
    currentRoundId,
    currentGameStatus,
    currentGameTokens,
    hasCurrentAnalysis,
    currentAnalysis,
    websocketStatus
  } = storeToRefs(predictionStore);

  // 响应式数据 - 只保留必要的本地状态
  const recentMessages = ref<Array<{ type: string; message: string; timestamp: string }>>([]);
  const gameDataTimestamp = ref<string>('');
  const predictionTimestamp = ref<string>('');

  // 使用统一的状态工具
  const wsStatusConfig = computed(() => getWebSocketStatusConfig(websocketStatus.value.status));

  // 计算属性
  const connectionStatusText = computed(() => wsStatusConfig.value.label);

  // 方法
  const addMessage = (type: string, message: string) => {
    recentMessages.value.unshift({
      type,
      message,
      timestamp: new Date().toISOString()
    });

    // 只保留最近20条消息
    if (recentMessages.value.length > 20) {
      recentMessages.value = recentMessages.value.slice(0, 20);
    }
  };

  const getStatusColor = (status: string) => {
    return `${getGameStatusConfig(status).color} font-medium`;
  };

  const getMessageColor = (type: string) => {
    switch (type) {
      case 'game':
        return 'text-blue-600';
      case 'prediction':
        return 'text-green-600';
      case 'error':
        return 'text-red-600';
      case 'connection':
        return 'text-purple-600';
      default:
        return 'text-gray-600';
    }
  };

  const formatTime = (timestamp: string) => {
    if (!timestamp) return 'N/A';
    return new Date(timestamp).toLocaleTimeString('zh-CN', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  };

  // 监听store数据变化，记录到消息日志
  const gameDataWatcher = watch(
    latestGameData,
    (newData) => {
      if (newData) {
        gameDataTimestamp.value = new Date().toISOString();
        addMessage('game', `🎮 游戏数据更新: ${newData.status} (轮次: ${currentRoundId.value || 'N/A'})`);
      }
    },
    { deep: true }
  );

  const analysisWatcher = watch(
    currentAnalysis,
    (newAnalysis) => {
      if (newAnalysis && newAnalysis.length > 0) {
        predictionTimestamp.value = new Date().toISOString();
        addMessage(
          'prediction',
          `🧠 预测数据更新: ${newAnalysis.length} 个代币 (轮次: ${currentRoundId.value || 'N/A'})`
        );
      }
    },
    { deep: true }
  );

  const websocketStatusWatcher = watch(
    () => websocketStatus.value.status,
    (newStatus, oldStatus) => {
      if (oldStatus && newStatus !== oldStatus) {
        const statusMessages = {
          connected: '✅ WebSocket连接成功',
          connecting: '🔄 正在连接WebSocket...',
          disconnected: '🔌 WebSocket连接已断开',
          error: '❌ WebSocket连接失败'
        };

        const message = statusMessages[newStatus as keyof typeof statusMessages] || `状态变更: ${newStatus}`;
        addMessage('connection', message);
      }
    }
  );

  // 生命周期
  onMounted(() => {
    addMessage('connection', '📡 实时数据显示组件已加载');

    // 初始化时间戳
    if (latestGameData.value) {
      gameDataTimestamp.value = new Date().toISOString();
    }
    if (hasCurrentAnalysis.value) {
      predictionTimestamp.value = new Date().toISOString();
    }
  });

  onUnmounted(() => {
    // 清理监听器
    gameDataWatcher();
    analysisWatcher();
    websocketStatusWatcher();
  });
</script>

<style scoped>
  .real-time-data-container {
    max-width: 1200px;
    margin: 0 auto;
  }

  /* 滚动条样式 */
  .overflow-y-auto::-webkit-scrollbar {
    width: 6px;
  }

  .overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
  }

  .overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
  }

  .overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
  }
</style>
