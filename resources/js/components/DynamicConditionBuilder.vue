<template>
  <div class="space-y-4">
    <!-- 条件构建器头部 -->
    <div class="flex items-center justify-between">
      <NTooltip trigger="hover" placement="top">
        <template #trigger>
          <span class="inline-flex cursor-help items-center text-sm text-gray-300 font-medium space-x-1">
            <span>动态条件构建器</span>
            <span class="text-blue-400">ℹ️</span>
          </span>
        </template>
        <div class="space-y-1">
          <div><strong>动态条件构建器说明：</strong></div>
          <div>• 通过可视化界面组合多个下注条件</div>
          <div>• 点击 + 号添加新条件</div>
          <div>• 选择 AND/OR 逻辑连接条件</div>
          <div>• 支持删除和重新排序条件</div>
        </div>
      </NTooltip>
      <n-button @click="addCondition" :disabled="disabled" size="tiny" type="primary">
        <template #icon>
          <span>➕</span>
        </template>
        添加条件
      </n-button>
    </div>

    <!-- 条件列表 -->
    <div class="space-y-3">
      <div
        v-for="(condition, index) in modelValue"
        :key="condition.id"
        class="border border-gray-500/20 rounded-lg bg-gray-500/5 p-3"
      >
        <!-- 条件头部 -->
        <div class="mb-3 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <span class="text-xs text-gray-400">条件 {{ index + 1 }}</span>
            <!-- 逻辑连接符（除第一个条件外） -->
            <div v-if="index > 0" class="flex items-center space-x-2">
              <n-select
                :value="condition.logic"
                :options="[
                  { label: 'AND', value: 'and' },
                  { label: 'OR', value: 'or' }
                ]"
                size="tiny"
                class="!w-20"
                @update:value="updateConditionLogic(condition.id, $event)"
              />
            </div>
          </div>
          <n-button @click="removeCondition(condition.id)" :disabled="disabled" size="tiny" type="error" ghost>
            <template #icon>
              <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fill-rule="evenodd"
                  d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                  clip-rule="evenodd"
                />
              </svg>
            </template>
          </n-button>
        </div>

        <!-- 条件内容 -->
        <div class="grid grid-cols-3 gap-2">
          <!-- 条件类型选择 -->
          <n-select
            :value="condition.type"
            :options="getConditionTypeOptions()"
            placeholder="选择条件"
            size="small"
            @update:value="updateConditionType(condition.id, $event)"
          />

          <!-- 操作符选择 -->
          <n-select
            :value="condition.operator"
            :options="getOperatorOptions(condition.type)"
            placeholder="操作符"
            size="small"
            @update:value="updateConditionOperator(condition.id, $event)"
          />

          <!-- 数值输入 -->
          <n-input-number
            :value="condition.value"
            :min="getMinValue(condition.type)"
            :max="getMaxValue(condition.type)"
            :step="getStepValue(condition.type)"
            :precision="getPrecision(condition.type)"
            :placeholder="getPlaceholder(condition.type)"
            size="small"
            @update:value="updateConditionValue(condition.id, $event)"
          />
        </div>

        <!-- 条件说明 -->
        <div class="mt-2 text-xs text-gray-400">
          {{ getConditionDescription(condition) }}
        </div>
      </div>

      <!-- 无条件时的提示 -->
      <div v-if="modelValue.length === 0" class="py-8 text-center">
        <NEmpty description="暂无条件，点击上方按钮添加条件" size="small" />
      </div>
    </div>

    <!-- 条件预览 -->
    <div v-if="modelValue.length > 0" class="border-t border-gray-600 pt-4">
      <div class="mb-2 text-sm text-gray-300 font-medium">条件预览：</div>
      <div class="rounded-lg bg-gray-800/50 p-3 text-xs text-gray-300">
        <div class="space-y-1">
          <div v-for="(condition, index) in modelValue" :key="condition.id" class="flex items-center">
            <span v-if="index > 0" class="mx-2 text-blue-400 font-bold">
              {{ condition.logic === 'and' ? 'AND' : 'OR' }}
            </span>
            <span>{{ getConditionPreview(condition) }}</span>
          </div>
        </div>
        <!-- 逻辑分组预览 -->
        <div class="mt-3 border-t border-gray-600 pt-2">
          <div class="mb-2 text-xs text-gray-400">逻辑分组预览：</div>
          <div class="rounded bg-gray-900/50 p-2 text-xs text-blue-300 font-mono">
            {{ getLogicGroupPreview(modelValue) }}
          </div>
        </div>
        <!-- 逻辑优先级说明 -->
        <div class="mt-2 text-xs text-gray-400">
          <div class="flex items-center space-x-1">
            <span class="text-yellow-400">⚠️</span>
            <span>逻辑优先级：AND 优先于 OR</span>
          </div>
          <div class="mt-1 text-gray-500">例如：A OR B AND C = A OR (B AND C)</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { NEmpty, NSelect, NInputNumber, NTooltip } from 'naive-ui';
  import { useConditionBuilder } from '@/composables/useConditionBuilder';

  // Props
  interface Props {
    modelValue: Array<{
      id: string;
      type: string;
      operator: string;
      value: number;
      logic: 'and' | 'or';
    }>;
    disabled?: boolean;
  }

  const props = withDefaults(defineProps<Props>(), {
    disabled: false
  });

  // Emits
  const emit = defineEmits<{
    'update:modelValue': [value: Props['modelValue']];
  }>();

  // 使用条件构建器composable
  const {
    generateId,
    getConditionTypeOptions,
    getOperatorOptions,
    getMinValue,
    getMaxValue,
    getStepValue,
    getPrecision,
    getPlaceholder,
    getConditionDescription,
    getConditionPreview,
    getLogicGroupPreview
  } = useConditionBuilder();

  // 🔧 修复：更新条件并触发emit
  const updateCondition = (id: string, updates: Partial<Props['modelValue'][0]>) => {
    const newConditions = props.modelValue.map((condition) => {
      if (condition.id === id) {
        return { ...condition, ...updates };
      }
      return condition;
    });
    emit('update:modelValue', newConditions);
  };

  // 更新条件类型
  const updateConditionType = (id: string, newType: string) => {
    const condition = props.modelValue.find((c) => c.id === id);
    if (condition) {
      // 根据条件类型设置合适的操作符和默认值
      let operator = condition.operator;
      let value = condition.value;

      if (['avg_rank', 'h2h_rank', 'momentum_rank'].includes(newType)) {
        operator = 'lte'; // 排名使用小于等于
      } else {
        operator = 'gte'; // 其他条件使用大于等于
      }

      // 设置默认值
      const placeholderValue = getPlaceholder(newType);
      value = parseFloat(placeholderValue) || 0;

      updateCondition(id, { type: newType, operator, value });
    }
  };

  // 更新条件操作符
  const updateConditionOperator = (id: string, newOperator: string) => {
    updateCondition(id, { operator: newOperator });
  };

  // 更新条件值
  const updateConditionValue = (id: string, newValue: number | null) => {
    if (newValue !== null) {
      updateCondition(id, { value: newValue });
    }
  };

  // 更新条件逻辑
  const updateConditionLogic = (id: string, newLogic: 'and' | 'or') => {
    updateCondition(id, { logic: newLogic });
  };

  // 添加条件
  const addCondition = () => {
    const newCondition = {
      id: generateId(),
      type: 'confidence',
      operator: 'gte',
      value: 85,
      logic: 'and' as const
    };
    const newConditions = [...props.modelValue, newCondition];
    emit('update:modelValue', newConditions);
  };

  // 删除条件
  const removeCondition = (id: string) => {
    const newConditions = props.modelValue.filter((c) => c.id !== id);
    emit('update:modelValue', newConditions);
  };
</script>
