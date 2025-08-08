<template>
  <div class="space-y-3">
    <div class="flex items-center gap-3">
      <div class="text-sm text-white/80">下注条件</div>
      <div class="flex items-center gap-2 text-xs">
        <span>Top</span>
        <n-slider v-model:value="topNProxy" :min="1" :max="5" :step="1" class="w-[140px]" />
        <span>{{ topNProxy }}</span>
      </div>
      <n-button size="small" tertiary @click="reset">重置</n-button>
      <n-button size="small" @click="saveToLocalStorage">保存</n-button>
    </div>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
      <n-card class="border border-white/10 bg-white/5">
        <div class="mb-2 text-xs text-white/70">特征最小阈值</div>
        <div class="grid grid-cols-2 gap-2">
          <div v-for="f in features" :key="`min-${f}`" class="flex items-center gap-2">
            <span class="text-xs text-white/70">{{ f }}</span>
            <n-input-number v-model:value="featureMin[f]" clearable :precision="3" class="w-full" />
          </div>
        </div>
      </n-card>

      <n-card class="border border-white/10 bg-white/5">
        <div class="mb-2 text-xs text-white/70">白/黑名单</div>
        <div class="space-y-2">
          <n-input v-model:value="whitelistText" type="text" placeholder="白名单, 逗号分隔 (e.g. BTC,ETH)" />
          <n-input v-model:value="blacklistText" type="text" placeholder="黑名单, 逗号分隔" />
        </div>
      </n-card>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, ref, watch } from 'vue';
  import { NCard, NButton, NSlider, NInput, NInputNumber } from 'naive-ui';
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

  // 初始化
  loadFromLocalStorage();

  defineExpose({
    topN,
    featureMin,
    featureMax,
    whitelist,
    blacklist,
    filterTokens
  });
</script>

<style scoped></style>
