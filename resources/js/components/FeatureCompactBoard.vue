<template>
  <div class="space-y-4">
    <!-- 顶部控制条 -->
    <div class="glass-bar flex flex-wrap items-center justify-between gap-3 border rounded-xl px-3 py-2">
      <div class="flex items-center gap-2">
        <span class="text-lg">🎯</span>
        <div class="title-text">快速预览</div>
      </div>
      <div class="flex items-center gap-3">
        <NSelect
          v-model:value="selectedFeatures"
          :options="featureOptions"
          multiple
          max-tag-count="responsive"
          placeholder="选择要对比的特征"
          class="min-w-[260px] w-[460px]"
        />
      </div>
    </div>

    <!-- 排名卡片栅格 -->
    <div class="grid gap-4" :class="gridColsClass">
      <div
        v-for="f in selectedFeatures"
        :key="f"
        class="feature-card border rounded-xl bg-black/20 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-lg"
      >
        <!-- 卡片头部 -->
        <div class="mb-3 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-base">📌</span>
            <div class="feature-name">{{ f }}</div>
          </div>
          <div class="pill">Top 5</div>
        </div>

        <!-- Top 列表 -->
        <div class="space-y-1.5">
          <div
            v-for="(row, idx) in getTopRows(f)"
            :key="`${f}-${row.symbol}`"
            class="item-row flex items-center justify-between rounded-lg px-3 py-2 text-xs"
          >
            <div class="flex items-center gap-3">
              <span class="rank-badge" :class="getRankClass(idx)">{{ getRankIcon(idx) }}</span>
              <span class="token text-white font-semibold">{{ row.symbol }}</span>
            </div>
            <div class="font-mono tabular-nums" :class="getScoreClass(row.score)">
              {{ formatScore(row.score) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, ref, watch } from 'vue';
  import { NSelect } from 'naive-ui';
  import type { RoundFeatureMatrixResponse } from '@/types/prediction';

  const props = defineProps<{
    matrix: RoundFeatureMatrixResponse | null;
  }>();

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
    // 固定展示本局 5 个代币（若少于5则按实际数量）
    const count = Math.min(5, rows.length);
    return rows.slice(0, count);
  }

  const formatScore = (v: number) => {
    if (v === null || v === undefined || Number.isNaN(v)) return '--';
    const fixed = Math.abs(v) >= 100 ? v.toFixed(0) : Math.abs(v) >= 10 ? v.toFixed(2) : v.toFixed(3);
    return fixed;
  };

  const getRankIcon = (idx: number) => (idx === 0 ? '🥇' : idx === 1 ? '🥈' : idx === 2 ? '🥉' : `#${idx + 1}`);

  const getRankClass = (idx: number) => {
    if (idx === 0) return 'rank-gold';
    if (idx === 1) return 'rank-silver';
    if (idx === 2) return 'rank-bronze';
    return 'rank-other';
  };

  const getScoreClass = (v: number) => (v >= 0 ? 'text-green-400' : 'text-red-400');
</script>

<style scoped>
  .glass-bar {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.03));
    border-color: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(16px) saturate(160%);
  }

  .title-text {
    background: linear-gradient(135deg, #60a5fa, #a78bfa, #f472b6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
  }

  .feature-card {
    border-color: rgba(255, 255, 255, 0.12);
  }

  .feature-name {
    font-weight: 700;
    color: #e5e7eb;
  }

  .pill {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 9999px;
    background: rgba(59, 130, 246, 0.15);
    color: #93c5fd;
    border: 1px solid rgba(59, 130, 246, 0.25);
  }

  .item-row {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
  }

  .rank-badge {
    width: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .rank-gold {
    color: #fbbf24;
  }
  .rank-silver {
    color: #cbd5e1;
  }
  .rank-bronze {
    color: #d97706;
  }
  .rank-other {
    color: #94a3b8;
  }
</style>
