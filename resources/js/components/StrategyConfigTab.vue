<template>
  <div class="space-y-6">
    <!-- 策略模板选择区域 -->
    <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="🎯 智能策略选择" size="large">
      <div class="space-y-4">
        <!-- 策略模式状态指示器 -->
        <div class="mb-4 flex items-center justify-between">
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
        <div v-if="!customStrategyMode" class="space-y-3">
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-3 md:grid-cols-2">
            <div
              v-for="(template, key) in strategyTemplates"
              :key="key"
              class="cursor-pointer border border-gray-500/30 rounded-lg bg-gray-500/10 p-3 transition-all duration-200 hover:border-blue-400/60 hover:bg-blue-500/10"
              :class="{
                'border-blue-400 bg-blue-500/20': selectedTemplate === String(key)
              }"
              @click="applyStrategyTemplate(String(key))"
            >
              <div class="mb-2 flex items-center justify-between">
                <span class="text-sm text-white font-medium">{{ template.name }}</span>
                <n-tag :type="selectedTemplate === String(key) ? 'primary' : 'default'" size="small">
                  {{ template.confidence_threshold }}%
                </n-tag>
              </div>
              <div class="text-xs text-gray-400">{{ template.description }}</div>
              <div class="mt-2 flex flex-wrap gap-1">
                <span class="rounded bg-gray-600 px-1.5 py-0.5 text-xs text-gray-300">
                  风险: {{ template.max_bet_percentage }}%
                </span>
                <span class="rounded bg-gray-600 px-1.5 py-0.5 text-xs text-gray-300">
                  {{
                    template.strategy === 'single_bet'
                      ? '单项'
                      : template.strategy === 'multi_bet'
                        ? '多项'
                        : template.strategy === 'hedge_bet'
                          ? '对冲'
                          : '指定排名'
                  }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- 自定义模式提示 -->
        <div v-else class="space-y-3">
          <div class="border border-orange-500/30 rounded-lg bg-orange-500/10 p-4">
            <div class="mb-2 flex items-center space-x-2">
              <span class="text-orange-400">🎨</span>
              <span class="text-sm text-orange-400 font-medium">自定义策略模式</span>
            </div>
            <div class="text-xs text-gray-300">
              您现在处于自定义模式，可以在下方配置面板中手动调整所有参数。 预设模板功能已禁用，所有参数变更将实时应用。
            </div>
          </div>
        </div>

        <!-- 实时策略验证 -->
        <div v-if="strategyValidation" class="border-t border-gray-600 pt-4 space-y-3">
          <h3 class="text-lg text-white font-semibold">📊 策略验证结果</h3>

          <!-- 符合条件的游戏数量 -->
          <div class="border border-green-500/30 rounded-lg bg-green-500/10 p-4 text-center">
            <div class="text-sm text-green-400">符合条件的游戏</div>
            <div class="text-3xl text-white font-bold">{{ strategyValidation.total_matched }}</div>
            <div class="text-xs text-gray-400">个游戏符合当前策略</div>
          </div>

          <!-- 符合条件的游戏列表 -->
          <div v-if="strategyValidation.matches && strategyValidation.matches.length > 0" class="space-y-2">
            <div class="text-sm text-gray-300 font-medium">符合条件的游戏详情：</div>
            <div class="max-h-40 overflow-y-auto space-y-2">
              <div
                v-for="match in strategyValidation.matches"
                :key="match.symbol"
                class="flex items-center justify-between border border-gray-600/30 rounded-lg bg-gray-800/50 p-3"
              >
                <div class="flex items-center space-x-3">
                  <span class="text-white font-medium">{{ match.symbol }}</span>
                  <span class="text-xs text-gray-400">TOP{{ match.predicted_rank }}</span>
                  <span class="text-xs text-blue-400">{{ match.confidence }}%</span>
                </div>
                <div class="text-right">
                  <div class="text-sm text-green-400">${{ match.bet_amount }}</div>
                  <div class="text-xs text-gray-500">{{ match.sample_count }}局</div>
                </div>
              </div>
            </div>
          </div>

          <!-- 一键执行按钮 -->
          <div class="text-center">
            <n-button
              v-if="strategyValidation.matches && strategyValidation.matches.length > 0"
              @click="executeStrategyBetting"
              :loading="executeLoading"
              :disabled="!strategyValidation.balance_sufficient"
              :type="strategyValidation.balance_sufficient ? 'success' : 'error'"
              size="large"
              class="shadow-green-500/25 shadow-lg hover:shadow-green-500/40"
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

        <!-- 策略回测功能 -->
      </div>
    </NCard>

    <!-- 详细配置面板 -->
    <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="⚙️ 详细配置面板" size="large">
      <!-- 配置分组 -->
      <n-collapse default-expanded-names="['money', 'strategy']">
        <!-- 资金管理配置 -->
        <n-collapse-item title="💰 资金管理配置" name="money">
          <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- 基础配置 -->
            <div class="space-y-4">
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">单次下注金额</label>
                <n-input-number
                  v-model:value="config.bet_amount"
                  :min="200"
                  :max="2000"
                  :step="50"
                  :disabled="isRunning"
                  class="w-full"
                />
              </div>

              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">最大下注比例 (%)</label>
                <n-input-number
                  v-model:value="config.max_bet_percentage"
                  :min="5"
                  :max="50"
                  :step="1"
                  :disabled="isRunning"
                  class="w-full"
                />
              </div>
            </div>

            <!-- 风险控制 -->
            <div class="space-y-4">
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">每日止损百分比</label>
                <n-input-number
                  v-model:value="config.daily_stop_loss_percentage"
                  :min="5"
                  :max="50"
                  :step="5"
                  :disabled="isRunning"
                  class="w-full"
                />
              </div>
            </div>
          </div>
        </n-collapse-item>

        <!-- 策略配置 -->
        <n-collapse-item title="🎯 策略参数配置" name="strategy">
          <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="space-y-4">
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">置信度阈值 (%)</label>
                <n-input-number
                  v-model:value="config.confidence_threshold"
                  :min="70"
                  :max="99"
                  :step="1"
                  :disabled="isRunning || config.strategy === 'rank_betting'"
                  class="w-full"
                />
                <div v-if="config.strategy === 'rank_betting'" class="text-xs text-gray-500">
                  指定排名下注策略不使用置信度阈值
                </div>
              </div>

              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">分数差距阈值</label>
                <n-input-number
                  v-model:value="config.score_gap_threshold"
                  :min="3.0"
                  :max="20.0"
                  :step="0.5"
                  :disabled="isRunning || config.strategy === 'rank_betting'"
                  :precision="1"
                  class="w-full"
                />
                <div v-if="config.strategy === 'rank_betting'" class="text-xs text-gray-500">
                  指定排名下注策略不使用分数差距阈值
                </div>
              </div>
            </div>

            <div class="space-y-4">
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">下注策略</label>
                <n-select
                  v-model:value="config.strategy"
                  :options="[
                    { label: '单项下注', value: 'single_bet' },
                    { label: '多项下注', value: 'multi_bet' },
                    { label: '对冲下注', value: 'hedge_bet' },
                    { label: '指定排名下注', value: 'rank_betting' }
                  ]"
                  :disabled="isRunning"
                  class="w-full"
                />
              </div>
            </div>
          </div>

          <!-- 指定排名下注配置 -->
          <div v-if="config.strategy === 'rank_betting'" class="mt-6 border-t border-gray-600 pt-6">
            <h4 class="mb-4 text-lg text-white font-semibold">🏆 排名下注配置</h4>

            <!-- 排名选择 -->
            <div class="mb-6 space-y-3">
              <label class="text-sm text-gray-300 font-medium">选择要下注的排名</label>
              <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <div
                  v-for="rank in [1, 2, 3, 4, 5]"
                  :key="rank"
                  class="cursor-pointer border-2 rounded-lg p-3 text-center transition-all duration-200"
                  :class="
                    config.rank_betting_enabled_ranks.includes(rank)
                      ? 'border-blue-400 bg-blue-500/20 text-blue-400'
                      : 'border-gray-500/30 bg-gray-500/10 text-gray-400 hover:border-gray-400/60'
                  "
                  @click="toggleRankBetting(rank, !config.rank_betting_enabled_ranks.includes(rank))"
                >
                  <div class="text-2xl font-bold">TOP {{ rank }}</div>
                  <div class="mt-1 text-xs">
                    {{ config.rank_betting_enabled_ranks.includes(rank) ? '已启用' : '点击启用' }}
                  </div>
                </div>
              </div>
              <div class="text-xs text-gray-500">
                已选择 {{ config.rank_betting_enabled_ranks.length }} 个排名， 预计每轮下注金额: ${{
                  getTotalRankBettingAmount()
                }}
              </div>
            </div>

            <!-- 金额配置 -->
            <div class="space-y-4">
              <div class="flex items-center space-x-4">
                <n-switch v-model:value="config.rank_betting_different_amounts" :disabled="isRunning" />
                <label class="text-sm text-gray-300 font-medium">
                  {{ config.rank_betting_different_amounts ? '为不同排名设置不同金额' : '所有排名使用相同金额' }}
                </label>
              </div>

              <!-- 统一金额设置 -->
              <div v-if="!config.rank_betting_different_amounts" class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">每个排名的下注金额</label>
                <n-input-number
                  v-model:value="config.rank_betting_amount_per_rank"
                  :min="200"
                  :max="2000"
                  :step="50"
                  :disabled="isRunning"
                  class="w-full"
                />
              </div>

              <!-- 分别金额设置 -->
              <div v-else class="space-y-4">
                <div v-for="rank in config.rank_betting_enabled_ranks" :key="rank" class="flex items-center space-x-4">
                  <div class="flex items-center space-x-2">
                    <span class="text-blue-400 font-bold">TOP {{ rank }}</span>
                    <span class="text-xs text-gray-500">下注金额:</span>
                  </div>
                  <n-input-number
                    v-if="rank === 1"
                    v-model:value="config.rank_betting_rank1_amount"
                    :min="200"
                    :max="2000"
                    :step="50"
                    :disabled="isRunning"
                    class="flex-1"
                  />
                  <n-input-number
                    v-else-if="rank === 2"
                    v-model:value="config.rank_betting_rank2_amount"
                    :min="200"
                    :max="2000"
                    :step="50"
                    :disabled="isRunning"
                    class="flex-1"
                  />
                  <n-input-number
                    v-else-if="rank === 3"
                    v-model:value="config.rank_betting_rank3_amount"
                    :min="200"
                    :max="2000"
                    :step="50"
                    :disabled="isRunning"
                    class="flex-1"
                  />
                  <n-input-number
                    v-else
                    v-model:value="config.rank_betting_amount_per_rank"
                    :min="200"
                    :max="2000"
                    :step="50"
                    :disabled="isRunning"
                    class="flex-1"
                  />
                </div>
              </div>
            </div>

            <!-- 排名下注说明 -->
            <div class="mt-4 border border-blue-500/30 rounded-lg bg-blue-500/10 p-4">
              <div class="mb-2 flex items-center space-x-2">
                <span class="text-blue-400">ℹ️</span>
                <span class="text-sm text-blue-400 font-medium">指定排名下注说明</span>
              </div>
              <div class="text-xs text-gray-300 space-y-1">
                <div>• 每轮游戏会自动下注您选择的排名，无需满足其他条件</div>
                <div>• 下注基于AI预测的排名结果，不考虑置信度等其他指标</div>
                <div>• 建议选择TOP 1-3排名以获得更好的胜率</div>
                <div>• 请合理设置下注金额，控制风险</div>
              </div>
            </div>
          </div>
        </n-collapse-item>
      </n-collapse>

      <!-- 保存配置按钮 -->
      <div class="mt-6 text-center">
        <n-button @click="manualSaveConfig" :disabled="isRunning" :loading="configSaving" type="primary" size="large">
          <template #icon>
            <span>💾</span>
          </template>
          {{ hasUID ? '保存配置到云端' : '保存配置到本地' }}
        </n-button>
      </div>
    </NCard>
  </div>
