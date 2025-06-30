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
      return 'border-yellow-400/30 bg-gradient-to-br from-yellow-500/10 to-amber-600/5 hover:border-yellow-400/50 hover:shadow-yellow-500/20';
    }
    if (index === 1) {
      return 'border-slate-400/30 bg-gradient-to-br from-slate-500/10 to-gray-600/5 hover:border-slate-400/50 hover:shadow-slate-500/20';
    }
    if (index === 2) {
      return 'border-orange-400/30 bg-gradient-to-br from-orange-500/10 to-red-600/5 hover:border-orange-400/50 hover:shadow-orange-500/20';
    }
    if (index === 3) {
      return 'border-blue-400/30 bg-gradient-to-br from-blue-500/10 to-indigo-600/5 hover:border-blue-400/50 hover:shadow-blue-500/20';
    }
    return 'border-purple-400/30 bg-gradient-to-br from-purple-500/10 to-pink-600/5 hover:border-purple-400/50 hover:shadow-purple-500/20';
  };

  // 获取评分文本颜色类
  const getScoreTextClass = (index: number) => {
    if (index === 0) return 'text-yellow-400';
    if (index === 1) return 'text-slate-400';
    if (index === 2) return 'text-orange-400';
    if (index === 3) return 'text-blue-400';
    return 'text-purple-400';
  };

  // 获取预测图标
  const getPredictionIcon = (index: number) => {
    if (index === 0) return '🥇';
    if (index === 1) return '🥈';
    if (index === 2) return '🥉';
    return '📊';
  };

  // 获取排名对应的图标
  const getPredictionRankIcon = (rank: number) => {
    if (rank === 1) return '🥇';
    if (rank === 2) return '🥈';
    if (rank === 3) return '🥉';
    if (rank === 4) return '4️⃣';
    if (rank === 5) return '5️⃣';
    return '📊';
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
          border: 'border-yellow-500/30',
          background: 'from-yellow-500/10 to-amber-600/5',
          hover: 'hover:border-yellow-400/50 hover:shadow-yellow-500/20',
          icon: '🥇',
          textColor: 'text-yellow-300'
        };
      case 2:
        return {
          border: 'border-slate-500/30',
          background: 'from-slate-500/10 to-gray-600/5',
          hover: 'hover:border-slate-400/50 hover:shadow-slate-500/20',
          icon: '🥈',
          textColor: 'text-slate-300'
        };
      case 3:
        return {
          border: 'border-orange-500/30',
          background: 'from-orange-500/10 to-red-600/5',
          hover: 'hover:border-orange-400/50 hover:shadow-orange-500/20',
          icon: '🥉',
          textColor: 'text-orange-300'
        };
      default:
        return {
          border: 'border-purple-500/30',
          background: 'from-purple-500/10 to-indigo-600/5',
          hover: 'hover:border-purple-400/50 hover:shadow-purple-500/20',
          icon: '📊',
          textColor: 'text-purple-300'
        };
    }
  };

  // 获取精准预测率卡片样式
  const getAccuracyCardClass = () => {
    return {
      border: 'border-green-500/30',
      background: 'from-green-500/10 to-emerald-600/5',
      hover: 'hover:border-green-400/50 hover:shadow-green-500/20',
      icon: '🎯',
      textColor: 'text-green-300',
      valueColor: 'text-green-400'
    };
  };

  // 获取总局数卡片样式
  const getTotalRoundsCardClass = () => {
    return {
      border: 'border-purple-500/30',
      background: 'from-purple-500/10 to-indigo-600/5',
      hover: 'hover:border-purple-400/50 hover:shadow-purple-500/20',
      icon: '📊',
      textColor: 'text-purple-300',
      valueColor: 'text-purple-400'
    };
  };

  // 合并所有卡片样式类
  const getCombinedCardClass = (cardStyle: ReturnType<typeof getRankStatsCardClass>) => {
    return `relative overflow-hidden ${cardStyle.border} rounded-xl bg-gradient-to-br ${cardStyle.background} p-4 transition-all duration-300 ${cardStyle.hover} sm:p-6 hover:shadow-lg`;
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
    getPredictionRankIcon,

    // 状态函数
    getStatusTagType,

    // 卡片样式配置
    getRankStatsCardClass,
    getAccuracyCardClass,
    getTotalRoundsCardClass
  };
}
