<template>
  <DefaultLayout>
    <Head title="WebSocket 测试" />
    <div class="container mx-auto p-6">
      <h1 class="text-2xl font-bold mb-6">WebSocket 测试页面</h1>
      <RealTimeDataDisplay />
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { Head } from '@inertiajs/vue3';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import RealTimeDataDisplay from '@/components/RealTimeDataDisplay.vue';
</script>
