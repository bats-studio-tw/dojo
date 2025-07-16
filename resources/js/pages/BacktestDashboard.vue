<template>
  <DefaultLayout>
    <div class="backtest-dashboard">
      <!-- 頁面標題 -->
      <div class="page-header">
        <h1 class="text-2xl text-gray-900 font-bold">回測分析中心</h1>
        <p class="mt-2 text-gray-600">專業化策略回測與參數優化平台</p>
      </div>

      <!-- 回測配置區域 -->
      <div class="config-section">
        <NCard title="回測配置" class="mb-6">
          <NForm
            ref="formRef"
            :model="formData"
            :rules="formRules"
            label-placement="left"
            label-width="auto"
            require-mark-placement="right-hanging"
          >
            <NGrid :cols="24" :x-gap="24">
              <!-- 回測類型選擇 -->
              <NFormItemGi :span="12" label="回測類型" path="backtestType">
                <NRadioGroup v-model:value="formData.backtestType">
                  <NSpace>
                    <NRadio value="single">單策略回測</NRadio>
                    <NRadio value="grid">Grid Search 參數優化</NRadio>
                  </NSpace>
                </NRadioGroup>
              </NFormItemGi>

              <!-- 策略選擇 -->
              <NFormItemGi :span="12" label="策略" path="strategyTag">
                <NSelect
                  v-model:value="formData.strategyTag"
                  :options="strategyOptions"
                  placeholder="選擇策略"
                  :disabled="formData.backtestType === 'grid'"
                />
              </NFormItemGi>

              <!-- 歷史數據範圍 -->
              <NFormItemGi :span="12" label="歷史範圍" path="historyRange">
                <NSelect
                  v-model:value="formData.historyRange"
                  :options="historyRangeOptions"
                  placeholder="選擇歷史數據範圍"
                />
              </NFormItemGi>

              <!-- 自定義回合數 -->
              <NFormItemGi :span="12" label="回測回合數" path="customRounds">
                <NInputNumber
                  v-model:value="formData.customRounds"
                  :min="10"
                  :max="1000"
                  placeholder="自定義回合數"
                  :disabled="formData.historyRange !== 'custom'"
                />
              </NFormItemGi>
            </NGrid>

            <!-- Grid Search 參數配置 -->
            <div v-if="formData.backtestType === 'grid'" class="mt-6">
              <NDivider>Grid Search 參數矩陣</NDivider>
              <NGrid :cols="24" :x-gap="24">
                <NFormItemGi :span="12" label="權重組合">
                  <NDynamicInput v-model:value="formData.gridSearch.weights" :on-create="onCreateWeight">
                    <template #default="{ value }">
                      <NSpace vertical>
                        <NInputGroup>
                          <NInput
                            v-model:value="value.elo"
                            placeholder="ELO權重"
                            type="number"
                            :min="0"
                            :max="1"
                            :step="0.1"
                          />
                          <NInput
                            v-model:value="value.momentum"
                            placeholder="動能權重"
                            type="number"
                            :min="0"
                            :max="1"
                            :step="0.1"
                          />
                          <NInput
                            v-model:value="value.volume"
                            placeholder="成交量權重"
                            type="number"
                            :min="0"
                            :max="1"
                            :step="0.1"
                          />
                        </NInputGroup>
                      </NSpace>
                    </template>
                  </NDynamicInput>
                </NFormItemGi>

                <NFormItemGi :span="12" label="標準化策略">
                  <NDynamicInput v-model:value="formData.gridSearch.normalization" :on-create="onCreateNormalization">
                    <template #default="{ value }">
                      <NSpace vertical>
                        <NSelect v-model:value="value.elo" :options="normalizationOptions" placeholder="ELO標準化" />
                        <NSelect
                          v-model:value="value.momentum"
                          :options="normalizationOptions"
                          placeholder="動能標準化"
                        />
                        <NSelect
                          v-model:value="value.volume"
                          :options="normalizationOptions"
                          placeholder="成交量標準化"
                        />
                      </NSpace>
                    </template>
                  </NDynamicInput>
                </NFormItemGi>
              </NGrid>
            </div>

            <!-- 操作按鈕 -->
            <div class="mt-6 flex justify-end space-x-4">
              <NButton @click="resetForm">重置</NButton>
              <NButton type="primary" :loading="isSubmitting" @click="submitBacktest">
                {{ formData.backtestType === 'grid' ? '開始 Grid Search' : '開始回測' }}
              </NButton>
            </div>
          </NForm>
        </NCard>
      </div>

      <!-- 任務進度監控 -->
      <div v-if="currentBatch" class="progress-section">
        <NCard title="任務進度" class="mb-6">
          <NSpace vertical>
            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600">批次ID: {{ currentBatch.id }}</span>
              <NTag :type="getBatchStatusType(currentBatch.status)">
                {{ getBatchStatusText(currentBatch.status) }}
              </NTag>
            </div>

            <NProgress
              :percentage="getBatchProgress(currentBatch)"
              :status="getBatchProgressStatus(currentBatch.status)"
            />

            <NGrid :cols="4" :x-gap="16">
              <div class="text-center">
                <div class="text-lg font-semibold">{{ currentBatch.total_jobs }}</div>
                <div class="text-sm text-gray-500">總任務</div>
              </div>
              <div class="text-center">
                <div class="text-lg text-blue-600 font-semibold">{{ currentBatch.processed_jobs }}</div>
                <div class="text-sm text-gray-500">已完成</div>
              </div>
              <div class="text-center">
                <div class="text-lg text-yellow-600 font-semibold">{{ currentBatch.pending_jobs }}</div>
                <div class="text-sm text-gray-500">待處理</div>
              </div>
              <div class="text-center">
                <div class="text-lg text-red-600 font-semibold">{{ currentBatch.failed_jobs }}</div>
                <div class="text-sm text-gray-500">失敗</div>
              </div>
            </NGrid>

            <div class="flex justify-end space-x-2">
              <NButton size="small" @click="refreshBatchStatus">刷新狀態</NButton>
              <NButton size="small" type="primary" @click="viewResults" :disabled="currentBatch.status !== 'finished'">
                查看結果
              </NButton>
            </div>
          </NSpace>
        </NCard>
      </div>

      <!-- 回測結果展示 -->
      <div v-if="backtestReports.length > 0" class="results-section">
        <NCard title="回測結果">
          <NDataTable :columns="reportColumns" :data="backtestReports" :pagination="pagination" :bordered="false" />
        </NCard>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { ref, reactive, onMounted, onUnmounted, h } from 'vue';
  import { useDialog } from 'naive-ui';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import api from '@/utils/api';
  import type { BacktestReport, BacktestBatchStatus, StrategyDTO, GridSearchParamMatrix } from '@/types/prediction';

  // 響應式數據
  const formRef = ref();
  const message = (window as any).$message;
  const dialog = useDialog();

  const formData = reactive({
    backtestType: 'single',
    strategyTag: '',
    historyRange: 'recent_100',
    customRounds: 100,
    gridSearch: {
      weights: [] as Record<string, number>[],
      normalization: [] as Record<string, string>[]
    }
  });

  const isSubmitting = ref(false);
  const currentBatch = ref<BacktestBatchStatus | null>(null);
  const backtestReports = ref<BacktestReport[]>([]);
  const availableStrategies = ref<StrategyDTO[]>([]);
  const progressInterval = ref<NodeJS.Timeout | null>(null);

  // 表單驗證規則
  const formRules = {
    strategyTag: {
      required: true,
      message: '請選擇策略',
      trigger: 'blur'
    },
    historyRange: {
      required: true,
      message: '請選擇歷史範圍',
      trigger: 'change'
    }
  };

  // 選項配置
  const strategyOptions = ref<{ label: string; value: string }[]>([]);
  const historyRangeOptions = [
    { label: '最近50回合', value: 'recent_50' },
    { label: '最近100回合', value: 'recent_100' },
    { label: '最近200回合', value: 'recent_200' },
    { label: '最近500回合', value: 'recent_500' },
    { label: '自定義回合數', value: 'custom' }
  ];

  const normalizationOptions = [
    { label: 'Z-Score標準化', value: 'z-score' },
    { label: 'Min-Max標準化', value: 'min-max' },
    { label: '無標準化', value: 'identity' }
  ];

  // 表格列配置
  const reportColumns = [
    {
      title: '策略',
      key: 'strategy_tag',
      width: 120,
      fixed: 'left'
    },
    {
      title: '状态',
      key: 'status',
      width: 100,
      render: (row: BacktestReport) => {
        const statusMap = {
          pending: { text: '等待中', type: 'warning' },
          processing: { text: '处理中', type: 'info' },
          completed: { text: '已完成', type: 'success' },
          failed: { text: '失败', type: 'error' }
        };
        const status = statusMap[row.status as keyof typeof statusMap] || { text: row.status, type: 'default' };
        return h('NTag', { type: status.type, size: 'small' }, { default: () => status.text });
      }
    },
    {
      title: '胜率',
      key: 'win_rate',
      width: 100,
      render: (row: BacktestReport) => `${(row.win_rate * 100).toFixed(2)}%`
    },
    {
      title: '保本率',
      key: 'breakeven_rate',
      width: 100,
      render: (row: BacktestReport) => `${(row.breakeven_rate * 100).toFixed(2)}%`
    },
    {
      title: '夏普比率',
      key: 'sharpe_ratio',
      width: 120,
      render: (row: BacktestReport) => row.sharpe_ratio.toFixed(2)
    },
    {
      title: 'Sortino比率',
      key: 'sortino_ratio',
      width: 120,
      render: (row: BacktestReport) => row.sortino_ratio.toFixed(2)
    },
    {
      title: 'Calmar比率',
      key: 'calmar_ratio',
      width: 120,
      render: (row: BacktestReport) => row.calmar_ratio.toFixed(2)
    },
    {
      title: '最大回撤',
      key: 'max_drawdown',
      width: 120,
      render: (row: BacktestReport) => `${(row.max_drawdown * 100).toFixed(2)}%`
    },
    {
      title: '盈利因子',
      key: 'profit_factor',
      width: 100,
      render: (row: BacktestReport) => row.profit_factor.toFixed(2)
    },
    {
      title: '总盈利',
      key: 'total_profit',
      width: 100,
      render: (row: BacktestReport) => row.total_profit.toFixed(2)
    },
    {
      title: '连续胜场',
      key: 'consecutive_wins',
      width: 100,
      render: (row: BacktestReport) => row.consecutive_wins
    },
    {
      title: '连续败场',
      key: 'consecutive_losses',
      width: 100,
      render: (row: BacktestReport) => row.consecutive_losses
    },
    {
      title: '回合数',
      key: 'total_rounds',
      width: 100,
      render: (row: BacktestReport) => `${row.successful_rounds}/${row.total_rounds}`
    },
    {
      title: '操作',
      key: 'actions',
      width: 120,
      fixed: 'right',
      render: (row: BacktestReport) => {
        return h('div', { class: 'flex space-x-2' }, [
          h(
            'NButton',
            {
              size: 'small',
              type: 'primary',
              onClick: () => viewReportDetail(row.id)
            },
            { default: () => '详情' }
          ),
          h(
            'NButton',
            {
              size: 'small',
              type: 'info',
              onClick: () => exportReport(row)
            },
            { default: () => '导出' }
          )
        ]);
      }
    }
  ];

  const pagination = {
    page: 1,
    pageSize: 10,
    showSizePicker: true,
    pageSizes: [10, 20, 50]
  };

  // 方法
  const onCreateWeight = () => ({ elo: 0.5, momentum: 0.3, volume: 0.2 });
  const onCreateNormalization = () => ({ elo: 'z-score', momentum: 'min-max', volume: 'identity' });

  const getBatchStatusType = (status: string) => {
    const typeMap: Record<string, string> = { finished: 'success', cancelled: 'warning', processing: 'info' };
    return typeMap[status] || 'default';
  };

  const getBatchStatusText = (status: string) => {
    const textMap: Record<string, string> = { finished: '已完成', cancelled: '已取消', processing: '處理中' };
    return textMap[status] || status;
  };

  const getBatchProgress = (batch: BacktestBatchStatus) => {
    if (batch.status === 'finished') return 100;
    if (batch.status === 'cancelled') return 0;
    return Math.round((batch.processed_jobs / batch.total_jobs) * 100);
  };

  const getBatchProgressStatus = (status: string) => {
    if (status === 'finished') return 'success';
    if (status === 'cancelled') return 'error';
    return 'normal';
  };

  // 獲取策略列表
  const fetchStrategies = async () => {
    try {
      const response = await api.get('/v2/strategies');
      if (response.data.success) {
        availableStrategies.value = response.data.data;
        strategyOptions.value = availableStrategies.value.map((s) => ({
          label: s.name,
          value: s.tag
        }));
      }
    } catch (error) {
      message.error('獲取策略列表失敗');
    }
  };

  // 提交回測任務
  const submitBacktest = async () => {
    try {
      await formRef.value?.validate();
      isSubmitting.value = true;

      // 準備請求數據
      const requestData = {
        rounds: await generateRoundsData(),
        ...(formData.backtestType === 'single'
          ? {
              strategy_tag: formData.strategyTag
            }
          : {
              param_matrix: {
                weights: formData.gridSearch.weights,
                normalization: formData.gridSearch.normalization
              }
            })
      };

      // 發送請求
      const endpoint = formData.backtestType === 'single' ? '/v2/backtest/async' : '/v2/backtest/grid-search';

      const response = await api.post(endpoint, requestData);

      if (response.data.success) {
        message.success('回測任務已提交');
        currentBatch.value = { id: response.data.batch_id } as BacktestBatchStatus;
        startProgressMonitoring();
      }
    } catch (error: any) {
      message.error(error.response?.data?.message || '提交回測任務失敗');
    } finally {
      isSubmitting.value = false;
    }
  };

  // 生成回合數據
  const generateRoundsData = async () => {
    // 這裡應該根據historyRange獲取實際的歷史回合數據
    // 暫時返回模擬數據
    const rounds = [];
    const count =
      formData.historyRange === 'custom' ? formData.customRounds : parseInt(formData.historyRange.split('_')[1]);

    for (let i = 1; i <= count; i++) {
      rounds.push({
        id: i,
        symbols: ['ETH', 'DOGE', 'SOL'],
        timestamp: Date.now() - i * 3600000, // 每小時一回合
        history: []
      });
    }
    return rounds;
  };

  // 開始進度監控
  const startProgressMonitoring = () => {
    if (progressInterval.value) {
      clearInterval(progressInterval.value);
    }

    progressInterval.value = setInterval(async () => {
      if (currentBatch.value?.id) {
        await refreshBatchStatus();
      }
    }, 5000); // 每5秒檢查一次
  };

  // 刷新批次狀態
  const refreshBatchStatus = async () => {
    if (!currentBatch.value?.id) return;

    try {
      const response = await api.post('/v2/backtest/batch-status', {
        batch_id: currentBatch.value.id
      });

      if (response.data.success) {
        currentBatch.value = response.data.batch;
        backtestReports.value = response.data.reports;

        // 如果任務完成，停止監控
        if (currentBatch.value?.status === 'finished') {
          stopProgressMonitoring();
          message.success('回測任務已完成');
        }
      }
    } catch (error) {
      console.error('刷新批次狀態失敗:', error);
    }
  };

  // 停止進度監控
  const stopProgressMonitoring = () => {
    if (progressInterval.value) {
      clearInterval(progressInterval.value);
      progressInterval.value = null;
    }
  };

  // 查看結果
  const viewResults = () => {
    if (backtestReports.value.length > 0) {
      // 滾動到結果區域
      const resultsSection = document.querySelector('.results-section');
      resultsSection?.scrollIntoView({ behavior: 'smooth' });
    }
  };

  // 查看報告詳情
  const viewReportDetail = async (reportId: number) => {
    try {
      const response = await api.post('/v2/backtest/report-detail', { id: reportId });
      if (response.data.success) {
        const report = response.data.data;
        dialog.info({
          title: `回測報告 #${report.id}`,
          content: () =>
            h('div', { class: 'space-y-4' }, [
              h('div', { class: 'grid grid-cols-2 gap-4' }, [
                h('div', { class: 'text-sm' }, [
                  h('div', { class: 'font-semibold' }, `策略: ${report.strategy_tag}`),
                  h('div', `勝率: ${(report.win_rate * 100).toFixed(2)}%`),
                  h('div', `夏普比率: ${report.sharpe_ratio.toFixed(2)}`),
                  h('div', `最大回撤: ${(report.max_drawdown * 100).toFixed(2)}%`),
                  h('div', `總盈利: ${report.total_profit.toFixed(2)}`),
                  h('div', `回合數: ${report.total_rounds}`)
                ])
              ])
            ])
        });
      }
    } catch (error) {
      message.error('獲取報告詳情失敗');
    }
  };

  // 導出報告
  const exportReport = async (report: BacktestReport) => {
    try {
      const reportData = {
        id: report.id,
        strategy_tag: report.strategy_tag,
        win_rate: `${(report.win_rate * 100).toFixed(2)}%`,
        breakeven_rate: `${(report.breakeven_rate * 100).toFixed(2)}%`,
        sharpe_ratio: report.sharpe_ratio.toFixed(2),
        sortino_ratio: report.sortino_ratio.toFixed(2),
        calmar_ratio: report.calmar_ratio.toFixed(2),
        max_drawdown: `${(report.max_drawdown * 100).toFixed(2)}%`,
        profit_factor: report.profit_factor.toFixed(2),
        total_profit: report.total_profit.toFixed(2),
        consecutive_wins: report.consecutive_wins,
        consecutive_losses: report.consecutive_losses,
        total_rounds: report.total_rounds,
        successful_rounds: report.successful_rounds,
        created_at: new Date(report.created_at).toLocaleString()
      };

      // 创建CSV内容
      const csvContent = [Object.keys(reportData).join(','), Object.values(reportData).join(',')].join('\n');

      // 下载文件
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', `backtest_report_${report.id}_${report.strategy_tag}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      message.success('報告導出成功');
    } catch (error) {
      message.error('導出報告失敗');
    }
  };

  // 重置表單
  const resetForm = () => {
    formRef.value?.restoreValidation();
    Object.assign(formData, {
      backtestType: 'single',
      strategyTag: '',
      historyRange: 'recent_100',
      customRounds: 100,
      gridSearch: { weights: [], normalization: [] }
    });
  };

  // 生命週期
  onMounted(() => {
    fetchStrategies();
  });

  onUnmounted(() => {
    stopProgressMonitoring();
  });
</script>

<style scoped>
  .backtest-dashboard {
    @apply max-w-7xl mx-auto px-4 py-6;
  }

  .page-header {
    @apply mb-8;
  }

  .config-section,
  .progress-section,
  .results-section {
    @apply mb-8;
  }

  .n-card {
    @apply shadow-sm;
  }
</style>
