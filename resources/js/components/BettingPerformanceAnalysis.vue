<template>
  <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="📊 投注表现分析" size="large">
    <div class="space-y-4">
      <!-- 分析控制面板 -->
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="flex items-center space-x-2">
            <label class="text-sm text-gray-300">分析周期:</label>
            <n-select
              v-model:value="selectedDays"
              :options="dayOptions"
              size="small"
              class="w-32"
              @update:value="refreshOnDaysChange"
            />
          </div>
          <n-button @click="refreshAnalysis" :loading="loading" type="primary" size="small">
            <template #icon>
              <span>🔄</span>
            </template>
            刷新分析
          </n-button>
        </div>
      </div>

      <!-- 统计卡片 -->
      <div v-if="bettingRecords.length > 0" class="grid grid-cols-2 gap-4 md:grid-cols-5">
        <div
          class="border border-cyan-500/30 rounded-lg from-cyan-500/10 to-blue-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-cyan-400/50 hover:shadow-cyan-500/20"
        >
          <div class="text-xs text-cyan-300">总下注次数</div>
          <div class="text-xl text-cyan-400 font-bold">{{ bettingStats.totalBets }}</div>
          <div class="text-xs text-cyan-200/70">包括失败下注</div>
        </div>
        <div
          class="border border-blue-500/30 rounded-lg from-blue-500/10 to-indigo-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-blue-400/50 hover:shadow-blue-500/20"
        >
          <div class="text-xs text-blue-300">已结算次数</div>
          <div class="text-xl text-blue-400 font-bold">{{ bettingStats.settledBets }}</div>
          <div class="text-xs text-blue-200/70">有结果数据</div>
        </div>
        <div
          class="border border-emerald-500/30 rounded-lg from-emerald-500/10 to-green-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-emerald-400/50 hover:shadow-emerald-500/20"
        >
          <div class="text-xs text-emerald-300">盈利次数</div>
          <div class="text-xl text-emerald-400 font-bold">{{ bettingStats.successfulBets }}</div>
          <div class="text-xs text-emerald-200/70">前三名</div>
        </div>
        <div
          class="border border-red-500/30 rounded-lg from-red-500/10 to-pink-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-red-400/50 hover:shadow-red-500/20"
        >
          <div class="text-xs text-red-300">亏损次数</div>
          <div class="text-xl text-red-400 font-bold">{{ bettingStats.failedBets }}</div>
          <div class="text-xs text-red-200/70">第四名及以后</div>
        </div>
        <div
          class="border border-violet-500/30 rounded-lg from-violet-500/10 to-purple-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-violet-400/50 hover:shadow-violet-500/20"
        >
          <div class="text-xs text-violet-300">胜率</div>
          <div class="text-xl text-violet-400 font-bold">{{ bettingStats.successRate.toFixed(1) }}%</div>
          <div class="text-xs text-violet-200/70">前三名比例</div>
        </div>
      </div>

      <!-- 筛选和控制 -->
      <div v-if="bettingRecords.length > 0" class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <n-select
            v-model:value="recordFilter"
            :options="[
              { label: '全部记录', value: 'all' },
              { label: '盈利记录', value: 'success' },
              { label: '亏损记录', value: 'failed' },
              { label: '已结算', value: 'settled' },
              { label: '未结算', value: 'unsettled' }
            ]"
            style="width: 150px"
            size="small"
          />
          <n-input
            v-model:value="searchKeyword"
            placeholder="搜索代币..."
            clearable
            size="small"
            style="width: 200px"
          />
        </div>
        <div class="flex items-center space-x-2">
          <n-button @click="exportBettingRecords" type="info" size="small">
            <template #icon>
              <span>📥</span>
            </template>
            导出数据
          </n-button>
        </div>
      </div>

      <!-- 投注记录表格 -->
      <div v-if="bettingRecords.length > 0" class="rounded-lg bg-black/30 p-4">
        <n-data-table
          :columns="recordColumns"
          :data="filteredBettingRecords"
          :loading="loading"
          :pagination="pagination"
          :scroll-x="600"
          size="small"
          class="betting-records-table"
        />
      </div>

      <!-- 加载状态 -->
      <div v-if="loading" class="flex items-center justify-center py-8">
        <div class="flex items-center text-cyan-400 space-x-2">
          <div class="h-4 w-4 animate-spin border-2 border-cyan-400 border-t-transparent rounded-full"></div>
          <span class="text-sm">正在分析投注表现...</span>
        </div>
      </div>

      <!-- 无数据状态 -->
      <div v-if="!loading && bettingRecords.length === 0" class="py-8 text-center text-gray-400">
        <div class="mb-2 text-2xl">📊</div>
        <div class="text-sm">暂无投注记录</div>
        <div class="mt-1 text-xs text-gray-500">开始自动下注后，数据将在此显示</div>
      </div>
    </div>
  </NCard>
