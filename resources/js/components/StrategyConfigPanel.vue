<template>
  <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="⚙️ 策略参数配置">
    <div class="space-y-6">
      <!-- 策略选择按钮 -->
      <div class="space-y-4">
        <div class="text-sm text-gray-300 font-medium">选择策略模式：</div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <div
            v-for="strategy in strategyOptions"
            :key="strategy.key"
            class="cursor-pointer border border-gray-500/30 rounded-lg bg-gray-500/10 p-4 transition-all duration-200 hover:border-blue-400/60 hover:bg-blue-500/10"
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

      <!-- 基础配置 -->
      <div class="border-t border-gray-600 pt-4">
        <div class="grid grid-cols-1 gap-4">
          <div class="space-y-2">
            <NTooltip trigger="hover" placement="top">
              <template #trigger>
                <label class="inline-flex cursor-help items-center text-xs text-gray-300 font-medium space-x-1">
                  <span>下注金额</span>
                  <span class="text-blue-400">ℹ️</span>
                </label>
              </template>
              每次下注的固定金额，范围在 $200-$2000
              之间。金额越高收益越大，但风险也相应增加。建议根据个人资金情况合理设置。
            </NTooltip>
            <n-input-number
              v-model:value="config.bet_amount"
              :min="200"
              :max="2000"
              :step="50"
              :disabled="isRunning"
              size="small"
            />
          </div>
        </div>
      </div>

      <!-- 动态条件构建器 -->
      <div class="border-t border-gray-600 pt-4">
        <DynamicConditionBuilder v-model="config.dynamic_conditions" :disabled="isRunning" />
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
            <span v-else class="text-green-400">就绪</span>
          </div>
          <div class="flex items-center justify-center gap-2">
            <span>存储:</span>
            <span v-if="hasUID" class="text-blue-400">云端同步</span>
            <span v-else class="text-orange-400">仅本地</span>
          </div>
        </div>

        <!-- 调试按钮 -->
        <div v-if="import.meta.env.DEV" class="mt-2">
          <n-button @click="debugSaveConfig" :disabled="isRunning" type="tertiary" size="small">
            <template #icon>
              <span>🔧</span>
            </template>
            调试保存
          </n-button>
        </div>

        <!-- 保存问题帮助 -->
        <div class="mt-4 border border-gray-600/30 rounded-lg bg-gray-500/10 p-3">
          <div class="mb-2 text-xs text-gray-300 font-medium">💡 保存问题排查:</div>
          <div class="text-xs text-gray-400 space-y-1">
            <div>• 确保已完成JWT Token验证</div>
            <div>• 检查网络连接是否正常</div>
            <div>• 查看浏览器控制台错误信息</div>
            <div>• 尝试刷新页面后重新保存</div>
          </div>
        </div>
      </div>
    </div>
  </NCard>
</template>

