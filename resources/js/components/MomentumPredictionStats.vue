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
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-4 md:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5 sm:gap-4">
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

          <!-- 平均动能分数 -->
          <div :class="getCombinedCardClass(getMomentumScoreCardClass())">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">📈</div>
            <div class="relative">
              <div class="text-xs font-medium sm:text-sm" :class="getMomentumScoreCardClass().textColor">
                平均动能分数
              </div>
              <div class="mt-2 text-2xl font-bold sm:text-3xl" :class="getMomentumScoreCardClass().valueColor">
                {{ (averageMomentumScore || 0).toFixed(1) }}
              </div>
              <div class="mt-2 text-xs" :class="getMomentumScoreCardClass().textColor + '/70'">动能模型评分</div>
            </div>
          </div>

          <!-- 平均置信度 -->
          <div :class="getCombinedCardClass(getConfidenceCardClass())">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🎯</div>
            <div class="relative">
              <div class="text-xs font-medium sm:text-sm" :class="getConfidenceCardClass().textColor">平均置信度</div>
              <div class="mt-2 text-2xl font-bold sm:text-3xl" :class="getConfidenceCardClass().valueColor">
                {{ (averageConfidence || 0).toFixed(1) }}
                <span class="text-base sm:text-lg">%</span>
              </div>
              <div class="mt-2 text-xs" :class="getConfidenceCardClass().textColor + '/70'">模型预测信心</div>
            </div>
          </div>

          <!-- 算法版本 -->
          <div :class="getCombinedCardClass(getAlgorithmCardClass())">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🤖</div>
            <div class="relative">
              <div class="text-xs font-medium sm:text-sm" :class="getAlgorithmCardClass().textColor">算法版本</div>
              <div class="mt-2 text-lg font-bold sm:text-xl" :class="getAlgorithmCardClass().valueColor">
                Hybrid-Edge v1.0
              </div>
              <div class="mt-2 text-xs" :class="getAlgorithmCardClass().textColor + '/70'">Elo + 动能混合算法</div>
            </div>
          </div>
        </div>

        <!-- 动能预测排名统计 -->
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-3 md:grid-cols-2 sm:grid-cols-1 xl:grid-cols-3 sm:gap-4">
          <!-- 动能预测第一名 -->
          <div :class="getCombinedCardClass(getRankStatsCardClass(1))">
            <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🥇</div>
            <div class="relative">
              <div class="text-xs font-medium sm:text-sm" :class="getRankStatsCardClass(1).textColor">预测第一名</div>
              <div class="mt-2 space-y-1">
                <!-- 全部历史数据 -->
                <div class="border-b border-yellow-400/20 pb-2">
                  <div class="mb-1 text-xs text-yellow-200/50">最新{{ allStats.rank1.total }}局</div>
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
                  <div class="mb-1 text-xs text-slate-200/50">最新{{ allStats.rank2.total }}局</div>
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
                  <div class="mb-1 text-xs text-orange-200/50">最新{{ allStats.rank3.total }}局</div>
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

        <!-- 动能预测算法详情 -->
        <div class="mt-6 p-4 border border-blue-400/20 rounded-lg bg-gradient-to-br from-blue-500/5 to-cyan-600/5">
          <div class="mb-3 text-sm text-blue-300 font-semibold">🔬 Hybrid-Edge v1.0 算法详情</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-gray-300">
            <div>
              <div class="mb-2 font-medium text-blue-200">算法组成</div>
              <div class="space-y-1">
                <div class="flex justify-between">
                  <span>Elo历史评分权重:</span>
                  <span class="text-blue-400 font-bold">65%</span>
                </div>
                <div class="flex justify-between">
                  <span>5秒动能变化权重:</span>
                  <span class="text-cyan-400 font-bold">35%</span>
                </div>
                <div class="flex justify-between">
                  <span>信心度计算:</span>
                  <span class="text-purple-400 font-bold">多因子综合</span>
                </div>
              </div>
            </div>
            <div>
              <div class="mb-2 font-medium text-blue-200">动能计算</div>
              <div class="space-y-1">
                <div class="flex justify-between">
                  <span>价格变化检测:</span>
                  <span class="text-green-400 font-bold">实时5秒</span>
                </div>
                <div class="flex justify-between">
                  <span>历史表现权重:</span>
                  <span class="text-yellow-400 font-bold">差异化评分</span>
                </div>
                <div class="flex justify-between">
                  <span>数据可靠性:</span>
                  <span class="text-orange-400 font-bold">自动降级</span>
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
    averageMomentumScore: number;
    averageConfidence: number;
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
  const {
    getCombinedCardClass,
    getRankStatsCardClass,
    getAccuracyCardClass,
    getTotalRoundsCardClass
  } = usePredictionDisplay();

  // 计算属性
  const hasData = computed(() => props.totalRounds > 0);

  // 动能分数卡片样式
  const getMomentumScoreCardClass = () => ({
    border: 'border-purple-500/30',
    background: 'from-purple-500/10 to-pink-600/5',
    hover: 'hover:border-purple-400/50 hover:shadow-purple-500/20',
    icon: '📈',
    textColor: 'text-purple-300',
    valueColor: 'text-purple-400'
  });

  // 置信度卡片样式
  const getConfidenceCardClass = () => ({
    border: 'border-blue-500/30',
    background: 'from-blue-500/10 to-cyan-600/5',
    hover: 'hover:border-blue-400/50 hover:shadow-blue-500/20',
    icon: '🎯',
    textColor: 'text-blue-300',
    valueColor: 'text-blue-400'
  });
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
