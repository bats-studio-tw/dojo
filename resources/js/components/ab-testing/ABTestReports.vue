<template>
  <div class="ab-test-reports">
    <!-- 測試選擇器 -->
    <div v-if="!selectedTest" class="test-selector mb-6">
      <n-card title="選擇測試">
        <p class="mb-4 text-gray-600">請從監控頁面選擇一個測試來查看報告</p>
        <n-button type="primary" @click="$emit('backToMonitor')">返回監控頁面</n-button>
      </n-card>
    </div>

    <!-- 報告內容 -->
    <div v-else class="report-content">
      <!-- 測試基本信息 -->
      <n-card title="測試基本信息" class="mb-6">
        <n-descriptions :column="3" bordered>
          <n-descriptions-item label="測試名稱">
            {{ selectedTest.name }}
          </n-descriptions-item>
          <n-descriptions-item label="狀態">
            <n-tag :type="getStatusType(selectedTest.status)">
              {{ getStatusText(selectedTest.status) }}
            </n-tag>
          </n-descriptions-item>
          <n-descriptions-item label="策略數量">
            {{ Object.keys(selectedTest.strategies).length }}
          </n-descriptions-item>
          <n-descriptions-item label="開始時間">
            {{ formatDateTime(selectedTest.start_date) }}
          </n-descriptions-item>
          <n-descriptions-item label="結束時間">
            {{ formatDateTime(selectedTest.end_date) }}
          </n-descriptions-item>
          <n-descriptions-item label="創建者">
            {{ selectedTest.creator?.name || '未知' }}
          </n-descriptions-item>
        </n-descriptions>
      </n-card>

      <!-- 時間範圍篩選 -->
      <n-card title="報告篩選" class="mb-6">
        <n-grid :cols="24" :x-gap="16">
          <n-form-item-gi :span="8" label="開始日期">
            <n-date-picker v-model:value="filters.startDate" type="date" placeholder="選擇開始日期" clearable />
          </n-form-item-gi>

          <n-form-item-gi :span="8" label="結束日期">
            <n-date-picker v-model:value="filters.endDate" type="date" placeholder="選擇結束日期" clearable />
          </n-form-item-gi>

          <n-form-item-gi :span="8" class="flex items-end">
            <n-button type="primary" @click="loadReport" :loading="isLoading">生成報告</n-button>
            <n-button class="ml-2" @click="resetFilters">重置</n-button>
          </n-form-item-gi>
        </n-grid>
      </n-card>

      <!-- 報告摘要 -->
      <n-card v-if="reportData" title="測試摘要" class="mb-6">
        <n-grid :cols="24" :x-gap="16">
          <n-gi :span="6">
            <div class="stat-card">
              <div class="stat-value">{{ reportData.summary.total_predictions }}</div>
              <div class="stat-label">總預測次數</div>
            </div>
          </n-gi>
          <n-gi :span="6">
            <div class="stat-card">
              <div class="stat-value">{{ (reportData.summary.overall_accuracy * 100).toFixed(2) }}%</div>
              <div class="stat-label">整體準確率</div>
            </div>
          </n-gi>
          <n-gi :span="6">
            <div class="stat-card">
              <div class="stat-value">{{ reportData.summary.best_strategy }}</div>
              <div class="stat-label">最佳策略</div>
            </div>
          </n-gi>
          <n-gi :span="6">
            <div class="stat-card">
              <div class="stat-value">{{ (reportData.summary.best_accuracy * 100).toFixed(2) }}%</div>
              <div class="stat-label">最佳準確率</div>
            </div>
          </n-gi>
        </n-grid>
      </n-card>

      <!-- 策略對比表格 -->
      <n-card v-if="reportData" title="策略對比分析" class="mb-6">
        <n-data-table :columns="strategyColumns" :data="reportData.results" :pagination="false" :bordered="false" />
      </n-card>

      <!-- 準確率趨勢圖 -->
      <n-card v-if="reportData && hasTrendData" title="準確率趨勢" class="mb-6">
        <div class="chart-container">
          <canvas ref="accuracyChartRef" width="800" height="400"></canvas>
        </div>
      </n-card>

      <!-- 流量分配圖表 -->
      <n-card v-if="reportData" title="流量分配分析" class="mb-6">
        <div class="chart-container">
          <canvas ref="trafficChartRef" width="600" height="400"></canvas>
        </div>
      </n-card>

      <!-- 詳細數據表格 -->
      <n-card v-if="reportData" title="詳細數據">
        <template #header-extra>
          <n-button type="primary" @click="exportReport">
            <template #icon>
              <n-icon><Download /></n-icon>
            </template>
            導出報告
          </n-button>
        </template>

        <n-data-table :columns="detailColumns" :data="reportData.results" :pagination="false" :bordered="false" />
      </n-card>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';

  import { Download } from '@vicons/ionicons5';
  import api from '@/utils/api';

  // 定義Props和事件
  const props = defineProps<{
    selectedTest: any;
  }>();

  const emit = defineEmits<{
    backToMonitor: [];
  }>();

  // 響應式數據
  const message = (window as any).$message;
  const isLoading = ref(false);
  const reportData = ref<any>(null);
  const accuracyChartRef = ref<HTMLCanvasElement>();
  const trafficChartRef = ref<HTMLCanvasElement>();

  const filters = reactive({
    startDate: null as number | null,
    endDate: null as number | null
  });

  // 計算屬性
  const hasTrendData = computed(() => {
    if (!reportData.value?.results) return false;
    return reportData.value.results.some(
      (result: any) => result.daily_accuracy && Object.keys(result.daily_accuracy).length > 0
    );
  });

  // 表格列配置
  const strategyColumns = [
    {
      title: '策略名稱',
      key: 'strategy',
      width: 150,
      fixed: 'left'
    },
    {
      title: '總預測次數',
      key: 'total_predictions',
      width: 120,
      render: (row: any) => row.total_predictions.toLocaleString()
    },
    {
      title: '正確預測',
      key: 'correct_predictions',
      width: 120,
      render: (row: any) => row.correct_predictions.toLocaleString()
    },
    {
      title: '準確率',
      key: 'accuracy_rate',
      width: 120,
      render: (row: any) => `${(row.accuracy_rate * 100).toFixed(2)}%`
    },
    {
      title: '流量分配',
      key: 'traffic_percentage',
      width: 120,
      render: (row: any) => `${row.traffic_percentage}%`
    },
    {
      title: '獨立用戶',
      key: 'unique_users',
      width: 120,
      render: (row: any) => row.unique_users.toLocaleString()
    },
    {
      title: '獨立回合',
      key: 'unique_rounds',
      width: 120,
      render: (row: any) => row.unique_rounds.toLocaleString()
    }
  ];

  const detailColumns = [
    ...strategyColumns,
    {
      title: '每日準確率',
      key: 'daily_accuracy',
      width: 200,
      render: (row: any) => {
        if (!row.daily_accuracy || Object.keys(row.daily_accuracy).length === 0) {
          return '無數據';
        }
        const dates = Object.keys(row.daily_accuracy).slice(-5); // 顯示最近5天
        return dates.map((date) => `${date}: ${(row.daily_accuracy[date] * 100).toFixed(1)}%`).join(', ');
      }
    }
  ];

  // 方法
  const loadReport = async () => {
    if (!props.selectedTest) return;

    try {
      isLoading.value = true;

      const params = {
        test_id: props.selectedTest.id,
        ...(filters.startDate && { start_date: new Date(filters.startDate).toISOString() }),
        ...(filters.endDate && { end_date: new Date(filters.endDate).toISOString() })
      };

      const response = await api.post('/api/ab-testing/report', params);

      if (response.data.success) {
        reportData.value = response.data.data;
        await nextTick();
        renderCharts();
      } else {
        message.error('生成報告失敗');
      }
    } catch (error: any) {
      message.error(error.response?.data?.message || '生成報告失敗');
    } finally {
      isLoading.value = false;
    }
  };

  const resetFilters = () => {
    filters.startDate = null;
    filters.endDate = null;
    loadReport();
  };

  const renderCharts = () => {
    if (!reportData.value) return;

    // 渲染準確率趨勢圖
    if (hasTrendData.value && accuracyChartRef.value) {
      renderAccuracyTrendChart();
    }

    // 渲染流量分配圖
    if (trafficChartRef.value) {
      renderTrafficDistributionChart();
    }
  };

  const renderAccuracyTrendChart = () => {
    const canvas = accuracyChartRef.value;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // 清除畫布
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const results = reportData.value.results;
    const allDates = new Set<string>();

    // 收集所有日期
    results.forEach((result: any) => {
      if (result.daily_accuracy) {
        Object.keys(result.daily_accuracy).forEach((date) => allDates.add(date));
      }
    });

    const sortedDates = Array.from(allDates).sort();
    const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];

    // 繪製每個策略的趨勢線
    results.forEach((result: any, index: number) => {
      if (!result.daily_accuracy) return;

      ctx.strokeStyle = colors[index % colors.length];
      ctx.lineWidth = 2;
      ctx.beginPath();

      let firstPoint = true;
      sortedDates.forEach((date, dateIndex) => {
        const accuracy = result.daily_accuracy[date] || 0;
        const x = (dateIndex / (sortedDates.length - 1)) * canvas.width;
        const y = canvas.height - accuracy * canvas.height;

        if (firstPoint) {
          ctx.moveTo(x, y);
          firstPoint = false;
        } else {
          ctx.lineTo(x, y);
        }
      });

      ctx.stroke();

      // 添加圖例
      ctx.fillStyle = colors[index % colors.length];
      ctx.fillRect(10, 10 + index * 20, 15, 10);
      ctx.fillStyle = '#000';
      ctx.font = '12px Arial';
      ctx.fillText(result.strategy, 30, 20 + index * 20);
    });
  };

  const renderTrafficDistributionChart = () => {
    const canvas = trafficChartRef.value;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // 清除畫布
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const results = reportData.value.results;
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = Math.min(centerX, centerY) - 50;

    let currentAngle = 0;
    const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];

    results.forEach((result: any, index: number) => {
      const percentage = result.traffic_percentage / 100;
      const angle = percentage * 2 * Math.PI;

      // 繪製扇形
      ctx.beginPath();
      ctx.moveTo(centerX, centerY);
      ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + angle);
      ctx.closePath();
      ctx.fillStyle = colors[index % colors.length];
      ctx.fill();

      // 添加標籤
      const labelAngle = currentAngle + angle / 2;
      const labelX = centerX + radius * 0.7 * Math.cos(labelAngle);
      const labelY = centerY + radius * 0.7 * Math.sin(labelAngle);

      ctx.fillStyle = '#fff';
      ctx.font = 'bold 14px Arial';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(`${result.strategy}\n${result.traffic_percentage}%`, labelX, labelY);

      currentAngle += angle;
    });
  };

  const exportReport = () => {
    if (!reportData.value) return;

    const csvContent = generateCSV();
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute(
      'download',
      `ab-test-report-${props.selectedTest.name}-${new Date().toISOString().split('T')[0]}.csv`
    );
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const generateCSV = () => {
    if (!reportData.value) return '';

    const headers = ['策略', '總預測次數', '正確預測', '準確率', '流量分配', '獨立用戶', '獨立回合'];
    const rows = reportData.value.results.map((result: any) => [
      result.strategy,
      result.total_predictions,
      result.correct_predictions,
      `${(result.accuracy_rate * 100).toFixed(2)}%`,
      `${result.traffic_percentage}%`,
      result.unique_users,
      result.unique_rounds
    ]);

    return [headers, ...rows].map((row) => row.map((cell: any) => `"${cell}"`).join(',')).join('\n');
  };

  const getStatusType = (status: string) => {
    const statusMap = {
      active: 'success',
      stopped: 'warning',
      completed: 'info'
    };
    return statusMap[status as keyof typeof statusMap] || 'default';
  };

  const getStatusText = (status: string) => {
    const statusMap = {
      active: '活躍',
      stopped: '已停止',
      completed: '已完成'
    };
    return statusMap[status as keyof typeof statusMap] || status;
  };

  const formatDateTime = (dateString: string) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('zh-CN');
  };

  // 監聽器
  watch(
    () => props.selectedTest,
    (newTest) => {
      if (newTest) {
        loadReport();
      } else {
        reportData.value = null;
      }
    },
    { immediate: true }
  );

  // 生命週期
  onMounted(() => {
    if (props.selectedTest) {
      loadReport();
    }
  });
</script>

<style scoped>
  .stat-card {
    @apply text-center p-4 bg-gray-50 rounded-lg;
  }

  .stat-value {
    @apply text-2xl font-bold text-blue-600;
  }

  .stat-label {
    @apply text-sm text-gray-600 mt-1;
  }

  .chart-container {
    @apply flex justify-center;
  }
</style>
