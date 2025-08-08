<template>
  <DefaultLayout>
    <Head title="特征排名 (本局)" />

    <div class="min-h-screen from-slate-900 via-slate-800 to-slate-900 bg-gradient-to-br">
      <div class="mx-auto max-w-7xl p-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between">
          <h1 class="text-xl text-white font-bold">特征排名（本局，自动刷新）</h1>
          <div class="text-xs opacity-70">WebSocket: {{ websocketStatus.message }}</div>
        </div>

        <div class="space-y-6">
          <FeatureRankingCard
            v-for="f in features"
            :key="f"
            :title="`🎯 特征 - ${f}`"
            :feature-key="f"
            :items="buildCardItems(f)"
            :current-game-tokens-with-ranks="currentGameTokensWithRanks"
          />
          <NEmpty v-if="!features.length" description="暂无特征数据" class="py-8" />
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { computed, onMounted } from 'vue';
  import { Head } from '@inertiajs/vue3';
  import { NEmpty } from 'naive-ui';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import FeatureRankingCard from '@/components/FeatureRankingCard.vue';
  import { useFeatureStore } from '@/stores/featureStore';
  import { websocketManager } from '@/utils/websocketManager';
  import { useGamePredictionStore } from '@/stores/gamePrediction';

  const store = useFeatureStore();
  const predictionStore = useGamePredictionStore();
  const matrix = computed(() => store.matrix);
  // 页面自动刷新，不使用loading状态显示
  // const loading = computed(() => store.loading);
  const features = computed(() => matrix.value?.features ?? []);
  const tokens = computed(() => matrix.value?.tokens ?? []);
  const websocketStatus = websocketManager.websocketStatus;
  const currentGameTokensWithRanks = computed(() => predictionStore.currentGameTokensWithRanks);

  const refresh = () => store.fetchRoundFeatures();

  // 将单一特征的矩阵列构造成 AIPredictionRanking 所需的数据结构
  function buildCardItems(featureKey: string) {
    const rows: Array<{ symbol: string; score: number; raw: number | null; probability?: number }> = [];
    for (const t of tokens.value) {
      const cell = matrix.value?.matrix?.[t]?.[featureKey];
      const norm = (cell?.norm ?? null) as number | null;
      const raw = (cell?.raw ?? null) as number | null;
      const score = norm ?? raw ?? 0;
      const probability =
        featureKey.includes('top3') && typeof raw === 'number' ? Math.max(0, Math.min(100, raw * 100)) : undefined;
      rows.push({ symbol: t, score, raw, probability });
    }
    const sorted = rows
      .slice()
      .sort((a, b) => b.score - a.score)
      .map((x, idx) => ({ symbol: x.symbol, rank: idx + 1, score: x.score, raw: x.raw, probability: x.probability }));
    return sorted;
  }

  onMounted((): void => {
    if (!websocketManager.isInitialized) websocketManager.initialize();
    predictionStore.fetchInitialData().catch(() => {});
    // 首次拉取
    refresh();
    // 订阅特征矩阵推送，减少HTTP压力
    store.subscribeFeatureMatrixPush();
    // 监听游戏事件，进入bet或新轮次变化时刷新特征矩阵
    websocketManager.listenToGameUpdates((event: { data?: { status?: string; rdId?: string } }) => {
      const status = event?.data?.status;
      const rdId = event?.data?.rdId;
      if (status === 'bet' && rdId) {
        refresh();
      }
    });
  });
</script>

<style scoped></style>
