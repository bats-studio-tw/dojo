<template>
  <div class="overflow-auto rounded-lg border border-white/10">
    <table class="min-w-full text-sm">
      <thead class="bg-white/5">
        <tr>
          <th class="px-3 py-2 text-left">Token</th>
          <th v-for="f in features" :key="f" class="px-3 py-2 text-left">{{ f }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="t in tokens" :key="t" class="border-t border-white/5">
          <td class="px-3 py-2 font-medium">{{ t }}</td>
          <td v-for="f in features" :key="`${t}-${f}`" class="px-3 py-2">
            <div class="flex items-center gap-2">
              <span class="text-xs opacity-70">{{ getRaw(t, f) }}</span>
              <span class="text-xs" :class="normClass(getNorm(t, f))">{{ getNorm(t, f) }}</span>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <div v-if="!tokens.length" class="py-6 text-center text-xs opacity-60">暂无特征数据</div>
  <div v-if="updatedAt" class="mt-2 text-right text-xs opacity-60">
    更新于 {{ new Date(updatedAt).toLocaleTimeString() }}
  </div>
  <div v-if="error" class="mt-2 text-right text-xs text-red-400">{{ error }}</div>
</template>

<script setup lang="ts">
  import type { RoundFeatureMatrixResponse } from '@/types/prediction';

  interface Props {
    matrix: RoundFeatureMatrixResponse | null;
    error?: string | null;
    updatedAt?: number | null;
  }

  const props = defineProps<Props>();

  const tokens = computed(() => props.matrix?.tokens ?? []);
  const features = computed(() => props.matrix?.features ?? []);

  const getRaw = (token: string, feature: string) => {
    const v = props.matrix?.matrix?.[token]?.[feature]?.raw ?? null;
    return v === null ? '-' : Number(v).toFixed(3);
  };

  const getNorm = (token: string, feature: string) => {
    const v = props.matrix?.matrix?.[token]?.[feature]?.norm ?? null;
    return v === null ? '-' : Number(v).toFixed(3);
  };

  const normClass = (v: string) => {
    if (v === '-') return 'opacity-50';
    const n = Number(v);
    if (n >= 1) return 'text-green-400';
    if (n <= -1) return 'text-red-400';
    return 'text-gray-300';
  };
</script>

<style scoped></style>
