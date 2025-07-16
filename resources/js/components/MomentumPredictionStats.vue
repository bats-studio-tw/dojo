<template>
  <NCard
    class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
    title="⚡ 动能预测统计分析"
    size="large"
  >
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
                :value="recentRoundsCount"
                @update:value="$emit('update:recentRoundsCount', $event)"
                :min="1"
                :max="Math.min(300, maxRounds)"
                :step="1"
                :tooltip="true"
              />
            </div>
            <div class="whitespace-nowrap text-xs text-gray-400">1-{{ Math.min(300, maxRounds) }}局</div>
          </div>
        </div>

        <!-- 动能预测准确度总结 -->
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-5 md:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5 sm:gap-4">
          <!-- 动能预测准确率 -->
          <div :class="getCombinedCardClass(getAccuracyCardClass())">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">⚡</div>
            <div class="relative">
              <div class="text-xs font-medium sm:text-sm" :class="getAccuracyCardClass().textColor">动能预测准确率</div>
              <div class="mt-2 text-2xl font-bold sm:text-3xl" :class="getAccuracyCardClass().valueColor">
                {{ (momentumAccuracy || 0).toFixed(1) }}
                <span class="text-base sm:text-lg">%</span>
              </div>
              <div class="mt-2 text-xs" :class="getAccuracyCardClass().textColor + '/70'">预测与实际完全相同</div>
            </div>
          </div>

          <!-- 动能预测总局数 -->
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

          <!-- 动能预测第一名 -->
          <div :class="getCombinedCardClass(getRankStatsCardClass(1))">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🥇</div>
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
                  <div class="flex items-center justify-between">
                    <span class="text-base text-amber-300 font-bold sm:text-lg">
                      {{ (allStats.rank1.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-amber-200/70">第一名率</span>
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
                  <div class="flex items-center justify-between">
                    <span class="text-base text-teal-300 font-bold">
                      {{ (recentStats.rank1.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-teal-200/70">第一名率</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 动能预测第二名 -->
          <div :class="getCombinedCardClass(getRankStatsCardClass(2))">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🥈</div>
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
                  <div class="flex items-center justify-between">
                    <span class="text-base text-gray-300 font-bold sm:text-lg">
                      {{ (allStats.rank2.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-gray-200/70">第一名率</span>
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
                  <div class="flex items-center justify-between">
                    <span class="text-base text-teal-300 font-bold">
                      {{ (recentStats.rank2.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-teal-200/70">第一名率</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 动能预测第三名 -->
          <div :class="getCombinedCardClass(getRankStatsCardClass(3))">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🥉</div>
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
                  <div class="flex items-center justify-between">
                    <span class="text-base text-red-300 font-bold sm:text-lg">
                      {{ (allStats.rank3.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-red-200/70">第一名率</span>
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
                  <div class="flex items-center justify-between">
                    <span class="text-base text-teal-300 font-bold">
                      {{ (recentStats.rank3.firstPlaceRate || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-teal-200/70">第一名率</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <NEmpty v-else description="暂无动能预测统计数据" class="py-8" />
    </NSpin>
  </NCard>
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import { NEmpty, NSpin } from 'naive-ui';
  import type { AllMomentumRankStats } from '@/composables/useMomentumPredictionStats';
  import { usePredictionDisplay } from '@/composables/usePredictionDisplay';

  // Props
  interface Props {
    momentumAccuracy: number;
    totalRounds: number;
    allStats: AllMomentumRankStats;
    recentStats: AllMomentumRankStats;
    recentRoundsCount: number;
    maxRounds: number;
    loading?: boolean;
    showRecentSelector?: boolean;
    showRecentStats?: boolean;
  }

  const props = withDefaults(defineProps<Props>(), {
    loading: false,
    showRecentSelector: true,
    showRecentStats: true
  });

  // Emits
  defineEmits<{
    refresh: [];
    'update:recentRoundsCount': [value: number];
  }>();

  // 使用预测显示工具
  const { getCombinedCardClass, getRankStatsCardClass, getAccuracyCardClass, getTotalRoundsCardClass } =
    usePredictionDisplay();

  // 计算属性
  const hasData = computed(() => props.totalRounds > 0);
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
