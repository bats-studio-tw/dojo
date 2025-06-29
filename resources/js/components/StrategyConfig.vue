<template>
  <div class="space-y-6">
    <!-- 策略模式选择 -->
    <div class="flex items-center justify-between">
      <h3 class="text-lg text-white font-semibold">📋 策略选择</h3>
      <div class="flex items-center space-x-2">
        <n-tag :type="customStrategyMode ? 'warning' : 'success'" size="small">
          {{ customStrategyMode ? '🎨 自定义模式' : '📋 模板模式' }}
        </n-tag>
        <n-button
          @click="customStrategyMode ? resetToTemplateMode() : switchToCustomMode()"
          :type="customStrategyMode ? 'default' : 'primary'"
          size="small"
        >
          {{ customStrategyMode ? '返回模板' : '自定义设置' }}
        </n-button>
      </div>
    </div>

    <!-- 策略模板选择 -->
    <div v-if="!customStrategyMode" class="grid grid-cols-1 gap-3 lg:grid-cols-3 md:grid-cols-2">
      <div
        v-for="(template, key) in strategyTemplates"
        :key="key"
        class="cursor-pointer border border-gray-500/30 rounded-lg bg-gray-500/10 p-3 transition-all duration-200 hover:border-blue-400/60 hover:bg-blue-500/10"
        :class="{ 'border-blue-400 bg-blue-500/20': selectedTemplate === key }"
        @click="applyStrategyTemplate(key)"
      >
        <div class="mb-2 flex items-center justify-between">
          <span class="text-sm text-white font-medium">{{ template.name }}</span>
          <n-tag :type="selectedTemplate === key ? 'primary' : 'default'" size="small">
            {{ template.confidence_threshold }}%
          </n-tag>
        </div>
        <div class="text-xs text-gray-400">{{ template.description }}</div>
        <div class="mt-2 flex flex-wrap gap-1">
          <span class="rounded bg-gray-600 px-1.5 py-0.5 text-xs text-gray-300">
            风险: {{ template.max_bet_percentage }}%
          </span>
          <span class="rounded bg-gray-600 px-1.5 py-0.5 text-xs text-gray-300">
            {{ getStrategyTypeName(template.strategy) }}
          </span>
        </div>
      </div>
    </div>

    <!-- 自定义模式提示 -->
    <div v-else class="border border-orange-500/30 rounded-lg bg-orange-500/10 p-4">
      <div class="mb-2 flex items-center space-x-2">
        <span class="text-orange-400">🎨</span>
        <span class="text-sm text-orange-400 font-medium">自定义策略模式</span>
      </div>
      <div class="text-xs text-gray-300">
        您现在处于自定义模式，可以在下方配置面板中手动调整所有参数。 预设模板功能已禁用，所有参数变更将实时应用。
      </div>
    </div>

    <!-- 策略验证结果 -->
    <div v-if="strategyValidation" class="border-t border-gray-600 pt-4">
      <h4 class="mb-3 text-lg text-white font-semibold">📊 策略验证结果</h4>

      <div class="grid grid-cols-2 gap-4 md:grid-cols-4 mb-4">
        <StatusCard
          title="符合条件"
          :value="strategyValidation.total_matched"
          subtitle="个游戏"
          icon="🎯"
          color="green"
        />
        <StatusCard
          title="成功概率"
          :value="`${(strategyValidation.success_probability * 100).toFixed(1)}%`"
          subtitle="预测平均"
          icon="📈"
          color="blue"
        />
        <StatusCard
          title="预期收益"
          :value="`$${strategyValidation.estimated_profit.toFixed(2)}`"
          subtitle="本轮预估"
          icon="💰"
          :color="strategyValidation.estimated_profit >= 0 ? 'green' : 'red'"
        />
        <StatusCard
          title="风险等级"
          :value="getRiskLevelText(strategyValidation.risk_level)"
          subtitle="风险评估"
          icon="⚠️"
          :color="getRiskLevelColor(strategyValidation.risk_level)"
        />
      </div>

      <!-- 符合条件的游戏列表 -->
      <div v-if="strategyValidation.matches.length > 0" class="space-y-2">
        <h5 class="text-sm text-gray-300 font-medium">🎮 符合条件的游戏 ({{ strategyValidation.matches.length }}个)</h5>
        <div class="grid grid-cols-1 gap-2 lg:grid-cols-3 sm:grid-cols-2">
          <div
            v-for="(match, index) in strategyValidation.matches"
            :key="index"
            class="border border-green-500/30 rounded bg-green-500/10 p-2"
          >
            <div class="flex items-center justify-between">
              <span class="text-sm text-white font-medium">{{ match.symbol }}</span>
              <span class="text-xs text-green-400">${{ match.bet_amount }}</span>
            </div>
            <div class="flex justify-between text-xs text-gray-400">
              <span>置信度: {{ match.confidence.toFixed(1) }}%</span>
              <span>预期: ${{ match.expected_return.toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 执行按钮 -->
      <div class="mt-4 text-center">
        <!-- 余额不足警告 -->
        <div
          v-if="!strategyValidation.balance_sufficient"
          class="mb-4 border border-red-500/30 rounded-lg bg-red-500/10 p-3"
        >
          <div class="flex items-center space-x-2">
            <span class="text-red-400">⚠️</span>
            <span class="text-sm text-red-400 font-medium">余额不足警告</span>
          </div>
          <div class="mt-1 text-xs text-gray-300">
            需要 ${{ strategyValidation.required_balance.toFixed(2) }}， 当前余额 ${{
              strategyValidation.actual_balance.toFixed(2)
            }}， 缺少 ${{ (strategyValidation.required_balance - strategyValidation.actual_balance).toFixed(2) }}
          </div>
        </div>

        <n-button
          v-if="strategyValidation.matches.length > 0"
          @click="$emit('executeStrategy')"
          :loading="executeLoading"
          :disabled="!strategyValidation.balance_sufficient"
          :type="strategyValidation.balance_sufficient ? 'success' : 'error'"
          size="large"
        >
          <template #icon>
            <span>{{ strategyValidation.balance_sufficient ? '🚀' : '⚠️' }}</span>
          </template>
          {{
            strategyValidation.balance_sufficient
              ? `一键执行策略下注 (${strategyValidation.matches.length}个)`
              : '余额不足，无法执行'
          }}
        </n-button>

        <div v-else class="text-center text-gray-400">
          <NEmpty description="当前没有符合策略条件的游戏" />
        </div>
      </div>
    </div>

    <!-- 策略回测 -->
    <div class="border-t border-gray-600 pt-4">
      <div class="flex items-center justify-between mb-3">
        <h4 class="text-lg text-white font-semibold">📈 策略回测</h4>
        <n-button
          @click="$emit('runBacktest')"
          :loading="backtestLoading"
          :disabled="!hasHistoryData"
          type="info"
          size="small"
        >
          <template #icon><span>⚡</span></template>
          运行回测
        </n-button>
      </div>

      <!-- 回测结果 -->
      <div v-if="backtestResults" class="space-y-3">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
          <StatusCard title="测试轮次" :value="backtestResults.total_rounds" icon="📊" color="blue" />
          <StatusCard title="胜率" :value="`${(backtestResults.win_rate * 100).toFixed(1)}%`" icon="🎯" color="green" />
          <StatusCard
            title="总收益"
            :value="`$${backtestResults.total_profit.toFixed(2)}`"
            icon="💰"
            :color="backtestResults.total_profit >= 0 ? 'green' : 'red'"
          />
          <StatusCard title="策略评级" :value="backtestResults.best_strategy" icon="⭐" color="purple" />
        </div>
      </div>

      <div v-else class="text-center text-gray-400">
        <div class="text-sm">点击"运行回测"查看当前策略在历史数据上的表现</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { NEmpty } from 'naive-ui';
  import StatusCard from './StatusCard.vue';

  interface Props {
    strategyTemplates: any;
    selectedTemplate: string;
    customStrategyMode: boolean;
    strategyValidation: any;
    backtestResults: any;
    backtestLoading: boolean;
    executeLoading: boolean;
    hasHistoryData: boolean;
  }

  interface Emits {
    (e: 'applyTemplate', key: string): void;
    (e: 'switchToCustom'): void;
    (e: 'resetToTemplate'): void;
    (e: 'executeStrategy'): void;
    (e: 'runBacktest'): void;
  }

  defineProps<Props>();
  const emit = defineEmits<Emits>();

  const getStrategyTypeName = (strategy: string) => {
    const map = {
      single_bet: '单项',
      multi_bet: '多项',
      hedge_bet: '对冲',
      rank_betting: '排名'
    };
    return map[strategy] || strategy;
  };

  const getRiskLevelText = (level: string) => {
    const map = {
      low: '低',
      medium: '中',
      high: '高'
    };
    return map[level] || level;
  };

  const getRiskLevelColor = (level: string) => {
    const map = {
      low: 'green',
      medium: 'yellow',
      high: 'red'
    };
    return map[level] || 'gray';
  };

  const applyStrategyTemplate = (key: string) => emit('applyTemplate', key);
  const switchToCustomMode = () => emit('switchToCustom');
  const resetToTemplateMode = () => emit('resetToTemplate');
</script>
