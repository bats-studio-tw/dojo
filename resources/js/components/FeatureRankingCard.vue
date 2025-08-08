<template>
  <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" :title="title" size="large">
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5">
      <div
        v-for="(item, index) in items"
        :key="`${featureKey}-${index}-${item.symbol}`"
        class="relative overflow-hidden border rounded-lg p-3 transition-all duration-300 hover:shadow-lg"
        :class="cardClass(index)"
      >
        <div class="mb-2 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <div class="text-lg">{{ getPredictionIcon(index + 1) }}</div>
            <div class="text-sm text-white font-bold">{{ item.symbol }}</div>
          </div>
          <div class="text-xs text-gray-400">#{{ item.rank }}</div>
        </div>

        <div class="mb-2 text-center">
          <div class="text-lg font-bold" :class="scoreClass(index)">{{ formatScore(item.score) }}</div>
          <div v-if="isProbability" class="text-xs text-gray-400">概率 {{ (item.probability || 0).toFixed(1) }}%</div>
        </div>

        <div class="text-xs space-y-1">
          <div class="flex justify-between">
            <span class="text-gray-400">标准化:</span>
            <span :class="normClass(item.score)" class="font-bold">{{ formatScore(item.score) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">原始:</span>
            <span class="text-blue-400 font-bold">{{ item.raw == null ? '-' : item.raw.toFixed(3) }}</span>
          </div>
          <div v-if="getTokenCurrentRank(item.symbol)" class="border-t border-gray-600/30 pt-1">
            <div class="flex justify-between">
              <span class="text-gray-400">当前排名:</span>
              <span class="text-cyan-400 font-bold">#{{ getTokenCurrentRank(item.symbol) }}</span>
            </div>
            <div v-if="getTokenCurrentChange(item.symbol)" class="flex justify-between">
              <span class="text-gray-400">价格变化:</span>
              <span class="font-bold" :class="formatPriceChange(getTokenCurrentChange(item.symbol)).color">
                {{ formatPriceChange(getTokenCurrentChange(item.symbol)).text }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </NCard>
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import { NCard } from 'naive-ui';
  import { usePredictionDisplay } from '@/composables/usePredictionDisplay';

  interface Item {
    symbol: string;
    rank: number;
    score: number; // normalized 优先
    raw: number | null;
    probability?: number; // 0-100
  }

  const props = defineProps<{
    title: string;
    featureKey: string;
    items: Item[];
    currentGameTokensWithRanks?: Array<{ symbol: string; rank: number; priceChange?: number | null }>;
  }>();

  const isProbability = computed(() => props.featureKey.includes('top3') || props.featureKey.includes('prob'));
  const { getPredictionIcon } = usePredictionDisplay();
  const cardClass = (index: number) =>
    index < 3 ? 'border-green-500/20 bg-green-500/5' : 'border-white/10 bg-white/5';
  const scoreClass = (index: number) => (index < 3 ? 'text-green-400' : 'text-gray-200');
  const normClass = (v: number) => (v >= 1 ? 'text-green-400' : v <= -1 ? 'text-red-400' : 'text-gray-300');
  const formatScore = (v: number) => v.toFixed(3);

  // 实时对照：当前排名与价格变化
  const getTokenCurrentRank = (symbol: string) => {
    const t = props.currentGameTokensWithRanks?.find((x) => x.symbol === symbol);
    return t?.rank ?? null;
  };

  const getTokenCurrentChange = (symbol: string) => {
    const t = props.currentGameTokensWithRanks?.find((x) => x.symbol === symbol);
    return t?.priceChange ?? null;
  };

  const formatPriceChange = (change: number | null) => {
    if (change === null || change === undefined) return { text: '-', color: 'text-gray-400' };
    const prefix = change > 0 ? '+' : '';
    const text = `${prefix}${change.toFixed(2)}%`;
    const color = change > 0 ? 'text-green-400' : change < 0 ? 'text-red-400' : 'text-gray-400';
    return { text, color };
  };
</script>

<style scoped></style>
