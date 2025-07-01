<template>
  <div class="space-y-6">
    <BettingPerformanceAnalysis :uid="getCurrentUID()" />

    <!-- 预测统计分析 -->
    <PredictionStats
      class="mb-6"
      :exact-rate="exactRate"
      :total-rounds="totalRounds"
      :all-stats="allStats"
      :recent-stats="recentStats"
      :recent-rounds-count="recentRoundsCount"
      @update:recent-rounds-count="$emit('update:recent-rounds-count', $event)"
      :max-rounds="maxRounds"
      :loading="historyLoading"
      @refresh="$emit('refreshPredictionHistory')"
    />

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

        <!-- 下注记录统计卡片 - 参考其他组件配色 -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
          <div
            class="border border-cyan-500/30 rounded-lg from-cyan-500/10 to-blue-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-cyan-400/50 hover:shadow-cyan-500/20"
          >
            <div class="text-sm text-cyan-300">总下注次数</div>
            <div class="text-xl text-cyan-400 font-bold">{{ bettingStats.totalBets }}</div>
            <div class="text-xs text-cyan-200/70">历史累计</div>
          </div>
          <div
            class="border border-emerald-500/30 rounded-lg from-emerald-500/10 to-green-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-emerald-400/50 hover:shadow-emerald-500/20"
          >
            <div class="text-sm text-emerald-300">成功率</div>
            <div class="text-xl text-emerald-400 font-bold">{{ bettingStats.successRate.toFixed(1) }}%</div>
            <div class="text-xs text-emerald-200/70">成功/总计</div>
          </div>
          <div
            class="border border-violet-500/30 rounded-lg from-violet-500/10 to-purple-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-violet-400/50 hover:shadow-violet-500/20"
          >
            <div class="text-sm text-violet-300">总盈亏</div>
            <div
              class="text-xl font-bold"
              :class="bettingStats.totalProfitLoss >= 0 ? 'text-emerald-400' : 'text-red-400'"
            >
              ${{ bettingStats.totalProfitLoss.toFixed(2) }}
            </div>
            <div class="text-xs text-violet-200/70">累计收益</div>
          </div>
          <div
            class="border border-amber-500/30 rounded-lg from-amber-500/10 to-orange-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-amber-400/50 hover:shadow-amber-500/20"
          >
            <div class="text-sm text-amber-300">平均收益</div>
            <div
              class="text-xl font-bold"
              :class="bettingStats.avgProfitPerBet >= 0 ? 'text-emerald-400' : 'text-red-400'"
            >
              ${{ bettingStats.avgProfitPerBet.toFixed(2) }}
            </div>
            <div class="text-xs text-amber-200/70">每次下注</div>
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
        <div v-if="!recordsLoading && filteredBettingRecords.length === 0" class="py-8 text-center text-gray-400">
          <div class="mb-2 text-2xl">📝</div>
          <div class="text-sm">暂无下注记录</div>
          <div class="mt-1 text-xs text-gray-500">开始自动下注后，记录将在此显示</div>
        </div>
      </div>
    </NCard>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed, onMounted } from 'vue';
  import { NDataTable } from 'naive-ui';
  import PredictionStats from './PredictionStats.vue';
  import BettingPerformanceAnalysis from './BettingPerformanceAnalysis.vue';
  import { autoBettingApi } from '@/utils/api';

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

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const props = defineProps<Props>();

  // Emits
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const emit = defineEmits<{
    refreshPredictionHistory: [];
    'update:recent-rounds-count': [value: number];
  }>();

  // 响应式数据
  const recordFilter = ref('all');
  const searchKeyword = ref('');
  const recordsLoading = ref(false);
  const bettingRecords = ref<any[]>([]);

  // 获取当前用户UID
  const getCurrentUID = () => {
    return localStorage.getItem('currentUID') || '';
  };

  // 获取下注记录
  const fetchBettingRecords = async () => {
    recordsLoading.value = true;
    try {
      const uid = getCurrentUID();
      if (!uid) {
        console.warn('未找到用户UID，无法获取下注记录');
        return;
      }

      const response = await autoBettingApi.getStatus(uid);
      if (response.data.success) {
        // 获取历史记录
        const historyResponse = await fetch(`/api/auto-betting/history?uid=${uid}`);
        if (historyResponse.ok) {
          const historyData = await historyResponse.json();
          if (historyData.success) {
            bettingRecords.value = historyData.data.map((record: any) => ({
              id: record.id,
              date: record.created_at,
              roundId: record.round_id,
              tokenSymbol: record.token_symbol,
              amount: parseFloat(record.bet_amount),
              success: record.success,
              profitLoss: parseFloat(record.profit_loss || 0),
              confidence: 0, // 需要从prediction_data中提取
              strategy: record.prediction_data?.strategy || '未知策略'
            }));
          }
        }
      }
    } catch (error) {
      console.error('获取下注记录失败:', error);
      window.$message?.error('获取下注记录失败');
    } finally {
      recordsLoading.value = false;
    }
  };

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

    // 按筛选条件过滤
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

    // 按搜索关键词过滤
    if (searchKeyword.value) {
      const keyword = searchKeyword.value.toLowerCase();
      filtered = filtered.filter((r) => r.tokenSymbol.toLowerCase().includes(keyword));
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
    await fetchBettingRecords();
  };

  const exportBettingRecords = () => {
    // 导出功能实现
    const data = filteredBettingRecords.value;
    const csv = [
      ['时间', '轮次ID', '代币', '下注金额', '状态'],
      ...data.map((record) => [
        new Date(record.date).toLocaleString(),
        record.roundId,
        record.tokenSymbol,
        record.amount,
        record.success ? '成功' : '失败'
      ])
    ]
      .map((row) => row.join(','))
      .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `betting_records_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // 组件挂载时获取数据
  onMounted(() => {
    fetchBettingRecords();
  });
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
