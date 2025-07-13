<template>
  <div class="ab-test-monitor">
    <!-- 篩選器 -->
    <div class="filter-section mb-6">
      <n-card title="篩選條件">
        <n-grid :cols="24" :x-gap="16">
          <n-form-item-gi :span="6" label="狀態">
            <n-select v-model:value="filters.status" :options="statusOptions" placeholder="選擇狀態" clearable />
          </n-form-item-gi>

          <n-form-item-gi :span="6" label="創建者">
            <n-input v-model:value="filters.creator" placeholder="輸入創建者" clearable />
          </n-form-item-gi>

          <n-form-item-gi :span="6" label="時間範圍">
            <n-date-picker v-model:value="filters.dateRange" type="daterange" placeholder="選擇時間範圍" clearable />
          </n-form-item-gi>

          <n-form-item-gi :span="6" class="flex items-end">
            <n-button type="primary" @click="loadTests" :loading="isLoading">篩選</n-button>
            <n-button class="ml-2" @click="resetFilters">重置</n-button>
          </n-form-item-gi>
        </n-grid>
      </n-card>
    </div>

    <!-- 測試列表 -->
    <n-card title="A/B測試列表">
      <template #header-extra>
        <n-button type="primary" @click="loadTests" :loading="isLoading">
          <template #icon>
            <n-icon><Refresh /></n-icon>
          </template>
          刷新
        </n-button>
      </template>

      <n-data-table
        :columns="columns"
        :data="tests"
        :pagination="pagination"
        :loading="isLoading"
        :row-key="(row) => row.id"
        @update:page="handlePageChange"
      />
    </n-card>

    <!-- 測試詳情對話框 -->
    <n-modal v-model:show="showDetailModal" preset="card" style="width: 800px">
      <template #header>
        <span class="text-lg font-semibold">測試詳情 - {{ selectedTest?.name }}</span>
      </template>

      <div v-if="selectedTest" class="test-detail">
        <n-descriptions :column="2" bordered>
          <n-descriptions-item label="測試名稱">
            {{ selectedTest.name }}
          </n-descriptions-item>
          <n-descriptions-item label="狀態">
            <n-tag :type="getStatusType(selectedTest.status)">
              {{ getStatusText(selectedTest.status) }}
            </n-tag>
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
          <n-descriptions-item label="創建時間">
            {{ formatDateTime(selectedTest.created_at) }}
          </n-descriptions-item>
          <n-descriptions-item label="描述" :span="2">
            {{ selectedTest.description || '無描述' }}
          </n-descriptions-item>
        </n-descriptions>

        <!-- 策略配置 -->
        <div class="mt-6">
          <h4 class="text-lg font-semibold mb-3">策略配置</h4>
          <n-table :bordered="false" :single-line="false">
            <thead>
              <tr>
                <th>策略名稱</th>
                <th>策略標籤</th>
                <th>流量分配</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(config, name) in selectedTest.strategies" :key="name">
                <td>{{ name }}</td>
                <td>
                  <n-tag>{{ config }}</n-tag>
                </td>
                <td>{{ selectedTest.traffic_distribution[name] }}%</td>
              </tr>
            </tbody>
          </n-table>
        </div>

        <!-- 操作按鈕 -->
        <div class="mt-6 flex justify-end space-x-4">
          <n-button @click="viewReport(selectedTest)">查看報告</n-button>
          <n-button v-if="selectedTest.status === 'active'" type="warning" @click="stopTest(selectedTest)">
            停止測試
          </n-button>
          <n-button @click="showDetailModal = false">關閉</n-button>
        </div>
      </div>
    </n-modal>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, onMounted, h } from 'vue';
  import { useMessage, useDialog } from 'naive-ui';
  import { Refresh, Eye, Stop } from '@vicons/ionicons5';
  import api from '@/utils/api';

  // 定義事件
  const emit = defineEmits<{
    testSelected: [test: any];
  }>();

  // 響應式數據
  const message = useMessage();
  const dialog = useDialog();
  const isLoading = ref(false);
  const tests = ref<any[]>([]);
  const showDetailModal = ref(false);
  const selectedTest = ref<any>(null);

  const filters = reactive({
    status: null as string | null,
    creator: '',
    dateRange: null as [number, number] | null
  });

  const statusOptions = [
    { label: '活躍', value: 'active' },
    { label: '已停止', value: 'stopped' },
    { label: '已完成', value: 'completed' }
  ];

  const pagination = reactive({
    page: 1,
    pageSize: 15,
    showSizePicker: true,
    pageSizes: [10, 15, 20, 50],
    onChange: (page: number) => {
      pagination.page = page;
      loadTests();
    },
    onUpdatePageSize: (pageSize: number) => {
      pagination.pageSize = pageSize;
      pagination.page = 1;
      loadTests();
    }
  });

  // 表格列配置
  const columns = [
    {
      title: '測試名稱',
      key: 'name',
      width: 200,
      ellipsis: {
        tooltip: true
      }
    },
    {
      title: '狀態',
      key: 'status',
      width: 100,
      render: (row: any) => {
        const statusMap = {
          active: { text: '活躍', type: 'success' },
          stopped: { text: '已停止', type: 'warning' },
          completed: { text: '已完成', type: 'info' }
        };
        const status = statusMap[row.status as keyof typeof statusMap] || { text: row.status, type: 'default' };
        return h('n-tag', { type: status.type, size: 'small' }, { default: () => status.text });
      }
    },
    {
      title: '策略數量',
      key: 'strategy_count',
      width: 100,
      render: (row: any) => Object.keys(row.strategies || {}).length
    },
    {
      title: '流量分配',
      key: 'traffic_summary',
      width: 200,
      ellipsis: {
        tooltip: true
      },
      render: (row: any) => {
        const summary = Object.entries(row.traffic_distribution || {})
          .map(([name, percentage]) => `${name}: ${percentage}%`)
          .join(', ');
        return summary || '未配置';
      }
    },
    {
      title: '開始時間',
      key: 'start_date',
      width: 150,
      render: (row: any) => formatDateTime(row.start_date)
    },
    {
      title: '結束時間',
      key: 'end_date',
      width: 150,
      render: (row: any) => formatDateTime(row.end_date)
    },
    {
      title: '創建者',
      key: 'creator_name',
      width: 120,
      render: (row: any) => row.creator?.name || '未知'
    },
    {
      title: '操作',
      key: 'actions',
      width: 200,
      fixed: 'right',
      render: (row: any) => {
        return h(
          'div',
          { class: 'flex space-x-2' },
          [
            h(
              'n-button',
              {
                size: 'small',
                onClick: () => viewDetail(row)
              },
              {
                default: () => '詳情',
                icon: () => h(Eye)
              }
            ),
            h(
              'n-button',
              {
                size: 'small',
                type: 'primary',
                onClick: () => viewReport(row)
              },
              {
                default: () => '報告'
              }
            ),
            row.status === 'active'
              ? h(
                  'n-button',
                  {
                    size: 'small',
                    type: 'warning',
                    onClick: () => stopTest(row)
                  },
                  {
                    default: () => '停止',
                    icon: () => h(Stop)
                  }
                )
              : null
          ].filter(Boolean)
        );
      }
    }
  ];

  // 方法
  const loadTests = async () => {
    try {
      isLoading.value = true;

      const params = {
        page: pagination.page,
        per_page: pagination.pageSize,
        ...(filters.status && { status: filters.status }),
        ...(filters.creator && { creator: filters.creator }),
        ...(filters.dateRange && {
          start_date: new Date(filters.dateRange[0]).toISOString(),
          end_date: new Date(filters.dateRange[1]).toISOString()
        })
      };

      const response = await api.get('/api/ab-testing/list', { params });

      if (response.data.success) {
        tests.value = response.data.data.data;
        pagination.page = response.data.data.current_page;
        pagination.pageSize = response.data.data.per_page;
      } else {
        message.error('加載測試列表失敗');
      }
    } catch (error: any) {
      message.error(error.response?.data?.message || '加載測試列表失敗');
    } finally {
      isLoading.value = false;
    }
  };

  const resetFilters = () => {
    filters.status = null;
    filters.creator = '';
    filters.dateRange = null;
    loadTests();
  };

  const handlePageChange = (page: number) => {
    pagination.page = page;
    loadTests();
  };

  const viewDetail = async (test: any) => {
    try {
      const response = await api.get('/api/ab-testing/detail', {
        params: { test_id: test.id }
      });

      if (response.data.success) {
        selectedTest.value = response.data.data;
        showDetailModal.value = true;
      } else {
        message.error('獲取測試詳情失敗');
      }
    } catch (error: any) {
      message.error(error.response?.data?.message || '獲取測試詳情失敗');
    }
  };

  const viewReport = (test: any) => {
    emit('testSelected', test);
  };

  const stopTest = (test: any) => {
    dialog.warning({
      title: '確認停止測試',
      content: `確定要停止測試 "${test.name}" 嗎？此操作不可逆。`,
      positiveText: '確定停止',
      negativeText: '取消',
      onPositiveClick: async () => {
        try {
          const response = await api.post('/api/ab-testing/stop', {
            test_id: test.id
          });

          if (response.data.success) {
            message.success('測試已停止');
            loadTests();
            if (showDetailModal.value) {
              showDetailModal.value = false;
            }
          } else {
            message.error(response.data.message || '停止測試失敗');
          }
        } catch (error: any) {
          message.error(error.response?.data?.message || '停止測試失敗');
        }
      }
    });
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

  // 生命週期
  onMounted(() => {
    loadTests();
  });
</script>

<style scoped>
  .test-detail {
    max-height: 600px;
    overflow-y: auto;
  }
</style>
