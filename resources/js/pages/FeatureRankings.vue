<template>
  <DefaultLayout>
    <Head title="特征排名 (本局)" />

    <div class="min-h-screen from-slate-900 via-slate-800 to-slate-900 bg-gradient-to-br">
      <div class="mx-auto max-w-7xl p-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between">
          <h1 class="text-xl text-white font-bold">特征排名（本局，自动刷新）</h1>
          <div class="text-xs opacity-70">WebSocket: {{ websocketStatus.message }}</div>
        </div>

        <!-- 新设计：顶部紧凑对比榜 -->
        <FeatureCompactBoard :matrix="matrix || null" />

        <div class="space-y-6">
          <NEmpty v-if="!(matrix && matrix.features && matrix.features.length)" description="暂无特征数据" class="py-8" />
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
  import FeatureCompactBoard from '@/components/FeatureCompactBoard.vue';
  import { useFeatureStore } from '@/stores/featureStore';
  import { websocketManager } from '@/utils/websocketManager';
  import { useGamePredictionStore } from '@/stores/gamePrediction';

  const store = useFeatureStore();
  const predictionStore = useGamePredictionStore();
  const matrix = computed(() => store.matrix);
  // 页面自动刷新，不使用loading状态显示
  // const loading = computed(() => store.loading);
  // 紧凑榜已覆盖主用例，下面列表已移除
  const websocketStatus = websocketManager.websocketStatus;
  const currentGameTokensWithRanks = computed(() => predictionStore.currentGameTokensWithRanks);

  const refresh = () => store.maybeFetchAfterTimeout();

  // 旧的单特征卡片已移除

  onMounted((): void => {
    if (!websocketManager.isInitialized) websocketManager.initialize();
    predictionStore.fetchInitialData().catch(() => {});
    // 首次拉取（若短时间内未收到推送将兜底请求）
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
