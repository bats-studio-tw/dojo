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
                v-model:value="localConfig.bet_amount"
                :min="200"
                :max="2000"
                :step="50"
                :disabled="isRunning"
                size="small"
                @update:value="updateConfig"
              />
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
            <div class="flex items-center justify-center gap-2">
              <span>存储:</span>
              <span v-if="hasUID" class="text-blue-400">云端同步</span>
              <span v-else class="text-orange-400">仅本地</span>
            </div>
          </div>

          <!-- 调试按钮 -->
          <div v-if="isDev" class="mt-2 space-x-2">
            <n-button @click="debugSaveConfig" :disabled="isRunning" type="tertiary" size="small">
              <template #icon>
                <span>🔧</span>
              </template>
              调试保存
            </n-button>
            <n-button @click="testConditionMatching" :disabled="isRunning" type="tertiary" size="small">
              <template #icon>
                <span>🧪</span>
              </template>
              测试条件
            </n-button>
          </div>

          <!-- 调试信息显示 -->
          <div v-if="isDev" class="mt-4 border border-blue-500/30 rounded-lg bg-blue-500/10 p-3">
            <div class="mb-2 text-xs text-blue-300 font-medium">🔧 调试信息:</div>
            <div class="text-xs text-blue-400 space-y-1">
              <div>• hasUID: {{ hasUID }}</div>
              <div>• configSaving: {{ configSaving }}</div>
              <div>• configLoading: {{ configLoading }}</div>
              <div>• isRunning: {{ isRunning }}</div>
              <div>• JWT Token: {{ localConfig.jwt_token ? '已设置' : '未设置' }}</div>
              <div>• 动态条件数量: {{ localConfig.dynamic_conditions?.length || 0 }}</div>
              <div>• 配置大小: {{ JSON.stringify(localConfig).length }} 字符</div>
              <div>• 当前策略类型: {{ selectedStrategyKey }}</div>
              <div>• 计算策略类型: {{ computedStrategyType }}</div>
              <div>• 正在应用预设: {{ isApplyingPreset }}</div>
              <div v-if="localConfig.dynamic_conditions?.length > 0" class="mt-2">
                <div class="text-blue-300 font-medium">动态条件详情:</div>
                <div v-for="(condition, index) in localConfig.dynamic_conditions" :key="condition.id" class="ml-2">
                  <div>条件 {{ index + 1 }}: {{ condition.type }} {{ condition.operator }} {{ condition.value }}</div>
                </div>
              </div>
              <!-- 新增：条件匹配测试 -->
              <div class="mt-2">
                <div class="text-blue-300 font-medium">条件匹配测试:</div>
                <div class="ml-2 text-xs">
                  <div>测试Token: SUI (置信度: 86%, 排名: #1)</div>
                  <div v-for="(condition, index) in localConfig.dynamic_conditions" :key="condition.id" class="ml-2">
                    <div>
                      条件{{ index + 1 }} ({{ condition.type }} {{ condition.operator }} {{ condition.value }}):
                      <span
                        v-if="condition.type === 'h2h_rank' && condition.operator === 'lte' && condition.value >= 1"
                        class="text-green-400"
                      >
                        ✅ 通过
                      </span>
                      <span
                        v-else-if="
                          condition.type === 'confidence' && condition.operator === 'gte' && condition.value <= 86
                        "
                        class="text-green-400"
                      >
                        ✅ 通过
                      </span>
                      <span v-else class="text-red-400">❌ 不通过</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </NSpin>
  </NCard>
</template>

<script setup lang="ts">
  import { computed, ref, watch, nextTick } from 'vue';
  import { NTag, NInputNumber, NTooltip, NSpin } from 'naive-ui';
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

  // 开发环境检测
  const isDev = computed(() => import.meta.env.DEV);

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
    // 实战模式的基础配置
    localConfig.value.bet_amount = 200;

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
    // 智能排名配置 - 使用排名策略
    localConfig.value.bet_amount = 200;

    // 设置动态条件：智能对战预测排名 <= 3
    localConfig.value.dynamic_conditions = [
      {
        id: generateId(),
        type: 'h2h_rank',
        operator: 'lte',
        value: 3,
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

  // 调试保存配置
  const debugSaveConfig = () => {
    console.log('🔧 [StrategyConfigPanel] 开始调试保存配置...');

    // 检查配置数据
    console.log('📋 当前配置数据:', {
      hasUID: props.hasUID,
      configSaving: props.configSaving,
      configLoading: props.configLoading,
      isRunning: props.isRunning,
      configKeys: Object.keys(localConfig.value),
      dynamicConditions: localConfig.value.dynamic_conditions,
      configSize: JSON.stringify(localConfig.value).length
    });

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
      console.log('💾 保存状态:', {
        configSaving: props.configSaving
      });
    }, 3000);
  };

  // 🔧 新增：条件匹配测试函数
  const testConditionMatching = () => {
    console.log('🧪 [StrategyConfigPanel] 开始条件匹配测试...');

    // 模拟测试Token数据 - 包含AI预测和动能预测数据
    const testToken: any = {
      symbol: 'SUI',
      // AI预测数据 (来自currentAnalysis)
      rank_confidence: 86.3,
      predicted_rank: 1, // 智能对战预测排名
      predicted_final_value: 76.5,
      total_games: 12,
      win_rate: 18.8, // 胜率已经是百分比格式
      top3_rate: 83.3, // 保本率已经是百分比格式
      absolute_score: 84.5,
      relative_score: 66.7,
      // 动能预测数据 (来自hybridPredictions，合并后)
      momentum_rank: 2, // 动能预测排名
      mom_score: 0.75,
      final_score: 0.82,
      elo_prob: 0.65
    };

    console.log('📊 测试Token数据:', testToken);

    // 测试每个条件
    if (localConfig.value.dynamic_conditions && localConfig.value.dynamic_conditions.length > 0) {
      localConfig.value.dynamic_conditions.forEach((condition, index) => {
        let tokenValue = 0;
        let conditionResult = false;

        // 根据条件类型获取Token值
        switch (condition.type) {
          case 'confidence':
            tokenValue = testToken.rank_confidence || 0;
            break;
          case 'h2h_rank':
            tokenValue = testToken.predicted_rank || 999;
            break;
          case 'momentum_rank':
            tokenValue = testToken.momentum_rank || testToken.predicted_rank || 999;
            break;

          case 'sample_count':
            tokenValue = testToken.total_games || 0;
            break;
          case 'win_rate':
            tokenValue = testToken.win_rate || 0;
            break;
          case 'top3_rate':
            tokenValue = testToken.top3_rate || 0;
            break;
          case 'momentum_score':
            tokenValue = testToken.momentum_score || testToken.mom_score || 0;
            break;
          case 'elo_win_rate':
            tokenValue = testToken.elo_win_rate || testToken.elo_prob || 0;
            break;
          default:
            tokenValue = 0;
        }

        // 评估条件
        switch (condition.operator) {
          case 'gte':
            conditionResult = tokenValue >= condition.value;
            break;
          case 'lte':
            conditionResult = tokenValue <= condition.value;
            break;
          case 'eq':
            conditionResult = Math.abs(tokenValue - condition.value) < 0.001;
            break;
          case 'ne':
            conditionResult = Math.abs(tokenValue - condition.value) >= 0.001;
            break;
          default:
            conditionResult = true;
        }

        console.log(`条件${index + 1} (${condition.type} ${condition.operator} ${condition.value}):`, {
          tokenValue,
          conditionResult: conditionResult ? '✅ 通过' : '❌ 不通过',
          details: `${tokenValue} ${condition.operator} ${condition.value}`
        });
      });
    } else {
      console.log('⚠️ 没有配置动态条件');
    }
  };
</script>
