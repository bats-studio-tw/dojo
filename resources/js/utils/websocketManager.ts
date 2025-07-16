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

  /**
   * 初始化WebSocket状态监控
   */
  public initialize(): void {
    if (this.isInitialized) {
      console.log('⚠️ WebSocket状态监控器已经初始化，跳过重复初始化');
      return;
    }

    console.log('🔄 初始化WebSocket状态监控器...');

    if (!window.Echo) {
      console.error('❌ Echo WebSocket未初始化');
      this.updateStatus('error', 'WebSocket Echo未初始化');
      return;
    }

    this.setupStatusMonitoring();
    this.isInitialized = true;
    console.log('✅ WebSocket状态监控器初始化完成');
  }

  /**
   * 设置状态监控
   */
  private setupStatusMonitoring(): void {
    if (!window.Echo) return;

    // 监听连接状态变化
    window.Echo.connector.pusher.connection.bind('connected', () => {
      console.log('✅ WebSocket连接成功');
      this.updateStatus('connected', '已连接');
    });

    window.Echo.connector.pusher.connection.bind('disconnected', () => {
      console.log('❌ WebSocket连接断开');
      this.updateStatus('disconnected', '连接已断开');
    });

    window.Echo.connector.pusher.connection.bind('connecting', () => {
      console.log('🔄 WebSocket正在连接...');
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
    console.log('🔄 手动重连WebSocket...');

    if (!window.Echo) {
      console.error('❌ Echo未初始化，无法重连');
      return;
    }

    try {
      this.updateStatus('connecting', '正在手动重连...');
      window.Echo.connector.pusher.connection.connect();
      console.log('✅ 重连请求已发送');
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
    console.log('🧹 清理WebSocket状态监控器资源...');

    if (this.statusCheckInterval) {
      clearInterval(this.statusCheckInterval);
      this.statusCheckInterval = null;
    }

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
}

// 创建全局WebSocket管理器实例
export const websocketManager = new WebSocketManager();
