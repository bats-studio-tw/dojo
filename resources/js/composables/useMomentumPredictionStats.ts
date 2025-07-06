import { computed, ref, Ref } from 'vue';
import { analyzePredictionResult } from '@/utils/statusUtils';

export interface MomentumPredictionAnalysis {
  status: 'exact' | 'breakeven' | 'loss';
  text: string;
  icon: string;
  color: string;
  bgColor: string;
}

export interface MomentumRankStats {
  total: number;
  breakeven: number;
  loss: number;
  firstPlace: number;
  breakevenRate: number;
  lossRate: number;
  firstPlaceRate: number;
}

export interface AllMomentumRankStats {
  rank1: MomentumRankStats;
  rank2: MomentumRankStats;
  rank3: MomentumRankStats;
}

export interface MomentumPredictionHistoryRound {
  round_id: string;
  settled_at?: string;
  predictions: Array<{
    symbol: string;
    predicted_rank: number;
    momentum_score: number;
    confidence: number;
  }>;
  results: Array<{
    symbol: string;
    actual_rank: number;
  }>;
}

export interface MomentumStats {
  momentumAccuracy: number;
  totalRounds: number;
  allStats: AllMomentumRankStats;
  recentStats: AllMomentumRankStats;
  averageMomentumScore: number;
  averageConfidence: number;
}

export function useMomentumPredictionStats(
  momentumPredictionHistory: Ref<MomentumPredictionHistoryRound[]>,
  recentRoundsCount: Ref<number> = ref(50)
) {
  // 获取单个代币的动能预测分析结果
  const getMomentumPredictionAnalysis = (predictedRank: number, actualRank: number): MomentumPredictionAnalysis => {
    const result = analyzePredictionResult(predictedRank, actualRank);
    return {
      status: result.label === '精准预测' ? 'exact' : result.label === '保本' ? 'breakeven' : 'loss',
      text: result.label,
      icon: result.icon,
      color: result.color,
      bgColor: result.bgColor
    };
  };

  // 计算动能预测准确率
  const calculateMomentumAccuracy = computed(() => {
    if (momentumPredictionHistory.value.length === 0) {
      return 0;
    }

    let exactPredictions = 0;
    let totalPredictions = 0;

    momentumPredictionHistory.value.forEach((round) => {
      const top3Predictions = round.predictions.filter((p) => p.predicted_rank <= 3);

      top3Predictions.forEach((prediction) => {
        const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
        if (actualResult) {
          totalPredictions++;
          const analysis = getMomentumPredictionAnalysis(prediction.predicted_rank, actualResult.actual_rank);

          if (analysis.status === 'exact') {
            exactPredictions++;
          }
        }
      });
    });

    return totalPredictions > 0 ? (exactPredictions / totalPredictions) * 100 : 0;
  });

  // 计算平均动能分数
  const calculateAverageMomentumScore = computed(() => {
    if (momentumPredictionHistory.value.length === 0) {
      return 0;
    }

    let totalScore = 0;
    let totalPredictions = 0;

    momentumPredictionHistory.value.forEach((round) => {
      const top3Predictions = round.predictions.filter((p) => p.predicted_rank <= 3);
      top3Predictions.forEach((prediction) => {
        totalScore += prediction.momentum_score;
        totalPredictions++;
      });
    });

    return totalPredictions > 0 ? totalScore / totalPredictions : 0;
  });

  // 计算平均置信度
  const calculateAverageConfidence = computed(() => {
    if (momentumPredictionHistory.value.length === 0) {
      return 0;
    }

    let totalConfidence = 0;
    let totalPredictions = 0;

    momentumPredictionHistory.value.forEach((round) => {
      const top3Predictions = round.predictions.filter((p) => p.predicted_rank <= 3);
      top3Predictions.forEach((prediction) => {
        totalConfidence += prediction.confidence;
        totalPredictions++;
      });
    });

    return totalPredictions > 0 ? totalConfidence / totalPredictions : 0;
  });

  // 按预测排名分别统计保本/亏本率和第一名率（全部历史）
  const calculateAllRankBasedStats = computed((): AllMomentumRankStats => {
    const rankStats: AllMomentumRankStats = {
      rank1: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank2: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank3: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 }
    };

    if (momentumPredictionHistory.value.length === 0) {
      return rankStats;
    }

    momentumPredictionHistory.value.forEach((round) => {
      [1, 2, 3].forEach((predictedRank) => {
        const predictions = round.predictions.filter((p) => p.predicted_rank === predictedRank);

        predictions.forEach((prediction) => {
          const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
          if (actualResult) {
            const key = `rank${predictedRank}` as keyof AllMomentumRankStats;
            rankStats[key].total++;

            const analysis = getMomentumPredictionAnalysis(prediction.predicted_rank, actualResult.actual_rank);

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
      const stats = rankStats[key as keyof AllMomentumRankStats];
      if (stats.total > 0) {
        stats.breakevenRate = (stats.breakeven / stats.total) * 100;
        stats.lossRate = (stats.loss / stats.total) * 100;
        stats.firstPlaceRate = (stats.firstPlace / stats.total) * 100;
      }
    });

    return rankStats;
  });

  // 按预测排名分别统计最新N局的保本/亏本率和第一名率
  const calculateRecentRankBasedStats = computed((): AllMomentumRankStats => {
    const rankStats: AllMomentumRankStats = {
      rank1: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank2: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank3: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 }
    };

    if (momentumPredictionHistory.value.length === 0) {
      return rankStats;
    }

    // 获取最新N局数据（按轮次ID倒序排列后取前N个）
    const recentRounds = momentumPredictionHistory.value
      .slice()
      .sort((a, b) => b.round_id.localeCompare(a.round_id))
      .slice(0, recentRoundsCount.value);

    recentRounds.forEach((round) => {
      [1, 2, 3].forEach((predictedRank) => {
        const predictions = round.predictions.filter((p) => p.predicted_rank === predictedRank);

        predictions.forEach((prediction) => {
          const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
          if (actualResult) {
            const key = `rank${predictedRank}` as keyof AllMomentumRankStats;
            rankStats[key].total++;

            const analysis = getMomentumPredictionAnalysis(prediction.predicted_rank, actualResult.actual_rank);

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
      const stats = rankStats[key as keyof AllMomentumRankStats];
      if (stats.total > 0) {
        stats.breakevenRate = (stats.breakeven / stats.total) * 100;
        stats.lossRate = (stats.loss / stats.total) * 100;
        stats.firstPlaceRate = (stats.firstPlace / stats.total) * 100;
      }
    });

    return rankStats;
  });

  // 计算总局数
  const calculateTotalRounds = computed(() => {
    return momentumPredictionHistory.value.length;
  });

  // 综合统计数据
  const stats = computed(
    (): MomentumStats => ({
      momentumAccuracy: calculateMomentumAccuracy.value,
      totalRounds: calculateTotalRounds.value,
      allStats: calculateAllRankBasedStats.value,
      recentStats: calculateRecentRankBasedStats.value,
      averageMomentumScore: calculateAverageMomentumScore.value,
      averageConfidence: calculateAverageConfidence.value
    })
  );

  return {
    // 分析函数
    getMomentumPredictionAnalysis,

    // 统计计算
    calculateMomentumAccuracy,
    calculateAverageMomentumScore,
    calculateAverageConfidence,
    calculateAllRankBasedStats,
    calculateRecentRankBasedStats,
    calculateTotalRounds,

    // 综合统计数据
    stats
  };
}
