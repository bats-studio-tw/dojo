import { computed, ref, type Ref } from 'vue';

export interface FeaturePredictionItem {
  symbol: string;
  predicted_rank: number; // 1|2|3...
}

export interface FeatureResultItem {
  symbol: string;
  actual_rank: number; // 1|2|3...
}

export interface FeatureHistoryRound {
  round_id: string | number;
  feature: string;
  predictions: FeaturePredictionItem[]; // 针对该特征得到的名次
  results: FeatureResultItem[]; // 本轮实际结果
  settled_at?: string | null;
}

export interface RankStats {
  total: number;
  breakeven: number; // 精准或更好视为保本
  loss: number; // 更差视为亏损
  firstPlace: number; // 实际第一名
  breakevenRate: number;
  lossRate: number;
  firstPlaceRate: number;
}

export interface AllRankStats {
  rank1: RankStats;
  rank2: RankStats;
  rank3: RankStats;
}

function emptyStats(): AllRankStats {
  return {
    rank1: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
    rank2: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
    rank3: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 }
  };
}

export function useFeaturePredictionStats(
  history: Ref<FeatureHistoryRound[]>,
  recentRoundsCount: Ref<number> = ref(50)
) {
  const totalRounds = computed(() => history.value.length);

  const calculateRankBasedStats = computed((): AllRankStats => {
    const stats = emptyStats();
    if (history.value.length === 0) return stats;

    history.value.forEach((round) => {
      [1, 2, 3].forEach((rank) => {
        const predictions = (round.predictions || []).filter((p) => p.predicted_rank === rank);
        predictions.forEach((p) => {
          const actual = (round.results || []).find((r) => r.symbol === p.symbol);
          if (!actual) return;
          const key = `rank${rank}` as keyof AllRankStats;
          stats[key].total++;
          // 精准或更好 => 保本
          if (actual.actual_rank <= p.predicted_rank) stats[key].breakeven++;
          else stats[key].loss++;
          if (actual.actual_rank === 1) stats[key].firstPlace++;
        });
      });
    });

    Object.values(stats).forEach((s) => {
      if (s.total > 0) {
        s.breakevenRate = (s.breakeven / s.total) * 100;
        s.lossRate = (s.loss / s.total) * 100;
        s.firstPlaceRate = (s.firstPlace / s.total) * 100;
      }
    });

    return stats;
  });

  const calculateRecentRankBasedStats = computed((): AllRankStats => {
    const stats = emptyStats();
    if (history.value.length === 0) return stats;

    const recent = history.value
      .slice()
      .sort((a, b) => String(b.round_id).localeCompare(String(a.round_id)))
      .slice(0, recentRoundsCount.value);

    recent.forEach((round) => {
      [1, 2, 3].forEach((rank) => {
        const predictions = (round.predictions || []).filter((p) => p.predicted_rank === rank);
        predictions.forEach((p) => {
          const actual = (round.results || []).find((r) => r.symbol === p.symbol);
          if (!actual) return;
          const key = `rank${rank}` as keyof AllRankStats;
          stats[key].total++;
          if (actual.actual_rank <= p.predicted_rank) stats[key].breakeven++;
          else stats[key].loss++;
          if (actual.actual_rank === 1) stats[key].firstPlace++;
        });
      });
    });

    Object.values(stats).forEach((s) => {
      if (s.total > 0) {
        s.breakevenRate = (s.breakeven / s.total) * 100;
        s.lossRate = (s.loss / s.total) * 100;
        s.firstPlaceRate = (s.firstPlace / s.total) * 100;
      }
    });

    return stats;
  });

  const exactRate = computed(() => {
    if (history.value.length === 0) return 0;
    let exact = 0;
    let total = 0;
    history.value.forEach((round) => {
      (round.predictions || [])
        .filter((p) => p.predicted_rank <= 3)
        .forEach((p) => {
          const actual = (round.results || []).find((r) => r.symbol === p.symbol);
          if (actual) {
            total++;
            if (actual.actual_rank === p.predicted_rank) exact++;
          }
        });
    });
    return total > 0 ? (exact / total) * 100 : 0;
  });

  return {
    totalRounds,
    exactRate,
    calculateRankBasedStats,
    calculateRecentRankBasedStats
  };
}
