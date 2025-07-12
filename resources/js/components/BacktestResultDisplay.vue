<template>
  <div class="backtest-result-display">
    <n-card title="回测结果分析" class="mb-4">
      <div v-if="backtestResult" class="space-y-6">
        <!-- 主要指标 -->
        <div>
          <h4 class="text-lg font-medium mb-3">主要性能指标</h4>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <n-statistic
              label="胜率"
              :value="`${(backtestResult.win_rate * 100).toFixed(1)}%`"
              :value-style="{ color: backtestResult.win_rate > 0.6 ? '#18a058' : '#d03050' }"
            />
            <n-statistic
              label="保本率"
              :value="`${(backtestResult.profit_rate * 100).toFixed(1)}%`"
              :value-style="{ color: backtestResult.profit_rate > 0.5 ? '#18a058' : '#d03050' }"
            />
            <n-statistic
              label="夏普比率"
              :value="backtestResult.sharpe_ratio.toFixed(2)"
              :value-style="{ color: backtestResult.sharpe_ratio > 1 ? '#18a058' : '#d03050' }"
            />
            <n-statistic
              label="最大回撤"
              :value="`${(backtestResult.max_drawdown * 100).toFixed(1)}%`"
              :value-style="{ color: backtestResult.max_drawdown < 0.2 ? '#18a058' : '#d03050' }"
            />
          </div>
        </div>

        <!-- 盈亏分析 -->
        <div>
          <h4 class="text-lg font-medium mb-3">盈亏分析</h4>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <n-statistic
              label="最大盈利"
              :value="`${(backtestResult.max_profit * 100).toFixed(1)}%`"
              :value-style="{ color: '#18a058' }"
            />
            <n-statistic
              label="最大亏损"
              :value="`${(backtestResult.max_loss * 100).toFixed(1)}%`"
              :value-style="{ color: '#d03050' }"
            />
            <n-statistic
              label="盈亏比"
              :value="backtestResult.avg_profit_loss_ratio.toFixed(2)"
              :value-style="{ color: backtestResult.avg_profit_loss_ratio > 1 ? '#18a058' : '#d03050' }"
            />
          </div>
        </div>

        <!-- 策略信息 -->
        <div>
          <h4 class="text-lg font-medium mb-3">策略配置</h4>
          <div class="bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <h5 class="font-medium text-gray-700 mb-2">策略标签</h5>
                <n-tag type="info" size="large">{{ backtestResult.strategy_tag }}</n-tag>
              </div>
              <div>
                <h5 class="font-medium text-gray-700 mb-2">测试轮次</h5>
                <span class="text-lg font-bold text-blue-600">{{ backtestResult.total_rounds }}</span>
              </div>
            </div>

            <!-- 配置快照 -->
            <div v-if="backtestResult.config_snapshot" class="mt-4">
              <h5 class="font-medium text-gray-700 mb-2">配置快照</h5>
              <n-code :code="JSON.stringify(backtestResult.config_snapshot, null, 2)" language="json" />
            </div>
          </div>
        </div>

        <!-- 性能评级 -->
        <div>
          <h4 class="text-lg font-medium mb-3">性能评级</h4>
          <div class="flex items-center gap-4">
            <div class="text-center">
              <div class="text-3xl font-bold" :class="performanceColor">{{ performanceGrade }}</div>
              <div class="text-sm text-gray-600">综合评级</div>
            </div>
            <div class="flex-1">
              <div class="space-y-2">
                <div class="flex items-center gap-2">
                  <span class="text-sm text-gray-600 w-16">胜率:</span>
                  <n-progress
                    type="line"
                    :percentage="backtestResult.win_rate * 100"
                    :color="backtestResult.win_rate > 0.6 ? '#18a058' : '#d03050'"
                    :show-indicator="false"
                  />
                  <span class="text-sm font-mono">{{ (backtestResult.win_rate * 100).toFixed(1) }}%</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-sm text-gray-600 w-16">夏普比:</span>
                  <n-progress
                    type="line"
                    :percentage="Math.min(backtestResult.sharpe_ratio * 50, 100)"
                    :color="backtestResult.sharpe_ratio > 1 ? '#18a058' : '#d03050'"
                    :show-indicator="false"
                  />
                  <span class="text-sm font-mono">{{ backtestResult.sharpe_ratio.toFixed(2) }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-sm text-gray-600 w-16">回撤:</span>
                  <n-progress
                    type="line"
                    :percentage="backtestResult.max_drawdown * 100"
                    :color="backtestResult.max_drawdown < 0.2 ? '#18a058' : '#d03050'"
                    :show-indicator="false"
                  />
                  <span class="text-sm font-mono">{{ (backtestResult.max_drawdown * 100).toFixed(1) }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 建议 -->
        <div>
          <h4 class="text-lg font-medium mb-3">策略建议</h4>
          <div class="bg-blue-50 p-4 rounded-lg">
            <div class="space-y-2">
              <div v-if="backtestResult.win_rate > 0.6" class="flex items-center gap-2 text-green-700">
                <n-icon><CheckmarkCircle /></n-icon>
                <span>胜率表现优秀，策略稳定性良好</span>
              </div>
              <div v-else class="flex items-center gap-2 text-red-700">
                <n-icon><CloseCircle /></n-icon>
                <span>胜率偏低，建议优化策略参数</span>
              </div>

              <div v-if="backtestResult.sharpe_ratio > 1" class="flex items-center gap-2 text-green-700">
                <n-icon><CheckmarkCircle /></n-icon>
                <span>夏普比率良好，风险收益比合理</span>
              </div>
              <div v-else class="flex items-center gap-2 text-red-700">
                <n-icon><CloseCircle /></n-icon>
                <span>夏普比率偏低，风险控制需要改进</span>
              </div>

              <div v-if="backtestResult.max_drawdown < 0.2" class="flex items-center gap-2 text-green-700">
                <n-icon><CheckmarkCircle /></n-icon>
                <span>最大回撤控制良好，风险可控</span>
              </div>
              <div v-else class="flex items-center gap-2 text-red-700">
                <n-icon><CloseCircle /></n-icon>
                <span>最大回撤过大，需要加强风险控制</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 text-gray-500">
        <n-icon size="48" class="mb-4">
          <AnalyticsOutline />
        </n-icon>
        <p>暂无回测数据</p>
      </div>
    </n-card>
  </div>
</template>

<script setup lang="ts">
  import { computed } from 'vue';
  import { CheckmarkCircle, CloseCircle, AnalyticsOutline } from '@vicons/ionicons5';
  import type { BacktestResult } from '@/types/prediction';

  // Props
  interface Props {
    backtestResult: BacktestResult | null;
  }

  const props = defineProps<Props>();

  // 计算属性
  const performanceGrade = computed(() => {
    if (!props.backtestResult) return '-';

    const { win_rate, sharpe_ratio, max_drawdown } = props.backtestResult;

    // 综合评分算法
    let score = 0;
    score += win_rate * 40; // 胜率权重40%
    score += (Math.min(sharpe_ratio, 3) / 3) * 30; // 夏普比率权重30%，最高3分
    score += (1 - max_drawdown) * 30; // 回撤控制权重30%

    if (score >= 80) return 'A+';
    if (score >= 70) return 'A';
    if (score >= 60) return 'B+';
    if (score >= 50) return 'B';
    if (score >= 40) return 'C+';
    if (score >= 30) return 'C';
    return 'D';
  });

  const performanceColor = computed(() => {
    const grade = performanceGrade.value;
    if (grade === 'A+' || grade === 'A') return 'text-green-600';
    if (grade === 'B+' || grade === 'B') return 'text-blue-600';
    if (grade === 'C+' || grade === 'C') return 'text-yellow-600';
    return 'text-red-600';
  });
</script>

<style scoped>
  .backtest-result-display {
    /* 组件样式 */
  }
</style>
