<template>
  <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="⚙️ 策略参数配置">
    <NSpin :show="configLoading" :description="configLoading ? '正在加载云端配置...' : ''">
      <div class="space-y-6">
        <!-- 策略选择按钮 -->
        <div class="space-y-4">
          <div class="text-sm text-gray-300 font-medium">选择策略模式：</div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div
              v-for="strategy in strategyOptions"
              :key="strategy.key"
              class="cursor-pointer border border-gray-500/20 rounded-lg bg-gray-500/5 p-4 transition-all duration-200 hover:border-blue-400/40 hover:bg-blue-500/5"
              :class="{
                'border-blue-400 bg-blue-500/20': selectedStrategyKey === strategy.key
              }"
              @click="selectStrategy(strategy.key)"
            >
              <div class="mb-2 flex items-center justify-between">
                <span class="flex items-center text-sm text-white font-medium space-x-2">
                  <span>{{ strategy.icon }}</span>
                  <span>{{ strategy.name }}</span>
                </span>
                <n-tag :type="selectedStrategyKey === strategy.key ? 'primary' : 'default'" size="small">
                  {{ strategy.tag }}
                </n-tag>
              </div>
              <div class="text-xs text-gray-400">{{ strategy.description }}</div>
            </div>
          </div>
        </div>

        <!-- 动态条件构建器 -->
        <div class="border-t border-gray-600 pt-4">
          <DynamicConditionBuilder
            v-model="localConfig.dynamic_conditions"
            :disabled="isRunning"
            @update:model-value="updateConfig"
          />
        </div>

        <!-- 保存按钮 -->
        <div class="text-center space-y-2">
          <n-button @click="saveConfig" :disabled="isRunning" :loading="configSaving" type="primary">
            <template #icon>
              <span>💾</span>
            </template>
            {{ hasUID ? '保存到云端' : '本地保存' }}
          </n-button>

          <!-- 配置状态显示 -->
          <div class="text-xs text-gray-400 space-y-1">
            <div class="flex items-center justify-center gap-2">
              <span>状态:</span>
              <span v-if="configSaving" class="text-yellow-400">保存中...</span>
              <span v-else-if="configLoading" class="text-blue-400">加载中...</span>
              <span v-else class="text-green-400">就绪</span>
            </div>
          </div>
        </div>
      </div>
    </NSpin>
  </NCard>
</template>

