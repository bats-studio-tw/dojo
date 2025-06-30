import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

// 最简单的WebSocket状态类型
export interface WebSocketStatus {
  status: 'connecting' | 'connected' | 'disconnected' | 'error';
  message: string;
  lastConnectedAt: string | null;
}

export const useGamePredictionStore = defineStore('gamePrediction', () => {
  // ==================== 最简单的状态管理 ====================
  const websocketStatus = ref<WebSocketStatus>({
    status: 'disconnected',
    message: '未连接',
    lastConnectedAt: null
  });

  // WebSocket连接引用
  let gameUpdatesChannel: any = null;
  let predictionsChannel: any = null;

  // ==================== 计算属性 ====================
  const isConnected = computed(() => websocketStatus.value.status === 'connected');

  // ==================== 最简单的WebSocket初始化 ====================
  const initializeWebSocket = () => {
    console.log('🔄 [DEBUG] 开始初始化WebSocket连接...');

    if (!window.Echo) {
      console.error('❌ [DEBUG] Echo WebSocket未初始化');
      websocketStatus.value = {
        status: 'error',
        message: 'WebSocket Echo未初始化',
        lastConnectedAt: null
      };
      return;
    }

    // 检查Echo状态
    console.log('🔍 [DEBUG] Echo实例:', window.Echo);
    console.log('🔍 [DEBUG] Echo connector:', window.Echo.connector);

    if (window.Echo.connector?.pusher) {
      console.log('🔍 [DEBUG] Pusher连接状态:', window.Echo.connector.pusher.connection.state);
    }

    websocketStatus.value = {
      status: 'connecting',
      message: '正在连接WebSocket...',
      lastConnectedAt: null
    };

    try {
      // 1. 监听游戏数据更新
      console.log('🎮 [DEBUG] 开始监听 game-updates 频道...');
      gameUpdatesChannel = window.Echo.channel('game-updates');

      gameUpdatesChannel
        .subscribed(() => {
          console.log('✅ [DEBUG] 成功订阅 game-updates 频道');
        })
        .listen('game.data.updated', (data: any) => {
          console.log('📨 [DEBUG] ========== 收到 game.data.updated 事件 ==========');
          console.log('📨 [DEBUG] 完整数据:', data);
          console.log('📨 [DEBUG] 数据类型:', typeof data);
          console.log('📨 [DEBUG] 数据键:', Object.keys(data));
          console.log('📨 [DEBUG] 时间戳:', new Date().toLocaleString());
          console.log('📨 [DEBUG] ==========================================');
        })
        .error((error: any) => {
          console.error('❌ [DEBUG] game-updates 频道错误:', error);
        });

      // 2. 监听预测数据更新
      console.log('🧠 [DEBUG] 开始监听 predictions 频道...');
      predictionsChannel = window.Echo.channel('predictions');

      predictionsChannel
        .subscribed(() => {
          console.log('✅ [DEBUG] 成功订阅 predictions 频道');
        })
        .listen('prediction.updated', (data: any) => {
          console.log('🔮 [DEBUG] ========== 收到 prediction.updated 事件 ==========');
          console.log('🔮 [DEBUG] 完整数据:', data);
          console.log('🔮 [DEBUG] 数据类型:', typeof data);
          console.log('🔮 [DEBUG] 数据键:', Object.keys(data));
          console.log('🔮 [DEBUG] 时间戳:', new Date().toLocaleString());
          console.log('🔮 [DEBUG] ==========================================');
        })
        .error((error: any) => {
          console.error('❌ [DEBUG] predictions 频道错误:', error);
        });

      // 连接成功
      websocketStatus.value = {
        status: 'connected',
        message: '已连接',
        lastConnectedAt: new Date().toISOString()
      };

      console.log('✅ [DEBUG] WebSocket连接成功建立');

      // 输出连接后的状态信息
      setTimeout(() => {
        console.log('🔍 [DEBUG] 连接建立后的状态检查:');
        if (window.Echo?.connector?.pusher) {
          console.log('🔍 [DEBUG] Pusher连接状态:', window.Echo.connector.pusher.connection.state);
          console.log('🔍 [DEBUG] 已订阅的频道:', Object.keys(window.Echo.connector.pusher.channels.channels));

          // 检查频道详情
          const channels = window.Echo.connector.pusher.channels.channels;
          if (channels['game-updates']) {
            console.log('🔍 [DEBUG] game-updates 频道存在');
          }
          if (channels['predictions']) {
            console.log('🔍 [DEBUG] predictions 频道存在');
          }
        }
      }, 2000);
    } catch (error) {
      console.error('❌ [DEBUG] WebSocket连接失败:', error);
      websocketStatus.value = {
        status: 'error',
        message: `连接失败: ${error instanceof Error ? error.message : String(error)}`,
        lastConnectedAt: null
      };
    }
  };

  // ==================== 断开连接 ====================
  const disconnectWebSocket = () => {
    console.log('🔌 [DEBUG] 断开WebSocket连接');

    if (gameUpdatesChannel) {
      window.Echo.leaveChannel('game-updates');
      gameUpdatesChannel = null;
      console.log('🔌 [DEBUG] 已断开 game-updates 频道');
    }

    if (predictionsChannel) {
      window.Echo.leaveChannel('predictions');
      predictionsChannel = null;
      console.log('🔌 [DEBUG] 已断开 predictions 频道');
    }

    websocketStatus.value = {
      status: 'disconnected',
      message: '已断开连接',
      lastConnectedAt: null
    };
  };

  // ==================== 重连 ====================
  const reconnectWebSocket = () => {
    console.log('🔄 [DEBUG] 手动重连WebSocket...');
    disconnectWebSocket();
    setTimeout(() => {
      initializeWebSocket();
    }, 1000);
  };

  // ==================== 初始化 ====================
  const initialize = async () => {
    console.log('🏗️ [DEBUG] 初始化游戏预测数据store...');

    // 延迟初始化WebSocket，确保Echo已准备好
    setTimeout(() => {
      initializeWebSocket();
    }, 1000);
  };

  // ==================== 清理 ====================
  const cleanup = () => {
    console.log('🧹 [DEBUG] 清理游戏预测数据store资源...');
    disconnectWebSocket();
  };

  // ==================== 测试方法 ====================
  const testConnection = () => {
    console.log('🧪 [DEBUG] ========== 连接测试开始 ==========');
    console.log('🧪 [DEBUG] WebSocket状态:', websocketStatus.value);
    console.log('🧪 [DEBUG] Echo实例:', window.Echo);

    if (window.Echo?.connector?.pusher) {
      const pusher = window.Echo.connector.pusher;
      console.log('🧪 [DEBUG] Pusher连接状态:', pusher.connection.state);
      console.log('🧪 [DEBUG] 已订阅的频道:', Object.keys(pusher.channels.channels));

      // 检查频道状态
      Object.entries(pusher.channels.channels).forEach(([channelName, channel]: [string, any]) => {
        console.log(`🧪 [DEBUG] 频道 ${channelName}:`, {
          subscribed: channel.subscribed,
          state: channel.state,
          callbacks: Object.keys(channel.callbacks || {})
        });
      });
    }
    console.log('🧪 [DEBUG] ========== 连接测试结束 ==========');
  };

  return {
    // 状态
    websocketStatus,
    isConnected,

    // 方法
    initializeWebSocket,
    disconnectWebSocket,
    reconnectWebSocket,
    initialize,
    cleanup,
    testConnection
  };
});
