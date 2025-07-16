import { ref, computed } from 'vue';

// 定义WebSocket状态类型
export interface WebSocketStatus {
  status: 'connecting' | 'connected' | 'disconnected' | 'error';
  message: string;
  lastConnectedAt: string | null;
}

export class WebSocketManager {
  private isInitialized = false;
  private statusCheckInterval: ReturnType<typeof setInterval> | null = null;

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

  // 事件回调存储
  private eventCallbacks: {
    gameUpdates?: (data: any) => void;
    predictions?: (data: any) => void;
    hybridPredictions?: (data: any) => void;
  } = {};

  /**
   * 初始化WebSocket连接和状态监控
   */
  public initialize(): void {
    if (this.isInitialized) {
      return;
    }

    if (!window.Echo) {
      console.error('❌ Echo WebSocket未初始化');
      this.updateStatus('error', 'WebSocket Echo未初始化');
      return;
    }

    this.setupStatusMonitoring();
    this.setupChannels();
    this.isInitialized = true;
  }

  /**
   * 设置状态监控
   */
  private setupStatusMonitoring(): void {
    if (!window.Echo) return;

    // 监听连接状态变化
    window.Echo.connector.pusher.connection.bind('connected', () => {
      this.updateStatus('connected', '已连接');
    });

    window.Echo.connector.pusher.connection.bind('disconnected', () => {
      this.updateStatus('disconnected', '连接已断开');
    });

    window.Echo.connector.pusher.connection.bind('connecting', () => {
      this.updateStatus('connecting', '正在连接...');
    });

    window.Echo.connector.pusher.connection.bind('error', (error: any) => {
      console.error('❌ WebSocket连接错误:', error);
      this.updateStatus('error', '连接错误');
    });

    // 设置定期状态检查
    this.statusCheckInterval = setInterval(() => {
      this.checkConnectionStatus();
    }, 5000); // 每5秒检查一次状态

    // 立即检查一次当前状态
    this.checkConnectionStatus();
  }

  /**
   * 设置频道监听
   */
  private setupChannels(): void {
    if (!window.Echo) return;

    // 游戏数据更新频道
    this.gameUpdatesChannel = window.Echo.channel('game-updates');
    this.gameUpdatesChannel.error((error: any) => console.error('❌ game-updates 频道错误:', error));

    // 预测数据更新频道
    this.predictionsChannel = window.Echo.channel('predictions');
    this.predictionsChannel.error((error: any) => console.error('❌ predictions 频道错误:', error));

    // Hybrid预测数据更新频道
    this.hybridPredictionsChannel = window.Echo.channel('hybrid-predictions');
    this.hybridPredictionsChannel.error((error: any) => console.error('❌ hybrid-predictions 频道错误:', error));
  }

  /**
   * 监听游戏数据更新
   */
  public listenToGameUpdates(callback: (data: any) => void): void {
    this.eventCallbacks.gameUpdates = callback;

    if (this.gameUpdatesChannel) {
      this.gameUpdatesChannel.listen('.game.data.updated', (event: any) => {
        callback(event);
      });
    }
  }

  /**
   * 监听预测数据更新
   */
  public listenToPredictions(callback: (data: any) => void): void {
    this.eventCallbacks.predictions = callback;

    if (this.predictionsChannel) {
      this.predictionsChannel.listen('.prediction.updated', (event: any) => {
        callback(event);
      });
    }
  }

  /**
   * 监听Hybrid预测数据更新
   */
  public listenToHybridPredictions(callback: (data: any) => void): void {
    this.eventCallbacks.hybridPredictions = callback;

    if (this.hybridPredictionsChannel) {
      this.hybridPredictionsChannel.listen('.hybrid.prediction.updated', (event: any) => {
        callback(event);
      });
    }
  }

  /**
   * 检查连接状态
   */
  private checkConnectionStatus(): void {
    if (!window.Echo) return;

    const connection = window.Echo.connector.pusher.connection;
    const currentState = connection.state;

    // 如果状态没有变化，不更新
    if (this.websocketStatus.value.status === currentState) {
      return;
    }

    switch (currentState) {
      case 'connected':
        this.updateStatus('connected', '已连接');
        break;
      case 'connecting':
        this.updateStatus('connecting', '正在连接...');
        break;
      case 'disconnected':
        this.updateStatus('disconnected', '连接已断开');
        break;
      case 'error':
        this.updateStatus('error', '连接错误');
        break;
      default:
        this.updateStatus('disconnected', '未知状态');
    }
  }

  /**
   * 手动重连
   */
  public manualReconnect(): void {
    if (!window.Echo) {
      console.error('❌ Echo未初始化，无法重连');
      return;
    }

    try {
      this.updateStatus('connecting', '正在手动重连...');
      window.Echo.connector.pusher.connection.connect();
    } catch (error) {
      console.error('❌ 重连失败:', error);
      this.updateStatus('error', '重连失败');
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
   * 清理资源
   */
  public cleanup(): void {
    console.log('🧹 清理WebSocket管理器资源...');

    if (this.statusCheckInterval) {
      clearInterval(this.statusCheckInterval);
      this.statusCheckInterval = null;
    }

    // 清理频道
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

    // 清理回调
    this.eventCallbacks = {};

    this.isInitialized = false;
    this.updateStatus('disconnected', '已清理');
  }

  /**
   * 获取当前连接状态
   */
  public getCurrentStatus(): WebSocketStatus {
    return this.websocketStatus.value;
  }

  /**
   * 检查是否已连接
   */
  public checkIfConnected(): boolean {
    if (!window.Echo) return false;
    return window.Echo.connector.pusher.connection.state === 'connected';
  }

  /**
   * 获取频道引用（用于高级用法）
   */
  public getChannels() {
    return {
      gameUpdates: this.gameUpdatesChannel,
      predictions: this.predictionsChannel,
      hybridPredictions: this.hybridPredictionsChannel
    };
  }
}

// 创建全局WebSocket管理器实例
export const websocketManager = new WebSocketManager();
