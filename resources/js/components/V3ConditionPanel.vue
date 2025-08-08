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

      <!-- 内容：左右分栏 -->
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <!-- 特征阈值 + 名次条件 -->
        <n-card size="small" class="border border-white/10 bg-white/5">
          <div class="mb-3 flex items-center justify-between">
            <div class="text-xs text-white/70">特征阈值与名次条件（归一化优先）</div>
            <div class="text-xs text-white/50">可设置 ≥ 最小值 / ≤ 最大值，或按名次筛选</div>
          </div>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div v-for="f in features" :key="`feature-${f}`" class="flex items-center gap-2">
              <span class="w-36 truncate text-xs text-white/70" :title="f">{{ f }}</span>
              <div class="w-full flex flex-wrap items-center gap-1">
                <n-input-number
                  v-model:value="featureMin[f]"
                  clearable
                  :precision="3"
                  placeholder="≥ 最小值"
                  class="w-full"
                />
                <n-input-number
                  v-model:value="featureMax[f]"
                  clearable
                  :precision="3"
                  placeholder="≤ 最大值"
                  class="w-full"
                />
                <span class="ml-1 text-xs text-white/50">名次</span>
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

        <!-- 白/黑名单 -->
        <n-card size="small" class="border border-white/10 bg-white/5">
          <div class="mb-3 text-xs text-white/70">白/黑名单（逗号分隔，自动转大写）</div>
          <div class="space-y-3">
            <n-input v-model:value="whitelistText" type="text" placeholder="白名单, 例如: BTC,ETH" />
            <div v-if="whitelist.length" class="flex flex-wrap gap-2">
              <n-tag v-for="t in whitelist" :key="`w-${t}`" size="small" type="success" round>{{ t }}</n-tag>
            </div>
            <n-input v-model:value="blacklistText" type="text" placeholder="黑名单, 例如: DOGE,PEPE" />
            <div v-if="blacklist.length" class="flex flex-wrap gap-2">
              <n-tag v-for="t in blacklist" :key="`b-${t}`" size="small" type="error" round>{{ t }}</n-tag>
            </div>
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
  import { computed, ref, watch } from 'vue';
  import type { RoundFeatureMatrixResponse } from '@/types/prediction';
  import { useV3Conditions } from '@/composables/useV3Conditions';

  const props = defineProps<{ matrix: RoundFeatureMatrixResponse | null }>();
  const m = computed(() => props.matrix);

  const {
    topN,
    featureMin,
    featureMax,
    whitelist,
    blacklist,
    featureRank,
    firstPlaceMinCount,
    filterTokens,
    reset,
    saveToLocalStorage,
    loadFromLocalStorage
  } = useV3Conditions(() => m.value);

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

  const whitelistText = ref('');
  const blacklistText = ref('');
  watch(
    () => whitelistText.value,
    (t) =>
      (whitelist.value = t
        .split(',')
        .map((x) => x.trim().toUpperCase())
        .filter(Boolean))
  );
  watch(
    () => blacklistText.value,
    (t) =>
      (blacklist.value = t
        .split(',')
        .map((x) => x.trim().toUpperCase())
        .filter(Boolean))
  );

  // 预览：符合条件与TopN
  const matchedTokens = computed(() => filterTokens());
  const previewTokens = computed(() => matchedTokens.value.slice(0, Math.max(1, topN.value)));

  // 统一按钮回调
  const onReset = () => {
    reset();
    whitelistText.value = '';
    blacklistText.value = '';
  };
  const onSave = () => saveToLocalStorage();

  // 初始化
  loadFromLocalStorage();
  // 将本地已存的黑白名单回显到输入框
  whitelistText.value = (whitelist.value || []).join(',');
  blacklistText.value = (blacklist.value || []).join(',');

  defineExpose({
    topN,
    featureMin,
    featureMax,
    whitelist,
    blacklist,
    featureRank,
    firstPlaceMinCount,
    filterTokens
  });
</script>

<style scoped></style>