<script setup lang="ts">
  import { computed, ref, watch, nextTick } from 'vue';
  import { NTag, NInputNumber, NTooltip } from 'naive-ui';
  import DynamicConditionBuilder from '@/components/DynamicConditionBuilder.vue';
  import type { AutoBettingConfig } from '@/composables/useAutoBettingConfig';

  // Props
  interface Props {
    config: AutoBettingConfig;
    isRunning: boolean;
    configSaving: boolean;
    hasUID: boolean;
  }

  const props = defineProps<Props>();

  // Emits
  const emit = defineEmits<{
    'update:config': [config: AutoBettingConfig];
    'save-config': [];
  }>();

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
      description: '基于AI预测排名的智能下注策略'
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
    if (props.config.dynamic_conditions && props.config.dynamic_conditions.length > 0) {
      const conditions = props.config.dynamic_conditions;

      // 实战模式的判断逻辑 (简化，不需要每次都精确匹配value)
      const hasRealisticConditions =
        conditions.length === 4 &&
        conditions.every((c) => ['confidence', 'score_gap', 'sample_count', 'historical_accuracy'].includes(c.type));

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
    const newConfig = { ...props.config };

    // 实战模式的基础配置
    newConfig.strategy_type = 'h2h_breakeven';
    newConfig.confidence_threshold = 85;
    newConfig.score_gap_threshold = 67;
    newConfig.min_sample_count = 12;
    newConfig.historical_accuracy_threshold = 25;
    newConfig.strategy = 'single_bet';

    // 设置实战模式的动态条件：基础且宽松的条件，确保有足够的下注机会
    newConfig.dynamic_conditions = [
      {
        id: `condition_${Date.now()}_confidence`,
        type: 'confidence',
        operator: 'gte',
        value: 70,
        logic: 'and'
      },
      {
        id: `condition_${Date.now()}_score_gap`,
        type: 'score_gap',
        operator: 'gte',
        value: 50,
        logic: 'and'
      },
      {
        id: `condition_${Date.now()}_sample_count`,
        type: 'sample_count',
        operator: 'gte',
        value: 8,
        logic: 'and'
      },
      {
        id: `condition_${Date.now()}_historical_accuracy`,
        type: 'historical_accuracy',
        operator: 'gte',
        value: 20,
        logic: 'and'
      }
    ];

    // 关闭所有高级过滤器，使用基础条件
    newConfig.enable_win_rate_filter = false;
    newConfig.enable_top3_rate_filter = false;
    newConfig.enable_avg_rank_filter = false;
    newConfig.enable_stability_filter = false;
    newConfig.enable_absolute_score_filter = false;
    newConfig.enable_relative_score_filter = false;
    newConfig.enable_h2h_score_filter = false;
    newConfig.enable_change_5m_filter = false;
    newConfig.enable_change_1h_filter = false;
    newConfig.enable_change_4h_filter = false;
    newConfig.enable_change_24h_filter = false;

    emit('update:config', newConfig);
  };

  // 应用智能排名配置
  const applySmartRankingStrategy = () => {
    const newConfig = { ...props.config };

    // 智能排名配置 - 使用排名策略
    newConfig.strategy_type = 'h2h_breakeven';
    newConfig.strategy = 'rank_betting';
    newConfig.rank_betting_enabled_ranks = [1, 2, 3];

    // 设置动态条件：AI预测排名 <= 3
    newConfig.dynamic_conditions = [
      {
        id: `condition_${Date.now()}_ranking`,
        type: 'h2h_rank',
        operator: 'lte',
        value: 3,
        logic: 'and'
      }
    ];

    emit('update:config', newConfig);
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

  // 调试保存配置
  const debugSaveConfig = () => {
    console.log('🔧 [StrategyConfigPanel] 开始调试保存配置...');

    // 检查配置数据
    console.log('📋 当前配置数据:', {
      hasUID: props.hasUID,
      configSaving: props.configSaving,
      isRunning: props.isRunning,
      configKeys: Object.keys(props.config),
      dynamicConditions: props.config.dynamic_conditions,
      configSize: JSON.stringify(props.config).length
    });

    // 检查localStorage
    try {
      const savedConfig = localStorage.getItem('autoBettingConfig');
      console.log('💾 localStorage状态:', {
        hasSavedConfig: !!savedConfig,
        savedConfigSize: savedConfig?.length || 0
      });
    } catch (error) {
      console.error('❌ localStorage检查失败:', error);
    }

    // 检查网络连接
    if (navigator.onLine) {
      console.log('🌐 网络连接: 在线');
    } else {
      console.log('🌐 网络连接: 离线');
    }

    // 触发保存并监听结果
    emit('save-config');

    // 3秒后检查保存结果
    setTimeout(() => {
      console.log('⏰ 3秒后检查保存结果...');
      try {
        const savedConfig = localStorage.getItem('autoBettingConfig');
        console.log('💾 保存后localStorage状态:', {
          hasSavedConfig: !!savedConfig,
          savedConfigSize: savedConfig?.length || 0,
          configSaving: props.configSaving
        });
      } catch (error) {
        console.error('❌ 保存后检查失败:', error);
      }
    }, 3000);
  };
</script>
