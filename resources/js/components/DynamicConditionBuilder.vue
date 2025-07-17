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
        class="border border-gray-500/30 rounded-lg bg-gray-500/10 p-3"
      >
        <!-- 条件头部 -->
        <div class="mb-3 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <span class="text-xs text-gray-400">条件 {{ index + 1 }}</span>
            <!-- 逻辑连接符（除第一个条件外） -->
            <div v-if="index > 0" class="flex items-center space-x-2">
              <n-select
                v-model:value="condition.logic"
                :options="[
                  { label: 'AND', value: 'and' },
                  { label: 'OR', value: 'or' }
                ]"
                size="tiny"
                class="w-16"
              />
            </div>
          </div>
          <n-button @click="removeCondition(condition.id)" :disabled="disabled" size="tiny" type="error" ghost>
            <template #icon>
              <span>🗑️</span>
            </template>
          </n-button>
        </div>

        <!-- 条件内容 -->
        <div class="grid grid-cols-3 gap-2">
          <!-- 条件类型选择 -->
          <n-select
            v-model:value="condition.type"
            :options="getConditionTypeOptions()"
            placeholder="选择条件"
            size="small"
            @update:value="onConditionTypeChange(condition)"
          />

          <!-- 操作符选择 -->
          <n-select
            v-model:value="condition.operator"
            :options="getOperatorOptions(condition.type)"
            placeholder="操作符"
            size="small"
          />

          <!-- 数值输入 -->
          <n-input-number
            v-model:value="condition.value"
            :min="getMinValue(condition.type)"
            :max="getMaxValue(condition.type)"
            :step="getStepValue(condition.type)"
            :precision="getPrecision(condition.type)"
            :placeholder="getPlaceholder(condition.type)"
            size="small"
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
        <div v-for="(condition, index) in modelValue" :key="condition.id">
          <span v-if="index > 0" class="mx-2 text-blue-400 font-bold">
            {{ condition.logic === 'and' ? 'AND' : 'OR' }}
          </span>
          <span>{{ getConditionPreview(condition) }}</span>
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
    onConditionTypeChange
  } = useConditionBuilder();

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
