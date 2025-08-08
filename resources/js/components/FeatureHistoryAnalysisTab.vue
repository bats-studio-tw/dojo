<template>
  <div class="space-y-6">
    <FeaturePredictionStats
      class="mb-6"
      title="🧬 特征预测分析"
      :exact-rate="exactRate"
      :total-rounds="totalRounds"
      :all-stats="allStats"
      :recent-stats="recentStats"
      :recent-rounds-count="recentRoundsCount"
      @update:recent-rounds-count="(v: number) => $emit('update:recent-rounds-count', v)"
      :max-rounds="maxRounds"
      :loading="historyLoading"
      @refresh="$emit('refreshFeatureHistory')"
    />

    <!-- 投注表现分析（与历史页一致） -->
    <BettingPerformanceAnalysis :uid="getCurrentUID()" />
  </div>
</template>

<script setup lang="ts">
  import FeaturePredictionStats from './FeaturePredictionStats.vue';
  import BettingPerformanceAnalysis from './BettingPerformanceAnalysis.vue';
  import type { AllRankStats } from '@/composables/useFeaturePredictionStats';

  interface Props {
    exactRate: number;
    totalRounds: number;
    allStats: AllRankStats;
    recentStats: AllRankStats;
    recentRoundsCount: number;
    maxRounds: number;
    historyLoading: boolean;
  }

  withDefaults(defineProps<Props>(), {});

  defineEmits<{
    refreshFeatureHistory: [];
    'update:recent-rounds-count': [value: number];
  }>();

  const getCurrentUID = () => {
    return localStorage.getItem('currentUID') || '';
  };
</script>
