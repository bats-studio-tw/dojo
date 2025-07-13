<template>
  <div class="create-ab-test-form">
    <n-card title="創建新的A/B測試" class="mb-6">
      <n-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-placement="left"
        label-width="auto"
        require-mark-placement="right-hanging"
      >
        <n-grid :cols="24" :x-gap="24">
          <!-- 測試基本信息 -->
          <n-form-item-gi :span="12" label="測試名稱" path="name">
            <n-input v-model:value="formData.name" placeholder="輸入測試名稱" maxlength="100" show-count />
          </n-form-item-gi>

          <n-form-item-gi :span="12" label="測試描述" path="description">
            <n-input
              v-model:value="formData.description"
              type="textarea"
              placeholder="描述測試目的和預期結果"
              :rows="3"
              maxlength="500"
              show-count
            />
          </n-form-item-gi>

          <!-- 時間範圍 -->
          <n-form-item-gi :span="12" label="開始時間" path="start_date">
            <n-date-picker
              v-model:value="formData.start_date"
              type="datetime"
              placeholder="選擇開始時間"
              :is-date-disabled="disablePastDates"
            />
          </n-form-item-gi>

          <n-form-item-gi :span="12" label="結束時間" path="end_date">
            <n-date-picker
              v-model:value="formData.end_date"
              type="datetime"
              placeholder="選擇結束時間"
              :is-date-disabled="disablePastDates"
            />
          </n-form-item-gi>
        </n-grid>

        <!-- 策略配置 -->
        <div class="mt-6">
          <h3 class="text-lg font-semibold mb-4">策略配置</h3>

          <div
            v-for="(strategy, index) in formData.strategies"
            :key="index"
            class="strategy-item mb-4 p-4 border rounded-lg"
          >
            <div class="flex items-center justify-between mb-3">
              <h4 class="font-medium">策略 {{ index + 1 }}</h4>
              <n-button v-if="formData.strategies.length > 2" type="error" size="small" @click="removeStrategy(index)">
                刪除
              </n-button>
            </div>

            <n-grid :cols="24" :x-gap="16">
              <n-form-item-gi :span="8" :label="`策略名稱`" :path="`strategies.${index}.name`">
                <n-input v-model:value="strategy.name" placeholder="策略名稱" maxlength="50" />
              </n-form-item-gi>

              <n-form-item-gi :span="8" :label="`流量分配 (%)`" :path="`traffic_distribution.${strategy.name}`">
                <n-input-number
                  v-model:value="formData.traffic_distribution[strategy.name]"
                  placeholder="流量百分比"
                  :min="0"
                  :max="100"
                  :precision="1"
                  @update:value="updateTrafficDistribution"
                />
              </n-form-item-gi>

              <n-form-item-gi :span="8" :label="`策略標籤`" :path="`strategies.${index}.tag`">
                <n-select v-model:value="strategy.tag" :options="availableStrategies" placeholder="選擇策略" />
              </n-form-item-gi>
            </n-grid>
          </div>

          <n-button type="dashed" block @click="addStrategy" :disabled="formData.strategies.length >= 5">
            <template #icon>
              <n-icon><Plus /></n-icon>
            </template>
            添加策略 (最多5個)
          </n-button>
        </div>

        <!-- 流量分配摘要 -->
        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
          <h4 class="font-medium mb-2">流量分配摘要</h4>
          <div class="flex flex-wrap gap-2">
            <n-tag
              v-for="(percentage, strategy) in formData.traffic_distribution"
              :key="strategy"
              :type="getTrafficTagType(percentage)"
            >
              {{ strategy }}: {{ percentage }}%
            </n-tag>
          </div>
          <div class="mt-2 text-sm text-gray-600">
            總計: {{ totalTrafficPercentage }}%
            <span v-if="totalTrafficPercentage !== 100" class="text-red-500">(必須等於100%)</span>
          </div>
        </div>

        <!-- 提交按鈕 -->
        <div class="mt-6 flex justify-end space-x-4">
          <n-button @click="resetForm" :disabled="isSubmitting">重置</n-button>
          <n-button type="primary" :loading="isSubmitting" :disabled="!isFormValid" @click="submitForm">
            創建A/B測試
          </n-button>
        </div>
      </n-form>
    </n-card>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, onMounted } from 'vue';
  import { useMessage } from 'naive-ui';
  import { Plus } from '@vicons/ionicons5';
  import api from '@/utils/api';

  // 定義事件
  const emit = defineEmits<{
    testCreated: [testId: number];
  }>();

  // 響應式數據
  const formRef = ref();
  const message = useMessage();
  const isSubmitting = ref(false);
  const availableStrategies = ref([
    { label: '保守策略', value: 'conservative' },
    { label: '激進策略', value: 'aggressive' },
    { label: '平衡策略', value: 'balanced' },
    { label: '動能策略', value: 'momentum' },
    { label: '混合策略', value: 'hybrid' }
  ]);

  const formData = reactive({
    name: '',
    description: '',
    start_date: null as number | null,
    end_date: null as number | null,
    strategies: [
      { name: '策略A', tag: 'conservative' },
      { name: '策略B', tag: 'aggressive' }
    ],
    traffic_distribution: {
      策略A: 50,
      策略B: 50
    }
  });

  // 表單驗證規則
  const formRules = {
    name: {
      required: true,
      message: '請輸入測試名稱',
      trigger: 'blur'
    },
    start_date: {
      required: true,
      message: '請選擇開始時間',
      trigger: 'change'
    },
    end_date: {
      required: true,
      message: '請選擇結束時間',
      trigger: 'change'
    }
  };

  // 計算屬性
  const totalTrafficPercentage = computed(() => {
    return Object.values(formData.traffic_distribution).reduce((sum, val) => sum + (val || 0), 0);
  });

  const isFormValid = computed(() => {
    return (
      formData.name &&
      formData.start_date &&
      formData.end_date &&
      totalTrafficPercentage.value === 100 &&
      formData.strategies.every((s) => s.name && s.tag)
    );
  });

  // 方法
  const disablePastDates = (timestamp: number) => {
    return timestamp < Date.now();
  };

  const addStrategy = () => {
    if (formData.strategies.length < 5) {
      const strategyName = `策略${String.fromCharCode(65 + formData.strategies.length)}`;
      formData.strategies.push({
        name: strategyName,
        tag: 'conservative'
      });
      formData.traffic_distribution[strategyName] = 0;
      updateTrafficDistribution();
    }
  };

  const removeStrategy = (index: number) => {
    const strategyName = formData.strategies[index].name;
    formData.strategies.splice(index, 1);
    delete formData.traffic_distribution[strategyName];
    updateTrafficDistribution();
  };

  const updateTrafficDistribution = () => {
    // 重新分配流量，確保總和為100%
    const activeStrategies = formData.strategies.filter((s) => s.name);
    const equalShare = 100 / activeStrategies.length;

    activeStrategies.forEach((strategy) => {
      if (!formData.traffic_distribution[strategy.name]) {
        formData.traffic_distribution[strategy.name] = equalShare;
      }
    });
  };

  const getTrafficTagType = (percentage: number) => {
    if (percentage === 0) return 'error';
    if (percentage < 20) return 'warning';
    if (percentage < 50) return 'info';
    return 'success';
  };

  const submitForm = async () => {
    try {
      await formRef.value?.validate();
      isSubmitting.value = true;

      // 準備提交數據
      const submitData = {
        name: formData.name,
        description: formData.description,
        strategies: formData.strategies.reduce(
          (acc, strategy) => {
            acc[strategy.name] = strategy.tag;
            return acc;
          },
          {} as Record<string, string>
        ),
        traffic_distribution: formData.traffic_distribution,
        start_date: new Date(formData.start_date!).toISOString(),
        end_date: new Date(formData.end_date!).toISOString()
      };

      const response = await api.post('/api/ab-testing/start', submitData);

      if (response.data.success) {
        message.success('A/B測試創建成功！');
        emit('testCreated', response.data.data.test_id);
        resetForm();
      } else {
        message.error(response.data.message || '創建失敗');
      }
    } catch (error: any) {
      message.error(error.response?.data?.message || '創建A/B測試失敗');
    } finally {
      isSubmitting.value = false;
    }
  };

  const resetForm = () => {
    formRef.value?.restoreValidation();
    Object.assign(formData, {
      name: '',
      description: '',
      start_date: null,
      end_date: null,
      strategies: [
        { name: '策略A', tag: 'conservative' },
        { name: '策略B', tag: 'aggressive' }
      ],
      traffic_distribution: {
        策略A: 50,
        策略B: 50
      }
    });
  };

  // 生命週期
  onMounted(() => {
    // 初始化表單
  });
</script>

<style scoped>
  .strategy-item {
    @apply bg-gray-50;
  }
</style>
