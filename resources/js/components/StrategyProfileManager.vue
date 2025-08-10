<template>
  <div class="space-y-3 rounded-lg border border-white/10 p-4">
    <div class="flex items-center justify-between">
      <div class="text-sm font-medium">策略配置</div>
      <div class="text-xs opacity-60">{{ profiles.length }} 个</div>
    </div>

    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
      <div
        v-for="p in profiles"
        :key="p.id"
        class="flex items-center justify-between rounded border border-white/10 p-3"
      >
        <div class="text-xs">
          <div class="font-medium">{{ p.name }}</div>
          <div class="opacity-60">默认：{{ p.is_default ? '是' : '否' }}</div>
        </div>
        <div class="space-x-2">
          <button class="rounded bg-white/10 px-3 py-1 text-xs" @click="$emit('load', p.id)">加载</button>
          <button class="rounded bg-blue-500/20 px-3 py-1 text-xs" @click="$emit('saveAs', p.id)">另存</button>
          <button class="rounded bg-red-500/20 px-3 py-1 text-xs" @click="$emit('remove', p.id)">删除</button>
        </div>
      </div>
    </div>

    <div class="border-t border-white/10 pt-3">
      <div class="flex items-center gap-2">
        <input
          v-model="newName"
          placeholder="新策略名称"
          class="w-full rounded bg-black/20 px-3 py-2 text-xs outline-none"
        />
        <button class="rounded bg-green-500/20 px-3 py-2 text-xs" @click="create">创建</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ref } from 'vue';

  interface StrategyProfile {
    id: number | string;
    name: string;
    is_default?: boolean;
  }

  defineProps<{ profiles: StrategyProfile[] }>();
  const emit = defineEmits<{
    (e: 'create', name: string): void;
    (e: 'load', id: number | string): void;
    (e: 'saveAs', id?: number | string): void;
    (e: 'remove', id: number | string): void;
  }>();

  const newName = ref('');
  const create = () => {
    if (!newName.value.trim()) return;
    emit('create', newName.value.trim());
    newName.value = '';
  };
</script>

<style scoped></style>
