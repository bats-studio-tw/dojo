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
              'border-blue-400 bg-blue-500/20': selectedStrategy === strategy.key
            }"
            @click="selectStrategy(strategy.key)"
          >
            <div class="mb-2 flex items-center justify-between">
              <span class="flex items-center text-sm text-white font-medium space-x-2">
                <span>{{ strategy.icon }}</span>
                <span>{{ strategy.name }}</span>
              </span>
              <n-tag :type="selectedStrategy === strategy.key ? 'primary' : 'default'" size="small">
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

      <!-- 下注策略选择 -->
      <div class="border-t border-gray-600 pt-4">
        <div class="grid grid-cols-1 gap-4">
          <div class="space-y-2">
            <NTooltip trigger="hover" placement="top">
              <template #trigger>
                <label class="inline-flex cursor-help items-center text-xs text-gray-300 font-medium space-x-1">
                  <span>下注策略</span>
                  <span class="text-blue-400">ℹ️</span>
                </label>
              </template>
              <div class="space-y-1">
                <div>
                  <strong>单项:</strong>
                  只下注最优的一个 Token
                </div>
                <div>
                  <strong>多项:</strong>
                  下注所有符合条件的 Token
                </div>
                <div>
                  <strong>对冲:</strong>
                  同时下注多个以分散风险
                </div>
                <div>
                  <strong>排名:</strong>
                  固定下注指定排名的 Token
                </div>
              </div>
            </NTooltip>
            <n-select
              v-model:value="config.strategy"
              :options="[
                { label: '单项', value: 'single_bet' },
                { label: '多项', value: 'multi_bet' },
                { label: '对冲', value: 'hedge_bet' },
                { label: '排名', value: 'rank_betting' }
              ]"
              :disabled="isRunning"
              size="small"
            />
          </div>
        </div>
      </div>

      <!-- 指定排名下注配置 -->
      <div v-if="config.strategy === 'rank_betting'" class="border-t border-gray-600 pt-4">
        <NTooltip trigger="hover" placement="top">
          <template #trigger>
            <label class="mb-2 block inline-flex cursor-help items-center text-xs text-gray-300 font-medium space-x-1">
              <span>选择排名</span>
              <span class="text-blue-400">ℹ️</span>
            </label>
          </template>
          <div class="space-y-1">
            <div><strong>排名策略说明：</strong></div>
            <div>• 自动下注 AI 预测排名为指定位置的 Token</div>
            <div>• 忽略置信度、分数等其他条件，只看排名</div>
            <div>• TOP1-3 通常有奖励，风险相对较低</div>
            <div>• 可以选择多个排名进行组合下注</div>
          </div>
        </NTooltip>
        <div class="grid grid-cols-5 gap-2">
          <div
            v-for="rank in [1, 2, 3, 4, 5]"
            :key="rank"
            class="cursor-pointer border-2 rounded p-2 text-center text-xs transition-all duration-200"
            :class="
              config.rank_betting_enabled_ranks.includes(rank)
                ? 'border-blue-400 bg-blue-500/20 text-blue-400'
                : 'border-gray-500/30 bg-gray-500/10 text-gray-400 hover:border-gray-400/60'
            "
            @click="toggleRankBetting(rank, !config.rank_betting_enabled_ranks.includes(rank))"
          >
            <div class="font-bold">TOP{{ rank }}</div>
          </div>
        </div>
      </div>

      <!-- 保存按钮 -->
      <div class="text-center">
        <n-button @click="saveConfig" :disabled="isRunning" :loading="configSaving" type="primary">
          <template #icon>
            <span>💾</span>
          </template>
          {{ hasUID ? '保存到云端' : '本地保存' }}
        </n-button>
      </div>
    </div>
  </NCard>
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import { NTag, NInputNumber, NTooltip, NSelect } from 'naive-ui';
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

  // 当前选中的策略
  const selectedStrategy = computed(() => {
    // 根据配置判断当前策略
    if (props.config.dynamic_conditions && props.config.dynamic_conditions.length > 0) {
      return 'custom';
    }
    // 这里可以根据其他配置判断是实战模式还是智能排名
    return 'realistic';
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

    // 清空动态条件，使用传统配置
    newConfig.dynamic_conditions = [];

    // 关闭所有高级过滤器
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

    // 智能排名配置
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

  // 选择策略
  const selectStrategy = (strategyKey: string) => {
    switch (strategyKey) {
      case 'realistic':
        // 应用实战模式配置
        applyRealisticStrategy();
        break;
      case 'smart_ranking':
        // 应用智能排名配置
        applySmartRankingStrategy();
        break;
      case 'custom':
        // 切换到自定义模式，不清空现有配置
        break;
    }
  };

  // 排名下注相关方法
  const toggleRankBetting = (rank: number, checked: boolean) => {
    const newConfig = { ...props.config };

    if (checked) {
      if (!newConfig.rank_betting_enabled_ranks.includes(rank)) {
        newConfig.rank_betting_enabled_ranks.push(rank);
        newConfig.rank_betting_enabled_ranks.sort((a: number, b: number) => a - b);
      }
    } else {
      const index = newConfig.rank_betting_enabled_ranks.indexOf(rank);
      if (index > -1) {
        newConfig.rank_betting_enabled_ranks.splice(index, 1);
      }
    }

    emit('update:config', newConfig);
  };

  // 保存配置
  const saveConfig = () => {
    emit('save-config');
  };
</script>
