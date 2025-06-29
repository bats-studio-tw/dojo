<template>
  <div class="space-y-6">
    <!-- 预测统计分析 -->
    <div class="mb-6">
      <PredictionStats
        :exact-rate="exactRate"
        :total-rounds="totalRounds"
        :all-stats="allStats"
        :recent-stats="recentStats"
        :recent-rounds-count="recentRoundsCount"
        @update:recent-rounds-count="$emit('updateRecentRoundsCount', $event)"
        :max-rounds="maxRounds"
        :loading="historyLoading"
        @refresh="$emit('refreshPredictionHistory')"
      />
    </div>

    <!-- 预测历史对比表格 -->
    <div class="mb-6">
      <PredictionHistoryTable
        :prediction-data="predictionComparisonData"
        :loading="historyLoading"
        @refresh="$emit('refreshPredictionHistory')"
      />
    </div>

    <!-- 自动下注记录 -->
    <NCard
      class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
      title="📊 自动下注历史记录"
      size="large"
    >
      <div class="space-y-4">
        <!-- 筛选和控制 -->
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <n-select
              v-model:value="recordFilter"
              :options="[
                { label: '全部记录', value: 'all' },
                { label: '成功下注', value: 'success' },
                { label: '失败下注', value: 'failed' },
                { label: '今日记录', value: 'today' },
                { label: '本周记录', value: 'week' }
              ]"
              style="width: 150px"
              size="small"
            />
            <n-input
              v-model:value="searchKeyword"
              placeholder="搜索游戏代币..."
              clearable
              size="small"
              style="width: 200px"
            />
          </div>
          <div class="flex items-center space-x-2">
            <n-button @click="refreshBettingRecords" :loading="recordsLoading" type="primary" size="small">
              <template #icon>
                <span>🔄</span>
              </template>
              刷新记录
            </n-button>
            <n-button @click="exportBettingRecords" type="info" size="small">
              <template #icon>
                <span>📥</span>
              </template>
              导出数据
            </n-button>
          </div>
        </div>

        <!-- 下注记录统计卡片 -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
          <div class="border border-blue-500/30 rounded-lg bg-blue-500/10 p-3 text-center">
            <div class="text-sm text-blue-400">总下注次数</div>
            <div class="text-xl text-white font-bold">{{ bettingStats.totalBets }}</div>
            <div class="text-xs text-gray-400">历史累计</div>
          </div>
          <div class="border border-green-500/30 rounded-lg bg-green-500/10 p-3 text-center">
            <div class="text-sm text-green-400">成功率</div>
            <div class="text-xl text-white font-bold">{{ bettingStats.successRate.toFixed(1) }}%</div>
            <div class="text-xs text-gray-400">成功/总计</div>
          </div>
          <div class="border border-purple-500/30 rounded-lg bg-purple-500/10 p-3 text-center">
            <div class="text-sm text-purple-400">总盈亏</div>
            <div
              class="text-xl font-bold"
              :class="bettingStats.totalProfitLoss >= 0 ? 'text-green-400' : 'text-red-400'"
            >
              ${{ bettingStats.totalProfitLoss.toFixed(2) }}
            </div>
            <div class="text-xs text-gray-400">累计收益</div>
          </div>
          <div class="border border-orange-500/30 rounded-lg bg-orange-500/10 p-3 text-center">
            <div class="text-sm text-orange-400">平均收益</div>
            <div
              class="text-xl font-bold"
              :class="bettingStats.avgProfitPerBet >= 0 ? 'text-green-400' : 'text-red-400'"
            >
              ${{ bettingStats.avgProfitPerBet.toFixed(2) }}
            </div>
            <div class="text-xs text-gray-400">每次下注</div>
          </div>
        </div>

        <!-- 下注记录表格 -->
        <div class="rounded-lg bg-black/30 p-4">
          <n-data-table
            :columns="recordColumns"
            :data="filteredBettingRecords"
            :loading="recordsLoading"
            :pagination="pagination"
            :scroll-x="800"
            size="small"
            class="betting-records-table"
          />
        </div>

        <!-- 空状态 -->
        <div v-if="!recordsLoading && filteredBettingRecords.length === 0" class="text-center py-8 text-gray-400">
          <div class="text-2xl mb-2">📝</div>
          <div class="text-sm">暂无下注记录</div>
          <div class="text-xs text-gray-500 mt-1">开始自动下注后，记录将在此显示</div>
        </div>
      </div>
    </NCard>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed } from 'vue';
  import { NDataTable } from 'naive-ui';
  import PredictionStats from './PredictionStats.vue';
  import PredictionHistoryTable from './PredictionHistoryTable.vue';

  // Props
  interface Props {
    exactRate: number;
    totalRounds: number;
    allStats: any;
    recentStats: any;
    recentRoundsCount: number;
    maxRounds: number;
    historyLoading: boolean;
    predictionComparisonData: any[];
  }

  const props = defineProps<Props>();

  // Emits
  const emit = defineEmits<{
    refreshPredictionHistory: [];
    updateRecentRoundsCount: [value: number];
  }>();

  // 响应式数据
  const recordFilter = ref('all');
  const searchKeyword = ref('');
  const recordsLoading = ref(false);

  // 模拟下注记录数据
  const bettingRecords = ref([
    {
      id: 1,
      date: '2024-01-15 14:30:25',
      roundId: 'R20240115001',
      tokenSymbol: 'BTC',
      amount: 200,
      success: true,
      profitLoss: 190,
      confidence: 92.5,
      strategy: '单项下注'
    },
    {
      id: 2,
      date: '2024-01-15 14:45:12',
      roundId: 'R20240115002',
      tokenSymbol: 'ETH',
      amount: 200,
      success: false,
      profitLoss: -200,
      confidence: 88.2,
      strategy: '单项下注'
    },
    {
      id: 3,
      date: '2024-01-15 15:00:08',
      roundId: 'R20240115003',
      tokenSymbol: 'SOL',
      amount: 300,
      success: true,
      profitLoss: 285,
      confidence: 95.1,
      strategy: '多项下注'
    }
  ]);

  // 下注统计
  const bettingStats = computed(() => {
    const records = bettingRecords.value;
    const totalBets = records.length;
    const successfulBets = records.filter((r) => r.success).length;
    const successRate = totalBets > 0 ? (successfulBets / totalBets) * 100 : 0;
    const totalProfitLoss = records.reduce((sum, r) => sum + r.profitLoss, 0);
    const avgProfitPerBet = totalBets > 0 ? totalProfitLoss / totalBets : 0;

    return {
      totalBets,
      successRate,
      totalProfitLoss,
      avgProfitPerBet
    };
  });

  // 过滤后的下注记录
  const filteredBettingRecords = computed(() => {
    let filtered = bettingRecords.value;

    // 按状态筛选
    if (recordFilter.value === 'success') {
      filtered = filtered.filter((r) => r.success);
    } else if (recordFilter.value === 'failed') {
      filtered = filtered.filter((r) => !r.success);
    } else if (recordFilter.value === 'today') {
      const today = new Date().toDateString();
      filtered = filtered.filter((r) => new Date(r.date).toDateString() === today);
    } else if (recordFilter.value === 'week') {
      const weekAgo = new Date();
      weekAgo.setDate(weekAgo.getDate() - 7);
      filtered = filtered.filter((r) => new Date(r.date) >= weekAgo);
    }

    // 按关键词搜索
    if (searchKeyword.value) {
      const keyword = searchKeyword.value.toLowerCase();
      filtered = filtered.filter(
        (r) => r.tokenSymbol.toLowerCase().includes(keyword) || r.roundId.toLowerCase().includes(keyword)
      );
    }

    return filtered;
  });

  // 表格列定义
  const recordColumns = [
    {
      title: '时间',
      key: 'date',
      width: 150,
      render: (row: any) => new Date(row.date).toLocaleString()
    },
    {
      title: '轮次ID',
      key: 'roundId',
      width: 120
    },
    {
      title: '代币',
      key: 'tokenSymbol',
      width: 80
    },
    {
      title: '下注金额',
      key: 'amount',
      width: 100,
      render: (row: any) => `$${row.amount}`
    },
    {
      title: '状态',
      key: 'success',
      width: 80,
      render: (row: any) => (row.success ? '✅ 成功' : '❌ 失败')
    },
    {
      title: '盈亏',
      key: 'profitLoss',
      width: 100,
      render: (row: any) => {
        const color = row.profitLoss >= 0 ? 'text-green-400' : 'text-red-400';
        const prefix = row.profitLoss >= 0 ? '+' : '';
        return `<span class="${color}">${prefix}$${row.profitLoss}</span>`;
      }
    },
    {
      title: '置信度',
      key: 'confidence',
      width: 100,
      render: (row: any) => `${row.confidence}%`
    },
    {
      title: '策略',
      key: 'strategy',
      width: 100
    }
  ];

  // 分页配置
  const pagination = {
    pageSize: 10,
    showSizePicker: true,
    pageSizes: [10, 20, 50]
  };

  // 方法
  const refreshBettingRecords = async () => {
    recordsLoading.value = true;
    try {
      // 模拟API调用
      await new Promise((resolve) => setTimeout(resolve, 1000));
      // 这里应该调用真实的API来获取下注记录
      console.log('刷新下注记录');
    } catch (error) {
      console.error('刷新下注记录失败:', error);
    } finally {
      recordsLoading.value = false;
    }
  };

  const exportBettingRecords = () => {
    // 实现数据导出功能
    const dataStr = JSON.stringify(filteredBettingRecords.value, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `betting-records-${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    URL.revokeObjectURL(url);
    window.$message?.success('数据导出成功');
  };
</script>

<style scoped>
  :deep(.betting-records-table .n-data-table-td) {
    background: rgba(0, 0, 0, 0.2);
    border-color: rgba(255, 255, 255, 0.1);
  }

  :deep(.betting-records-table .n-data-table-th) {
    background: rgba(0, 0, 0, 0.3);
    border-color: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
  }

  :deep(.betting-records-table .n-data-table-tr:hover .n-data-table-td) {
    background: rgba(255, 255, 255, 0.05);
  }
</style>
