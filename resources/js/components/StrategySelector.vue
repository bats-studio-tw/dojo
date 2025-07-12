<template>
  <div class="strategy-selector">
    <n-card title="预测策略选择" class="mb-4">
      <div class="flex flex-col gap-4">
        <!-- 策略选择下拉框 -->
        <div class="flex items-center gap-3">
          <label class="text-sm font-medium text-gray-700 min-w-20">策略类型:</label>
          <n-select
            v-model:value="selectedStrategy"
            :options="strategyOptions"
            placeholder="请选择预测策略"
            class="flex-1"
            :disabled="!strategies.length"
            @update:value="handleStrategyChange"
          />
        </div>

        <!-- 当前策略信息 -->
        <div v-if="currentStrategyInfo" class="bg-blue-50 p-3 rounded-lg">
          <h4 class="font-medium text-blue-900 mb-2">{{ currentStrategyInfo.name }}</h4>
          <p class="text-sm text-blue-700">{{ currentStrategyInfo.description }}</p>

          <!-- 权重信息 -->
          <div v-if="currentStrategyInfo.weights" class="mt-3">
            <h5 class="text-xs font-medium text-blue-800 mb-1">特征权重:</h5>
            <div class="flex flex-wrap gap-2">
              <n-tag v-for="(weight, feature) in currentStrategyInfo.weights" :key="feature" type="info" size="small">
                {{ feature }}: {{ (weight * 100).toFixed(0) }}%
              </n-tag>
            </div>
          </div>

          <!-- 标准化方法 -->
          <div v-if="currentStrategyInfo.normalization" class="mt-2">
            <h5 class="text-xs font-medium text-blue-800 mb-1">标准化方法:</h5>
            <div class="flex flex-wrap gap-2">
              <n-tag
                v-for="(method, feature) in currentStrategyInfo.normalization"
                :key="feature"
                type="success"
                size="small"
              >
                {{ feature }}: {{ method }}
              </n-tag>
            </div>
          </div>
        </div>

        <!-- 代币选择 -->
        <div class="flex items-center gap-3">
          <label class="text-sm font-medium text-gray-700 min-w-20">代币列表:</label>
          <n-select
            v-model:value="selectedTokens"
            :options="tokenOptions"
            placeholder="请选择要预测的代币"
            multiple
            class="flex-1"
            :disabled="!selectedStrategy"
          />
        </div>

        <!-- 操作按钮 -->
        <div class="flex gap-3">
          <n-button type="primary" :loading="isLoading" :disabled="!canExecute" @click="handleExecute">
            <template #icon>
              <n-icon><PlayCircleOutline /></n-icon>
            </template>
            执行预测
          </n-button>

          <n-button type="info" :disabled="!selectedStrategy" @click="handleBacktest">
            <template #icon>
              <n-icon><AnalyticsOutline /></n-icon>
            </template>
            执行回测
          </n-button>
        </div>
      </div>
    </n-card>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed, onMounted } from 'vue';
  import { useMessage } from 'naive-ui';
  import { PlayCircleOutline, AnalyticsOutline } from '@vicons/ionicons5';
  import type { StrategyDTO } from '@/types/prediction';

  // Props
  interface Props {
    strategies: StrategyDTO[];
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
  const selectedStrategy = ref<string>('');
  const selectedTokens = ref<string[]>([]);

  // 常用代币列表
  const commonTokens = [
    'BTC',
    'ETH',
    'SOL',
    'DOGE',
    'ADA',
    'DOT',
    'LINK',
    'UNI',
    'MATIC',
    'AVAX',
    'ATOM',
    'LTC',
    'BCH',
    'XRP',
    'TRX',
    'ETC'
  ];

  // 计算属性
  const strategyOptions = computed(() =>
    props.strategies.map((strategy) => ({
      label: strategy.name,
      value: strategy.tag,
      description: strategy.description
    }))
  );

  const tokenOptions = computed(() =>
    commonTokens.map((token) => ({
      label: token,
      value: token
    }))
  );

  const currentStrategyInfo = computed(() => props.strategies.find((s) => s.tag === selectedStrategy.value));

  const canExecute = computed(() => selectedStrategy.value && selectedTokens.value.length > 0);

  // 方法
  const handleStrategyChange = (value: string) => {
    selectedStrategy.value = value;
    // 可以在这里触发策略变更事件
  };

  const handleExecute = () => {
    if (!canExecute.value) {
      useMessage().warning('请选择策略和代币');
      return;
    }

    emit('execute', selectedStrategy.value, selectedTokens.value);
  };

  const handleBacktest = () => {
    if (!selectedStrategy.value) {
      useMessage().warning('请先选择策略');
      return;
    }

    emit('backtest', selectedStrategy.value);
  };

  // 生命周期
  onMounted(() => {
    // 如果有默认策略，可以在这里设置
  });
</script>

<style scoped>
  .strategy-selector {
    /* 组件样式 */
  }
</style>
