<template>
  <div class="betting-performance-analysis">
    <!-- 分析控制面板 -->
    <div class="mb-6 rounded-lg border border-gray-600 bg-gray-800/50 p-4">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-white">📊 投注表现分析</h3>
        <div class="flex items-center space-x-3">
          <div class="flex items-center space-x-2">
            <label class="text-sm text-gray-300">分析周期:</label>
            <n-select v-model:value="selectedDays" :options="dayOptions" size="small" class="w-32" />
          </div>
          <n-button @click="refreshAnalysis" :loading="loading" type="primary" size="small">
            <template #icon>
              <span>🔄</span>
            </template>
            刷新分析
          </n-button>
        </div>
      </div>

      <!-- 快速概览 -->
      <div v-if="performanceData" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-lg border border-green-500/30 bg-green-500/10 p-3">
          <div class="text-xs text-green-400">实际保本率</div>
          <div class="text-xl font-bold text-green-400">{{ performanceData.betting_performance.break_even_rate }}%</div>
          <div class="text-xs text-gray-400">
            (ROI: {{ performanceData.betting_performance.actual_roi_percentage }}%)
          </div>
        </div>

        <div class="rounded-lg border border-blue-500/30 bg-blue-500/10 p-3">
          <div class="text-xs text-blue-400">胜率</div>
          <div class="text-xl font-bold text-blue-400">
            {{ performanceData.betting_performance.win_rate_percentage }}%
          </div>
          <div class="text-xs text-gray-400">
            {{ performanceData.betting_performance.betting_distribution.winning_bets }}/{{
              performanceData.betting_performance.settled_bets
            }}
            胜
          </div>
        </div>

        <div class="rounded-lg border border-purple-500/30 bg-purple-500/10 p-3">
          <div class="text-xs text-purple-400">总投入</div>
          <div class="text-xl font-bold text-purple-400">
            ${{ performanceData.betting_performance.total_amount_invested }}
          </div>
          <div class="text-xs text-gray-400">{{ performanceData.betting_performance.total_bets }} 笔下注</div>
        </div>

        <div class="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3">
          <div class="text-xs text-yellow-400">实际盈亏</div>
          <div
            class="text-xl font-bold"
            :class="getProfitLossClass(performanceData.betting_performance.actual_profit_loss)"
          >
            ${{ performanceData.betting_performance.actual_profit_loss }}
          </div>
          <div class="text-xs text-gray-400">
            平均: ${{ performanceData.betting_performance.average_profit_per_bet }}/笔
          </div>
        </div>
      </div>
    </div>

    <!-- 详细分析标签页 -->
    <n-tabs v-model:value="activeTab" type="card" class="betting-analysis-tabs">
      <!-- 投注表现标签页 -->
      <n-tab-pane name="performance" tab="📈 投注表现">
        <div v-if="performanceData" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <!-- 排名分布 -->
          <div class="rounded-lg border border-gray-600 bg-gray-800/30 p-4">
            <h4 class="mb-3 text-sm font-medium text-gray-300">排名分布</h4>
            <div class="space-y-2">
              <div
                v-for="(count, rank) in performanceData.betting_performance.rank_distribution"
                :key="rank"
                class="flex items-center justify-between"
              >
                <span class="text-sm text-gray-400">
                  {{ rank === 'other' ? '其他排名' : `第${rank}名` }}
                </span>
                <div class="flex items-center space-x-2">
                  <div class="h-2 w-20 rounded-full bg-gray-700">
                    <div
                      class="h-2 rounded-full bg-gradient-to-r from-blue-500 to-purple-500"
                      :style="{ width: `${(count / performanceData.betting_performance.settled_bets) * 100}%` }"
                    ></div>
                  </div>
                  <span class="text-sm text-white">{{ count }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- 预测准确度 -->
          <div class="rounded-lg border border-gray-600 bg-gray-800/30 p-4">
            <h4 class="mb-3 text-sm font-medium text-gray-300">AI预测准确度</h4>
            <div
              v-if="
                performanceData.prediction_accuracy &&
                performanceData.prediction_accuracy.total_predictions_analyzed > 0
              "
              class="space-y-3"
            >
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">精确匹配</span>
                <span class="text-sm font-medium text-green-400">
                  {{ performanceData.prediction_accuracy.exact_accuracy_percentage }}%
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">接近匹配(±1)</span>
                <span class="text-sm font-medium text-blue-400">
                  {{ performanceData.prediction_accuracy.close_accuracy_percentage }}%
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">平均排名差</span>
                <span class="text-sm font-medium text-yellow-400">
                  {{ performanceData.prediction_accuracy.average_rank_difference }}
                </span>
              </div>
            </div>
            <div v-else class="text-sm text-gray-500">暂无预测准确度数据</div>
          </div>

          <!-- 日均统计 -->
          <div class="rounded-lg border border-gray-600 bg-gray-800/30 p-4">
            <h4 class="mb-3 text-sm font-medium text-gray-300">日均统计</h4>
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">下注频次</span>
                <span class="text-sm text-white">
                  {{ performanceData.betting_performance.daily_average.bets_per_day }} 笔/日
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">投入金额</span>
                <span class="text-sm text-white">
                  ${{ performanceData.betting_performance.daily_average.amount_per_day }}/日
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">盈亏金额</span>
                <span
                  class="text-sm"
                  :class="getProfitLossClass(performanceData.betting_performance.daily_average.profit_per_day)"
                >
                  ${{ performanceData.betting_performance.daily_average.profit_per_day }}/日
                </span>
              </div>
            </div>
          </div>

          <!-- 策略表现 -->
          <div class="rounded-lg border border-gray-600 bg-gray-800/30 p-4">
            <h4 class="mb-3 text-sm font-medium text-gray-300">策略表现</h4>
            <div
              v-if="performanceData.strategy_analysis && performanceData.strategy_analysis.length > 0"
              class="space-y-3"
            >
              <div
                v-for="strategy in performanceData.strategy_analysis"
                :key="strategy.strategy_name"
                class="rounded border border-gray-700 bg-gray-800/50 p-3"
              >
                <div class="mb-2 flex items-center justify-between">
                  <span class="text-sm font-medium text-white">{{ strategy.strategy_name }}</span>
                  <span class="text-xs text-gray-400">{{ strategy.bet_count }} 笔</span>
                </div>
                <div class="grid grid-cols-3 gap-2 text-xs">
                  <div class="text-center">
                    <div class="text-gray-400">胜率</div>
                    <div class="text-white">{{ strategy.win_rate_percentage }}%</div>
                  </div>
                  <div class="text-center">
                    <div class="text-gray-400">ROI</div>
                    <div :class="getProfitLossClass(strategy.roi_percentage)">{{ strategy.roi_percentage }}%</div>
                  </div>
                  <div class="text-center">
                    <div class="text-gray-400">平均盈利</div>
                    <div :class="getProfitLossClass(strategy.average_profit_per_bet)">
                      ${{ strategy.average_profit_per_bet }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-gray-500">暂无策略表现数据</div>
          </div>
        </div>
      </n-tab-pane>

      <!-- 代币表现标签页 -->
      <n-tab-pane name="tokens" tab="🪙 代币表现">
        <div v-if="tokenStats" class="space-y-4">
          <div class="text-sm text-gray-400">分析了 {{ tokenStats.total_tokens }} 个代币的表现</div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="token in tokenStats.token_performance"
              :key="token.token_symbol"
              class="rounded-lg border border-gray-600 bg-gray-800/30 p-4"
            >
              <div class="mb-3 flex items-center justify-between">
                <h5 class="font-medium text-white">{{ token.token_symbol }}</h5>
                <span class="text-xs text-gray-400">{{ token.bet_count }} 笔</span>
              </div>

              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-400">投入金额</span>
                  <span class="text-xs text-white">${{ token.total_invested }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-400">实际盈亏</span>
                  <span class="text-xs" :class="getProfitLossClass(token.total_profit)">${{ token.total_profit }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-400">ROI</span>
                  <span class="text-xs" :class="getProfitLossClass(token.roi_percentage)">
                    {{ token.roi_percentage }}%
                  </span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-400">平均排名</span>
                  <span class="text-xs text-white">{{ token.avg_actual_rank }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-400">前3率</span>
                  <span class="text-xs text-blue-400">{{ token.top3_rate_percentage }}%</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-400">胜率</span>
                  <span class="text-xs text-green-400">{{ token.win_rate_percentage }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center text-gray-400">暂无代币表现数据</div>
      </n-tab-pane>

      <!-- 详细记录标签页 -->
      <n-tab-pane name="records" tab="📋 详细记录">
        <div v-if="performanceData && performanceData.detailed_records" class="space-y-4">
          <div class="text-sm text-gray-400">显示最近 {{ performanceData.detailed_records.length }} 笔投注记录</div>

          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="py-2 text-left text-xs text-gray-400">时间</th>
                  <th class="py-2 text-left text-xs text-gray-400">轮次</th>
                  <th class="py-2 text-left text-xs text-gray-400">代币</th>
                  <th class="py-2 text-right text-xs text-gray-400">下注金额</th>
                  <th class="py-2 text-center text-xs text-gray-400">预测排名</th>
                  <th class="py-2 text-center text-xs text-gray-400">实际排名</th>
                  <th class="py-2 text-right text-xs text-gray-400">实际收益</th>
                  <th class="py-2 text-right text-xs text-gray-400">ROI</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="record in performanceData.detailed_records.slice(0, 50)"
                  :key="record.id"
                  class="border-b border-gray-800"
                >
                  <td class="py-2 text-xs text-gray-300">
                    {{ formatDate(record.created_at) }}
                  </td>
                  <td class="py-2 text-xs text-gray-300">
                    {{ record.round_id.slice(-6) }}
                  </td>
                  <td class="py-2 text-xs font-medium text-white">
                    {{ record.token_symbol }}
                  </td>
                  <td class="py-2 text-right text-xs text-white">${{ record.bet_amount }}</td>
                  <td class="py-2 text-center text-xs text-blue-400">
                    {{ record.predicted_rank || '-' }}
                  </td>
                  <td class="py-2 text-center text-xs text-yellow-400">
                    {{ record.actual_rank || '-' }}
                  </td>
                  <td class="py-2 text-right text-xs" :class="getProfitLossClass(record.actual_profit)">
                    ${{ record.actual_profit }}
                  </td>
                  <td class="py-2 text-right text-xs" :class="getProfitLossClass(record.roi_percentage)">
                    {{ record.roi_percentage }}%
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="text-center text-gray-400">暂无详细记录</div>
      </n-tab-pane>
    </n-tabs>

    <!-- 加载状态 -->
    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="flex items-center space-x-2 text-blue-400">
        <div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-400 border-t-transparent"></div>
        <span class="text-sm">正在分析投注表现...</span>
      </div>
    </div>

    <!-- 无数据状态 -->
    <div v-if="!loading && !performanceData" class="py-8 text-center text-gray-400">
      <div class="mb-2 text-2xl">📊</div>
      <div class="text-sm">暂无投注表现数据</div>
      <div class="mt-1 text-xs text-gray-500">开始自动下注后，数据将在此显示</div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ref, onMounted, computed } from 'vue';
  import { NTabs, NTabPane, NSelect, NButton } from 'naive-ui';
  import { bettingAnalysisApi } from '@/utils/api';
  import { handleError } from '@/utils/errorHandler';

  // Props
  interface Props {
    uid: string;
  }

  const props = defineProps<Props>();

  // 响应式数据
  const loading = ref(false);
  const performanceData = ref<any>(null);
  const tokenStats = ref<any>(null);
  const activeTab = ref('performance');
  const selectedDays = ref(30);

  // 天数选项
  const dayOptions = [
    { label: '7天', value: 7 },
    { label: '30天', value: 30 },
    { label: '90天', value: 90 },
    { label: '180天', value: 180 },
    { label: '365天', value: 365 }
  ];

  // 计算属性
  const getProfitLossClass = (value: number) => {
    if (value > 0) return 'text-green-400';
    if (value < 0) return 'text-red-400';
    return 'text-gray-400';
  };

  // 格式化日期
  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('zh-CN', {
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  // 刷新分析数据
  const refreshAnalysis = async () => {
    if (!props.uid) {
      window.$message?.warning('请先完成身份验证');
      return;
    }

    loading.value = true;
    try {
      // 并行获取投注表现和代币统计
      const [performanceResponse, tokenResponse] = await Promise.all([
        bettingAnalysisApi.getPerformanceAnalysis(props.uid, selectedDays.value, 100),
        bettingAnalysisApi.getTokenStats(props.uid, selectedDays.value)
      ]);

      if (performanceResponse.data.success) {
        performanceData.value = performanceResponse.data.data;
        console.log('📊 投注表现数据:', performanceData.value);
      } else {
        throw new Error(performanceResponse.data.message || '获取投注表现数据失败');
      }

      if (tokenResponse.data.success) {
        tokenStats.value = tokenResponse.data.data;
        console.log('🪙 代币统计数据:', tokenStats.value);
      } else {
        console.warn('获取代币统计数据失败:', tokenResponse.data.message);
      }
    } catch (error) {
      handleError(error, {
        showToast: true,
        fallbackMessage: '获取投注表现分析失败'
      });
    } finally {
      loading.value = false;
    }
  };

  // 监听天数变化
  const selectedDaysWatcher = computed(() => selectedDays.value);

  // 组件挂载时获取数据
  onMounted(async () => {
    if (props.uid) {
      await refreshAnalysis();
    }
  });

  // 暴露方法给父组件
  defineExpose({
    refreshAnalysis
  });
</script>

<style scoped>
  :deep(.betting-analysis-tabs .n-tabs-nav) {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
  }

  :deep(.betting-analysis-tabs .n-tabs-tab) {
    border-radius: 6px;
    margin: 2px;
  }

  :deep(.betting-analysis-tabs .n-tabs-tab.n-tabs-tab--active) {
    background: rgba(59, 130, 246, 0.2);
    color: rgb(96, 165, 250);
  }
</style>
