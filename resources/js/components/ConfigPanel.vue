<template>
  <div class="space-y-6">
    <!-- 基础配置 -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- 资金管理 -->
      <div class="space-y-4">
        <h3 class="text-lg text-white font-semibold">💰 资金管理</h3>

        <div class="border border-green-500/30 rounded-lg bg-green-500/5 p-4">
          <label class="text-sm text-green-400 font-medium mb-3 block">单次下注金额</label>
          <n-input-number
            v-model:value="config.bet_amount"
            :min="200"
            :max="2000"
            :step="50"
            :disabled="autoBettingRunning"
            size="large"
            class="w-full"
          />
          <div class="mt-2 text-xs text-gray-400">
            每次下注的固定金额，建议根据钱包余额合理设置
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm text-gray-300 font-medium mb-2 block">每日止损百分比</label>
            <n-input-number
              v-model:value="config.daily_stop_loss_percentage"
              :min="5"
              :max="50"
              :step="5"
              :disabled="autoBettingRunning"
              class="w-full"
            />
          </div>
          <div>
            <label class="text-sm text-gray-300 font-medium mb-2 block">最大下注比例</label>
            <n-input-number
              v-model:value="config.max_bet_percentage"
              :min="5"
              :max="50"
              :step="1"
              :disabled="autoBettingRunning"
              class="w-full"
            />
          </div>
        </div>
      </div>

      <!-- 策略配置 -->
      <div class="space-y-4">
        <h3 class="text-lg text-white font-semibold">🎯 策略配置</h3>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm text-gray-300 font-medium mb-2 block">置信度阈值</label>
            <n-input-number
              v-model:value="config.confidence_threshold"
              :min="70"
              :max="99"
              :step="1"
              :disabled="autoBettingRunning"
              class="w-full"
            />
          </div>
          <div>
            <label class="text-sm text-gray-300 font-medium mb-2 block">分数差距阈值</label>
            <n-input-number
              v-model:value="config.score_gap_threshold"
              :min="3.0"
              :max="20.0"
              :step="0.5"
              :precision="1"
              :disabled="autoBettingRunning"
              class="w-full"
            />
          </div>
        </div>

        <div>
          <label class="text-sm text-gray-300 font-medium mb-2 block">下注策略</label>
          <n-select
            v-model:value="config.strategy"
            :options="strategyOptions"
            :disabled="autoBettingRunning"
          />
        </div>
      </div>
    </div>

    <!-- 高级配置 -->
    <n-collapse>
      <n-collapse-item title="高级配置" name="advanced">
        <template #header-extra>
          <span class="text-xs text-gray-400">点击展开更多选项</span>
        </template>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <div class="space-y-4">
            <div>
              <label class="text-sm text-gray-300 font-medium mb-2 block">历史准确率阈值</label>
              <n-input-number
                v-model:value="config.historical_accuracy_threshold"
                :min="0.5"
                :max="1.0"
                :step="0.05"
                :precision="2"
                :disabled="autoBettingRunning"
                class="w-full"
              />
            </div>

            <div>
              <label class="text-sm text-gray-300 font-medium mb-2 block">最小样本数量</label>
              <n-input-number
                v-model:value="config.min_sample_count"
                :min="10"
                :max="200"
                :step="10"
                :disabled="autoBettingRunning"
                class="w-full"
              />
            </div>
          </div>

          <div class="space-y-4">
            <n-checkbox
              v-model:checked="config.enable_trend_analysis"
              :disabled="autoBettingRunning"
            >
              启用趋势分析
            </n-checkbox>

            <n-checkbox
              v-model:checked="config.enable_volume_filter"
              :disabled="autoBettingRunning"
            >
              启用成交量过滤
            </n-checkbox>

            <n-checkbox
              v-model:checked="config.enable_kelly_criterion"
              :disabled="autoBettingRunning"
            >
              启用Kelly准则
            </n-checkbox>
          </div>
        </div>
      </n-collapse-item>
    </n-collapse>

    <!-- 保存按钮 -->
    <div class="text-center">
      <n-button
        @click="$emit('saveConfig')"
        :disabled="autoBettingRunning"
        :loading="configSaving"
        type="primary"
        size="large"
      >
        <template #icon><span>💾</span></template>
        保存配置
      </n-button>
    </div>
  </div>
</template>

<script setup lang="ts">
  interface Props {
    config: any;
    autoBettingRunning: boolean;
    configSaving: boolean;
  }

  defineProps<Props>();
  defineEmits<{ saveConfig: () => void }>();

  const strategyOptions = [
    { label: '单项下注', value: 'single_bet' },
    { label: '多项下注', value: 'multi_bet' },
    { label: '对冲下注', value: 'hedge_bet' },
    { label: '指定排名下注', value: 'rank_betting' }
  ];
</script>
