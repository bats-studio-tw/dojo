<template>
  <div class="space-y-4 rounded-lg border border-white/10 p-4">
    <div class="flex items-center justify-between">
      <div class="text-sm font-medium">权重设置</div>
      <div class="text-xs opacity-60">特征数：{{ features.length }}</div>
    </div>

    <div v-if="!features.length" class="text-xs opacity-60">暂无特征可调</div>

    <div v-for="f in features" :key="f" class="grid grid-cols-5 items-center gap-3">
      <div class="col-span-1 text-xs">{{ f }}</div>
      <div class="col-span-3">
        <input type="range" min="-3" max="3" step="0.1" v-model.number="localWeights[f]" class="w-full" />
      </div>
      <div class="col-span-1 text-right text-xs">{{ localWeights[f]?.toFixed(2) ?? '0.00' }}</div>
    </div>

    <div class="flex items-center justify-between border-t border-white/10 pt-3">
      <div class="text-xs opacity-60">权重和：{{ weightSum.toFixed(2) }}</div>
      <div class="space-x-2">
        <button class="rounded bg-white/10 px-3 py-1 text-xs" @click="emit('applyPreset', 'conservative')">保守</button>
        <button class="rounded bg-white/10 px-3 py-1 text-xs" @click="emit('applyPreset', 'balanced')">平衡</button>
        <button class="rounded bg-white/10 px-3 py-1 text-xs" @click="emit('applyPreset', 'aggressive')">进攻</button>
        <button class="rounded bg-blue-500/20 px-3 py-1 text-xs" @click="save">保存</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, reactive, watch } from 'vue';

  interface Props {
    features: string[];
    weights: Record<string, number>;
  }
  const props = defineProps<Props>();
  const emit = defineEmits<{
    (e: 'update:weights', value: Record<string, number>): void;
    (e: 'applyPreset', key: string): void;
  }>();

  const localWeights = reactive<Record<string, number>>({});

  watch(
    () => props.weights,
    (w) => {
      Object.assign(localWeights, w || {});
      for (const f of props.features) if (!(f in localWeights)) localWeights[f] = 0;
    },
    { deep: true, immediate: true }
  );

  const weightSum = computed(() => Object.values(localWeights).reduce((a, b) => a + (b || 0), 0));

  const save = () => emit('update:weights', { ...localWeights });
</script>

<style scoped></style>
