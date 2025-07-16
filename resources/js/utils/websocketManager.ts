import { ref, computed } from 'vue';

// 定义WebSocket状态类型
export interface WebSocketStatus {
  status: 'connecting' | 'connected' | 'disconnected' | 'error';
  message: string;
  lastConnectedAt: string | null;
}

export class WebSocketManager {
  private reconnectAttempts = 0;
  private maxReconnectAttempts = 5;
  private baseReconnectDelay = 3000;
  private isInitialized = false;
  private connectionTimeoutId: ReturnType<typeof setTimeout> | null = null;
  private reconnectTimeoutId: ReturnType<typeof setTimeout> | null = null;

  // 状态管理
  public websocketStatus = ref<WebSocketStatus>({
    status: 'disconnected',
    message: '未连接',
    lastConnectedAt: null
  });

  public isConnected = computed(() => this.websocketStatus.value.status === 'connected');

  // 频道引用
  private gameUpdatesChannel: any = null;
  private predictionsChannel: any = null;
  private hybridPredictionsChannel: any = null;

  /**
   * 初始化WebSocket连接
   */
  public initialize(): void {
    if (this.isInitialized) {
      console.log('⚠️ WebSocket已经初始化，跳过重复初始化');
      return;
    }

    console.log('🔄 初始化WebSocket连接...');

    if (!window.Echo) {
      console.error('❌ Echo WebSocket未初始化');
      this.updateStatus('error', 'WebSocket Echo未初始化');
      return;
    }

    this.updateStatus('connecting', '正在连接WebSocket...');

    // 设置连接超时
    this.connectionTimeoutId = setTimeout(() => {
      if (this.websocketStatus.value.status === 'connecting') {
        console.warn('⚠️ WebSocket连接超时，尝试重连...');
        this.updateStatus('error', '连接超时，正在重试...');
        this.scheduleReconnect(3000);
      }
    }, 10000);

    try {
      this.setupConnectionListeners();
      this.setupChannels();
      this.isInitialized = true;
      console.log('✅ WebSocket初始化完成');
    } catch (error) {
      console.error('❌ WebSocket初始化失败:', error);
      this.updateStatus('error', '初始化失败');
      this.clearConnectionTimeout();
    }
  }

  /**
   * 设置连接监听器
   */
  private setupConnectionListeners(): void {
    if (!window.Echo) return;

    window.Echo.connector.pusher.connection.bind('connected', () => {
      this.clearConnectionTimeout();
      console.log('✅ WebSocket连接成功');
      this.updateStatus('connected', '已连接');
      this.reconnectAttempts = 0; // 重置重连计数
    });

    window.Echo.connector.pusher.connection.bind('disconnected', () => {
      console.log('❌ WebSocket连接断开');
      this.updateStatus('disconnected', '连接已断开');
      this.scheduleReconnect(5000);
    });

    window.Echo.connector.pusher.connection.bind('error', (error: any) => {
      console.error('❌ WebSocket连接错误:', error);
      this.updateStatus('error', '连接错误');
    });
  }

  /**
   * 设置频道监听
   */
  private setupChannels(): void {
    if (!window.Echo) return;

    // 游戏数据更新频道
    this.gameUpdatesChannel = window.Echo.channel('game-updates');
    this.gameUpdatesChannel
      .subscribed(() => console.log('✅ 成功订阅 game-updates 频道'))
      .error((error: any) => console.error('❌ game-updates 频道错误:', error));

    // 预测数据更新频道
    this.predictionsChannel = window.Echo.channel('predictions');
    this.predictionsChannel
      .subscribed(() => console.log('✅ 成功订阅 predictions 频道'))
      .error((error: any) => console.error('❌ predictions 频道错误:', error));

    // Hybrid预测数据更新频道
    this.hybridPredictionsChannel = window.Echo.channel('hybrid-predictions');
    this.hybridPredictionsChannel
      .subscribed(() => console.log('✅ 成功订阅 hybrid-predictions 频道'))
      .error((error: any) => console.error('❌ hybrid-predictions 频道错误:', error));
  }

  /**
   * 断开WebSocket连接
   */
  public disconnect(): void {
    console.log('🔄 断开WebSocket连接...');

    this.clearTimeouts();

    try {
      if (this.gameUpdatesChannel) {
        window.Echo?.leaveChannel('game-updates');
        this.gameUpdatesChannel = null;
      }

      if (this.predictionsChannel) {
        window.Echo?.leaveChannel('predictions');
        this.predictionsChannel = null;
      }

      if (this.hybridPredictionsChannel) {
        window.Echo?.leaveChannel('hybrid-predictions');
        this.hybridPredictionsChannel = null;
      }

      this.isInitialized = false;
      this.updateStatus('disconnected', '已断开连接');
      console.log('✅ WebSocket连接已断开');
    } catch (error) {
      console.error('❌ 断开WebSocket连接时发生错误:', error);
    }
  }

  /**
   * 手动重连
   */
  public manualReconnect(): void {
    console.log('🔄 手动重连WebSocket...');
    this.reconnectAttempts = 0; // 重置重连计数
    this.updateStatus('connecting', '正在手动重连...');

    this.disconnect();

    setTimeout(() => {
      this.initialize();
    }, 1000);
  }

  /**
   * 自动重连
   */
  private reconnect(): void {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('❌ 达到最大重连次数，停止重连');
      this.updateStatus('error', '重连失败，请手动刷新页面');
      return;
    }

    this.reconnectAttempts++;
    const delay = this.baseReconnectDelay * Math.pow(2, this.reconnectAttempts - 1);

    console.log(`🔄 第 ${this.reconnectAttempts} 次重连尝试，延迟 ${delay}ms`);

    this.updateStatus('connecting', `正在重连... (${this.reconnectAttempts}/${this.maxReconnectAttempts})`);

    this.reconnectTimeoutId = setTimeout(() => {
      try {
        this.disconnect();
        this.initialize();
      } catch (error) {
        console.error('❌ 重连失败:', error);
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
          this.reconnect();
        }
      }
    }, delay);
  }

  /**
   * 安排重连
   */
  private scheduleReconnect(delay: number): void {
    if (this.websocketStatus.value.status === 'disconnected') {
      setTimeout(() => {
        if (this.websocketStatus.value.status === 'disconnected') {
          console.log('🔄 尝试自动重连...');
          this.reconnect();
        }
      }, delay);
    }
  }

  /**
   * 更新状态
   */
  private updateStatus(status: WebSocketStatus['status'], message: string): void {
    this.websocketStatus.value = {
      status,
      message,
      lastConnectedAt: status === 'connected' ? new Date().toISOString() : this.websocketStatus.value.lastConnectedAt
    };
  }

  /**
   * 清理超时定时器
   */
  private clearTimeouts(): void {
    if (this.connectionTimeoutId) {
      clearTimeout(this.connectionTimeoutId);
      this.connectionTimeoutId = null;
    }
    if (this.reconnectTimeoutId) {
      clearTimeout(this.reconnectTimeoutId);
      this.reconnectTimeoutId = null;
    }
  }

  /**
   * 清理连接超时
   */
  private clearConnectionTimeout(): void {
    if (this.connectionTimeoutId) {
      clearTimeout(this.connectionTimeoutId);
      this.connectionTimeoutId = null;
    }
  }

  /**
   * 清理资源
   */
  public cleanup(): void {
    console.log('🧹 清理WebSocket管理器资源...');
    this.disconnect();
    this.clearTimeouts();
  }
}

// 创建全局WebSocket管理器实例
export const websocketManager = new WebSocketManager();
