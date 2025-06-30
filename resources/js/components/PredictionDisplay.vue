<template>
  <NCard
    v-if="showCard && currentAnalysis && currentAnalysis.length > 0"
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

    <!-- 横向预测排名展示 -->
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5">
      <div
        v-for="(token, index) in sortedPredictionsByRank"
        :key="`prediction-${index}-${token.symbol}`"
        class="relative overflow-hidden border rounded-lg p-3 transition-all duration-300 hover:shadow-lg"
        :class="getUnifiedCardClass(index)"
      >
        <!-- 预测排名头部 -->
        <div class="mb-2 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <div class="text-lg">{{ getPredictionIcon(index) }}</div>
            <div class="text-sm text-white font-bold">{{ token.symbol }}</div>
          </div>
          <div class="text-xs text-gray-400">#{{ token.predicted_rank }}</div>
        </div>

        <!-- 核心评分 -->
        <div class="mb-3 text-center">
          <div class="text-xs text-gray-400">预测分数</div>
          <div class="text-lg font-bold" :class="getScoreTextClass(index)">
            {{ (token.final_prediction_score || token.risk_adjusted_score || token.prediction_score || 0).toFixed(1) }}
          </div>
          <div v-if="token.rank_confidence" class="text-xs text-gray-400">
            置信度 {{ (token.rank_confidence || 0).toFixed(0) }}%
          </div>
        </div>

        <!-- 详细数据参数 -->
        <div class="text-xs space-y-1">
          <div class="flex justify-between">
            <span class="text-gray-400">绝对分数:</span>
            <span class="text-purple-400 font-bold">
              {{ token.absolute_score ? (token.absolute_score || 0).toFixed(1) : '-' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">相对分数:</span>
            <span class="text-orange-400 font-bold">
              {{
                token.relative_score || token.h2h_score
                  ? (token.relative_score || token.h2h_score || 0).toFixed(1)
                  : '-'
              }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">保本率:</span>
            <span class="text-green-400 font-bold">
              {{ token.top3_rate ? (token.top3_rate || 0).toFixed(1) + '%' : '-' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">胜率:</span>
            <span class="text-yellow-400 font-bold">
              {{ token.win_rate ? (token.win_rate || 0).toFixed(1) + '%' : '-' }}
            </span>
          </div>

          <!-- 实时游戏数据对比（如果有） -->
          <div v-if="getTokenCurrentRank(token.symbol)" class="mt-2 border-t border-gray-600/30 pt-1">
            <div class="flex justify-between">
              <span class="text-gray-400">当前排名:</span>
              <span class="text-cyan-400 font-bold">#{{ getTokenCurrentRank(token.symbol) }}</span>
            </div>
            <div v-if="getTokenCurrentChange(token.symbol)" class="flex justify-between">
              <span class="text-gray-400">价格变化:</span>
              <span class="font-bold" :class="formatPriceChange(getTokenCurrentChange(token.symbol)).color">
                {{ formatPriceChange(getTokenCurrentChange(token.symbol)).text }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </NCard>
  <NEmpty v-else-if="showCard" description="暂无AI预测数据" class="py-8" />
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import { NCard, NTag, NEmpty } from 'naive-ui';

  // Props
  interface Props {
    currentAnalysis: any[];
    analysisMeta: any;
    currentRoundId: string | null;
    currentGameStatus: string;
    currentGameTokensWithRanks: any[];
    analysisLoading: boolean;
    title?: string;
    showCard?: boolean;
  }

  const props = withDefaults(defineProps<Props>(), {
    title: '🔮 AI预测排名',
    showCard: true
  });

  // Emits
  defineEmits<{
    'refresh-analysis': [];
  }>();

  // ==================== 计算属性 ====================

  // 预测Token按排名排序
  const sortedPredictionsByRank = computed(() => {
    return [...props.currentAnalysis].sort((a, b) => a.predicted_rank - b.predicted_rank);
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

  // 获取Token当前排名
  const getTokenCurrentRank = (symbol: string) => {
    const token = props.currentGameTokensWithRanks.find((t) => t.symbol === symbol);
    return token?.rank || null;
  };

  // 获取Token当前价格变化
  const getTokenCurrentChange = (symbol: string) => {
    const token = props.currentGameTokensWithRanks.find((t) => t.symbol === symbol);
    return token?.priceChange || null;
  };

  // 格式化价格变化
  const formatPriceChange = (change: number | null) => {
    if (change === null || change === undefined) return { text: '-', color: 'text-gray-400' };

    const prefix = change > 0 ? '+' : '';
    const text = `${prefix}${change.toFixed(2)}%`;
    const color = change > 0 ? 'text-green-400' : change < 0 ? 'text-red-400' : 'text-gray-400';

    return { text, color };
  };

  // ==================== 样式相关函数 ====================

  const getUnifiedCardClass = (index: number) => {
    if (index === 0)
      return 'border-yellow-400/30 bg-gradient-to-br from-yellow-500/10 to-amber-600/5 hover:border-yellow-400/50 hover:shadow-yellow-500/20';
    if (index === 1)
      return 'border-slate-400/30 bg-gradient-to-br from-slate-500/10 to-gray-600/5 hover:border-slate-400/50 hover:shadow-slate-500/20';
    if (index === 2)
      return 'border-orange-400/30 bg-gradient-to-br from-orange-500/10 to-red-600/5 hover:border-orange-400/50 hover:shadow-orange-500/20';
    if (index === 3)
      return 'border-blue-400/30 bg-gradient-to-br from-blue-500/10 to-indigo-600/5 hover:border-blue-400/50 hover:shadow-blue-500/20';
    return 'border-purple-400/30 bg-gradient-to-br from-purple-500/10 to-pink-600/5 hover:border-purple-400/50 hover:shadow-purple-500/20';
  };

  const getScoreTextClass = (index: number) => {
    if (index === 0) return 'text-yellow-400';
    if (index === 1) return 'text-slate-400';
    if (index === 2) return 'text-orange-400';
    if (index === 3) return 'text-blue-400';
    return 'text-purple-400';
  };

  const getPredictionIcon = (index: number) => {
    if (index === 0) return '🥇';
    if (index === 1) return '🥈';
    if (index === 2) return '🥉';
    return '📊';
  };
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
