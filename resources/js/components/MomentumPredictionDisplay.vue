<template>
  <NCard
    v-if="showCard"
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
        <n-button :loading="analysisLoading" @click="refreshAnalysis" type="primary" size="small">🔄 刷新分析</n-button>
      </div>
    </template>

    <!-- 換回合時的加載狀態 -->
    <div v-if="isRoundTransitioning" class="flex flex-col items-center justify-center py-12">
      <NSpin size="large" />
      <div class="mt-4 text-center">
        <div class="mb-2 text-lg text-blue-300 font-semibold">
          {{ transitionMessage }}
        </div>
        <div class="text-sm text-gray-400">
          {{ transitionDescription }}
        </div>
      </div>
    </div>

    <!-- 動能預測排名展示 -->
    <div v-else-if="shouldShowPredictions" class="grid grid-cols-1 gap-3 lg:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5">
      <div
        v-for="(token, index) in displayPredictions"
        :key="`momentum-${index}-${token.symbol}`"
        class="relative overflow-hidden border rounded-lg p-3 transition-all duration-300 hover:shadow-lg"
        :class="getMomentumCardClass(index)"
      >
        <!-- 動能預測排名头部 -->
        <div class="mb-2 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <div class="text-lg">{{ getMomentumIcon(index) }}</div>
            <div class="text-sm text-white font-bold">{{ token.symbol }}</div>
          </div>
          <div class="text-xs text-gray-400">#{{ token.predicted_rank }}</div>
        </div>

        <!-- 核心動能分數 -->
        <div class="mb-3 text-center">
          <div class="text-xs text-gray-400">動能分數</div>
          <div class="text-lg font-bold" :class="getMomentumScoreTextClass(index)">
            {{ (token.mom_score || 50).toFixed(1) }}
          </div>
          <div v-if="token.confidence" class="text-xs text-gray-400">
            信心度 {{ (token.confidence || 0).toFixed(0) }}%
          </div>
        </div>

        <!-- 詳細動能數據 -->
        <div class="text-xs space-y-1">
          <!-- Elo 機率 -->
          <div class="flex justify-between">
            <span class="text-gray-400">Elo 機率:</span>
            <span class="text-blue-400 font-bold">{{ (token.elo_prob || 0).toFixed(1) }}%</span>
          </div>

          <!-- 最終混合分數 -->
          <div class="flex justify-between">
            <span class="text-gray-400">混合分數:</span>
            <span class="text-purple-400 font-bold">{{ (token.final_score || 0).toFixed(1) }}</span>
          </div>

          <!-- 動能變化指示器 -->
          <div class="mt-2 border-t border-gray-600/30 pt-1">
            <div class="flex justify-between">
              <span class="text-gray-400">動能狀態:</span>
              <span class="font-bold" :class="getMomentumStatusClass(token.mom_score)">
                {{ getMomentumStatusText(token.mom_score) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 無數據狀態 -->
    <div v-else class="flex flex-col items-center justify-center py-12">
      <div class="mb-4 text-4xl">⚡</div>
      <div class="text-center">
        <div class="mb-2 text-lg text-blue-300 font-semibold">暫無動能預測數據</div>
        <div class="mb-4 text-sm text-gray-400">當前輪次尚未生成 Hybrid-Edge 預測數據</div>
      </div>
    </div>
  </NCard>
</template>

<script setup lang="ts">
  import { computed, ref, watch } from 'vue';
  import { NCard, NTag, NSpin, NButton } from 'naive-ui';

  // Props
  interface Props {
    hybridPredictions: any[];
    analysisMeta: any;
    currentRoundId: string | null;
    currentGameStatus: string;
    analysisLoading: boolean;
    title?: string;
    showCard?: boolean;
    config?: any;
    refreshAnalysis?: () => void;
  }

  const props = withDefaults(defineProps<Props>(), {
    title: '⚡ AI動能預測排名',
    showCard: true
  });

  // Emits
  const emit = defineEmits<{
    'refresh-analysis': [];
  }>();

  // 狀態管理
  const isRoundTransitioning = ref(false);
  const currentDisplayRoundId = ref<string | null>(null);
  const transitionMessage = ref('');
  const transitionDescription = ref('');

  // 開始回合過渡
  const startRoundTransition = (message: string, description: string) => {
    isRoundTransitioning.value = true;
    transitionMessage.value = message;
    transitionDescription.value = description;
    // 立即清空當前顯示的輪次ID，確保舊數據不會顯示
    currentDisplayRoundId.value = null;
  };

  // 結束回合過渡
  const endRoundTransition = () => {
    isRoundTransitioning.value = false;
  };

  // 監聽回合變化和遊戲狀態變化
  watch(
    [() => props.currentRoundId, () => props.currentGameStatus],
    ([newRoundId, newGameStatus], [oldRoundId, oldGameStatus]) => {
      // 回合變化
      if (newRoundId && oldRoundId && newRoundId !== oldRoundId) {
        startRoundTransition('正在準備新回合預測...', '清空上一回合數據，等待新回合開始');
        return;
      }

      // 遊戲狀態從結算變成投注中
      if (oldGameStatus === 'settled' && newGameStatus === 'bet') {
        startRoundTransition('正在計算新回合預測...', 'AI 正在分析新回合的動能數據');
        return;
      }

      // 當有新的預測數據且當前輪次匹配時，結束過渡狀態
      if (newRoundId && props.hybridPredictions && props.hybridPredictions.length > 0) {
        if (currentDisplayRoundId.value !== newRoundId) {
          currentDisplayRoundId.value = newRoundId;
          endRoundTransition();
        }
      }
    },
    { immediate: true }
  );

  // 額外監聽 hybridPredictions 變化，確保數據清空
  watch(
    () => props.hybridPredictions,
    () => {
      // 如果正在過渡狀態，且數據發生變化，確保清空顯示
      if (isRoundTransitioning.value) {
        currentDisplayRoundId.value = null;
      }
    },
    { deep: true }
  );

  // 刷新分析方法
  const refreshAnalysis = () => {
    if (props.refreshAnalysis) {
      props.refreshAnalysis();
    } else {
      emit('refresh-analysis');
    }
  };

  // ==================== 計算屬性 ====================

  // 是否應該顯示預測數據
  const shouldShowPredictions = computed(() => {
    // 如果正在過渡狀態，絕對不顯示數據
    if (isRoundTransitioning.value) {
      return false;
    }

    // 如果沒有數據，不顯示
    if (!props.hybridPredictions || props.hybridPredictions.length === 0) {
      return false;
    }

    // 如果當前顯示的輪次ID與當前輪次不匹配，不顯示
    if (currentDisplayRoundId.value !== props.currentRoundId) {
      return false;
    }

    return true;
  });

  // 顯示的預測數據（去重並排序）
  const displayPredictions = computed(() => {
    if (!shouldShowPredictions.value) {
      return [];
    }

    // 数据去重：基于symbol去重，保留排名最高的记录
    const uniquePredictions = new Map();

    props.hybridPredictions.forEach((prediction) => {
      const symbol = prediction.symbol?.toUpperCase();
      if (!symbol) return;

      if (!uniquePredictions.has(symbol) || prediction.predicted_rank < uniquePredictions.get(symbol).predicted_rank) {
        uniquePredictions.set(symbol, prediction);
      }
    });

    // 转换为数组并按排名排序
    const deduplicatedArray = Array.from(uniquePredictions.values());
    return deduplicatedArray.sort((a, b) => a.predicted_rank - b.predicted_rank);
  });

  // ==================== 工具函數 ====================

  // 狀態文本轉換
  const getStatusText = (status: string) => {
    const statusMap = {
      bet: '🟢 投注中',
      lock: '🟡 已鎖定',
      settling: '🟠 結算中',
      settled: '🔵 已結算',
      unknown: '❓ 未知'
    };
    return statusMap[status as keyof typeof statusMap] || '❓ 未知';
  };

  // 狀態標籤類型
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

  // 獲取動能卡片樣式類
  const getMomentumCardClass = (index: number) => {
    if (index === 0) {
      return 'border-yellow-400/30 bg-gradient-to-br from-yellow-500/10 to-amber-600/5 hover:border-yellow-400/50 hover:shadow-yellow-500/20';
    }
    if (index === 1) {
      return 'border-slate-400/30 bg-gradient-to-br from-slate-500/10 to-gray-600/5 hover:border-slate-400/50 hover:shadow-slate-500/20';
    }
    if (index === 2) {
      return 'border-orange-400/30 bg-gradient-to-br from-orange-500/10 to-red-600/5 hover:border-orange-400/50 hover:shadow-orange-500/20';
    }
    if (index === 3) {
      return 'border-blue-400/30 bg-gradient-to-br from-blue-500/10 to-indigo-600/5 hover:border-blue-400/50 hover:shadow-blue-500/20';
    }
    return 'border-purple-400/30 bg-gradient-to-br from-purple-500/10 to-pink-600/5 hover:border-purple-400/50 hover:shadow-purple-500/20';
  };

  // 獲取動能分數文本顏色類
  const getMomentumScoreTextClass = (index: number) => {
    if (index === 0) return 'text-yellow-400';
    if (index === 1) return 'text-slate-400';
    if (index === 2) return 'text-orange-400';
    if (index === 3) return 'text-blue-400';
    return 'text-purple-400';
  };

  // 獲取動能圖標
  const getMomentumIcon = (index: number) => {
    if (index === 0) return '🥇';
    if (index === 1) return '🥈';
    if (index === 2) return '🥉';
    return '🏅';
  };

  // 獲取動能狀態文本
  const getMomentumStatusText = (momScore: number) => {
    if (momScore >= 70) return '強勁上升';
    if (momScore >= 60) return '穩健上升';
    if (momScore >= 50) return '中性穩定';
    if (momScore >= 40) return '輕微下降';
    return '明顯下降';
  };

  // 獲取動能狀態顏色類
  const getMomentumStatusClass = (momScore: number) => {
    if (momScore >= 70) return 'text-green-400';
    if (momScore >= 60) return 'text-blue-400';
    if (momScore >= 50) return 'text-gray-400';
    if (momScore >= 40) return 'text-yellow-400';
    return 'text-red-400';
  };
</script>
