<template>
  <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" :title="title" size="large">
    <template #header-extra>
      <div class="flex items-center space-x-3">
        <n-button :loading="loading" @click="$emit('refresh')" type="primary" size="small">🔄 刷新统计</n-button>
      </div>
    </template>

    <NSpin :show="loading">
      <div v-if="hasData" class="space-y-4">
        <!-- 局数选择器 -->
        <div
          v-if="showRecentSelector"
          class="mb-3 border border-white/20 rounded-lg from-gray-500/10 to-slate-600/5 bg-gradient-to-br px-3 py-2"
        >
          <div class="mb-1 flex items-center justify-between">
            <div class="py-1 text-sm text-white font-medium">📊 最新N局分析设置</div>
            <div class="text-xs text-gray-300">
              当前:
              <span class="text-cyan-400 font-bold">{{ recentRoundsCount }}</span>
              局
            </div>
          </div>
          <div class="flex items-center space-x-3">
            <span class="whitespace-nowrap text-xs text-gray-300 font-medium">局数:</span>
            <div class="min-w-0 flex-1">
              <n-slider
                v-model:value="internalRecentRounds"
                :min="1"
                :max="Math.min(1000, maxRounds)"
                :step="1"
                :tooltip="true"
                @change="onSliderChange"
              />
            </div>
            <div class="whitespace-nowrap text-xs text-gray-400">1-{{ Math.min(1000, maxRounds) }}局</div>
          </div>
        </div>

        <!-- 预测准确度总结 -->
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-4 md:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5 sm:gap-4">
          <!-- 精准预测率 -->
          <div :class="getCombinedCardClass(getAccuracyCardClass())">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🎯</div>
            <div class="relative">
              <div class="text-xs font-medium sm:text-sm" :class="getAccuracyCardClass().textColor">精准预测率</div>
              <div class="mt-2 text-2xl font-bold sm:text-3xl" :class="getAccuracyCardClass().valueColor">
                {{ (exactRate || 0).toFixed(1) }}
                <span class="text-base sm:text-lg">%</span>
              </div>
              <div class="mt-2 text-xs" :class="getAccuracyCardClass().textColor + '/70'">预测与实际完全相同</div>
              <div class="mt-1 text-[11px] text-white/60">
                基线 20.0%，提升 {{ formatLift(exactRate, BASELINES.exact) }}
              </div>
            </div>
          </div>

          <!-- 预测总局数 -->
          <div :class="getCombinedCardClass(getTotalRoundsCardClass())">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">📊</div>
            <div class="relative">
              <div class="text-xs font-medium sm:text-sm" :class="getTotalRoundsCardClass().textColor">预测总局数</div>
              <div class="mt-2 text-2xl font-bold sm:text-3xl" :class="getTotalRoundsCardClass().valueColor">
                {{ totalRounds }}
              </div>
              <div class="mt-2 text-xs" :class="getTotalRoundsCardClass().textColor + '/70'">模型运行总局数</div>
            </div>
          </div>

          <!-- 预测第一名 -->
          <div :class="getCombinedCardClass(getRankStatsCardClass(1))">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">{{ getPredictionIcon(1) }}</div>
            <div class="relative">
              <div class="text-xs font-medium sm:text-sm" :class="getRankStatsCardClass(1).textColor">预测第一名</div>
              <div class="mt-2 space-y-1">
                <!-- 全部历史数据 -->
                <div class="border-b border-yellow-400/20 pb-2">
                  <div class="mb-1 text-xs text-yellow-200/50">最新{{ allStats?.rank1?.total || 0 }}局</div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-yellow-400 font-bold sm:text-lg">
                      {{ (allStats.rank1.breakevenRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-yellow-200/70">保本率</span>
                  </div>
                  <div class="mt-1 flex items-center justify-between text-[11px] text-yellow-200/70">
                    <span>
                      基线 {{ BASELINES.breakeven.toFixed(1) }}%，提升
                      {{ formatLift(allStats.rank1.breakevenRate, BASELINES.breakeven) }}
                    </span>
                    <span v-if="allStats.rank1.total > 0">
                      CI95 {{ formatCI(ci95(allStats.rank1.breakeven, allStats.rank1.total)) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-yellow-300 font-bold sm:text-lg">
                      {{ (allStats.rank1.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-yellow-200/70">第一名率</span>
                  </div>
                  <div class="mt-1 text-[11px] text-yellow-200/70">
                    基线 {{ BASELINES.first.toFixed(1) }}%，提升
                    {{ formatLift(allStats.rank1.firstPlaceRate, BASELINES.first) }}
                  </div>
                </div>
                <!-- 最新N局数据 -->
                <div v-if="showRecentStats" class="pt-1">
                  <div class="mb-1 text-xs text-cyan-300/70">最新{{ recentRoundsCount }}局</div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-cyan-400 font-bold">
                      {{ (recentStats.rank1.breakevenRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-cyan-200/70">保本率</span>
                  </div>
                  <div class="mt-1 flex items-center justify-between text-[11px] text-cyan-200/70">
                    <span>
                      基线 {{ BASELINES.breakeven.toFixed(1) }}%，提升
                      {{ formatLift(recentStats.rank1.breakevenRate, BASELINES.breakeven) }}
                    </span>
                    <span v-if="recentStats.rank1.total > 0">
                      CI95 {{ formatCI(ci95(recentStats.rank1.breakeven, recentStats.rank1.total)) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-teal-300 font-bold">
                      {{ (recentStats.rank1.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-teal-200/70">第一名率</span>
                  </div>
                  <div class="mt-1 text-[11px] text-cyan-200/70">
                    基线 {{ BASELINES.first.toFixed(1) }}%，提升
                    {{ formatLift(recentStats.rank1.firstPlaceRate, BASELINES.first) }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 预测第二名 -->
          <div :class="getCombinedCardClass(getRankStatsCardClass(2))">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">{{ getPredictionIcon(2) }}</div>
            <div class="relative">
              <div class="text-xs font-medium sm:text-sm" :class="getRankStatsCardClass(2).textColor">预测第二名</div>
              <div class="mt-2 space-y-1">
                <!-- 全部历史数据 -->
                <div class="border-b border-slate-400/20 pb-2">
                  <div class="mb-1 text-xs text-slate-200/50">最新{{ allStats?.rank2?.total || 0 }}局</div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-slate-400 font-bold sm:text-lg">
                      {{ (allStats.rank2.breakevenRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-slate-200/70">保本率</span>
                  </div>
                  <div class="mt-1 flex items-center justify-between text-[11px] text-slate-200/70">
                    <span>
                      基线 {{ BASELINES.breakeven.toFixed(1) }}%，提升
                      {{ formatLift(allStats.rank2.breakevenRate, BASELINES.breakeven) }}
                    </span>
                    <span v-if="allStats.rank2.total > 0">
                      CI95 {{ formatCI(ci95(allStats.rank2.breakeven, allStats.rank2.total)) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-slate-300 font-bold sm:text-lg">
                      {{ (allStats.rank2.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-slate-200/70">第一名率</span>
                  </div>
                  <div class="mt-1 text-[11px] text-slate-200/70">
                    基线 {{ BASELINES.first.toFixed(1) }}%，提升
                    {{ formatLift(allStats.rank2.firstPlaceRate, BASELINES.first) }}
                  </div>
                </div>
                <!-- 最新N局数据 -->
                <div v-if="showRecentStats" class="pt-1">
                  <div class="mb-1 text-xs text-cyan-300/70">最新{{ recentRoundsCount }}局</div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-cyan-400 font-bold">
                      {{ (recentStats.rank2.breakevenRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-cyan-200/70">保本率</span>
                  </div>
                  <div class="mt-1 flex items-center justify-between text-[11px] text-cyan-200/70">
                    <span>
                      基线 {{ BASELINES.breakeven.toFixed(1) }}%，提升
                      {{ formatLift(recentStats.rank2.breakevenRate, BASELINES.breakeven) }}
                    </span>
                    <span v-if="recentStats.rank2.total > 0">
                      CI95 {{ formatCI(ci95(recentStats.rank2.breakeven, recentStats.rank2.total)) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-teal-300 font-bold">
                      {{ (recentStats.rank2.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-teal-200/70">第一名率</span>
                  </div>
                  <div class="mt-1 text-[11px] text-cyan-200/70">
                    基线 {{ BASELINES.first.toFixed(1) }}%，提升
                    {{ formatLift(recentStats.rank2.firstPlaceRate, BASELINES.first) }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 预测第三名 -->
          <div :class="getCombinedCardClass(getRankStatsCardClass(3))">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">{{ getPredictionIcon(3) }}</div>
            <div class="relative">
              <div class="text-xs font-medium sm:text-sm" :class="getRankStatsCardClass(3).textColor">预测第三名</div>
              <div class="mt-2 space-y-1">
                <!-- 全部历史数据 -->
                <div class="border-b border-orange-400/20 pb-2">
                  <div class="mb-1 text-xs text-orange-200/50">最新{{ allStats?.rank3?.total || 0 }}局</div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-orange-400 font-bold sm:text-lg">
                      {{ (allStats.rank3.breakevenRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-orange-200/70">保本率</span>
                  </div>
                  <div class="mt-1 flex items-center justify-between text-[11px] text-orange-200/70">
                    <span>
                      基线 {{ BASELINES.breakeven.toFixed(1) }}%，提升
                      {{ formatLift(allStats.rank3.breakevenRate, BASELINES.breakeven) }}
                    </span>
                    <span v-if="allStats.rank3.total > 0">
                      CI95 {{ formatCI(ci95(allStats.rank3.breakeven, allStats.rank3.total)) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-orange-300 font-bold sm:text-lg">
                      {{ (allStats.rank3.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-orange-200/70">第一名率</span>
                  </div>
                  <div class="mt-1 text-[11px] text-orange-200/70">
                    基线 {{ BASELINES.first.toFixed(1) }}%，提升
                    {{ formatLift(allStats.rank3.firstPlaceRate, BASELINES.first) }}
                  </div>
                </div>
                <!-- 最新N局数据 -->
                <div v-if="showRecentStats" class="pt-1">
                  <div class="mb-1 text-xs text-cyan-300/70">最新{{ recentRoundsCount }}局</div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-cyan-400 font-bold">
                      {{ (recentStats.rank3.breakevenRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-cyan-200/70">保本率</span>
                  </div>
                  <div class="mt-1 flex items-center justify-between text-[11px] text-cyan-200/70">
                    <span>
                      基线 {{ BASELINES.breakeven.toFixed(1) }}%，提升
                      {{ formatLift(recentStats.rank3.breakevenRate, BASELINES.breakeven) }}
                    </span>
                    <span v-if="recentStats.rank3.total > 0">
                      CI95 {{ formatCI(ci95(recentStats.rank3.breakeven, recentStats.rank3.total)) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-base text-teal-300 font-bold">
                      {{ (recentStats.rank3.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-teal-200/70">第一名率</span>
                  </div>
                  <div class="mt-1 text-[11px] text-cyan-200/70">
                    基线 {{ BASELINES.first.toFixed(1) }}%，提升
                    {{ formatLift(recentStats.rank3.firstPlaceRate, BASELINES.first) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <NEmpty v-else description="暂无特征预测统计数据" class="py-8" />
    </NSpin>
  </NCard>
</template>

<script setup lang="ts">
  import { computed, ref, watch } from 'vue';
  import { NEmpty, NSpin } from 'naive-ui';
  import type { AllRankStats } from '@/composables/useFeaturePredictionStats';
  import { usePredictionDisplay } from '@/composables/usePredictionDisplay';

  interface Props {
    title?: string;
    exactRate: number;
    totalRounds: number;
    allStats: AllRankStats;
    recentStats: AllRankStats;
    recentRoundsCount: number;
    maxRounds: number;
    loading?: boolean;
    showRecentSelector?: boolean;
    showRecentStats?: boolean;
  }

  const props = withDefaults(defineProps<Props>(), {
    title: '🧬 特征预测分析',
    loading: false,
    showRecentSelector: true,
    showRecentStats: true
  });

  defineEmits<{ refresh: []; 'update:recent-rounds-count': [value: number] }>();

  const {
    getCombinedCardClass,
    getRankStatsCardClass,
    getAccuracyCardClass,
    getTotalRoundsCardClass,
    getPredictionIcon
  } = usePredictionDisplay();
  const hasData = computed(() => props.totalRounds > 0);

  // 防抖：滑块本地值，避免每步触发父级重算
  const internalRecentRounds = ref(props.recentRoundsCount);
  watch(
    () => props.recentRoundsCount,
    (v) => {
      if (typeof v === 'number' && v !== internalRecentRounds.value) internalRecentRounds.value = v;
    }
  );
  let debounceTimer: number | null = null;
  watch(
    internalRecentRounds,
    (v) => {
      if (debounceTimer) window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(() => {
        // 通过自定义事件派发给父组件（kebab-case）
        // eslint-disable-next-line vue/custom-event-name-casing
        // @ts-ignore - 运行时emit
        // 这里使用原生$emit API（<script setup> 下用 defineEmits）
        emitUpdateRecent(v);
      }, 200);
    },
    { immediate: false }
  );

  const emit = defineEmits<{ refresh: []; 'update:recent-rounds-count': [value: number] }>();
  function emitUpdateRecent(v: number) {
    emit('update:recent-rounds-count', v);
  }

  // 当用户松手或 change 触发时，立即派发，减少等待感
  function onSliderChange(v: number) {
    if (debounceTimer) {
      window.clearTimeout(debounceTimer);
      debounceTimer = null;
    }
    emitUpdateRecent(v);
  }

  // 统计展示：基线与区间
  const BASELINES = {
    breakeven: 60.0, // Top3 随机基线
    first: 20.0, // 第一名随机基线
    exact: 20.0 // 精准预测率近似基线
  } as const;

  function clamp01(x: number) {
    return Math.min(1, Math.max(0, x));
  }

  function ci95(successes: number, total: number): { low: number; high: number } {
    if (!total) return { low: 0, high: 0 };
    const z = 1.96;
    const n = total;
    const phat = clamp01(successes / n);
    const denom = 1 + (z * z) / n;
    const center = (phat + (z * z) / (2 * n)) / denom;
    const half = (z * Math.sqrt((phat * (1 - phat)) / n + (z * z) / (4 * n * n))) / denom;
    return { low: clamp01(center - half) * 100, high: clamp01(center + half) * 100 };
  }

  function formatLift(valuePct: number | undefined, baselinePct: number): string {
    const v = (valuePct ?? 0) - baselinePct;
    const sign = v >= 0 ? '+' : '';
    return `${sign}${v.toFixed(1)} pp`;
  }

  function formatCI(ci: { low: number; high: number }): string {
    return `[${ci.low.toFixed(1)}%, ${ci.high.toFixed(1)}%]`;
  }
</script>

<style scoped>
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
