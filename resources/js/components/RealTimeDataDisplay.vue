<template>
  <div class="real-time-data-container">
    <!-- 连接状态指示器 -->
    <div class="mb-4 flex items-center gap-2">
      <div
        :class="[
          'w-3 h-3 rounded-full',
          connectionStatus === 'connected'
            ? 'bg-green-500'
            : connectionStatus === 'connecting'
              ? 'bg-yellow-500'
              : 'bg-red-500'
        ]"
      ></div>
      <span class="text-sm font-medium">WebSocket {{ connectionStatusText }}</span>
      <button
        @click="reconnectWebSocket"
        class="ml-2 px-2 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600"
      >
        重新连接
      </button>
      <button @click="testBroadcast" class="ml-2 px-2 py-1 text-xs bg-green-500 text-white rounded hover:bg-green-600">
        测试广播
      </button>
    </div>

    <!-- 实时游戏数据 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- 游戏数据面板 -->
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
          <div class="w-2 h-2 rounded-full bg-blue-500"></div>
          游戏数据
        </h3>

        <div v-if="latestGameData" class="space-y-3">
          <div class="flex justify-between">
            <span class="text-gray-600">轮次ID:</span>
            <span class="font-mono">{{ latestGameData.rdId || 'N/A' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">状态:</span>
            <span :class="getStatusColor(latestGameData.status)">
              {{ latestGameData.status || 'unknown' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">代币数量:</span>
            <span>{{ gameTokenCount }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">更新时间:</span>
            <span class="text-sm text-gray-500">{{ formatTime(gameDataTimestamp) }}</span>
          </div>

          <!-- 代币列表 -->
          <div v-if="gameTokens.length > 0" class="mt-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">当前代币:</h4>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="token in gameTokens"
                :key="token"
                class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full"
              >
                {{ token }}
              </span>
            </div>
          </div>
        </div>

        <div v-else class="text-center text-gray-500 py-8">等待游戏数据...</div>
      </div>

      <!-- 预测数据面板 -->
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
          <div class="w-2 h-2 rounded-full bg-green-500"></div>
          预测分析
        </h3>

        <div v-if="latestPrediction" class="space-y-3">
          <div class="flex justify-between">
            <span class="text-gray-600">轮次ID:</span>
            <span class="font-mono">{{ latestPrediction.round_id || 'N/A' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">预测数量:</span>
            <span>{{ latestPrediction.data?.length || 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">更新时间:</span>
            <span class="text-sm text-gray-500">{{ formatTime(predictionTimestamp) }}</span>
          </div>

          <!-- 预测排名 -->
          <div v-if="latestPrediction.data && latestPrediction.data.length > 0" class="mt-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">预测排名:</h4>
            <div class="space-y-2">
              <div
                v-for="(prediction, index) in latestPrediction.data.slice(0, 5)"
                :key="prediction.symbol"
                class="flex items-center justify-between p-2 bg-gray-50 rounded"
              >
                <div class="flex items-center gap-2">
                  <span class="w-6 h-6 bg-blue-500 text-white text-xs rounded-full flex items-center justify-center">
                    {{ index + 1 }}
                  </span>
                  <span class="font-medium">{{ prediction.symbol }}</span>
                </div>
                <div class="text-right">
                  <div class="text-sm font-medium">{{ prediction.risk_adjusted_score?.toFixed(1) || 'N/A' }}</div>
                  <div class="text-xs text-gray-500">
                    置信度: {{ prediction.rank_confidence?.toFixed(1) || 'N/A' }}%
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-center text-gray-500 py-8">等待预测数据...</div>
      </div>
    </div>

    <!-- 消息日志 -->
    <div class="mt-6 bg-gray-50 rounded-lg p-4">
      <h3 class="text-lg font-semibold mb-3">实时消息日志</h3>
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
      <div v-if="recentMessages.length === 0" class="text-center text-gray-500 py-4">暂无消息</div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ref, onMounted, onUnmounted, computed } from 'vue';

  // 响应式数据
  const connectionStatus = ref<'connecting' | 'connected' | 'disconnected'>('connecting');
  const latestGameData = ref<any>(null);
  const latestPrediction = ref<any>(null);
  const gameDataTimestamp = ref<string>('');
  const predictionTimestamp = ref<string>('');
  const recentMessages = ref<Array<{ type: string; message: string; timestamp: string }>>([]);

  // WebSocket 连接
  let gameUpdatesChannel: any = null;
  let predictionsChannel: any = null;

  // 计算属性
  const connectionStatusText = computed(() => {
    switch (connectionStatus.value) {
      case 'connected':
        return '已连接';
      case 'connecting':
        return '连接中';
      case 'disconnected':
        return '已断开';
      default:
        return '未知状态';
    }
  });

  const gameTokenCount = computed(() => {
    return latestGameData.value?.token ? Object.keys(latestGameData.value.token).length : 0;
  });

  const gameTokens = computed(() => {
    return latestGameData.value?.token ? Object.keys(latestGameData.value.token) : [];
  });

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
    switch (status) {
      case 'bet':
        return 'text-green-600 font-medium';
      case 'settling':
        return 'text-yellow-600 font-medium';
      case 'settled':
        return 'text-blue-600 font-medium';
      default:
        return 'text-gray-600';
    }
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

  const connectWebSocket = () => {
    if (!window.Echo) {
      addMessage('error', '❌ WebSocket Echo 未初始化');
      return;
    }

    connectionStatus.value = 'connecting';
    addMessage('connection', '🔄 正在连接WebSocket...');

    try {
      // 监听游戏数据更新频道
      gameUpdatesChannel = window.Echo.channel('game-updates')
        .listen('game.data.updated', (data: any) => {
          console.log('🎮 收到游戏数据更新:', data);
          latestGameData.value = data.data;
          gameDataTimestamp.value = data.timestamp;
          addMessage('game', `🎮 游戏数据更新: ${data.type} (轮次: ${data.data?.rdId || 'N/A'})`);
        })
        .subscribed(() => {
          console.log('✅ 已订阅 game-updates 频道');
          addMessage('connection', '📡 已订阅游戏数据频道');
        })
        .error((error: any) => {
          console.error('❌ game-updates 频道订阅错误:', error);
          addMessage('error', '❌ 游戏数据频道订阅失败');
        });

      // 监听预测数据更新频道
      predictionsChannel = window.Echo.channel('predictions')
        .listen('prediction.updated', (data: any) => {
          console.log('🧠 收到预测数据更新:', data);
          latestPrediction.value = data;
          predictionTimestamp.value = data.timestamp;
          addMessage('prediction', `🧠 预测数据更新: ${data.data?.length || 0} 个代币 (轮次: ${data.round_id})`);
        })
        .subscribed(() => {
          console.log('✅ 已订阅 predictions 频道');
          addMessage('connection', '📡 已订阅预测数据频道');
        })
        .error((error: any) => {
          console.error('❌ predictions 频道订阅错误:', error);
          addMessage('error', '❌ 预测数据频道订阅失败');
        });

      connectionStatus.value = 'connected';
      addMessage('connection', '✅ WebSocket连接成功');

      // 额外的调试信息
      console.log('Echo实例:', window.Echo);
      console.log('游戏数据频道:', gameUpdatesChannel);
      console.log('预测数据频道:', predictionsChannel);
    } catch (error) {
      console.error('WebSocket连接失败:', error);
      connectionStatus.value = 'disconnected';
      addMessage('error', '❌ WebSocket连接失败: ' + (error as Error).message);
    }
  };

  const disconnectWebSocket = () => {
    if (gameUpdatesChannel) {
      window.Echo.leaveChannel('game-updates');
      gameUpdatesChannel = null;
    }

    if (predictionsChannel) {
      window.Echo.leaveChannel('predictions');
      predictionsChannel = null;
    }

    connectionStatus.value = 'disconnected';
    addMessage('connection', '🔌 WebSocket连接已断开');
  };

  const reconnectWebSocket = () => {
    disconnectWebSocket();
    setTimeout(() => {
      connectWebSocket();
    }, 1000);
  };

  // 测试广播功能
  const testBroadcast = async () => {
    try {
      addMessage('connection', '🧪 触发测试广播...');
      const response = await window.axios.get('/websocket/test-broadcast');

      if (response.data.success) {
        addMessage('connection', '✅ 测试广播已发送');
        console.log('测试广播响应:', response.data);
      } else {
        addMessage('error', '❌ 测试广播失败: ' + response.data.message);
      }
    } catch (error) {
      console.error('测试广播错误:', error);
      addMessage('error', '❌ 测试广播请求失败: ' + (error as any).message);
    }
  };

  // 获取初始数据
  const fetchInitialData = async () => {
    try {
      const response = await window.axios.get('/websocket/latest-data');
      if (response.data.success) {
        const data = response.data.data;
        if (data.latest_game_data) {
          latestGameData.value = data.latest_game_data.data;
          gameDataTimestamp.value = data.latest_game_data.timestamp;
        }
        if (data.current_round) {
          // 可以显示当前轮次信息
        }
        addMessage('connection', '📦 获取初始数据成功');
      }
    } catch (error) {
      console.error('获取初始数据失败:', error);
      addMessage('error', '❌ 获取初始数据失败');
    }
  };

  // 生命周期
  onMounted(() => {
    // 等待Echo初始化
    setTimeout(() => {
      connectWebSocket();
      fetchInitialData();
    }, 1000);
  });

  onUnmounted(() => {
    disconnectWebSocket();
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
