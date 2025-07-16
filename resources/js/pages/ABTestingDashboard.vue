<template>
  <DefaultLayout>
    <div class="ab-testing-dashboard">
      <!-- 頁面標題 -->
      <div class="page-header">
        <h1 class="text-2xl text-gray-900 font-bold">A/B 測試管理面板</h1>
        <p class="mt-2 text-gray-600">科學化策略對比與流量分配實驗平台</p>
      </div>

      <!-- 標籤頁導航 -->
      <NTabs v-model:value="activeTab" type="line" size="large" class="mb-6">
        <NTabPane name="create" tab="創建測試">
          <div class="tab-content">
            <CreateABTestForm @test-created="handleTestCreated" />
          </div>
        </NTabPane>

        <NTabPane name="monitor" tab="測試監控">
          <div class="tab-content">
            <ABTestMonitor @test-selected="handleTestSelected" />
          </div>
        </NTabPane>

        <NTabPane name="reports" tab="測試報告">
          <div class="tab-content">
            <ABTestReports :selected-test="selectedTest" />
          </div>
        </NTabPane>
      </NTabs>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { ref } from 'vue';

  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import CreateABTestForm from '@/components/ab-testing/CreateABTestForm.vue';
  import ABTestMonitor from '@/components/ab-testing/ABTestMonitor.vue';
  import ABTestReports from '@/components/ab-testing/ABTestReports.vue';

  // 響應式數據
  const activeTab = ref('create');
  const selectedTest = ref<any>(null);
  const message = (window as any).$message;

  // 事件處理
  const handleTestCreated = (testId: number) => {
    message.success('A/B測試創建成功！');
    activeTab.value = 'monitor';
    // 可以傳遞測試ID到監控頁面
  };

  const handleTestSelected = (test: any) => {
    selectedTest.value = test;
    activeTab.value = 'reports';
  };
</script>

<style scoped>
  .ab-testing-dashboard {
    @apply max-w-7xl mx-auto px-4 py-6;
  }

  .page-header {
    @apply mb-8;
  }

  .tab-content {
    @apply bg-white rounded-lg shadow-sm p-6;
  }
</style>
