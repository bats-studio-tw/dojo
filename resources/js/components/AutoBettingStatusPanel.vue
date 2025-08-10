<template>
  <NCard
    class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
    :title="`自动下注状态 (${bettingMode === 'real' ? 'Real' : 'Dummy'})`"
    size="large"
  >
    <template #header-extra>
      <div class="flex items-center space-x-3">
        <div
          class="rounded px-2 py-1 text-xs font-medium"
          :class="
            bettingMode === 'real'
              ? 'bg-red-500/20 text-red-400 border border-red-500/30'
              : 'bg-blue-500/20 text-blue-400 border border-blue-500/30'
          "
        >
          {{ bettingMode === 'real' ? '真实模式' : '模拟模式' }}
        </div>
        <!-- 模式切换（可选显示） -->
        <NSwitch
          v-if="enableModeSwitch"
          :value="bettingMode"
          :checked-value="'real'"
          :unchecked-value="'dummy'"
          size="small"
          @update:value="$emit('changeMode', $event)"
        >
          <template #checked>
            <span class="text-xs text-red-400 font-medium">Real</span>
          </template>
          <template #unchecked>
            <span class="text-xs text-blue-400 font-medium">Dummy</span>
          </template>
        </NSwitch>
        <NButton v-if="!isRunning" :loading="toggleLoading" type="success" size="small" @click="$emit('start')">
          <template #icon>
            <span>▶️</span>
          </template>
          启动自动下注
        </NButton>
        <NButton v-else :loading="toggleLoading" type="error" size="small" @click="$emit('stop')">
          <template #icon>
            <span>⏹️</span>
          </template>
          停止自动下注
        </NButton>
      </div>
    </template>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 md:grid-cols-3 sm:grid-cols-2">
      <!-- 用户余额 -->
      <div
        class="prediction-stat-card border-blue-500/20 from-blue-500/5 to-indigo-600/2 bg-gradient-to-br hover:border-blue-400/30 hover:shadow-blue-500/10"
      >
        <div class="stat-icon">👤</div>
        <div class="stat-content">
          <div class="stat-label text-blue-300">{{ bettingMode === 'real' ? '真实余额' : '模拟余额' }}</div>
          <div class="stat-value text-blue-400">${{ (userBalance || 0).toFixed(2) }}</div>
          <div class="stat-desc text-blue-200/70">{{ bettingMode === 'real' ? 'OJO代币' : '模拟代币' }}</div>
        </div>
      </div>

      <!-- 策略匹配 -->
      <div
        class="prediction-stat-card border-cyan-500/20 from-cyan-500/5 to-blue-600/2 bg-gradient-to-br hover:border-cyan-400/30 hover:shadow-cyan-500/10"
      >
        <div class="stat-icon">🎯</div>
        <div class="stat-content">
          <div class="stat-label text-cyan-300">策略匹配</div>
          <div class="stat-value text-cyan-400">{{ strategyValidation?.total_matched || 0 }}</div>
          <div class="stat-desc text-cyan-200/70">符合条件的Token</div>
        </div>
      </div>

      <!-- 下注金额 -->
      <div
        class="prediction-stat-card border-cyan-500/20 from-cyan-500/5 to-blue-600/2 bg-gradient-to-br hover:border-cyan-400/30 hover:shadow-cyan-500/10"
      >
        <div class="stat-icon">💰</div>
        <div class="stat-content">
          <div class="stat-label text-cyan-300">所需金额</div>
          <div class="stat-value text-cyan-400">${{ (strategyValidation?.required_balance || 0).toFixed(0) }}</div>
          <div class="stat-desc text-cyan-200/70">总下注金额</div>
        </div>
      </div>

      <!-- 余额状态 -->
      <div
        class="prediction-stat-card hover:shadow-lg"
        :class="
          (strategyValidation?.balance_sufficient ?? true)
            ? 'border-green-500/20 from-green-500/5 to-emerald-600/2 bg-gradient-to-br hover:border-green-400/30 hover:shadow-green-500/10'
            : 'border-red-500/20 from-red-500/5 to-pink-600/2 bg-gradient-to-br hover:border-red-400/30 hover:shadow-red-500/10'
        "
      >
        <div class="stat-icon">{{ (strategyValidation?.balance_sufficient ?? true) ? '✅' : '❌' }}</div>
        <div class="stat-content">
          <div
            class="stat-label"
            :class="(strategyValidation?.balance_sufficient ?? true) ? 'text-green-300' : 'text-red-300'"
          >
            余额状态
          </div>
          <div
            class="stat-value"
            :class="(strategyValidation?.balance_sufficient ?? true) ? 'text-green-400' : 'text-red-400'"
          >
            {{ (strategyValidation?.balance_sufficient ?? true) ? '充足' : '不足' }}
          </div>
          <div
            class="stat-desc"
            :class="(strategyValidation?.balance_sufficient ?? true) ? 'text-green-200/70' : 'text-red-200/70'"
          >
            实际余额: ${{ (userBalance || 0).toFixed(0) }}
          </div>
        </div>
      </div>

      <!-- 总下注次数 -->
      <div
        class="prediction-stat-card border-amber-500/20 from-amber-500/5 to-yellow-600/2 bg-gradient-to-br hover:border-amber-400/30 hover:shadow-amber-500/10"
      >
        <div class="stat-icon">📊</div>
        <div class="stat-content">
          <div class="stat-label text-amber-300">总下注次数</div>
          <div class="stat-value text-amber-400">{{ autoBettingStatus?.total_bets || 0 }}</div>
          <div class="stat-desc text-amber-200/70">历史累计</div>
        </div>
      </div>
    </div>
  </NCard>
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import { NCard, NButton, NSwitch } from 'naive-ui';
  import type { UserInfo } from '@/types';
  import type { AutoBettingStatus } from '@/composables/useAutoBettingControl';

  interface Props {
    bettingMode: 'real' | 'dummy';
    userInfo: UserInfo | null;
    autoBettingStatus: AutoBettingStatus | null | undefined;
    strategyValidation?: { total_matched?: number; required_balance?: number; balance_sufficient?: boolean } | null;
    isRunning?: boolean;
    toggleLoading?: boolean;
    enableModeSwitch?: boolean;
  }

  const props = withDefaults(defineProps<Props>(), {
    bettingMode: 'real',
    userInfo: null,
    autoBettingStatus: undefined,
    strategyValidation: null,
    isRunning: false,
    toggleLoading: false,
    enableModeSwitch: false
  });

  const userBalance = computed(() => {
    if (!props.userInfo) return 0;
    return props.bettingMode === 'real' ? props.userInfo.ojoValue || 0 : props.userInfo.available || 0;
  });
</script>

<style scoped>
  .prediction-stat-card {
    @apply relative overflow-hidden border rounded-xl p-4 transition-all duration-300 hover:shadow-lg sm:p-6;
  }
  .stat-icon {
    @apply absolute right-2 top-2 text-xl opacity-20 sm:text-2xl;
  }
  .stat-content {
    @apply relative;
  }
  .stat-label {
    @apply text-xs font-medium sm:text-sm;
  }
  .stat-value {
    @apply mt-2 text-2xl font-bold sm:text-3xl;
  }
  .stat-desc {
    @apply mt-2 text-xs;
  }
</style>
