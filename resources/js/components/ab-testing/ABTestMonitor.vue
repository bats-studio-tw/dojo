<template>
  <div class="ab-test-monitor">
    <NCard title="A/B 测试监控" class="mb-6">
      <!-- 测试状态概览 -->
      <div class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <NStatistic label="活跃测试" :value="activeTestsCount" />
          <NStatistic label="总测试数" :value="tests.length" />
          <NStatistic label="今日新增" :value="todayNewTests" />
          <NStatistic label="完成测试" :value="completedTestsCount" />
        </div>
      </div>

      <!-- 操作按钮 -->
      <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <NButton type="primary" @click="showCreateForm = true">
            <template #icon>
              <Add />
            </template>
            创建新测试
          </NButton>
          <NButton type="info" @click="refreshTests" :loading="isLoading">
            <template #icon>
              <RefreshOutline />
            </template>
            刷新
          </NButton>
        </div>

        <div class="flex items-center space-x-2">
          <NSelect
            v-model:value="statusFilter"
            :options="statusOptions"
            placeholder="筛选状态"
            clearable
            class="w-32"
          />
          <NInput v-model:value="searchKeyword" placeholder="搜索测试名称" class="w-48" />
        </div>
      </div>

      <!-- 测试列表 -->
      <NDataTable
        :columns="columns"
        :data="filteredTests"
        :pagination="pagination"
        :loading="isLoading"
        :row-key="(row: any) => row.id"
        @update:page="handlePageChange"
      />

      <!-- 创建测试表单 -->
      <NModal v-model:show="showCreateForm" preset="card" title="创建 A/B 测试" style="width: 800px">
        <CreateABTestForm @created="handleTestCreated" @cancel="showCreateForm = false" />
      </NModal>
    </NCard>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed, onMounted, h } from 'vue';
  import { useMessage, NButton, NCard, NDataTable, NTag, NModal, NSelect, NInput, NStatistic } from 'naive-ui';
  import { Add, RefreshOutline } from '@vicons/ionicons5';
  import CreateABTestForm from './CreateABTestForm.vue';
  import api from '@/utils/api';

  // 响应式数据
  const tests = ref<any[]>([]);
  const isLoading = ref(false);
  const showCreateForm = ref(false);
  const statusFilter = ref<string | null>(null);
  const searchKeyword = ref('');
  const message = useMessage();

  // 分页
  const pagination = ref({
    page: 1,
    pageSize: 10,
    showSizePicker: true,
    pageSizes: [10, 20, 50],
    itemCount: 0
  });

  // 计算属性
  const activeTestsCount = computed(() => tests.value.filter((test) => test.status === 'active').length);

  const completedTestsCount = computed(() => tests.value.filter((test) => test.status === 'completed').length);

  const todayNewTests = computed(() => {
    const today = new Date().toDateString();
    return tests.value.filter((test) => new Date(test.created_at).toDateString() === today).length;
  });

  const statusOptions = computed(() => [
    { label: '活跃', value: 'active' },
    { label: '暂停', value: 'paused' },
    { label: '完成', value: 'completed' },
    { label: '已停止', value: 'stopped' }
  ]);

  const filteredTests = computed(() => {
    let filtered = tests.value;

    if (statusFilter.value) {
      filtered = filtered.filter((test) => test.status === statusFilter.value);
    }

    if (searchKeyword.value) {
      const keyword = searchKeyword.value.toLowerCase();
      filtered = filtered.filter(
        (test) => test.name.toLowerCase().includes(keyword) || test.description.toLowerCase().includes(keyword)
      );
    }

    return filtered;
  });

  // 表格列定义
  const columns = [
    {
      title: '测试名称',
      key: 'name',
      render: (row: any) => {
        return h('div', { class: 'font-medium' }, row.name);
      }
    },
    {
      title: '状态',
      key: 'status',
      render: (row: any) => {
        const statusMap: Record<string, { type: string; text: string }> = {
          active: { type: 'success', text: '活跃' },
          paused: { type: 'warning', text: '暂停' },
          completed: { type: 'info', text: '完成' },
          stopped: { type: 'error', text: '已停止' }
        };
        const status = statusMap[row.status] || { type: 'default', text: row.status };
        return h(NTag, { type: status.type as any, size: 'small' }, { default: () => status.text });
      }
    },
    {
      title: '策略',
      key: 'strategies',
      render: (row: any) => {
        return h(
          'div',
          { class: 'space-y-1' },
          row.strategies.map((strategy: any) =>
            h(NTag, { type: 'info', size: 'small' }, { default: () => strategy.name })
          )
        );
      }
    },
    {
      title: '流量分配',
      key: 'traffic_distribution',
      render: (row: any) => {
        return h(
          'div',
          { class: 'text-sm' },
          Object.entries(row.traffic_distribution).map(([name, percentage]: [string, any]) =>
            h('div', { key: name }, `${name}: ${percentage}%`)
          )
        );
      }
    },
    {
      title: '开始时间',
      key: 'start_date',
      render: (row: any) => {
        return h('span', { class: 'text-sm text-gray-500' }, new Date(row.start_date).toLocaleDateString('zh-CN'));
      }
    },
    {
      title: '结束时间',
      key: 'end_date',
      render: (row: any) => {
        return h(
          'span',
          { class: 'text-sm text-gray-500' },
          row.end_date ? new Date(row.end_date).toLocaleDateString('zh-CN') : '-'
        );
      }
    },
    {
      title: '操作',
      key: 'actions',
      render: (row: any) => {
        return h('div', { class: 'flex items-center space-x-2' }, [
          h(
            NButton,
            {
              size: 'small',
              type: 'info',
              ghost: true,
              onClick: () => viewReports(row.id)
            },
            { default: () => '查看报告' }
          ),
          h(
            NButton,
            {
              size: 'small',
              type: row.status === 'active' ? 'warning' : 'success',
              ghost: true,
              onClick: () => toggleTestStatus(row)
            },
            { default: () => (row.status === 'active' ? '暂停' : '启动') }
          )
        ]);
      }
    }
  ];

  // 方法
  const fetchTests = async () => {
    isLoading.value = true;
    try {
      const response = await api.get('/api/v2/ab-tests');
      tests.value = response.data.data || [];
      pagination.value.itemCount = tests.value.length;
    } catch (error: any) {
      message.error('获取测试列表失败: ' + error.message);
    } finally {
      isLoading.value = false;
    }
  };

  const refreshTests = () => {
    fetchTests();
  };

  const handlePageChange = (page: number) => {
    pagination.value.page = page;
  };

  const handleTestCreated = () => {
    showCreateForm.value = false;
    fetchTests();
    message.success('A/B 测试创建成功');
  };

  const viewReports = (testId: number) => {
    // 跳转到报告页面
    console.log('查看报告:', testId);
  };

  const toggleTestStatus = async (test: any) => {
    try {
      const newStatus = test.status === 'active' ? 'paused' : 'active';
      await api.put(`/api/v2/ab-tests/${test.id}/status`, { status: newStatus });
      test.status = newStatus;
      message.success(`测试已${newStatus === 'active' ? '启动' : '暂停'}`);
    } catch (error: any) {
      message.error('操作失败: ' + error.message);
    }
  };

  // 生命周期
  onMounted(() => {
    fetchTests();
  });
</script>

<style scoped>
  .test-detail {
    max-height: 600px;
    overflow-y: auto;
  }
</style>
