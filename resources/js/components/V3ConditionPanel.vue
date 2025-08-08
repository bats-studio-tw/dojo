<template>
  <n-card class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="🎯 下注条件">
    <div class="space-y-5">
      <!-- 顶部：Top N + 操作按钮 -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2 whitespace-nowrap text-xs">
            <span class="text-white/80">Top N</span>
            <n-input-number v-model:value="topNProxy" :min="1" :max="5" :step="1" size="small" class="w-[100px]" />
          </div>
          <div class="text-xs text-gray-400">最终会从符合条件的列表中选取前 {{ topNProxy }} 个</div>
        </div>
        <div class="flex items-center gap-2">
          <n-button size="small" tertiary @click="onReset">重置</n-button>
          <n-button size="small" type="primary" @click="onSave">保存到本地</n-button>
        </div>
      </div>

      <!-- 内容：条件分组 -->
      <div class="grid grid-cols-1 gap-4">
        <!-- 名次条件 -->
        <n-card size="small" class="border border-white/10 bg-white/5">
          <div class="mb-3 flex items-center justify-between">
            <div class="text-xs text-white/70">按名次筛选（基于归一化值排名，1 为最佳）</div>
            <div class="text-xs text-white/50">每个特征设置名次条件；可选“第一名数量下限”</div>
          </div>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div v-for="f in features" :key="`feature-${f}`" class="flex items-center gap-2">
              <span class="w-36 truncate text-xs text-white/70" :title="f">{{ f }}</span>
              <div class="w-full flex flex-wrap items-center gap-1">
                <span class="text-xs text-white/50">名次</span>
                <n-select
                  v-model:value="ensureRankRule(f).operator"
                  :options="rankOperatorOptions"
                  size="small"
                  class="w-[88px]"
                />
                <n-input-number
                  v-model:value="ensureRankRule(f).value"
                  :min="1"
                  :precision="0"
                  size="small"
                  class="w-[90px]"
                />
                <n-button size="tiny" tertiary @click="clearRankRule(f)">清除</n-button>
              </div>
            </div>
          </div>

          <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-white/70">满足第一名(=1)的特征数量 ≥</span>
            <n-input-number
              v-model:value="firstPlaceMinCountProxy"
              :min="1"
              :precision="0"
              size="small"
              class="w-[100px]"
            />
            <span class="text-xs text-white/50">（留空表示不限制）</span>
          </div>
        </n-card>
      </div>

      <!-- 预览区 -->
      <div class="border border-white/10 rounded-lg bg-white/5 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="text-xs text-gray-300">
            符合条件的 Token：
            <span class="text-green-400 font-semibold">{{ matchedTokens.length }}</span>
            个；最终选取 Top {{ topNProxy }}：
            <span class="text-blue-400 font-semibold">{{ previewTokens.length }}</span>
            个
          </div>
          <div class="text-xs text-white/60">变更会实时预览，不会影响服务器</div>
        </div>
        <div v-if="previewTokens.length" class="mt-2 flex flex-wrap gap-2">
          <n-tag v-for="s in previewTokens" :key="`p-${s}`" size="small" type="info" round>{{ s }}</n-tag>
        </div>
      </div>
    </div>
  </n-card>
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import type { RoundFeatureMatrixResponse } from '@/types/prediction';
  import { useV3Conditions } from '@/composables/useV3Conditions';

  const props = defineProps<{ matrix: RoundFeatureMatrixResponse | null }>();
  const m = computed(() => props.matrix);

  const { topN, featureRank, firstPlaceMinCount, filterTokens, reset, saveToLocalStorage, loadFromLocalStorage } =
    useV3Conditions(() => m.value);

  const features = computed(() => m.value?.features ?? []);
  const topNProxy = computed({
    get: () => topN.value,
    set: (v: number) => (topN.value = v)
  });

  // 名次操作符选项
  const rankOperatorOptions = [
    { label: '<', value: 'lt' as const },
    { label: '≤', value: 'lte' as const },
    { label: '=', value: 'eq' as const },
    { label: '≥', value: 'gte' as const },
    { label: '>', value: 'gt' as const }
  ];

  // 确保存在某特征的名次条件对象
  type RankRule = { operator: 'lt' | 'lte' | 'eq' | 'gte' | 'gt'; value: number | null };
  const ensureRankRule = (feature: string): RankRule => {
    if (!featureRank.value[feature]) {
      featureRank.value[feature] = { operator: 'lte', value: null } as RankRule;
    }
    return featureRank.value[feature] as RankRule;
  };

  const clearRankRule = (feature: string) => {
    featureRank.value[feature] = null as unknown as RankRule | null;
  };

  const firstPlaceMinCountProxy = computed({
    get: () => firstPlaceMinCount.value ?? null,
    set: (v: number | null) => (firstPlaceMinCount.value = v ?? null)
  });

  // 已移除白/黑名单输入

  // 预览：符合条件与TopN
  const matchedTokens = computed(() => filterTokens());
  const previewTokens = computed(() => matchedTokens.value.slice(0, Math.max(1, topN.value)));

  // 统一按钮回调
  const onReset = () => {
    reset();
  };
  const onSave = () => saveToLocalStorage();

  // 初始化
  loadFromLocalStorage();
  // 白/黑名单已移除

  defineExpose({
    topN,
    featureRank,
    firstPlaceMinCount,
    filterTokens
  });
</script>

<style scoped></style>
