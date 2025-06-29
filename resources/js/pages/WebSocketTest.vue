<template>
  <DefaultLayout>
    <Head title="WebSocket 测试" />
    <div class="mx-auto p-6 container">
      <h1 class="mb-6 text-2xl font-bold">WebSocket 测试页面</h1>
      <RealTimeDataDisplay />
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { Head } from '@inertiajs/vue3';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import RealTimeDataDisplay from '@/components/RealTimeDataDisplay.vue';
</script>
