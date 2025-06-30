import { computed, ref, Ref } from 'vue';
import { analyzePredictionResult } from '@/utils/statusUtils';
import type { PredictionHistoryRound } from '@/stores/gamePrediction';

export interface PredictionAnalysis {
  status: 'exact' | 'breakeven' | 'loss';
  text: string;
  icon: string;
  color: string;
  bgColor: string;
}

export interface RankStats {
  total: number;
  breakeven: number;
  loss: number;
  firstPlace: number;
  breakevenRate: number;
  lossRate: number;
  firstPlaceRate: number;
}

export interface AllRankStats {
  rank1: RankStats;
  rank2: RankStats;
  rank3: RankStats;
}

export interface DetailedPredictionItem {
  round_id: string;
  symbol: string;
  predicted_rank: number;
  actual_rank: number;
  is_exact_match: boolean;
  is_better_than_expected: boolean;
  rank_difference: number;
  settled_at: string;
}

export function usePredictionStats(
  predictionHistory: Ref<PredictionHistoryRound[]>,
  recentRoundsCount: Ref<number> = ref(50)
) {
  // 获取单个代币的预测分析结果（使用统一的状态工具）
  const getTokenPredictionAnalysis = (predictedRank: number, actualRank: number): PredictionAnalysis => {
    const result = analyzePredictionResult(predictedRank, actualRank);
    return {
      status: result.label === '精准预测' ? 'exact' : result.label === '保本' ? 'breakeven' : 'loss',
      text: result.label,
      icon: result.icon,
      color: result.color,
      bgColor: result.bgColor
    };
  };

  // 计算基于单次预测的精准率
  const calculateRoundBasedStats = computed(() => {
    if (predictionHistory.value.length === 0) {
      return { exactRate: 0 };
    }

    let exactPredictions = 0;
    let totalPredictions = 0;

    predictionHistory.value.forEach((round) => {
      const top3Predictions = round.predictions.filter((p) => p.predicted_rank <= 3);

      top3Predictions.forEach((prediction) => {
        const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
        if (actualResult) {
          totalPredictions++;
          const analysis = getTokenPredictionAnalysis(prediction.predicted_rank, actualResult.actual_rank);

          if (analysis.status === 'exact') {
            exactPredictions++;
          }
        }
      });
    });

    return {
      exactRate: totalPredictions > 0 ? (exactPredictions / totalPredictions) * 100 : 0
    };
  });

  // 按预测排名分别统计保本/亏本率和第一名率
  const calculateRankBasedStats = computed((): AllRankStats => {
    const rankStats: AllRankStats = {
      rank1: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank2: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank3: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 }
    };

    if (predictionHistory.value.length === 0) {
      return rankStats;
    }

    predictionHistory.value.forEach((round) => {
      [1, 2, 3].forEach((predictedRank) => {
        const predictions = round.predictions.filter((p) => p.predicted_rank === predictedRank);

        predictions.forEach((prediction) => {
          const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
          if (actualResult) {
            const key = `rank${predictedRank}` as keyof AllRankStats;
            rankStats[key].total++;

            const analysis = getTokenPredictionAnalysis(prediction.predicted_rank, actualResult.actual_rank);

            if (analysis.status === 'exact' || analysis.status === 'breakeven') {
              rankStats[key].breakeven++;
            } else if (analysis.status === 'loss') {
              rankStats[key].loss++;
            }

            // 计算第一名率：实际排名是第一名的情况
            if (actualResult.actual_rank === 1) {
              rankStats[key].firstPlace++;
            }
          }
        });
      });
    });

    // 计算百分比
    Object.keys(rankStats).forEach((key) => {
      const stats = rankStats[key as keyof AllRankStats];
      if (stats.total > 0) {
        stats.breakevenRate = (stats.breakeven / stats.total) * 100;
        stats.lossRate = (stats.loss / stats.total) * 100;
        stats.firstPlaceRate = (stats.firstPlace / stats.total) * 100;
      }
    });

    return rankStats;
  });

  // 按预测排名分别统计最新N局的保本/亏本率和第一名率
  const calculateRecentRankBasedStats = computed((): AllRankStats => {
    const rankStats: AllRankStats = {
      rank1: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank2: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank3: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 }
    };

    if (predictionHistory.value.length === 0) {
      return rankStats;
    }

    // 获取最新N局数据（按轮次ID倒序排列后取前N个）
    const recentRounds = predictionHistory.value
      .slice()
      .sort((a, b) => b.round_id.localeCompare(a.round_id))
      .slice(0, recentRoundsCount.value);

    recentRounds.forEach((round) => {
      [1, 2, 3].forEach((predictedRank) => {
        const predictions = round.predictions.filter((p) => p.predicted_rank === predictedRank);

        predictions.forEach((prediction) => {
          const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
          if (actualResult) {
            const key = `rank${predictedRank}` as keyof AllRankStats;
            rankStats[key].total++;

            const analysis = getTokenPredictionAnalysis(prediction.predicted_rank, actualResult.actual_rank);

            if (analysis.status === 'exact' || analysis.status === 'breakeven') {
              rankStats[key].breakeven++;
            } else if (analysis.status === 'loss') {
              rankStats[key].loss++;
            }

            // 计算第一名率：实际排名是第一名的情况
            if (actualResult.actual_rank === 1) {
              rankStats[key].firstPlace++;
            }
          }
        });
      });
    });

    // 计算百分比
    Object.keys(rankStats).forEach((key) => {
      const stats = rankStats[key as keyof AllRankStats];
      if (stats.total > 0) {
        stats.breakevenRate = (stats.breakeven / stats.total) * 100;
        stats.lossRate = (stats.loss / stats.total) * 100;
        stats.firstPlaceRate = (stats.firstPlace / stats.total) * 100;
      }
    });

    return rankStats;
  });

  // 获取预测总局数统计
  const calculatePortfolioStats = computed(() => {
    return {
      totalRounds: predictionHistory.value.length
    };
  });

  // 获取前三名预测对比数据
  const getPredictionComparisonData = computed((): DetailedPredictionItem[] => {
    const detailedData: DetailedPredictionItem[] = [];

    predictionHistory.value.forEach((round) => {
      // 只处理预测前三名的数据
      const top3Predictions = round.predictions.filter((p) => p.predicted_rank <= 3);

      top3Predictions.forEach((prediction) => {
        const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
        if (actualResult) {
          const rankDifference = Math.abs(prediction.predicted_rank - actualResult.actual_rank);

          detailedData.push({
            round_id: round.round_id,
            symbol: prediction.symbol,
            predicted_rank: prediction.predicted_rank,
            actual_rank: actualResult.actual_rank,
            is_exact_match: rankDifference === 0,
            is_better_than_expected: actualResult.actual_rank < prediction.predicted_rank,
            rank_difference: rankDifference,
            settled_at: round.settled_at || '-'
          });
        }
      });
    });

    // 按轮次倒序排列，最新的在前面
    return detailedData.sort((a, b) => b.round_id.localeCompare(a.round_id));
  });

  return {
    // 分析函数
    getTokenPredictionAnalysis,

    // 统计计算
    calculateRoundBasedStats,
    calculateRankBasedStats,
    calculateRecentRankBasedStats,
    calculatePortfolioStats,

    // 预测对比数据
    getPredictionComparisonData
  };
}
