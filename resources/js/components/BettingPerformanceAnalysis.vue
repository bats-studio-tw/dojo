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
      <div v-if="bettingRecords.length > 0" class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div
          class="border border-cyan-500/30 rounded-lg from-cyan-500/10 to-blue-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-cyan-400/50 hover:shadow-cyan-500/20"
        >
          <div class="text-sm text-cyan-300">总下注次数</div>
          <div class="text-xl text-cyan-400 font-bold">{{ bettingStats.totalBets }}</div>
          <div class="text-xs text-cyan-200/70">{{ selectedDays }}天内</div>
        </div>
        <div
          class="border border-emerald-500/30 rounded-lg from-emerald-500/10 to-green-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-emerald-400/50 hover:shadow-emerald-500/20"
        >
          <div class="text-sm text-emerald-300">成功率</div>
          <div class="text-xl text-emerald-400 font-bold">{{ bettingStats.successRate.toFixed(1) }}%</div>
          <div class="text-xs text-emerald-200/70">前三名率</div>
        </div>
        <div
          class="border border-violet-500/30 rounded-lg from-violet-500/10 to-purple-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-violet-400/50 hover:shadow-violet-500/20"
        >
          <div class="text-sm text-violet-300">成功次数</div>
          <div class="text-xl text-violet-400 font-bold">{{ bettingStats.successfulBets }}</div>
          <div class="text-xs text-violet-200/70">前三名</div>
        </div>
        <div
          class="border border-amber-500/30 rounded-lg from-amber-500/10 to-orange-600/5 bg-gradient-to-br p-3 text-center transition-all duration-300 hover:border-amber-400/50 hover:shadow-amber-500/20"
        >
          <div class="text-sm text-amber-300">失败次数</div>
          <div class="text-xl text-red-400 font-bold">{{ bettingStats.failedBets }}</div>
          <div class="text-xs text-amber-200/70">第四名及以后</div>
        </div>
      </div>

      <!-- 筛选和控制 -->
      <div v-if="bettingRecords.length > 0" class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <n-select
            v-model:value="recordFilter"
            :options="[
              { label: '全部记录', value: 'all' },
              { label: '成功记录', value: 'success' },
              { label: '失败记录', value: 'failed' }
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
  const selectedDays = ref(30);
  const recordFilter = ref('all');
  const searchKeyword = ref('');

  // 天数选项
  const dayOptions = [
    { label: '7天', value: 7 },
    { label: '30天', value: 30 },
    { label: '90天', value: 90 },
    { label: '180天', value: 180 },
    { label: '365天', value: 365 }
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
      title: '预测排名',
      key: 'predicted_rank',
      width: 100,
      render: (row: any) => row.predicted_rank || '-'
    },
    {
      title: '实际排名',
      key: 'actual_rank',
      width: 100,
      render: (row: any) => row.actual_rank || '-'
    },
    {
      title: '结果',
      key: 'is_top3',
      width: 80,
      render: (row: any) => {
        if (!row.actual_rank) return '⏳ 待定';
        return row.actual_rank <= 3 ? '✅ 成功' : '❌ 失败';
      }
    }
  ];

  // 分页配置
  const pagination = {
    pageSize: 20,
    showSizePicker: true,
    pageSizes: [10, 20, 50, 100]
  };

  // 计算投注统计
  const bettingStats = computed(() => {
    const records = bettingRecords.value.filter((r) => r.success && r.actual_rank !== null);
    const totalBets = records.length;
    const successfulBets = records.filter((r) => r.actual_rank <= 3).length;
    const failedBets = records.filter((r) => r.actual_rank > 3).length;
    const successRate = totalBets > 0 ? (successfulBets / totalBets) * 100 : 0;

    return {
      totalBets,
      successfulBets,
      failedBets,
      successRate
    };
  });

  // 过滤后的投注记录
  const filteredBettingRecords = computed(() => {
    let filtered = bettingRecords.value;

    // 按状态过滤
    if (recordFilter.value === 'success') {
      filtered = filtered.filter((r) => r.actual_rank <= 3);
    } else if (recordFilter.value === 'failed') {
      filtered = filtered.filter((r) => r.actual_rank > 3);
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
      const response = await bettingAnalysisApi.getPerformanceAnalysis(props.uid, selectedDays.value, 200);

      if (response.data.success) {
        const data = response.data.data;
        // 处理详细记录，只保留成功下注且有实际结果的记录
        bettingRecords.value = (data.detailed_records || [])
          .filter((record: any) => record.success) // 只显示成功下注的记录
          .map((record: any) => ({
            id: record.id,
            created_at: record.created_at,
            round_id: record.round_id,
            token_symbol: record.token_symbol,
            predicted_rank: record.predicted_rank,
            actual_rank: record.actual_rank,
            success: record.success,
            is_top3: record.actual_rank ? record.actual_rank <= 3 : null
          }));

        console.log('📊 投注记录数据:', bettingRecords.value.length, '条记录');
      } else {
        throw new Error(response.data.message || '获取投注记录失败');
      }
    } catch (error) {
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
