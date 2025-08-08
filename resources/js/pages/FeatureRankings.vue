<template>
  <DefaultLayout>
    <Head title="特征排名 (本局)" />

    <div class="mx-auto max-w-7xl p-4 sm:p-6">
      <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold text-white">特征排名（本局）</h1>
        <button class="rounded bg-white/10 px-3 py-1.5 text-xs" :disabled="loading" @click="refresh">
          {{ loading ? '加载中...' : '刷新' }}
        </button>
      </div>

      <div class="space-y-6">
        <AIPredictionRanking
          v-for="f in features"
          :key="f"
          :current-analysis="buildFeatureRanking(f)"
          :analysis-meta="{ round_id: matrix?.round_id || '', feature_key: f }"
          :current-round-id="String(matrix?.round_id || '')"
          :current-game-status="'unknown'"
          :current-game-tokens-with-ranks="[]"
          :analysis-loading="loading"
          :title="`🎯 特征 - ${f}`"
          @refresh-analysis="refresh"
        />
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { computed, onMounted } from 'vue';
  import { Head } from '@inertiajs/vue3';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import AIPredictionRanking from '@/components/AIPredictionRanking.vue';
  import { useFeatureStore } from '@/stores/featureStore';

  const store = useFeatureStore();
  const matrix = computed(() => store.matrix);
  const loading = computed(() => store.loading);
  const features = computed(() => matrix.value?.features ?? []);
  const tokens = computed(() => matrix.value?.tokens ?? []);

  const refresh = () => store.fetchRoundFeatures();

  // 将单一特征的矩阵列构造成 AIPredictionRanking 所需的数据结构
  function buildFeatureRanking(featureKey: string) {
    const rows: Array<{ symbol: string; value: number; raw: number | null }> = [];
    for (const t of tokens.value) {
      const cell = matrix.value?.matrix?.[t]?.[featureKey];
      const norm = (cell?.norm ?? null) as number | null;
      const raw = (cell?.raw ?? null) as number | null;
      const score = norm ?? raw ?? 0;
      rows.push({ symbol: t, value: score, raw });
    }
    // 按分数降序并生成 rank
    const sorted = rows
      .slice()
      .sort((a, b) => b.value - a.value)
      .map((x, idx) => ({ symbol: x.symbol, predicted_rank: idx + 1, prediction_score: x.value, raw: x.raw }));

    // AIPredictionRanking 期望的字段映射（最小集）
    return sorted.map((r) => {
      const base: any = {
        symbol: r.symbol,
        predicted_rank: r.predicted_rank,
        prediction_score: r.prediction_score,
        absolute_score: r.raw ?? undefined
      };
      // 对 p_top3_from_elo 这类概率特征，提供 top3_rate 显示（百分比）
      if (featureKey.includes('top3') && typeof r.raw === 'number') {
        base.top3_rate = Math.max(0, Math.min(100, r.raw * 100));
      }
      return base;
    });
  }

  onMounted(() => {
    if (!matrix.value) refresh();
  });
</script>

<style scoped></style>