<script setup lang="ts">
  import { computed, ref, watch, nextTick } from 'vue';
  import { NTag, NSpin } from 'naive-ui';
  import DynamicConditionBuilder from '@/components/DynamicConditionBuilder.vue';
  import type { AutoBettingConfig } from '@/composables/useAutoBettingConfig';
  import { useConditionBuilder } from '@/composables/useConditionBuilder';

  // Props
  interface Props {
    config: AutoBettingConfig;
    isRunning: boolean;
    configSaving: boolean;
    configLoading: boolean; // 新增：配置加载状态
    hasUID: boolean;
  }

  const props = defineProps<Props>();

  // 使用条件构建器
  const { generateId } = useConditionBuilder();

  // Emits
  const emit = defineEmits<{
    'update:config': [config: AutoBettingConfig];
    'save-config': [];
  }>();

  // 🔧 修复：创建本地config副本，避免直接修改props
  const localConfig = ref<AutoBettingConfig>(JSON.parse(JSON.stringify(props.config)));

  // 🔧 修复：监听props.config变化，同步到本地副本
  watch(
    () => props.config,
    (newConfig) => {
      localConfig.value = JSON.parse(JSON.stringify(newConfig));
    },
    { deep: true }
  );

  // 🔧 修复：更新配置并触发emit
  const updateConfig = () => {
    console.log(
      '🔄 [StrategyConfigPanel] updateConfig called with:',
      JSON.stringify(localConfig.value.dynamic_conditions, null, 2)
    );
    emit('update:config', JSON.parse(JSON.stringify(localConfig.value)));
  };

  // 策略选项
  const strategyOptions = [
    {
      key: 'realistic',
      name: '实战模式',
      icon: '🎯',
      tag: '推荐',
      description: '新手模板：条件最宽，确保每日都有机会可下'
    },
    {
      key: 'smart_ranking',
      name: '智能排名',
      icon: '🧠',
      tag: '智能',
      description: '基于智能对战预测的智能下注策略'
    },
    {
      key: 'custom',
      name: '自定义',
      icon: '🎨',
      tag: '高级',
      description: '完全自定义所有参数和条件'
    }
  ];

  // 被重命名的计算属性，用于"推断"当前配置属于哪种模式
  const computedStrategyType = computed(() => {
    if (localConfig.value.dynamic_conditions && localConfig.value.dynamic_conditions.length > 0) {
      const conditions = localConfig.value.dynamic_conditions;

      // 实战模式的判断逻辑 (简化，不需要每次都精确匹配value)
      const hasRealisticConditions =
        conditions.length === 4 &&
        conditions.every((c) => ['confidence', 'score', 'sample_count', 'win_rate'].includes(c.type));

      if (hasRealisticConditions) {
        return 'realistic';
      }

      // 智能排名模式的判断逻辑 (简化)
      const hasSmartRankingConditions = conditions.length === 1 && conditions[0].type === 'h2h_rank';

      if (hasSmartRankingConditions) {
        return 'smart_ranking';
      }

      return 'custom';
    }

    return 'realistic';
  });

  // [新增] 一个标志位，用于标识我们正在主动应用一个预设
  const isApplyingPreset = ref(false);

  // 使用 ref 作为用户选择的"唯一真实来源"
  const selectedStrategyKey = ref(computedStrategyType.value);

  // [修改] 调整 watch 逻辑，增加对标志位的判断
  watch(computedStrategyType, (newType) => {
    // 如果我们正在程序性地应用一个预设，就暂时不要让 watch 生效
    // 这是为了防止子组件 v-model 可能引发的更新循环
    if (isApplyingPreset.value) {
      return;
    }

    // 只有当计算出的类型和用户当前选择不一致时才更新
    // 主要是为了处理"变成custom"的场景
    if (selectedStrategyKey.value !== newType) {
      selectedStrategyKey.value = newType;
    }
  });

  // 应用实战模式配置
  const applyRealisticStrategy = () => {
    // 根据是否有UID设置硬编码的下注金额
    localConfig.value.bet_amount = props.hasUID ? 200 : 5;

    // 设置实战模式的动态条件：基础且宽松的条件，确保有足够的下注机会
    localConfig.value.dynamic_conditions = [
      {
        id: generateId(),
        type: 'confidence',
        operator: 'gte',
        value: 70,
        logic: 'and'
      },
      {
        id: generateId(),
        type: 'score',
        operator: 'gte',
        value: 50,
        logic: 'and'
      },
      {
        id: generateId(),
        type: 'sample_count',
        operator: 'gte',
        value: 8,
        logic: 'and'
      },
      {
        id: generateId(),
        type: 'win_rate',
        operator: 'gte',
        value: 20,
        logic: 'and'
      }
    ];

    updateConfig();
  };

  // 应用智能排名配置
  const applySmartRankingStrategy = () => {
    // 根据是否有UID设置硬编码的下注金额
    localConfig.value.bet_amount = props.hasUID ? 200 : 5;

    // 设置动态条件：智能对战预测排名 <= 3
    localConfig.value.dynamic_conditions = [
      {
        id: generateId(),
        type: 'h2h_rank',
        operator: 'eq',
        value: 1,
        logic: 'and'
      }
    ];

    updateConfig();
  };

  // [修改] 调整 selectStrategy 逻辑，使用标志位
  const selectStrategy = (strategyKey: string) => {
    // 如果点击的是当前已选中的，则不执行任何操作
    if (selectedStrategyKey.value === strategyKey) {
      return;
    }

    // 1. 设置标志位为 true，表示开始应用预设
    isApplyingPreset.value = true;

    // 2. 更新我们的"意图"状态
    selectedStrategyKey.value = strategyKey;

    // 3. 根据意图应用配置
    switch (strategyKey) {
      case 'realistic':
        applyRealisticStrategy();
        break;
      case 'smart_ranking':
        applySmartRankingStrategy();
        break;
      case 'custom':
        // 切换到自定义模式，不清空现有配置
        break;
    }

    // 4. 使用 nextTick，在 DOM 更新循环之后，将标志位重置为 false
    // 此时，所有数据和组件状态已经稳定下来
    nextTick(() => {
      isApplyingPreset.value = false;
    });
  };

  // 保存配置
  const saveConfig = () => {
    emit('save-config');
  };
</script>
