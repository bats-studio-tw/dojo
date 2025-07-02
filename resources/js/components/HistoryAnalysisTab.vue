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

    <!-- 投注表现分析 -->
    <BettingPerformanceAnalysis :uid="getCurrentUID()" />
  </div>
</template>

<script setup lang="ts">
  import PredictionStats from './PredictionStats.vue';
  import BettingPerformanceAnalysis from './BettingPerformanceAnalysis.vue';

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
  }

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const props = defineProps<Props>();

  // Emits
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const emit = defineEmits<{
    refreshPredictionHistory: [];
    'update:recent-rounds-count': [value: number];
  }>();

  // 获取当前用户UID
  const getCurrentUID = () => {
    return localStorage.getItem('currentUID') || '';
  };
</script>