</template>

<script setup lang="ts">
  import { ref, onMounted, computed } from 'vue';
  import { NSelect, NButton, NDataTable, NInput, NCard } from 'naive-ui';
  import { bettingAnalysisApi } from '@/utils/api';
  import { handleError } from '@/utils/errorHandler';

  // Props
  interface Props {
    uid: string;
  }

  const props = defineProps<Props>();

  // 响应式数据
  const loading = ref(false);
  const bettingRecords = ref<any[]>([]);
  const selectedDays = ref(-1); // 默认显示全部历史，与自动下注状态保持一致
  const recordFilter = ref('all');
  const searchKeyword = ref('');
  const backendStats = ref<any>({});

  // 天数选项 - 确保所有值都符合后端验证要求（-1或大于0的天数）
  const dayOptions = [
    { label: '7天', value: 7 },
    { label: '30天', value: 30 },
    { label: '90天', value: 90 },
    { label: '180天', value: 180 },
    { label: '365天', value: 365 },
    { label: '全部历史', value: -1 }
  ];

  // 格式化日期
  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('zh-CN', {
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  // 表格列定义
  const recordColumns = [
    {
      title: '时间',
      key: 'created_at',
      width: 120,
      render: (row: any) => formatDate(row.created_at)
    },
    {
      title: '轮次',
      key: 'round_id',
      width: 100,
      render: (row: any) => row.round_id.slice(-6)
    },
    {
      title: '代币',
      key: 'token_symbol',
      width: 80,
      render: (row: any) => row.token_symbol
    },
    {
      title: '下注金额',
      key: 'bet_amount',
      width: 100,
      render: (row: any) => (row.bet_amount ? `$${row.bet_amount.toFixed(2)}` : '-')
    },
    {
      title: '预测排名',
      key: 'predicted_rank',
      width: 100,
      render: (row: any) => row.predicted_rank || '-'
    },
    {
      title: '实际排名',
      key: 'actual_rank',
      width: 100,
      render: (row: any) => row.actual_rank || '⏳'
    },

    {
      title: '状态',
      key: 'status',
      width: 80,
      render: (row: any) => {
        if (!row.success) return '❌ 下注失败';
        if (!row.actual_rank) return '⏳ 待结算';

        // 🎯 以排名为主要成功标准：只有前三名才算盈利
        return row.actual_rank <= 3 ? '✅ 盈利' : '📉 亏损';
      }
    }
  ];

  // 分页配置
  const pagination = {
    pageSize: 20,
    showSizePicker: true,
    pageSizes: [10, 20, 50, 100]
  };

  // 计算投注统计数据
  const bettingStats = computed(() => {
    // 优先使用后端统计数据
    if (backendStats.value && Object.keys(backendStats.value).length > 0) {
      const stats = backendStats.value;
      return {
        totalBets: stats.total_bets || 0, // 真实的总下注次数（包括成功和失败的）
        successfulBets:
          (stats.betting_distribution?.winning_bets || 0) + (stats.betting_distribution?.break_even_bets || 0), // 盈利+保本的下注
        failedBets: stats.betting_distribution?.losing_bets || 0, // 亏损的下注
        successRate: stats.win_rate_percentage || 0, // 后端计算的胜率（前三名比例）
        settledBets: stats.settled_bets || 0 // 有实际结果的下注数
      };
    }

    // 兜底：如果没有后端数据，使用前端计算（但提示数据可能不完整）
    const settledRecords = bettingRecords.value.filter((r) => r.success && r.actual_rank !== null);
    const allRecords = bettingRecords.value;
    const successfulBets = settledRecords.filter((r) => r.actual_rank <= 3).length;
    const failedBets = settledRecords.filter((r) => r.actual_rank > 3).length;
    const successRate = settledRecords.length > 0 ? (successfulBets / settledRecords.length) * 100 : 0;

    return {
      totalBets: allRecords.length, // 所有记录数
      successfulBets,
      failedBets,
      successRate,
      settledBets: settledRecords.length
    };
  });

  // 过滤后的投注记录
  const filteredBettingRecords = computed(() => {
    let filtered = bettingRecords.value;

    // 按状态过滤
    if (recordFilter.value === 'success') {
      filtered = filtered.filter((r) => r.success && r.actual_rank && r.actual_rank <= 3);
    } else if (recordFilter.value === 'failed') {
      filtered = filtered.filter((r) => !r.success || (r.actual_rank && r.actual_rank > 3));
    } else if (recordFilter.value === 'settled') {
      filtered = filtered.filter((r) => r.success && r.actual_rank !== null);
    } else if (recordFilter.value === 'unsettled') {
      filtered = filtered.filter((r) => r.success && r.actual_rank === null);
    }

    // 按关键词搜索
    if (searchKeyword.value) {
      const keyword = searchKeyword.value.toLowerCase();
      filtered = filtered.filter(
        (r) => r.token_symbol.toLowerCase().includes(keyword) || r.round_id.toLowerCase().includes(keyword)
      );
    }

    return filtered;
  });

  // 刷新分析数据
  const refreshAnalysis = async () => {
    if (!props.uid) {
      window.$message?.warning('请先完成身份验证');
      return;
    }

    loading.value = true;
    try {
      // 🔧 修复：确保days参数符合后端验证要求（-1或大于0的天数）
      let daysParam = selectedDays.value;

      // 验证并修正参数
      if (daysParam === null || daysParam === undefined || daysParam === 0) {
        daysParam = -1; // 默认使用全部历史
      } else if (daysParam > 0) {
        daysParam = Math.max(1, Math.floor(daysParam)); // 确保是大于0的整数
      } else if (daysParam !== -1) {
        daysParam = -1; // 其他无效值都改为全部历史
      }

      console.log('📊 发送API请求，days参数:', daysParam);
      const response = await bettingAnalysisApi.getPerformanceAnalysis(props.uid, daysParam);

      if (response.data.success) {
        const data = response.data.data;

        // 🔧 修复：保存后端统计数据，用于显示正确的总下注次数
        backendStats.value = data.betting_performance || {};

        // 处理详细记录，保留所有记录用于表格显示
        bettingRecords.value = (data.detailed_records || []).map((record: any) => ({
          id: record.id,
          created_at: record.created_at,
          round_id: record.round_id,
          token_symbol: record.token_symbol,
          predicted_rank: record.predicted_rank,
          actual_rank: record.actual_rank,
          success: record.success,
          bet_amount: record.bet_amount,
          is_top3: record.actual_rank ? record.actual_rank <= 3 : null
        }));

        console.log('📊 投注记录数据:', bettingRecords.value.length, '条记录');
        console.log('📊 后端统计数据:', backendStats.value);
      } else {
        throw new Error(response.data.message || '获取投注记录失败');
      }
    } catch (error) {
      console.error('📊 API请求失败:', error);
      handleError(error, {
        showToast: true,
        fallbackMessage: '获取投注记录失败'
      });
    } finally {
      loading.value = false;
    }
  };

  // 监听天数变化并重新获取数据
  const refreshOnDaysChange = async () => {
    if (props.uid) {
      console.log('📊 天数选择变化，当前值:', selectedDays.value);
      await refreshAnalysis();
    }
  };

  // 导出投注记录
  const exportBettingRecords = () => {
    const data = filteredBettingRecords.value;
    const csv = [
      ['时间', '轮次ID', '代币', '预测排名', '实际排名', '结果'],
      ...data.map((record) => [
        new Date(record.created_at).toLocaleString(),
        record.round_id,
        record.token_symbol,
        record.predicted_rank || '无预测',
        record.actual_rank || '未知',
        record.actual_rank ? (record.actual_rank <= 3 ? '成功' : '失败') : '待定'
      ])
    ]
      .map((row) => row.join(','))
      .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `betting_analysis_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // 组件挂载时获取数据
  onMounted(async () => {
    console.log('📊 组件挂载，初始参数:', { uid: props.uid, selectedDays: selectedDays.value });
    if (props.uid) {
      await refreshAnalysis();
    }
  });

  // 暴露方法给父组件
  defineExpose({
    refreshAnalysis
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
