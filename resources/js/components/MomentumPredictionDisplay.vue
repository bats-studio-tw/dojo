<template>
  <NCard
    v-if="hybridPredictions && hybridPredictions.length > 0"
    class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
    :title="title"
    size="large"
  >
    <template #header-extra>
      <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-x-3 sm:space-y-0">
        <div v-if="analysisMeta" class="flex flex-wrap items-center gap-1 text-xs text-gray-300 sm:gap-2 sm:text-sm">
          <span class="font-medium">轮次:</span>
          <span class="text-cyan-400">{{ analysisMeta.round_id || currentRoundId }}</span>
          <span class="font-medium">状态:</span>
          <NTag :type="getStatusTagType(currentGameStatus)" size="small">
            {{ getStatusText(currentGameStatus) }}
          </NTag>
        </div>
        <n-button :loading="analysisLoading" @click="$emit('refresh-analysis')" type="primary" size="small">
          🔄 刷新分析
        </n-button>
      </div>
    </template>

    <!-- 横向动能预测排名展示 -->
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5">
      <div
        v-for="(token, index) in sortedPredictionsByRank"
        :key="`momentum-${index}-${token.symbol}`"
        class="relative overflow-hidden border rounded-lg p-3 transition-all duration-300 hover:shadow-lg"
        :class="getUnifiedCardClass(index)"
      >
        <!-- 动能预测排名头部 -->
        <div class="mb-2 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <div class="text-lg">{{ getPredictionIcon(index + 1) }}</div>
            <div class="text-sm text-white font-bold">{{ token.symbol }}</div>
          </div>
          <div class="text-xs text-gray-400">#{{ token.predicted_rank }}</div>
        </div>

        <!-- 核心动能分数 -->
        <div class="mb-3 text-center">
          <div class="text-xs text-gray-400">动能分数</div>
          <div class="text-lg font-bold" :class="getScoreTextClass(index)">
            {{ (token.mom_score || 50).toFixed(1) }}
          </div>
          <div v-if="token.confidence" class="text-xs text-gray-400">
            置信度 {{ (token.confidence || 0).toFixed(0) }}%
          </div>
        </div>

        <!-- 详细动能数据参数 -->
        <div class="text-xs space-y-1">
          <div class="flex justify-between">
            <span class="text-gray-400">Elo概率:</span>
            <span class="text-blue-400 font-bold">
              {{ token.elo_prob ? (token.elo_prob || 0).toFixed(1) + '%' : '-' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">混合分数:</span>
            <span class="text-orange-400 font-bold">
              {{ token.final_score ? (token.final_score || 0).toFixed(1) : '-' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">动能状态:</span>
            <span class="text-green-400 font-bold">
              {{ getMomentumStatusText(token.mom_score) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </NCard>
  <NEmpty v-else description="暂无动能预测数据" class="py-8" />
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import { NCard, NTag, NEmpty } from 'naive-ui';
  import { usePredictionDisplay } from '@/composables/usePredictionDisplay';

  // Props
  interface Props {
    hybridPredictions: any[];
    analysisMeta: any;
    currentRoundId: string | null;
    currentGameStatus: string;
    currentGameTokensWithRanks: any[];
    analysisLoading?: boolean;
    title?: string;
  }

  const props = withDefaults(defineProps<Props>(), {
    analysisLoading: false,
    title: '⚡ 动能预测分析'
  });

  // Emits
  defineEmits<{
    'refresh-analysis': [];
  }>();

  // ==================== 计算属性 ====================

  // 预测Token按排名排序
  const sortedPredictionsByRank = computed(() => {
    return [...props.hybridPredictions].sort((a, b) => a.predicted_rank - b.predicted_rank);
  });

  // ==================== 工具函数 ====================

  // 状态文本转换
  const getStatusText = (status: string) => {
    const statusMap = {
      bet: '🟢 投注中',
      lock: '🟡 已锁定',
      settling: '🟠 结算中',
      settled: '🔵 已结算',
      unknown: '❓ 未知'
    };
    return statusMap[status as keyof typeof statusMap] || '❓ 未知';
  };

  // 状态标签类型
  const getStatusTagType = (status: string) => {
    switch (status) {
      case 'bet':
        return 'success';
      case 'settling':
        return 'warning';
      case 'settled':
        return 'info';
      default:
        return 'default';
    }
  };

  // 获取动能状态文本
  const getMomentumStatusText = (momScore: number) => {
    if (momScore >= 70) return '强劲上升';
    if (momScore >= 60) return '稳健上升';
    if (momScore >= 50) return '中性稳定';
    if (momScore >= 40) return '轻微下降';
    return '明显下降';
  };

  // ==================== 样式相关函数 ====================

  const { getUnifiedCardClass, getScoreTextClass, getPredictionIcon } = usePredictionDisplay();
</script>
