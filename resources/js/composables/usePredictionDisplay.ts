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
      return 'border-amber-500/20 from-amber-500/5 to-yellow-600/2 bg-gradient-to-br hover:border-amber-400/30 hover:shadow-amber-500/10';
    }
    if (index === 1) {
      return 'border-white/20 from-white/5 to-gray-300/2 bg-gradient-to-br hover:border-white/30 hover:shadow-white/10';
    }
    if (index === 2) {
      return 'border-yellow-500/20 from-yellow-500/5 to-amber-600/2 bg-gradient-to-br hover:border-yellow-400/30 hover:shadow-yellow-500/10';
    }
    if (index === 3) {
      return 'border-blue-500/20 from-blue-500/5 to-indigo-600/2 bg-gradient-to-br hover:border-blue-400/30 hover:shadow-blue-500/10';
    }
    return 'border-cyan-500/20 from-cyan-500/5 to-blue-600/2 bg-gradient-to-br hover:border-cyan-400/30 hover:shadow-cyan-500/10';
  };

  // 获取边框样式
  const getBorderStyle = (index: number) => {
    const colors = [
      'rgba(245, 158, 11, 0.2)', // 金色 (amber-500)
      'rgba(156, 163, 175, 0.2)', // 银色
      'rgba(217, 119, 6, 0.2)', // 铜色
      'rgba(59, 130, 246, 0.2)', // 蓝色
      'rgba(34, 211, 238, 0.2)' // 青色
    ];
    return { borderColor: colors[index] || colors[4] };
  };

  // 获取评分文本颜色类
  const getScoreTextClass = (index: number) => {
    if (index === 0) return 'text-amber-400';
    if (index === 1) return 'text-gray-400';
    if (index === 2) return 'text-amber-400';
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
          border: 'border-amber-500/20',
          background: 'from-amber-500/5 to-yellow-600/2',
          hover: 'hover:border-amber-400/30 hover:shadow-amber-500/10',
          icon: '🥇',
          textColor: 'text-amber-300'
        };
      case 2:
        return {
          border: 'border-gray-400/20',
          background: 'from-gray-400/15 to-gray-500/10',
          hover: 'hover:border-gray-400/30 hover:shadow-gray-400/10',
          icon: '🥈',
          textColor: 'text-gray-300'
        };
      case 3:
        return {
          border: 'border-amber-600/20',
          background: 'from-amber-600/15 to-orange-700/10',
          hover: 'hover:border-amber-600/30 hover:shadow-amber-600/10',
          icon: '🥉',
          textColor: 'text-amber-300'
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
    getBorderStyle,

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
