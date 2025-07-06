import { ref, computed, readonly } from 'vue';
import { api } from '@/utils/api';

// 动能预测统计数据接口
export interface MomentumRankStats {
  total: number;
  breakevenRate: number;
  firstPlaceRate: number;
}

export interface AllMomentumRankStats {
  rank1: MomentumRankStats;
  rank2: MomentumRankStats;
  rank3: MomentumRankStats;
}

export interface MomentumPredictionStats {
  momentumAccuracy: number;
  totalRounds: number;
  averageMomentumScore: number;
  averageConfidence: number;
  allStats: AllMomentumRankStats;
  recentStats: AllMomentumRankStats;
}

export const useMomentumPredictionStats = () => {
  // 状态
  const stats = ref<MomentumPredictionStats>({
    momentumAccuracy: 0,
    totalRounds: 0,
    averageMomentumScore: 50,
    averageConfidence: 50,
    allStats: {
      rank1: { total: 0, breakevenRate: 0, firstPlaceRate: 0 },
      rank2: { total: 0, breakevenRate: 0, firstPlaceRate: 0 },
      rank3: { total: 0, breakevenRate: 0, firstPlaceRate: 0 }
    },
    recentStats: {
      rank1: { total: 0, breakevenRate: 0, firstPlaceRate: 0 },
      rank2: { total: 0, breakevenRate: 0, firstPlaceRate: 0 },
      rank3: { total: 0, breakevenRate: 0, firstPlaceRate: 0 }
    }
  });

  const loading = ref(false);
  const error = ref<string | null>(null);
  const recentRoundsCount = ref(50);
  const maxRounds = ref(300);

  // 计算属性
  const hasData = computed(() => stats.value.totalRounds > 0);

  // 获取动能预测统计数据
  const fetchMomentumPredictionStats = async (roundsCount?: number) => {
    loading.value = true;
    error.value = null;

    try {
      const count = roundsCount || recentRoundsCount.value;
      const response = await api.get('/game/momentum-prediction-stats', {
        params: { recent_rounds: count }
      });

      if (response.data.success) {
        const data = response.data.data;

        stats.value = {
          momentumAccuracy: data.momentum_accuracy || 0,
          totalRounds: data.total_rounds || 0,
          averageMomentumScore: data.average_momentum_score || 50,
          averageConfidence: data.average_confidence || 50,
          allStats: {
            rank1: data.all_stats?.rank1 || { total: 0, breakevenRate: 0, firstPlaceRate: 0 },
            rank2: data.all_stats?.rank2 || { total: 0, breakevenRate: 0, firstPlaceRate: 0 },
            rank3: data.all_stats?.rank3 || { total: 0, breakevenRate: 0, firstPlaceRate: 0 }
          },
          recentStats: {
            rank1: data.recent_stats?.rank1 || { total: 0, breakevenRate: 0, firstPlaceRate: 0 },
            rank2: data.recent_stats?.rank2 || { total: 0, breakevenRate: 0, firstPlaceRate: 0 },
            rank3: data.recent_stats?.rank3 || { total: 0, breakevenRate: 0, firstPlaceRate: 0 }
          }
        };

        maxRounds.value = data.max_rounds || 300;
        console.log('⚡ 动能预测统计数据获取成功:', data);
      } else {
        error.value = response.data.message || '获取动能预测统计数据失败';
        console.warn('⚠️ 动能预测统计数据获取失败:', response.data.message);
      }
    } catch (err: any) {
      error.value = err.message || '网络错误';
      console.error('❌ 获取动能预测统计数据出错:', err);
    } finally {
      loading.value = false;
    }
  };

  // 更新最近局数
  const updateRecentRoundsCount = async (count: number) => {
    recentRoundsCount.value = count;
    await fetchMomentumPredictionStats(count);
  };

  // 刷新统计数据
  const refreshStats = async () => {
    await fetchMomentumPredictionStats();
  };

  // 初始化
  const initialize = async () => {
    await fetchMomentumPredictionStats();
  };

  return {
    // 状态
    stats: readonly(stats),
    loading: readonly(loading),
    error: readonly(error),
    recentRoundsCount: readonly(recentRoundsCount),
    maxRounds: readonly(maxRounds),

    // 计算属性
    hasData,

    // 方法
    fetchMomentumPredictionStats,
    updateRecentRoundsCount,
    refreshStats,
    initialize
  };
};
