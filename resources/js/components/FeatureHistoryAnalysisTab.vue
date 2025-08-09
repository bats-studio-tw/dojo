<template>
  <div class="space-y-6">
    <!-- 动态渲染：针对每个特征各一张卡片 -->
    <template v-for="feature in features" :key="`feature-card-${feature}`">
      <FeaturePredictionStats
        class="mb-6"
        :title="`🧬 特征预测分析 · ${feature}`"
        :exact-rate="getFeatureStats(feature).exactRate"
        :total-rounds="getFeatureStats(feature).totalRounds"
        :all-stats="getFeatureStats(feature).allStats"
        :recent-stats="getFeatureStats(feature).recentStats"
        :recent-rounds-count="recentRoundsCount"
        @update:recent-rounds-count="(v: number) => $emit('update:recent-rounds-count', v)"
        :max-rounds="maxRounds"
        :loading="historyLoading"
        @refresh="$emit('refreshFeatureHistory')"
      />
    </template>

    <!-- 条件回测（基于历史数据与名次条件） -->
    <BacktestByConditions v-if="historyList && historyList.length" :history-list="historyList" />

    <!-- 投注表现分析（与历史页一致） -->
    <BettingPerformanceAnalysis :uid="getCurrentUID()" />
  </div>
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import FeaturePredictionStats from './FeaturePredictionStats.vue';
  import BettingPerformanceAnalysis from './BettingPerformanceAnalysis.vue';
  import BacktestByConditions from './BacktestByConditions.vue';
  import type { AllRankStats } from '@/composables/useFeaturePredictionStats';
  import type { FeatureHistoryRound } from '@/composables/useFeaturePredictionStats';

  interface Props {
    // 全量（已合并）的统计仍保留，但主要用于兜底或未来汇总展示
    exactRate: number;
    totalRounds: number;
    allStats: AllRankStats;
    recentStats: AllRankStats;
    recentRoundsCount: number;
    maxRounds: number;
    historyLoading: boolean;
    // 新增：各特征列表与各特征独立统计
    features?: string[];
    featureStatsMap?: Record<
      string,
      { exactRate: number; totalRounds: number; allStats: AllRankStats; recentStats: AllRankStats }
    >;
    // 新增：用于条件回测的原始历史列表
    historyList?: FeatureHistoryRound[];
  }

  const props = withDefaults(defineProps<Props>(), {
    features: () => [],
    featureStatsMap: () => ({}),
    historyList: () => []
  });

  defineEmits<{
    refreshFeatureHistory: [];
    'update:recent-rounds-count': [value: number];
  }>();

  const getCurrentUID = () => {
    return localStorage.getItem('currentUID') || '';
  };

  // 提供特征名数组（来自父组件）
  const features = computed(() => props.features || []);

  // 取某个特征的统计，若没有则回退至整体统计
  const getFeatureStats = (feature: string) => {
    const m = props.featureStatsMap || {};
    const s = m[feature];
    if (s) return s;
    return {
      exactRate: props.exactRate,
      totalRounds: props.totalRounds,
      allStats: props.allStats,
      recentStats: props.recentStats
    };
  };

  const historyList = computed(() => props.historyList || []);
</script>
