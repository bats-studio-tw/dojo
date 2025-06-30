<template>
  <DefaultLayout>
    <Head title="Dashboard" />

    <div class="min-h-screen from-slate-900 via-purple-900 to-slate-900 bg-gradient-to-br p-3 sm:p-6">
      <div class="mx-auto max-w-4xl">
        <!-- 页面标题 -->
        <div class="mb-6 text-center">
          <h1 class="text-3xl text-white font-bold">🔍 WebSocket 调试面板</h1>
          <p class="mt-2 text-gray-400">专注于检查 WebSocket 事件接收</p>
        </div>

        <!-- WebSocket状态面板 -->
        <NCard
          class="mb-6 border border-blue-500/30 bg-blue-500/5 shadow-lg backdrop-blur-lg"
          title="📡 WebSocket 连接状态"
        >
          <div class="space-y-4">
            <!-- 状态指示器 -->
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div
                  class="h-4 w-4 rounded-full"
                  :class="{
                    'bg-green-500 animate-pulse': isConnected,
                    'bg-yellow-500 animate-spin': websocketStatus.status === 'connecting',
                    'bg-red-500': websocketStatus.status === 'error',
                    'bg-gray-500': websocketStatus.status === 'disconnected'
                  }"
                ></div>
                <span class="text-lg text-white font-medium">{{ websocketStatus.message }}</span>
              </div>
              <div class="flex space-x-2">
                <n-button v-if="!isConnected" @click="reconnectWebSocket" type="primary" size="small">
                  🔄 重新连接
                </n-button>
                <n-button @click="testConnection" type="info" size="small">🧪 测试连接</n-button>
              </div>
            </div>

            <!-- 连接详情 -->
            <div class="text-sm text-gray-300 space-y-1">
              <div>
                状态:
                <span class="text-cyan-400 font-mono">{{ websocketStatus.status }}</span>
              </div>
              <div>
                最后连接:
                <span class="text-cyan-400 font-mono">{{ websocketStatus.lastConnectedAt || '从未连接' }}</span>
              </div>
            </div>
          </div>
        </NCard>

        <!-- 事件监听面板 -->
        <NCard
          class="mb-6 border border-green-500/30 bg-green-500/5 shadow-lg backdrop-blur-lg"
          title="📨 WebSocket 事件监听"
        >
          <div class="space-y-4">
            <div class="text-sm text-gray-300">
              <p>当前正在监听以下 WebSocket 事件：</p>
              <ul class="mt-2 list-disc list-inside space-y-1">
                <li>
                  📨
                  <code class="text-cyan-400">game.data.updated</code>
                  - 游戏数据更新事件
                </li>
                <li>
                  🔮
                  <code class="text-purple-400">prediction.updated</code>
                  - 预测数据更新事件
                </li>
              </ul>
            </div>

            <div class="rounded bg-yellow-900/20 p-3 text-xs text-yellow-300">
              <strong>📝 说明：</strong>
              所有收到的事件数据都会在浏览器控制台 (F12) 中详细打印出来。
              <br />
              请打开控制台查看以
              <code>[DEBUG]</code>
              开头的日志信息。
            </div>
          </div>
        </NCard>

        <!-- 控制按钮 -->
        <NCard class="mb-6 border border-purple-500/30 bg-purple-500/5 shadow-lg backdrop-blur-lg" title="🎛️ 调试控制">
          <div class="flex flex-wrap gap-3">
            <n-button @click="initializeWebSocket" type="primary">🚀 初始化 WebSocket</n-button>
            <n-button @click="disconnectWebSocket" type="error">🔌 断开连接</n-button>
            <n-button @click="reconnectWebSocket" type="warning">🔄 重新连接</n-button>
            <n-button @click="testConnection" type="info">🧪 连接测试</n-button>
            <n-button @click="clearConsole" type="default">🧹 清空控制台</n-button>
          </div>
        </NCard>

        <!-- 使用说明 -->
        <NCard class="border border-gray-500/30 bg-gray-500/5 shadow-lg backdrop-blur-lg" title="📖 使用说明">
          <div class="text-sm text-gray-300 space-y-3">
            <div>
              <h4 class="mb-2 text-white font-medium">🔍 如何检查 WebSocket 事件：</h4>
              <ol class="list-decimal list-inside space-y-1">
                <li>
                  按
                  <kbd class="rounded bg-gray-700 px-2 py-1 text-xs">F12</kbd>
                  打开浏览器开发者工具
                </li>
                <li>
                  切换到
                  <strong>Console</strong>
                  标签页
                </li>
                <li>确保 WebSocket 状态显示为 "已连接"</li>
                <li>等待后端发送 WebSocket 事件，或手动触发事件</li>
                <li>
                  查看控制台中以
                  <code class="text-cyan-400">[DEBUG]</code>
                  开头的日志
                </li>
              </ol>
            </div>

            <div>
              <h4 class="mb-2 text-white font-medium">📨 预期的日志格式：</h4>
              <div class="rounded bg-black/50 p-3 text-xs font-mono">
                <div class="text-green-400">📨 [DEBUG] ========== 收到 game.data.updated 事件 ==========</div>
                <div class="text-gray-300">📨 [DEBUG] 完整数据: {...}</div>
                <div class="text-gray-300">📨 [DEBUG] ==========================================</div>
              </div>
            </div>

            <div class="rounded bg-yellow-900/20 p-3 text-yellow-300">
              <strong>⚠️ 注意：</strong>
              如果长时间没有看到事件日志，可能是：
              <ul class="mt-1 list-disc list-inside">
                <li>后端没有发送 WebSocket 事件</li>
                <li>频道名称或事件名称不匹配</li>
                <li>WebSocket 连接有问题</li>
              </ul>
            </div>
          </div>
        </NCard>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { Head } from '@inertiajs/vue3';
  import { storeToRefs } from 'pinia';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';

  // 使用简化的游戏预测store
  import { useGamePredictionStore } from '@/stores/gamePrediction';

  // 使用store
  const gamePredictionStore = useGamePredictionStore();

  // 从store中获取状态
  const { websocketStatus, isConnected } = storeToRefs(gamePredictionStore);

  // 从store中获取方法
  const { initializeWebSocket, disconnectWebSocket, reconnectWebSocket, testConnection } = gamePredictionStore;

  // 清空控制台
  const clearConsole = () => {
    console.clear();
    console.log('🧹 [DEBUG] 控制台已清空，开始新的调试会话');
    console.log('🔍 [DEBUG] 当前 WebSocket 状态:', websocketStatus.value);
  };

  // 页面初始化
  import { onMounted } from 'vue';

  onMounted(async () => {
    console.log('🏗️ [DEBUG] Dashboard 页面开始初始化...');
    console.log('🔍 [DEBUG] Echo 是否存在:', !!window.Echo);

    // 初始化store
    await gamePredictionStore.initialize();

    console.log('✅ [DEBUG] Dashboard 页面初始化完成');
    console.log('📖 [DEBUG] 请查看上方的使用说明，了解如何检查 WebSocket 事件');
  });
</script>

<style scoped>
  kbd {
    @apply bg-gray-700 text-gray-200 px-1.5 py-0.5 rounded text-xs font-mono;
  }

  code {
    @apply bg-gray-800 text-gray-200 px-1 py-0.5 rounded text-xs font-mono;
  }
</style>
