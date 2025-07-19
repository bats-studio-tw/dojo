import { getGameStatusTagType } from '@/utils/statusUtils';

export function usePredictionDisplay() {
  // 格式化价格变化百分比
  const formatPriceChange = (change: number | null) => {
    if (change === null || change === undefined) {
      return { text: '-', color: 'text-gray-500' };
    }

    const value = change.toFixed(2);
    if (change > 0) {
      return { text: `+${value}%`, color: 'text-green-400' };
    } else if (change < 0) {
      return { text: `${value}%`, color: 'text-red-400' };
    } else {
      return { text: '0.00%', color: 'text-gray-400' };
    }
  };

  // 获取统一的卡片样式类
  const getUnifiedCardClass = (index: number) => {
    if (index === 0) {
      return 'border-yellow-400/20 bg-yellow-500/15 hover:border-yellow-400/30 hover:bg-yellow-500/20';
    }
    if (index === 1) {
      return 'border-blue-400/20 bg-blue-500/15 hover:border-blue-400/30 hover:bg-blue-500/20';
    }
    if (index === 2) {
      return 'border-green-400/20 bg-green-500/15 hover:border-green-400/30 hover:bg-green-500/20';
    }
    if (index === 3) {
      return 'border-blue-400/20 bg-gradient-to-br from-blue-500/5 to-indigo-600/2 hover:border-blue-400/30 hover:shadow-blue-500/10';
    }
    return 'border-cyan-400/20 bg-gradient-to-br from-cyan-500/5 to-blue-600/2 hover:border-cyan-400/30 hover:shadow-cyan-500/10';
  };

  // 获取评分文本颜色类
  const getScoreTextClass = (index: number) => {
    if (index === 0) return 'text-yellow-400';
    if (index === 1) return 'text-blue-400';
    if (index === 2) return 'text-green-400';
    if (index === 3) return 'text-blue-400';
    return 'text-cyan-400';
  };

  // 获取预测图标 (统一使用排名参数)
  const getPredictionIcon = (rank: number) => {
    if (rank === 1) return '🥇';
    if (rank === 2) return '🥈';
    if (rank === 3) return '🥉';
    return '🏅';
  };

  // 状态标签类型（使用统一的状态工具）
  const getStatusTagType = getGameStatusTagType;

  // 获取预测行的样式类
  const getPredictionRowClass = (isExactMatch: boolean, isBetterThanExpected: boolean) => {
    if (isExactMatch || isBetterThanExpected) {
      return 'bg-green-500/20 border-l-4 border-green-500';
    } else {
      return 'bg-red-500/20 border-l-4 border-red-500';
    }
  };

  // 获取排名统计卡片样式
  const getRankStatsCardClass = (rank: number) => {
    switch (rank) {
      case 1:
        return {
          border: 'border-yellow-500/20',
          background: 'bg-yellow-500/15',
          hover: 'hover:border-yellow-400/30 hover:bg-yellow-500/20',
          icon: '🥇',
          textColor: 'text-yellow-300'
        };
      case 2:
        return {
          border: 'border-blue-500/20',
          background: 'bg-blue-500/15',
          hover: 'hover:border-blue-400/30 hover:bg-blue-500/20',
          icon: '🥈',
          textColor: 'text-blue-300'
        };
      case 3:
        return {
          border: 'border-green-500/20',
          background: 'bg-green-500/15',
          hover: 'hover:border-green-400/30 hover:bg-green-500/20',
          icon: '🥉',
          textColor: 'text-green-300'
        };
      default:
        return {
          border: 'border-cyan-500/20',
          background: 'from-cyan-500/5 to-blue-600/2',
          hover: 'hover:border-cyan-400/30 hover:shadow-cyan-500/10',
          icon: '📊',
          textColor: 'text-cyan-300'
        };
    }
  };

  // 获取精准预测率卡片样式
  const getAccuracyCardClass = () => {
    return {
      border: 'border-green-500/20',
      background: 'from-green-500/5 to-emerald-600/2',
      hover: 'hover:border-green-400/30 hover:shadow-green-500/10',
      icon: '🎯',
      textColor: 'text-green-300',
      valueColor: 'text-green-400'
    };
  };

  // 获取总局数卡片样式
  const getTotalRoundsCardClass = () => {
    return {
      border: 'border-cyan-500/20',
      background: 'from-cyan-500/5 to-blue-600/2',
      hover: 'hover:border-cyan-400/30 hover:shadow-cyan-500/10',
      icon: '📊',
      textColor: 'text-cyan-300',
      valueColor: 'text-cyan-400'
    };
  };

  // 合并所有卡片样式类
  const getCombinedCardClass = (cardStyle: ReturnType<typeof getRankStatsCardClass>) => {
    const backgroundClass = cardStyle.background.startsWith('bg-')
      ? cardStyle.background
      : `bg-gradient-to-br ${cardStyle.background}`;

    return `relative overflow-hidden ${cardStyle.border} rounded-xl ${backgroundClass} p-4 transition-all duration-300 ${cardStyle.hover} sm:p-6 hover:shadow-lg`;
  };

  return {
    // 格式化函数
    formatPriceChange,

    // 样式类函数
    getUnifiedCardClass,
    getScoreTextClass,
    getPredictionRowClass,
    getCombinedCardClass,

    // 图标函数
    getPredictionIcon,

    // 状态函数
    getStatusTagType,

    // 卡片样式配置
    getRankStatsCardClass,
    getAccuracyCardClass,
    getTotalRoundsCardClass
  };
}