</template>

<script setup lang="ts">
  import { NEmpty } from 'naive-ui';
  import type { AutoBettingConfig } from '@/composables/useAutoBettingConfig';
  import { useAutoBettingConfig } from '@/composables/useAutoBettingConfig';

  // Props
  interface Props {
    config: AutoBettingConfig;
    selectedTemplate: string;
    customStrategyMode: boolean;
    configSaving: boolean;
    configSyncStatus: { type: 'success' | 'error' | 'info'; message: string } | null;
    strategyTemplates: any;
    strategyValidation: any;

    executeLoading: boolean;
    isRunning: boolean;
    hasUID: boolean;
  }

  const props = defineProps<Props>();

  // Emits
  const emit = defineEmits<{
    applyStrategyTemplate: [key: string];
    switchToCustomMode: [];
    resetToTemplateMode: [];
    executeStrategyBetting: [];

    manualSaveConfig: [];
  }>();

  // 排名下注相关方法 - 直接操作props中的config
  const toggleRankBetting = (rank: number, checked: boolean) => {
    if (checked) {
      if (!props.config.rank_betting_enabled_ranks.includes(rank)) {
        props.config.rank_betting_enabled_ranks.push(rank);
        props.config.rank_betting_enabled_ranks.sort((a, b) => a - b);
      }
    } else {
      const index = props.config.rank_betting_enabled_ranks.indexOf(rank);
      if (index > -1) {
        props.config.rank_betting_enabled_ranks.splice(index, 1);
      }
    }
  };

  // 获取其他排名下注相关方法
  const { getRankBettingAmount, getTotalRankBettingAmount } = useAutoBettingConfig();

  // Methods
  const applyStrategyTemplate = (key: string) => emit('applyStrategyTemplate', key);
  const switchToCustomMode = () => emit('switchToCustomMode');
  const resetToTemplateMode = () => emit('resetToTemplateMode');
  const executeStrategyBetting = () => emit('executeStrategyBetting');

  const manualSaveConfig = () => emit('manualSaveConfig');
</script>
