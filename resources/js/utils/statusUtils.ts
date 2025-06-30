/**
 * 状态管理工具 - 统一处理游戏状态、WebSocket状态等
 */

// ==================== 类型定义 ====================

export type GameStatus = 'bet' | 'lock' | 'settling' | 'settled';
export type WebSocketStatus = 'connecting' | 'connected' | 'disconnected' | 'error';
export type PredictionStatus = 'exact' | 'breakeven' | 'loss';

// ==================== 游戏状态工具 ====================

/**
 * 游戏状态配置
 */
export const GAME_STATUS_CONFIG = {
  bet: {
    label: '可下注',
    icon: '🟢',
    color: 'text-green-400',
    bgColor: 'bg-green-500',
    tagType: 'success' as const,
    priority: 1
  },
  lock: {
    label: '锁定中',
    icon: '🟠',
    color: 'text-orange-400',
    bgColor: 'bg-orange-500',
    tagType: 'warning' as const,
    priority: 2
  },
  settling: {
    label: '结算中',
    icon: '🔴',
    color: 'text-red-400',
    bgColor: 'bg-red-500',
    tagType: 'error' as const,
    priority: 3
  },
  settled: {
    label: '已结算',
    icon: '🔵',
    color: 'text-blue-400',
    bgColor: 'bg-blue-500',
    tagType: 'info' as const,
    priority: 4
  }
} as const;

/**
 * 获取游戏状态配置
 */
export function getGameStatusConfig(status: string) {
  return (
    GAME_STATUS_CONFIG[status as GameStatus] || {
      label: '未知状态',
      icon: '⚪',
      color: 'text-gray-400',
      bgColor: 'bg-gray-500',
      tagType: 'default' as const,
      priority: 99
    }
  );
}

/**
 * 获取游戏状态标签类型 (NaiveUI)
 */
export function getGameStatusTagType(status: string): 'success' | 'warning' | 'error' | 'info' | 'default' {
  return getGameStatusConfig(status).tagType;
}

/**
 * 判断是否可以下注
 */
export function canBet(status: string): boolean {
  return status === 'bet';
}

/**
 * 判断是否锁定中
 */
export function isLocked(status: string): boolean {
  return status === 'lock';
}

/**
 * 判断是否结算中
 */
export function isSettling(status: string): boolean {
  return status === 'settling';
}

/**
 * 判断是否已结算
 */
export function isSettled(status: string): boolean {
  return status === 'settled';
}

// ==================== WebSocket状态工具 ====================

/**
 * WebSocket状态配置
 */
export const WEBSOCKET_STATUS_CONFIG = {
  connecting: {
    label: '连接中',
    icon: '🟡',
    color: 'text-yellow-400',
    bgColor: 'bg-yellow-500/20',
    borderColor: 'border-yellow-500/30'
  },
  connected: {
    label: '已连接',
    icon: '🟢',
    color: 'text-green-400',
    bgColor: 'bg-green-500/20',
    borderColor: 'border-green-500/30'
  },
  disconnected: {
    label: '已断开',
    icon: '⚪',
    color: 'text-gray-400',
    bgColor: 'bg-gray-500/20',
    borderColor: 'border-gray-500/30'
  },
  error: {
    label: '连接失败',
    icon: '🔴',
    color: 'text-red-400',
    bgColor: 'bg-red-500/20',
    borderColor: 'border-red-500/30'
  }
} as const;

/**
 * 获取WebSocket状态配置
 */
export function getWebSocketStatusConfig(status: string) {
  return WEBSOCKET_STATUS_CONFIG[status as WebSocketStatus] || WEBSOCKET_STATUS_CONFIG.disconnected;
}

/**
 * 获取WebSocket状态样式类
 */
export function getWebSocketStatusClass(status: string): string {
  const config = getWebSocketStatusConfig(status);
  return `${config.bgColor} border ${config.borderColor} ${config.color}`;
}

// ==================== 预测状态工具 ====================

/**
 * 预测状态配置
 */
export const PREDICTION_STATUS_CONFIG = {
  exact: {
    label: '精准预测',
    icon: '🎯',
    color: 'text-green-400',
    bgColor: 'bg-green-500/20'
  },
  breakeven: {
    label: '保本',
    icon: '💰',
    color: 'text-blue-400',
    bgColor: 'bg-blue-500/20'
  },
  loss: {
    label: '亏本',
    icon: '📉',
    color: 'text-red-400',
    bgColor: 'bg-red-500/20'
  }
} as const;

/**
 * 获取预测状态配置
 */
export function getPredictionStatusConfig(status: string) {
  return (
    PREDICTION_STATUS_CONFIG[status as PredictionStatus] || {
      label: '未知',
      icon: '❓',
      color: 'text-gray-400',
      bgColor: 'bg-gray-500/20'
    }
  );
}

/**
 * 分析预测结果
 */
export function analyzePredictionResult(predictedRank: number, actualRank: number) {
  // 精准预测：预测排名和实际排名完全一致
  if (predictedRank === actualRank) {
    return getPredictionStatusConfig('exact');
  }

  // 保本：实际排名在前三名
  if (actualRank <= 3) {
    return getPredictionStatusConfig('breakeven');
  }

  // 亏本：实际排名不在前三名
  return getPredictionStatusConfig('loss');
}

// ==================== 通用状态工具 ====================

/**
 * 格式化时间
 */
export function formatTime(timeString: string | null | undefined): string {
  if (!timeString) return '无';
  try {
    const date = new Date(timeString);
    return date.toLocaleTimeString('zh-CN', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  } catch {
    return '无效';
  }
}

/**
 * 获取状态指示器组件属性
 */
export function getStatusIndicatorProps(status: string) {
  const config = getGameStatusConfig(status);
  return {
    class: `h-2 w-2 rounded-full ${config.bgColor}`,
    text: config.label,
    textClass: config.color,
    icon: config.icon
  };
}

/**
 * 创建状态响应式工具
 */
export function createStatusUtils(getCurrentStatus: () => string) {
  return {
    get config() {
      return getGameStatusConfig(getCurrentStatus());
    },
    get canBet() {
      return canBet(getCurrentStatus());
    },
    get isLocked() {
      return isLocked(getCurrentStatus());
    },
    get isSettling() {
      return isSettling(getCurrentStatus());
    },
    get isSettled() {
      return isSettled(getCurrentStatus());
    },
    get tagType() {
      return getGameStatusTagType(getCurrentStatus());
    },
    get indicatorProps() {
      return getStatusIndicatorProps(getCurrentStatus());
    }
  };
}
