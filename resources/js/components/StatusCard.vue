<template>
  <div class="border rounded-lg p-4 text-center" :class="[`border-${color}-500/30`, `bg-${color}-500/10`]">
    <div class="mb-2 flex items-center justify-center space-x-2">
      <span v-if="icon" class="text-lg">{{ icon }}</span>
      <span :class="`text-${color}-400`" class="text-sm font-medium">{{ title }}</span>
    </div>

    <div class="text-xl text-white font-bold">{{ value }}</div>

    <div v-if="subtitle" class="text-xs text-gray-400">{{ subtitle }}</div>

    <slot name="extra" />
  </div>
</template>

<script setup lang="ts">
  interface Props {
    title: string;
    value: string | number;
    subtitle?: string;
    icon?: string;
    color?: 'blue' | 'green' | 'purple' | 'orange' | 'red' | 'yellow';
  }

  withDefaults(defineProps<Props>(), {
    color: 'blue'
  });
</script>
