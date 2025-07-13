<template>
  <div class="prediction-dashboard">
    <DefaultLayout>
      <div class="mx-auto px-4 py-6 container">
        <!-- 页面标题 -->
        <div class="mb-6">
          <h1 class="mb-2 text-3xl text-gray-900 font-bold">AI预测系统</h1>
          <p class="text-gray-600">新一代多特征融合预测引擎，支持策略切换与A/B测试</p>
        </div>

        <!-- WebSocket 连接状态指示器 -->
        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <div class="realtime-indicator">
              <span :class="{ connected: store.isConnected }" class="status-dot"></span>
              <span class="ml-2 text-sm">
                {{ store.isConnected ? '🔗 实时监听中' : '❌ 未连接' }}
              </span>
            </div>
            <button
              v-if="!store.isConnected"
              @click="reconnectWebSocket"
              class="text-xs text-blue-600 underline hover:text-blue-800"
            >
              重连
            </button>
          </div>

          <!-- WebSocket 测试按钮 -->
          <div class="flex items-center space-x-2">
            <NButton size="small" type="info" ghost @click="testWebSocket" :loading="isTestingWebSocket">
              测试 WebSocket
            </NButton>
          </div>
        </div>

        <!-- 错误提示 -->
        <NAlert
          v-if="store.error"
          type="error"
          :title="'操作失败'"
          :description="store.error"
          class="mb-4"
          closable
          @close="store.clearError()"
        />

        <!-- 策略选择器 -->
        <StrategySelector
          :strategies="store.availableStrategies"
          :is-loading="store.isLoading"
          @execute="handleExecute"
          @backtest="handleBacktest"
        />

        <!-- 预测结果展示 -->
        <div v-if="store.hasResults" class="space-y-6">
          <!-- 预测结果表格 -->
          <PredictionResultTable :results="store.results" :is-loading="store.isLoading" />

          <!-- 预测分数图表 -->
          <PredictionScoreChart :results="store.results" />
        </div>

        <!-- 回测结果展示 -->
        <BacktestResultDisplay v-if="store.backtestResults" :backtest-result="store.backtestResults" />

        <!-- 历史预测记录 -->
        <div v-if="showHistory" class="space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg text-gray-900 font-semibold">历史预测记录</h3>
            <div class="flex items-center space-x-4">
              <!-- 策略筛选 -->
              <NSelect
                v-model:value="historyFilter.strategy_tag"
                :options="strategyFilterOptions"
                placeholder="选择策略"
                clearable
                class="w-48"
                @update:value="refreshHistory"
              />

              <!-- 时间范围筛选 -->
              <NDatePicker
                v-model:value="historyFilter.dateRange"
                type="daterange"
                placeholder="选择时间范围"
                clearable
                class="w-64"
                @update:value="refreshHistory"
              />

              <NButton @click="refreshHistory" :loading="store.isLoading">
                <template #icon>
                  <NIcon><RefreshOutline /></NIcon>
                </template>
                刷新
              </NButton>

              <NButton @click="showHistory = false">
                <template #icon>
                  <NIcon><TimeOutline /></NIcon>
                </template>
                隐藏历史
              </NButton>
            </div>
          </div>

          <PredictionResultTable :results="store.predictionHistory" :is-loading="store.isLoading" />
        </div>

        <!-- 显示历史按钮 -->
        <div v-else class="mt-6">
          <NButton @click="showHistory = true">
            <template #icon>
              <TimeOutline />
            </template>
            查看历史预测记录
          </NButton>
        </div>
      </div>
    </DefaultLayout>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed, onMounted, onUnmounted } from 'vue';
  import { useMessage, NButton, NAlert, NSelect, NDatePicker, NIcon } from 'naive-ui';
  import { RefreshOutline, TimeOutline } from '@vicons/ionicons5';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import StrategySelector from './StrategySelector.vue';
  import PredictionResultTable from './PredictionResultTable.vue';
  import PredictionScoreChart from './PredictionScoreChart.vue';
  import BacktestResultDisplay from './BacktestResultDisplay.vue';
  import { usePredictionStore } from '@/stores/prediction';

  // 使用store
  const store = usePredictionStore();
  const message = useMessage();

  // 响应式数据
  const showHistory = ref(false);
  const historyFilter = ref({
    strategy_tag: null as string | null,
    dateRange: null as [number, number] | null
  });

  // WebSocket 相关变量
  let predictionsChannel: any = null;
  const isTestingWebSocket = ref(false);

  // 计算属性
  const strategyFilterOptions = computed(() =>
    store.availableStrategies.map((strategy) => ({
      label: strategy.name,
      value: strategy.tag
    }))
  );

  // 方法
  const handleExecute = async (strategy: string, tokens: string[]) => {
    try {
      await store.runPrediction(strategy, tokens);
      message.success('预测执行成功');
    } catch (err: any) {
      message.error(err.message || '预测执行失败');
    }
  };

  const handleBacktest = async (strategy: string) => {
    try {
      await store.runBacktest({
        strategy_tag: strategy,
        rounds: 100 // 默认回测100轮
      });
      message.success('回测执行成功');
    } catch (err: any) {
      message.error(err.message || '回测执行失败');
    }
  };

  const refreshHistory = async () => {
    try {
      const options: any = {};
      if (historyFilter.value.strategy_tag) {
        options.strategy_tag = historyFilter.value.strategy_tag;
      }
      if (historyFilter.value.dateRange) {
        options.start_date = historyFilter.value.dateRange[0];
        options.end_date = historyFilter.value.dateRange[1];
      }

      await store.fetchPredictionHistory(options);
    } catch (err: any) {
      message.error('获取历史记录失败');
    }
  };

  // WebSocket 相关方法
  const initializeWebSocket = () => {
    try {
      // @ts-ignore
      if (window.Echo) {
        console.log('🔌 初始化 WebSocket 连接...');

        // 监听连接状态变化
        // @ts-ignore
        window.Echo.connector.pusher.connection.bind('connected', () => {
          console.log('✅ WebSocket 连接成功');
          store.setConnectionStatus(true);
        });

        // @ts-ignore
        window.Echo.connector.pusher.connection.bind('disconnected', () => {
          console.log('❌ WebSocket 连接断开');
          store.setConnectionStatus(false);
        });

        // @ts-ignore
        window.Echo.connector.pusher.connection.bind('error', (error: any) => {
          console.error('❌ WebSocket 连接错误:', error);
          store.setConnectionStatus(false);
        });

        // 监听预测数据更新频道
        // @ts-ignore
        predictionsChannel = window.Echo.channel('predictions');

        predictionsChannel
          .subscribed(() => {
            console.log('✅ 成功订阅 predictions 频道');
          })
          .listen('.NewPredictionMade', (event: any) => {
            console.log('🔮 收到新的预测数据:', event);

            try {
              // 解析预测数据
              const predictionData = event.prediction;
              if (predictionData) {
                // 转换为 PredictionResultDTO 格式
                const predictionResult: any = {
                  id: predictionData.id,
                  game_round_id: predictionData.game_round_id,
                  token: predictionData.token,
                  predict_rank: predictionData.predict_rank,
                  predict_score: predictionData.predict_score,
                  elo_score: predictionData.elo_score,
                  momentum_score: predictionData.momentum_score,
                  volume_score: predictionData.volume_score,
                  norm_elo: predictionData.norm_elo,
                  norm_momentum: predictionData.norm_momentum,
                  norm_volume: predictionData.norm_volume,
                  used_weights: predictionData.used_weights,
                  used_normalization: predictionData.used_normalization,
                  strategy_tag: predictionData.strategy_tag,
                  config_snapshot: predictionData.config_snapshot,
                  created_at: predictionData.created_at
                };

                // 添加到 store
                store.addRealtimePrediction(predictionResult);

                // 显示通知
                message.success(`新预测: ${predictionData.token} 排名第${predictionData.predict_rank}`);
              }
            } catch (err: any) {
              console.error('处理预测数据失败:', err);
            }
          })
          .error((error: any) => {
            console.error('❌ predictions 频道错误:', error);
          });

        // 设置初始连接状态
        // @ts-ignore
        store.setConnectionStatus(window.Echo.connector.pusher.connection.state === 'connected');
      } else {
        console.warn('⚠️ WebSocket 客户端未初始化');
      }
    } catch (error: any) {
      console.error('初始化 WebSocket 失败:', error);
    }
  };

  const reconnectWebSocket = () => {
    try {
      // @ts-ignore
      if (window.Echo) {
        // @ts-ignore
        window.Echo.connector.pusher.connection.connect();
        message.info('正在重新连接...');
      }
    } catch (error: any) {
      console.error('重连失败:', error);
      message.error('重连失败');
    }
  };

  const cleanupWebSocket = () => {
    try {
      if (predictionsChannel) {
        // @ts-ignore
        window.Echo.leaveChannel('predictions');
        predictionsChannel = null;
        console.log('🔌 WebSocket 连接已清理');
      }
    } catch (err: any) {
      console.error('清理 WebSocket 失败:', err);
    }
  };

  const testWebSocket = async () => {
    try {
      isTestingWebSocket.value = true;

      const response = await fetch('/api/v2/websocket/test-broadcast', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
          round_id: `test_${Date.now()}`,
          symbols: ['BTC', 'ETH', 'SOL', 'DOGE', 'ADA']
        })
      });

      const result = await response.json();

      if (result.success) {
        message.success('WebSocket 测试数据已发送，请查看实时更新');
      } else {
        message.error(`WebSocket 测试失败: ${result.message}`);
      }
    } catch (err: any) {
      console.error('WebSocket 测试失败:', err);
      message.error('WebSocket 测试失败');
    } finally {
      isTestingWebSocket.value = false;
    }
  };

  // 生命周期
  onMounted(async () => {
    // 获取可用策略列表
    await store.fetchStrategies();

    // 初始化 WebSocket
    initializeWebSocket();
  });

  onUnmounted(() => {
    // 清理 WebSocket 连接
    cleanupWebSocket();
  });
</script>

<style scoped>
  .realtime-indicator {
    @apply flex items-center;
  }

  .status-dot {
    @apply w-3 h-3 rounded-full bg-red-500;
    transition: background-color 0.3s ease;
  }

  .status-dot.connected {
    @apply bg-green-500;
  }

  .prediction-dashboard {
    @apply min-h-screen bg-gray-50;
  }

  .container {
    @apply max-w-7xl;
  }
</style>
