<template>
  <div class="prediction-dashboard">
    <DefaultLayout>
      <div class="container mx-auto px-4 py-6">
        <!-- 页面标题 -->
        <div class="mb-6">
          <h1 class="text-3xl font-bold text-gray-900 mb-2">AI预测系统</h1>
          <p class="text-gray-600">新一代多特征融合预测引擎，支持策略切换与A/B测试</p>
        </div>

        <!-- 错误提示 -->
        <n-alert
          v-if="store.error"
          type="error"
          :title="'操作失败'"
          :description="store.error"
          class="mb-4"
          closable
          @close="store.clearError()"
        />

        <!-- 策略选择器 -->
        <StrategySelector
          :strategies="store.availableStrategies"
          :is-loading="store.isLoading"
          @execute="handleExecute"
          @backtest="handleBacktest"
        />

        <!-- 预测结果展示 -->
        <div v-if="store.hasResults" class="space-y-6">
          <!-- 预测结果表格 -->
          <PredictionResultTable :results="store.results" :is-loading="store.isLoading" />

          <!-- 预测分数图表 -->
          <PredictionScoreChart :results="store.results" />
        </div>

        <!-- 回测结果展示 -->
        <BacktestResultDisplay v-if="store.backtestResults" :backtest-result="store.backtestResults" />

        <!-- 历史预测记录 -->
        <div v-if="showHistory" class="mt-6">
          <n-card title="历史预测记录" class="mb-4">
            <div class="flex justify-between items-center mb-4">
              <div class="flex gap-2">
                <n-select
                  v-model:value="historyFilter.strategy_tag"
                  :options="strategyFilterOptions"
                  placeholder="选择策略"
                  clearable
                  class="w-40"
                />
                <n-date-picker
                  v-model:value="historyFilter.dateRange"
                  type="daterange"
                  placeholder="选择日期范围"
                  clearable
                  class="w-60"
                />
              </div>
              <n-button @click="loadHistory">
                <template #icon>
                  <n-icon><Refresh /></n-icon>
                </template>
                刷新历史
              </n-button>
            </div>

            <PredictionResultTable :results="store.predictionHistory" :is-loading="false" />
          </n-card>
        </div>

        <!-- 操作按钮 -->
        <div class="flex gap-3 mt-6">
          <n-button type="info" ghost @click="toggleHistory">
            <template #icon>
              <n-icon><TimeOutline /></n-icon>
            </template>
            {{ showHistory ? '隐藏历史' : '查看历史' }}
          </n-button>

          <n-button type="warning" ghost @click="clearAll">
            <template #icon>
              <n-icon><TrashOutline /></n-icon>
            </template>
            清除所有
          </n-button>
        </div>
      </div>
    </DefaultLayout>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed, onMounted } from 'vue';
  import { useMessage, useDialog } from 'naive-ui';
  import { Refresh, TimeOutline, TrashOutline } from '@vicons/ionicons5';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import StrategySelector from './StrategySelector.vue';
  import PredictionResultTable from './PredictionResultTable.vue';
  import PredictionScoreChart from './PredictionScoreChart.vue';
  import BacktestResultDisplay from './BacktestResultDisplay.vue';
  import { usePredictionStore } from '@/stores/prediction';

  // 使用store
  const store = usePredictionStore();
  const message = useMessage();
  const dialog = useDialog();

  // 响应式数据
  const showHistory = ref(false);
  const historyFilter = ref({
    strategy_tag: null as string | null,
    dateRange: null as [string, string] | null
  });

  // 计算属性
  const strategyFilterOptions = computed(() =>
    store.availableStrategies.map((strategy) => ({
      label: strategy.name,
      value: strategy.tag
    }))
  );

  // 方法
  const handleExecute = async (strategy: string, tokens: string[]) => {
    try {
      await store.runPrediction(strategy, tokens);
      message.success('预测执行成功');
    } catch (error: any) {
      message.error(error.message || '预测执行失败');
    }
  };

  const handleBacktest = async (strategy: string) => {
    try {
      await store.runBacktest({
        strategy_tag: strategy,
        rounds: 100 // 默认回测100轮
      });
      message.success('回测执行成功');
    } catch (error: any) {
      message.error(error.message || '回测执行失败');
    }
  };

  const loadHistory = async () => {
    try {
      const options: any = {};

      if (historyFilter.value.strategy_tag) {
        options.strategy_tag = historyFilter.value.strategy_tag;
      }

      if (historyFilter.value.dateRange) {
        options.start_date = historyFilter.value.dateRange[0];
        options.end_date = historyFilter.value.dateRange[1];
      }

      await store.fetchPredictionHistory(options);
      message.success('历史数据加载成功');
    } catch (error: any) {
      message.error(error.message || '历史数据加载失败');
    }
  };

  const toggleHistory = () => {
    showHistory.value = !showHistory.value;
    if (showHistory.value && store.predictionHistory.length === 0) {
      loadHistory();
    }
  };

  const clearAll = () => {
    dialog.warning({
      title: '确认清除',
      content: '确定要清除所有预测结果和回测数据吗？此操作不可撤销。',
      positiveText: '确定',
      negativeText: '取消',
      onPositiveClick: () => {
        store.clearResults();
        store.backtestResults = null;
        message.success('数据已清除');
      }
    });
  };

  // 生命周期
  onMounted(async () => {
    try {
      await store.fetchStrategies();
    } catch (error: any) {
      message.error('加载策略列表失败');
    }
  });
</script>

<style scoped>
  .prediction-dashboard {
    min-height: 100vh;
    background-color: #f8fafc;
  }
</style>
