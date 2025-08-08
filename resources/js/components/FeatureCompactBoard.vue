<template>
  <div class="space-y-3">
    <!-- 控制条 -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="text-sm text-white/80">快速预览</div>
      <NSelect
        v-model:value="selectedFeatures"
        :options="featureOptions"
        multiple
        max-tag-count="responsive"
        placeholder="选择要对比的特征"
        class="min-w-[260px] w-[460px]"
      />
      <div class="flex items-center gap-2 text-xs text-white/70">
        <span>Top</span>
        <NSlider v-model:value="topN" :min="3" :max="10" :step="1" class="w-[160px]" />
        <span>{{ topN }}</span>
      </div>
    </div>

    <!-- 排名并列栅格 -->
    <div class="grid gap-3" :class="gridColsClass">
      <NCard v-for="f in selectedFeatures" :key="f" class="border border-white/10 bg-white/5 backdrop-blur">
        <div class="mb-2 flex items-center justify-between">
          <div class="text-sm text-white font-bold">🎯 {{ f }}</div>
          <div class="text-xs text-white/50">Top {{ topN }}</div>
        </div>
        <div class="space-y-1">
          <div
            v-for="(row, idx) in getTopRows(f)"
            :key="`${f}-${row.symbol}`"
            class="flex items-center justify-between rounded-md px-2 py-1 text-xs"
            :class="idx < 3 ? 'bg-green-500/5' : 'bg-white/0'"
          >
            <div class="flex items-center gap-2">
              <span class="opacity-70">#{{ row.rank }}</span>
              <span class="text-white font-bold">{{ row.symbol }}</span>
            </div>
            <div class="font-mono tabular-nums" :class="idx < 3 ? 'text-green-400' : 'text-white/80'">
              {{ formatScore(row.score) }}
            </div>
          </div>
        </div>
      </NCard>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, ref, watch } from 'vue';
  import { NSelect, NSlider, NCard } from 'naive-ui';
  import type { RoundFeatureMatrixResponse } from '@/types/prediction';

  const props = defineProps<{
    matrix: RoundFeatureMatrixResponse | null;
  }>();

  const topN = ref(5);
  const selectedFeatures = ref<string[]>([]);

  const featureOptions = computed(() => (props.matrix?.features ?? []).map((f) => ({ label: f, value: f })));

  // 默认全选
  watch(
    () => props.matrix?.features,
    (list) => {
      if (!list || list.length === 0) return;
      if (selectedFeatures.value.length === 0) {
        selectedFeatures.value = list;
      } else {
        // 移除不存在的，保留已有顺序
        selectedFeatures.value = selectedFeatures.value.filter((f) => list.includes(f));
        if (selectedFeatures.value.length === 0) selectedFeatures.value = list.slice(0, 4);
      }
    },
    { immediate: true }
  );

  const gridColsClass = computed(() => {
    const n = Math.max(1, Math.min(6, selectedFeatures.value.length || 1));
    // 根据列数生成响应式列
    return `grid-cols-1 sm:grid-cols-2 lg:grid-cols-${Math.min(4, n)} xl:grid-cols-${Math.min(6, n)}`;
  });

  function getTopRows(featureKey: string) {
    const tokens = props.matrix?.tokens ?? [];
    const rows: Array<{ symbol: string; score: number; rank: number }> = [];
    for (const t of tokens) {
      const cell = props.matrix?.matrix?.[t]?.[featureKey];
      const score = (cell?.norm ?? cell?.raw ?? 0) as number;
      rows.push({ symbol: t, score, rank: 0 });
    }
    rows.sort((a, b) => b.score - a.score);
    for (let i = 0; i < rows.length; i++) rows[i].rank = i + 1;
    return rows.slice(0, topN.value);
  }

  const formatScore = (v: number) => (Math.abs(v) >= 1 ? v.toFixed(3) : v.toFixed(3));
</script>

<style scoped></style>
