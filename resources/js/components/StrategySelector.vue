<template>
  <NCard title="策略选择与执行" class="mb-6">
    <div class="space-y-4">
      <!-- 策略选择 -->
      <div class="flex items-center space-x-4">
        <label class="text-sm text-gray-700 font-medium">选择策略:</label>
        <NSelect
          v-model:value="selectedStrategy"
          :options="strategyOptions"
          placeholder="请选择预测策略"
          class="w-64"
          :loading="isLoading"
        />
      </div>

      <!-- 代币输入 -->
      <div class="flex items-center space-x-4">
        <label class="text-sm text-gray-700 font-medium">预测代币:</label>
        <NInput
          v-model:value="tokenInput"
          placeholder="输入代币符号，用逗号分隔 (如: BTC,ETH,SOL)"
          class="flex-1"
          :disabled="!selectedStrategy"
        />
      </div>

      <!-- 操作按钮 -->
      <div class="flex items-center space-x-4">
        <NButton type="primary" @click="executePrediction" :loading="isLoading" :disabled="!canExecute">
          执行预测
        </NButton>

        <NButton type="info" @click="executeBacktest" :loading="isLoading" :disabled="!selectedStrategy">
          执行回测
        </NButton>

        <NButton type="success" @click="toggleStrategyInfo" :disabled="!selectedStrategy">策略详情</NButton>
      </div>

      <!-- 策略信息展示 -->
      <div v-if="showStrategyInfoFlag" class="mt-4 rounded-lg bg-gray-50 p-4">
        <h4 class="mb-2 text-gray-900 font-medium">{{ selectedStrategyInfo?.name }}</h4>
        <p class="mb-2 text-sm text-gray-600">{{ selectedStrategyInfo?.description }}</p>
        <div class="text-xs text-gray-500">
          <div>权重配置: {{ JSON.stringify(selectedStrategyInfo?.weights) }}</div>
          <div>标准化方式: {{ JSON.stringify(selectedStrategyInfo?.normalization) }}</div>
        </div>
      </div>
    </div>
  </NCard>
</template>

<script setup lang="ts">
  import { ref, computed, watch } from 'vue';
  import { NButton, NSelect, NInput, NCard } from 'naive-ui';

  // Props
  interface Props {
    strategies: any[];
    isLoading?: boolean;
  }

  const props = withDefaults(defineProps<Props>(), {
    isLoading: false
  });

  // Emits
  const emit = defineEmits<{
    execute: [strategy: string, tokens: string[]];
    backtest: [strategy: string];
  }>();

  // 响应式数据
  const selectedStrategy = ref<string | null>(null);
  const tokenInput = ref('');
  const showStrategyInfoFlag = ref(false);
  const message = (window as any).$message;

  // 计算属性
  const strategyOptions = computed(() =>
    props.strategies.map((strategy) => ({
      label: strategy.name,
      value: strategy.tag
    }))
  );

  const selectedStrategyInfo = computed(() => {
    if (!selectedStrategy.value) return null;
    return props.strategies.find((s) => s.tag === selectedStrategy.value);
  });

  const canExecute = computed(() => {
    return selectedStrategy.value && tokenInput.value.trim();
  });

  // 方法
  const executePrediction = () => {
    if (!canExecute.value) return;

    const tokens = tokenInput.value
      .split(',')
      .map((t) => t.trim())
      .filter((t) => t);

    if (tokens.length === 0) {
      message.error('请输入有效的代币符号');
      return;
    }

    emit('execute', selectedStrategy.value!, tokens);
  };

  const executeBacktest = () => {
    if (!selectedStrategy.value) return;
    emit('backtest', selectedStrategy.value);
  };

  const toggleStrategyInfo = () => {
    if (!selectedStrategy.value) return;
    showStrategyInfoFlag.value = !showStrategyInfoFlag.value;
  };

  // 监听策略变化
  watch(selectedStrategy, (newStrategy) => {
    if (newStrategy) {
      message.info(`已选择策略: ${strategyOptions.value.find((s) => s.value === newStrategy)?.label}`);
    }
  });
</script>

<style scoped>
  .strategy-selector {
    /* 组件样式 */
  }
</style>
