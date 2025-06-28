<template>
  <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="名次預測" size="large">
    <template #header-extra>
      <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-x-3 sm:space-y-0">
        <div v-if="analysisMeta" class="flex flex-wrap items-center gap-1 text-xs text-gray-300 sm:gap-2 sm:text-sm">
          <span class="font-medium">轮次:</span>
          <span class="text-red">{{ analysisMeta.round_id }}</span>
          <span class="font-medium">状态:</span>
          <NTag :type="getStatusTagType(analysisMeta.status)" size="small">
            {{ analysisMeta.status }}
          </NTag>
        </div>
        <n-button
          :loading="loading"
          @click="$emit('refresh')"
          type="primary"
          size="small"
          class="self-end sm:self-auto"
        >
          🔄 刷新分析
        </n-button>
      </div>
    </template>

    <div v-if="analysisData.length > 0" class="space-y-4">
      <!-- 横向名次預測展示 -->
      <div class="grid grid-cols-1 gap-3 lg:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5">
        <div
          v-for="(token, index) in analysisData"
          :key="`unified-${index}-${token.symbol}-${token.name}`"
          class="relative overflow-hidden border rounded-lg p-3 transition-all duration-300 hover:shadow-lg"
          :class="getUnifiedCardClass(index)"
        >
          <!-- 简洁头部 -->
          <div class="mb-2 flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <div class="text-lg">{{ getPredictionIcon(index) }}</div>
              <div class="text-sm text-white font-bold">{{ token.symbol }}</div>
            </div>
            <div class="text-xs text-gray-400">#{{ index + 1 }}</div>
          </div>

          <!-- 核心评分 -->
          <div class="mb-3 text-center">
            <div class="text-xs text-gray-400">最终评分</div>
            <div class="text-lg font-bold" :class="getScoreTextClass(index)">
              {{
                (token.risk_adjusted_score || token.final_prediction_score || token.prediction_score || 0).toFixed(1)
              }}
            </div>
            <div v-if="token.rank_confidence" class="text-xs text-gray-400">
              置信度 {{ (token.rank_confidence || 0).toFixed(0) }}%
            </div>
          </div>

          <!-- 所有数据参数 -->
          <div class="text-xs space-y-1">
            <div class="flex justify-between">
              <span class="text-gray-400">绝对分数:</span>
              <span class="text-purple-400 font-bold">{{ (token.absolute_score || 0).toFixed(1) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">H2H分数:</span>
              <span class="text-orange-400 font-bold">
                {{ (token.relative_score || token.h2h_score || 0).toFixed(1) }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">保本率:</span>
              <span class="text-green-400 font-bold">{{ (token.top3_rate || 0).toFixed(1) }}%</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">稳定性:</span>
              <span class="text-yellow-400 font-bold">
                <span v-if="token.value_stddev !== undefined">{{ (token.value_stddev || 0).toFixed(3) }}</span>
                <span v-else class="text-gray-500">-</span>
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">市场动量:</span>
              <span class="text-teal-400 font-bold">
                <span v-if="token.market_momentum_score">
                  {{ (token.market_momentum_score || 0).toFixed(1) }}
                </span>
                <span v-else class="text-gray-500">-</span>
              </span>
            </div>

            <!-- 价格变化数据 -->
            <div class="mt-2 border-t border-gray-600/30 pt-1">
              <div class="flex justify-between">
                <span class="text-gray-400">5分钟:</span>
                <span class="font-bold" :class="formatPriceChange(token.change_5m).color">
                  {{ formatPriceChange(token.change_5m).text }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">1小时:</span>
                <span class="font-bold" :class="formatPriceChange(token.change_1h).color">
                  {{ formatPriceChange(token.change_1h).text }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">4小时:</span>
                <span class="font-bold" :class="formatPriceChange(token.change_4h).color">
                  {{ formatPriceChange(token.change_4h).text }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">24小时:</span>
                <span class="font-bold" :class="formatPriceChange(token.change_24h).color">
                  {{ formatPriceChange(token.change_24h).text }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div v-else-if="loading" class="py-8 text-center">
      <NSpin size="large" />
      <div class="mt-4 text-gray-400">正在加载预测数据...</div>
    </div>
    <NEmpty v-else description="暂无 H2H 对战分析数据" class="py-8" />
  </NCard>
</template>

<script setup lang="ts">
  import { NEmpty, NSpin } from 'naive-ui';
  import type { TokenAnalysis, AnalysisMeta } from '@/stores/gamePrediction';
  import { usePredictionDisplay } from '@/composables/usePredictionDisplay';

  // Props
  interface Props {
    analysisData: TokenAnalysis[];
    analysisMeta: AnalysisMeta | null;
    loading?: boolean;
  }

  defineProps<Props>();

  // Emits
  defineEmits<{
    refresh: [];
  }>();

  // 使用预测显示工具
  const { formatPriceChange, getUnifiedCardClass, getScoreTextClass, getPredictionIcon, getStatusTagType } =
    usePredictionDisplay();
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
