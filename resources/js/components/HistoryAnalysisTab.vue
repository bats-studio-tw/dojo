<template>
  <div class="space-y-6">
    <!-- 预测统计分析 -->
    <PredictionStats
      class="mb-6"
      :exact-rate="exactRate"
      :total-rounds="totalRounds"
      :all-stats="allStats"
      :recent-stats="recentStats"
      :recent-rounds-count="recentRoundsCount"
      @update:recent-rounds-count="$emit('update:recent-rounds-count', $event)"
      :max-rounds="maxRounds"
      :loading="historyLoading"
      @refresh="$emit('refreshPredictionHistory')"
    />

    <!-- 动能预测统计分析 -->
    <MomentumPredictionStats
      class="mb-6"
      :momentum-accuracy="momentumStats.momentumAccuracy"
      :total-rounds="momentumStats.totalRounds"
      :all-stats="momentumStats.allStats"
      :recent-stats="momentumStats.recentStats"
      :recent-rounds-count="momentumRecentRoundsCount"
      @update:recent-rounds-count="updateMomentumRecentRoundsCount"
      :max-rounds="momentumMaxRounds"
      :loading="momentumLoading"
      :average-momentum-score="momentumStats.averageMomentumScore"
      :average-confidence="momentumStats.averageConfidence"
      @refresh="$emit('refreshMomentumHistory')"
    />

    <!-- 投注表现分析 -->
    <BettingPerformanceAnalysis :uid="getCurrentUID()" />
  </div>
</template>

<script setup lang="ts">
  import { ref } from 'vue';
  import PredictionStats from './PredictionStats.vue';
  import MomentumPredictionStats from './MomentumPredictionStats.vue';
  import BettingPerformanceAnalysis from './BettingPerformanceAnalysis.vue';
  import type { MomentumStats } from '@/composables/useMomentumPredictionStats';

  // Props
  interface Props {
    exactRate: number;
    totalRounds: number;
    allStats: any;
    recentStats: any;
    recentRoundsCount: number;
    maxRounds: number;
    historyLoading: boolean;
    predictionComparisonData: any[];
    momentumStats: MomentumStats;
    momentumLoading?: boolean;
  }

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const props = withDefaults(defineProps<Props>(), {
    momentumLoading: false
  });

  // Emits
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const emit = defineEmits<{
    refreshPredictionHistory: [];
    refreshMomentumHistory: [];
    'update:recent-rounds-count': [value: number];
  }>();

  // 动能预测相关状态
  const momentumRecentRoundsCount = ref(50);
  const momentumMaxRounds = ref(300);

  // 更新动能预测最近局数
  const updateMomentumRecentRoundsCount = (count: number) => {
    momentumRecentRoundsCount.value = count;
  };

  // 获取当前用户UID
  const getCurrentUID = () => {
    return localStorage.getItem('currentUID') || '';
  };
</script>
