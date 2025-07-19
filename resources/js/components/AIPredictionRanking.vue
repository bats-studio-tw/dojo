<template>
  <NCard
    v-if="currentAnalysis && currentAnalysis.length > 0"
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
        :style="{ ...getBorderStyle(index), ...getBackgroundStyle(index) }"
        :class="getUnifiedCardClass(index)"
      >
        <!-- 预测排名头部 -->
        <div class="mb-2 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <div class="text-lg">{{ getPredictionIcon(index + 1) }}</div>
            <div class="text-sm text-white font-bold">{{ token.symbol }}</div>
          </div>
          <div class="text-xs text-gray-400">#{{ token.predicted_rank }}</div>
        </div>

        <!-- 核心评分 -->
        <div class="mb-3 text-center">
          <div class="text-lg font-bold" :class="getScoreTextClass(index)">
            {{ (token.prediction_score || 0).toFixed(1) }}
          </div>
          <div class="text-xs text-gray-400">置信度 {{ (token.rank_confidence || 0).toFixed(0) }}%</div>
        </div>

        <!-- 详细数据参数 -->
        <div class="text-xs space-y-1">
          <div class="flex justify-between">
            <span class="text-gray-400">绝对分数:</span>
            <span class="text-blue-400 font-bold">
              {{ token.absolute_score && token.absolute_score > 0 ? (token.absolute_score || 0).toFixed(1) : '-' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">相对分数:</span>
            <span class="text-orange-400 font-bold">
              {{
                (token.relative_score && token.relative_score > 0) || (token.h2h_score && token.h2h_score > 0)
                  ? (token.relative_score || token.h2h_score || 0).toFixed(1)
                  : '-'
              }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">保本率:</span>
            <span class="text-green-400 font-bold">
              {{ token.top3_rate && token.top3_rate > 0 ? (token.top3_rate || 0).toFixed(1) + '%' : '-' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">胜率:</span>
            <span class="text-yellow-400 font-bold">
              {{ token.win_rate && token.win_rate > 0 ? (token.win_rate || 0).toFixed(1) + '%' : '-' }}
            </span>
          </div>

          <!-- 代币涨跌幅信息 -->
          <div class="mt-2 border-t border-gray-600/30 pt-1">
            <div class="flex justify-between">
              <span class="text-gray-400">5分钟:</span>
              <span class="font-bold" :class="formatTokenPriceChange(token.change_5m).color">
                {{ formatTokenPriceChange(token.change_5m).text }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">1小时:</span>
              <span class="font-bold" :class="formatTokenPriceChange(token.change_1h).color">
                {{ formatTokenPriceChange(token.change_1h).text }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">24小时:</span>
              <span class="font-bold" :class="formatTokenPriceChange(token.change_24h).color">
                {{ formatTokenPriceChange(token.change_24h).text }}
              </span>
            </div>
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
  <NEmpty v-else description="暂无AI预测数据" class="py-8" />
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import { NCard, NTag, NEmpty } from 'naive-ui';
  import { usePredictionDisplay } from '@/composables/usePredictionDisplay';

  // Props
  interface Props {
    currentAnalysis: any[];
    analysisMeta: any;
    currentRoundId: string | null;
    currentGameStatus: string;
    currentGameTokensWithRanks: any[];
    analysisLoading?: boolean;
    title?: string;
  }

  const props = withDefaults(defineProps<Props>(), {
    analysisLoading: false,
    title: '🎯 智能对战预测'
  });

  // Emits
  defineEmits<{
    'refresh-analysis': [];
  }>();

  // ==================== 计算属性 ====================

  // 预测Token按排名排序
  const sortedPredictionsByRank = computed(() => {
    console.log('🔮 AIPredictionRanking: 接收到currentAnalysis数据:', props.currentAnalysis);
    console.log('🔮 AIPredictionRanking: 数据长度:', props.currentAnalysis?.length);
    console.log('🔮 AIPredictionRanking: 数据类型:', typeof props.currentAnalysis);
    console.log('🔮 AIPredictionRanking: 是否为数组:', Array.isArray(props.currentAnalysis));

    if (!props.currentAnalysis || props.currentAnalysis.length === 0) {
      console.warn('⚠️ AIPredictionRanking: currentAnalysis为空或长度为0');
      console.warn('⚠️ AIPredictionRanking: currentAnalysis值:', props.currentAnalysis);
      return [];
    }

    const sorted = [...props.currentAnalysis].sort((a, b) => a.predicted_rank - b.predicted_rank);
    console.log('🔮 AIPredictionRanking: 排序后的数据:', sorted.slice(0, 3)); // 只显示前3个
    console.log('🔮 AIPredictionRanking: 排序后总长度:', sorted.length);
    return sorted;
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

  // 格式化Token涨跌幅数据
  const formatTokenPriceChange = (change: number | null) => {
    if (change === null || change === undefined) return { text: '-', color: 'text-gray-400' };

    const prefix = change > 0 ? '+' : '';
    const text = `${prefix}${change.toFixed(2)}%`;
    const color = change > 0 ? 'text-green-400' : change < 0 ? 'text-red-400' : 'text-gray-400';

    return { text, color };
  };

  // ==================== 样式相关函数 ====================

  const { getUnifiedCardClass, getScoreTextClass, getPredictionIcon, getBorderStyle, getBackgroundStyle } =
    usePredictionDisplay();
</script>
