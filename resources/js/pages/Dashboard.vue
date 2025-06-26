<template>
  <DefaultLayout>
    <Head title="Dashboard" />

    <div class="min-h-screen from-slate-900 via-purple-900 to-slate-900 bg-gradient-to-br p-6">
      <div class="mx-auto max-w-7xl">
        <!-- 页面标题 -->
        <!-- <div class="mb-8 text-center">
          <h1
            class="mb-4 from-blue-400 via-purple-400 to-pink-400 bg-gradient-to-r bg-clip-text text-4xl text-transparent font-bold"
          >
            🎯 游戏数据中心
          </h1>
          <p class="text-lg text-gray-300">实时游戏数据分析与预测系统</p>
          <div class="mx-auto mt-4 h-1 w-24 rounded-full from-blue-400 to-purple-400 bg-gradient-to-r"></div>
        </div> -->

        <!-- 当前局分析（预测+市场） -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="🎯 当前局分析"
          size="large"
        >
          <template #header-extra>
            <div class="flex items-center space-x-3">
              <div v-if="analysisMeta" class="flex items-center gap-2 text-sm text-gray-300">
                <span class="font-medium">轮次:</span>
                <span class="text-red">{{ analysisMeta.round_id }}</span>
                <span class="font-medium">状态:</span>

                <NTag :type="getStatusTagType(analysisMeta.status)" size="small">
                  {{ getStatusText(analysisMeta.status) }}
                </NTag>
              </div>
              <n-button :loading="analysisLoading" @click="refreshAnalysis" type="primary" size="small">
                🔄 刷新分析
              </n-button>
            </div>
          </template>

          <div v-if="analysisData.length > 0" class="space-y-3">
            <!-- 预测排名卡片 -->
            <div>
              <h3 class="mb-4 text-lg text-white font-semibold">🔮 预测排名</h3>
              <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 md:grid-cols-3">
                <div
                  v-for="(token, index) in analysisData"
                  :key="`prediction-${index}-${token.symbol}-${token.name}`"
                  class="relative border-2 rounded-lg p-4 transition-all duration-200 hover:shadow-lg"
                  :class="getPredictionCardClass(index)"
                >
                  <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                      <img
                        v-if="token.logo"
                        :src="token.logo"
                        :alt="token.symbol"
                        class="h-6 w-6 rounded-full"
                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                      />
                      <div v-else class="h-6 w-6 flex items-center justify-center rounded-full bg-gray-300 text-xs">
                        {{ token.symbol.charAt(0) }}
                      </div>
                      <span class="text-16px text-gray-800 font-bold">{{ token.symbol }}</span>
                    </div>
                    <div class="flex items-center space-x-1">
                      <span class="text-lg text-gray-700 font-medium">#{{ index + 1 }}</span>
                    </div>
                  </div>

                  <div class="text-sm space-y-1">
                    <div class="flex justify-between">
                      <span class="text-gray-700">预测评分:</span>
                      <span class="text-gray-800 font-medium">{{ token.prediction_score.toFixed(1) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-700">胜率:</span>
                      <span class="text-green-700 font-medium">{{ token.win_rate.toFixed(1) }}%</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-700">前三率:</span>
                      <span class="text-blue-700 font-medium">{{ token.top3_rate.toFixed(1) }}%</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 详细市场数据表格 -->
            <div>
              <h3 class="mb-4 text-lg text-white font-semibold">💰 详细市场数据</h3>
              <div class="overflow-x-auto border border-white/10 rounded-xl bg-white/5 backdrop-blur-sm">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-white/20 bg-white/5">
                      <th class="px-4 py-3 text-left text-white font-medium">排名</th>
                      <th class="px-4 py-3 text-left text-white font-medium">代币</th>
                      <th class="px-4 py-3 text-right text-white font-medium">价格 (USD)</th>
                      <th class="px-4 py-3 text-right text-white font-medium">5分钟</th>
                      <th class="px-4 py-3 text-right text-white font-medium">1小时</th>
                      <th class="px-4 py-3 text-right text-white font-medium">4小时</th>
                      <th class="px-4 py-3 text-right text-white font-medium">24小时</th>
                      <th class="px-4 py-3 text-right text-white font-medium">成交量 24h</th>
                      <th class="px-4 py-3 text-right text-white font-medium">预测评分</th>
                      <th class="px-4 py-3 text-right text-white font-medium">市场动量</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(token, index) in analysisData"
                      :key="`table-${index}-${token.symbol}-${token.name}`"
                      class="border-b border-white/10 transition-colors duration-200 hover:bg-white/10"
                    >
                      <td class="px-4 py-3">
                        <div class="flex items-center space-x-2">
                          <span class="text-lg">{{ getPredictionIcon(index) }}</span>
                          <span class="text-white font-medium">#{{ index + 1 }}</span>
                        </div>
                      </td>
                      <td class="px-4 py-3">
                        <div class="flex items-center space-x-3">
                          <img
                            v-if="token.logo"
                            :src="token.logo"
                            :alt="token.symbol"
                            class="h-8 w-8 rounded-full"
                            @error="($event.target as HTMLImageElement).style.display = 'none'"
                          />
                          <div v-else class="h-8 w-8 flex items-center justify-center rounded-full bg-gray-300 text-xs">
                            {{ token.symbol.charAt(0) }}
                          </div>
                          <div>
                            <div class="text-white font-medium">{{ token.symbol }}</div>
                            <div class="text-xs text-gray-300">{{ token.name }}</div>
                          </div>
                        </div>
                      </td>
                      <td class="px-4 py-3 text-right text-white font-mono">
                        ${{ parseFloat(token.price).toFixed(6) }}
                      </td>
                      <td class="px-4 py-3 text-right">
                        <span :class="getChangeColor(token.change_5m)">
                          {{ formatChange(token.change_5m) }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-right">
                        <span :class="getChangeColor(token.change_1h)">
                          {{ formatChange(token.change_1h) }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-right">
                        <span :class="getChangeColor(token.change_4h)">
                          {{ formatChange(token.change_4h) }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-right">
                        <span :class="getChangeColor(token.change_24h)">
                          {{ formatChange(token.change_24h) }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-right text-xs text-white font-mono">
                        ${{ formatVolume(token.volume_24h) }}
                      </td>
                      <td class="px-4 py-3 text-right">
                        <div class="flex flex-col items-end">
                          <span class="text-blue-400 font-medium">
                            {{ (token.final_prediction_score || token.prediction_score).toFixed(1) }}
                          </span>
                          <span class="text-xs text-gray-400">历史: {{ token.prediction_score.toFixed(1) }}</span>
                        </div>
                      </td>
                      <td class="px-4 py-3 text-right">
                        <span v-if="token.market_momentum_score" class="text-green-400 font-medium">
                          {{ token.market_momentum_score.toFixed(1) }}
                        </span>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <NEmpty v-else description="暂无当前局数据" class="py-8" />
        </NCard>

        <!-- 第四部分：预测历史数据表格 -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="🔮 预测历史数据 (最近300局)"
          size="large"
        >
          <template #header-extra>
            <n-button
              :loading="predictionHistoryLoading"
              @click="refreshPredictionHistoryData"
              type="primary"
              size="small"
            >
              🔄 刷新预测历史
            </n-button>
          </template>

          <NSpin :show="predictionHistoryLoading">
            <div v-if="predictionHistoryData.length > 0" class="space-y-4">
              <!-- 预测准确度总结 -->
              <div class="grid grid-cols-1 gap-4 lg:grid-cols-4 md:grid-cols-2">
                <div class="border border-white/20 rounded-lg bg-white/5 p-4">
                  <div class="text-sm text-gray-300">🎯 精准预测率</div>
                  <div class="text-2xl text-green-400 font-bold">
                    {{ calculateRoundBasedStats().exactRate.toFixed(1) }}%
                  </div>
                  <div class="mt-1 text-xs text-gray-400">预测与实际完全相同</div>
                </div>
                <div class="border border-white/20 rounded-lg bg-white/5 p-4">
                  <div class="text-sm text-gray-300">💰 保本率</div>
                  <div class="text-2xl text-blue-400 font-bold">
                    {{ calculateRoundBasedStats().breakevenRate.toFixed(1) }}%
                  </div>
                  <div class="mt-1 text-xs text-gray-400">前三名中有命中</div>
                </div>
                <div class="border border-white/20 rounded-lg bg-white/5 p-4">
                  <div class="text-sm text-gray-300">📉 亏本率</div>
                  <div class="text-2xl text-red-400 font-bold">
                    {{ calculateRoundBasedStats().lossRate.toFixed(1) }}%
                  </div>
                  <div class="mt-1 text-xs text-gray-400">前三名全部错误</div>
                </div>
                <div class="border border-white/20 rounded-lg bg-white/5 p-4">
                  <div class="text-sm text-gray-300">📊 预测总数</div>
                  <div class="text-2xl text-purple-400 font-bold">
                    {{ calculateRoundBasedStats().totalPredictions }}
                  </div>
                  <div class="mt-1 text-xs text-gray-400">前三名预测总数</div>
                </div>
              </div>

              <!-- 按预测排名分别统计 -->
              <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 md:grid-cols-3">
                <!-- 预测第一名统计 -->
                <div class="border border-white/20 rounded-lg bg-white/5 p-4">
                  <div class="mb-3 text-sm text-gray-300">🥇 预测第一名</div>
                  <div class="space-y-2">
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-gray-400">保本率</span>
                      <span class="text-sm text-blue-400 font-medium">
                        {{ calculateRankBasedStats().rank1.breakevenRate.toFixed(1) }}%
                      </span>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-gray-400">亏本率</span>
                      <span class="text-sm text-red-400 font-medium">
                        {{ calculateRankBasedStats().rank1.lossRate.toFixed(1) }}%
                      </span>
                    </div>
                  </div>
                </div>

                <!-- 预测第二名统计 -->
                <div class="border border-white/20 rounded-lg bg-white/5 p-4">
                  <div class="mb-3 text-sm text-gray-300">🥈 预测第二名</div>
                  <div class="space-y-2">
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-gray-400">保本率</span>
                      <span class="text-sm text-blue-400 font-medium">
                        {{ calculateRankBasedStats().rank2.breakevenRate.toFixed(1) }}%
                      </span>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-gray-400">亏本率</span>
                      <span class="text-sm text-red-400 font-medium">
                        {{ calculateRankBasedStats().rank2.lossRate.toFixed(1) }}%
                      </span>
                    </div>
                  </div>
                </div>

                <!-- 预测第三名统计 -->
                <div class="border border-white/20 rounded-lg bg-white/5 p-4">
                  <div class="mb-3 text-sm text-gray-300">🥉 预测第三名</div>
                  <div class="space-y-2">
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-gray-400">保本率</span>
                      <span class="text-sm text-blue-400 font-medium">
                        {{ calculateRankBasedStats().rank3.breakevenRate.toFixed(1) }}%
                      </span>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-gray-400">亏本率</span>
                      <span class="text-sm text-red-400 font-medium">
                        {{ calculateRankBasedStats().rank3.lossRate.toFixed(1) }}%
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 前三名预测对比表格 -->
              <NDataTable
                :columns="predictionComparisonColumns"
                :data="predictionComparisonTableData"
                :pagination="{ pageSize: 10 }"
                :scroll-x="1000"
                striped
                :row-props="rowProps"
              />
            </div>
            <NEmpty v-else description="暂无预测历史数据" class="py-8" />
          </NSpin>
        </NCard>

        <!-- 第三部分：历史数据表格 -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="📊 历史游戏数据 (最近50局)"
          size="large"
        >
          <template #header-extra>
            <n-button :loading="historyLoading" @click="refreshHistoryData" type="primary" size="small">
              🔄 刷新历史
            </n-button>
          </template>

          <NSpin :show="historyLoading">
            <NDataTable
              v-if="historyData.length > 0"
              :columns="historyColumns"
              :data="historyTableData"
              :pagination="{ pageSize: 5 }"
              :scroll-x="800"
              striped
            />
            <NEmpty v-else description="暂无历史数据" class="py-8" />
          </NSpin>
        </NCard>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { ref, onMounted, computed, h } from 'vue';
  import { NEmpty, useMessage, type DataTableColumn } from 'naive-ui';
  import { Head } from '@inertiajs/vue3';
  import api from '@/utils/api';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';

  // 定义接口类型
  interface TokenAnalysis {
    symbol: string;
    name: string;
    price: string;
    change_5m: number | null;
    change_1h: number | null;
    change_4h: number | null;
    change_24h: number | null;
    volume_24h: string;
    market_cap: number | null;
    logo: string | null;
    prediction_score: number;
    market_momentum_score?: number;
    final_prediction_score?: number;
    win_rate: number;
    top3_rate: number;
    avg_rank: number;
    total_games: number;
    wins: number;
    top3: number;
    predicted_rank: number;
  }

  interface RoundToken {
    symbol: string;
    rank: number;
    value: string;
  }

  interface HistoryRound {
    id: number;
    round_id: string;
    settled_at: string | null;
    tokens: RoundToken[];
  }

  // 表格行数据类型 (包含 key 属性)
  interface HistoryTableRow extends HistoryRound {
    key: number;
  }

  // 预测相关接口
  interface PredictionData {
    symbol: string;
    predicted_rank: number;
    prediction_score: number;
    predicted_at: string;
  }

  interface ResultData {
    symbol: string;
    actual_rank: number;
    value: string;
  }

  interface AccuracyDetail {
    symbol: string;
    predicted_rank: number;
    actual_rank: number;
    rank_difference: number;
    is_exact_match: boolean;
    is_close_match: boolean;
  }

  interface Accuracy {
    total_predictions: number;
    exact_matches: number;
    close_matches: number;
    exact_accuracy: number;
    close_accuracy: number;
    avg_rank_difference: number;
    details: AccuracyDetail[];
  }

  interface PredictionHistoryRound {
    id: number;
    round_id: string;
    settled_at: string | null;
    predictions: PredictionData[];
    results: ResultData[];
    accuracy: Accuracy;
  }

  interface DetailedPredictionItem {
    round_id: string;
    symbol: string;
    predicted_rank: number;
    actual_rank: number;
    is_exact_match: boolean;
    is_better_than_expected: boolean;
    rank_difference: number;
    settled_at: string;
  }

  // 响应式数据
  const analysisData = ref<TokenAnalysis[]>([]);
  const historyData = ref<HistoryRound[]>([]);
  const predictionHistoryData = ref<PredictionHistoryRound[]>([]);
  const analysisMeta = ref<any>(null);

  const analysisLoading = ref(false);
  const historyLoading = ref(false);
  const predictionHistoryLoading = ref(false);

  // 延迟获取message实例，避免在providers还未准备好时调用
  const getMessageInstance = () => {
    try {
      return useMessage();
    } catch {
      console.warn('Message provider not ready yet');
      return null;
    }
  };

  // 工具函数：获取指定排名的所有代币
  const getTokensByRank = (tokens: RoundToken[], rank: number): string => {
    const tokensAtRank = tokens.filter((t) => t.rank === rank);
    if (tokensAtRank.length === 0) return '-';
    if (tokensAtRank.length === 1) return tokensAtRank[0].symbol;
    // 多个代币并列时，用 / 分隔显示
    return tokensAtRank.map((t) => t.symbol).join(' / ');
  };

  // 历史数据表格列定义
  const historyColumns: DataTableColumn<HistoryTableRow>[] = [
    {
      title: '轮次ID',
      key: 'round_id',
      width: 120
    },
    {
      title: '结算时间',
      key: 'settled_at',
      width: 160
    },
    {
      title: '第1名',
      key: 'rank_1',
      width: 100,
      render: (row: HistoryTableRow) => getTokensByRank(row.tokens, 1)
    },
    {
      title: '第2名',
      key: 'rank_2',
      width: 100,
      render: (row: HistoryTableRow) => getTokensByRank(row.tokens, 2)
    },
    {
      title: '第3名',
      key: 'rank_3',
      width: 100,
      render: (row: HistoryTableRow) => getTokensByRank(row.tokens, 3)
    },
    {
      title: '第4名',
      key: 'rank_4',
      width: 100,
      render: (row: HistoryTableRow) => getTokensByRank(row.tokens, 4)
    },
    {
      title: '第5名',
      key: 'rank_5',
      width: 100,
      render: (row: HistoryTableRow) => getTokensByRank(row.tokens, 5)
    }
  ];

  // 计算属性
  const historyTableData = computed((): HistoryTableRow[] => {
    return historyData.value.map(
      (item: HistoryRound): HistoryTableRow => ({
        ...item,
        key: item.id
      })
    );
  });

  // 工具函数
  const getPredictionCardClass = (index: number) => {
    if (index === 0)
      return 'border-2 border-yellow-400 bg-gradient-to-br from-yellow-50 to-amber-100 shadow-xl shadow-yellow-200/30 hover:shadow-yellow-300/50 transform hover:scale-105 transition-all duration-300';
    if (index === 1)
      return 'border-2 border-slate-400 bg-gradient-to-br from-slate-50 to-gray-100 shadow-xl shadow-slate-200/30 hover:shadow-slate-300/50 transform hover:scale-105 transition-all duration-300';
    if (index === 2)
      return 'border-2 border-orange-400 bg-gradient-to-br from-orange-50 to-red-100 shadow-xl shadow-orange-200/30 hover:shadow-orange-300/50 transform hover:scale-105 transition-all duration-300';
    if (index === 3)
      return 'border-2 border-blue-400 bg-gradient-to-br from-blue-50 to-indigo-100 shadow-xl shadow-blue-200/30 hover:shadow-blue-300/50 transform hover:scale-105 transition-all duration-300';
    return 'border-2 border-purple-400 bg-gradient-to-br from-purple-50 to-pink-100 shadow-xl shadow-purple-200/30 hover:shadow-purple-300/50 transform hover:scale-105 transition-all duration-300';
  };

  const getPredictionIcon = (index: number) => {
    if (index === 0) return '🥇';
    if (index === 1) return '🥈';
    if (index === 2) return '🥉';
    return '📊';
  };

  // 获取排名对应的图标
  const getPredictionRankIcon = (rank: number) => {
    if (rank === 1) return '🥇';
    if (rank === 2) return '🥈';
    if (rank === 3) return '🥉';
    if (rank === 4) return '4️⃣';
    if (rank === 5) return '5️⃣';
    return '📊';
  };

  const getChangeColor = (change: number | null) => {
    if (change === null || change === undefined) return 'text-gray-400';
    if (change > 0) return 'text-green-600';
    if (change < 0) return 'text-red-600';
    return 'text-gray-600';
  };

  const formatChange = (change: number | null) => {
    if (change === null || change === undefined) return '-';
    const sign = change >= 0 ? '+' : '';
    return `${sign}${change.toFixed(2)}%`;
  };

  const formatVolume = (volume: string | number) => {
    const num = parseFloat(volume.toString());
    if (num >= 1000000) return `${(num / 1000000).toFixed(2)}M`;
    if (num >= 1000) return `${(num / 1000).toFixed(2)}K`;
    return num.toFixed(2);
  };

  const getStatusTagType = (status: string) => {
    switch (status) {
      case 'bet':
        return 'success';
      case 'settling':
        return 'warning';
      case 'settled':
        return 'info';
      default:
        return 'default';
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case 'starting':
        return '开始中';
      case 'running':
      case 'active':
        return '进行中';
      case 'settling':
        return '结算中';
      case 'settled':
        return '已结算';
      default:
        return status;
    }
  };

  // API调用函数
  const fetchAnalysisData = async () => {
    analysisLoading.value = true;
    try {
      const response = await api.get('/game/current-analysis');
      if (response.data.success) {
        analysisData.value = response.data.data;
        analysisMeta.value = response.data.meta || null;
      } else {
        getMessageInstance()?.error(response.data.message || '获取当前局分析数据失败');
      }
    } catch (error) {
      console.error('获取当前局分析数据失败:', error);
      getMessageInstance()?.error('获取当前局分析数据失败');
    } finally {
      analysisLoading.value = false;
    }
  };

  const fetchHistoryData = async () => {
    historyLoading.value = true;
    try {
      const response = await api.get('/game/history');
      if (response.data.success) {
        historyData.value = response.data.data;
      } else {
        getMessageInstance()?.error(response.data.message || '获取历史数据失败');
      }
    } catch (error) {
      console.error('获取历史数据失败:', error);
      getMessageInstance()?.error('获取历史数据失败');
    } finally {
      historyLoading.value = false;
    }
  };

  const fetchPredictionHistoryData = async () => {
    predictionHistoryLoading.value = true;
    try {
      const response = await api.get('/game/prediction-history');
      if (response.data.success) {
        predictionHistoryData.value = response.data.data;
      } else {
        getMessageInstance()?.error(response.data.message || '获取预测历史数据失败');
      }
    } catch (error) {
      console.error('获取预测历史数据失败:', error);
      getMessageInstance()?.error('获取预测历史数据失败');
    } finally {
      predictionHistoryLoading.value = false;
    }
  };

  // 刷新函数
  const refreshAnalysis = () => fetchAnalysisData();
  const refreshHistoryData = () => fetchHistoryData();
  const refreshPredictionHistoryData = () => fetchPredictionHistoryData();

  // 初始化数据
  onMounted(() => {
    fetchAnalysisData();
    fetchHistoryData();
    fetchPredictionHistoryData();

    // 设置定时刷新 - 分析数据5秒刷新，历史数据30秒刷新，预测历史数据60秒刷新
    setInterval(() => {
      fetchAnalysisData();
    }, 5000);

    setInterval(() => {
      fetchHistoryData();
      fetchPredictionHistoryData();
    }, 30000);
  });

  // 获取前三名预测对比数据 (带key属性用于DataTable)
  interface PredictionComparisonRow extends DetailedPredictionItem {
    key: string;
  }

  const predictionComparisonTableData = computed((): PredictionComparisonRow[] => {
    const detailedData: PredictionComparisonRow[] = [];

    predictionHistoryData.value.forEach((round) => {
      // 只处理预测前三名的数据
      const top3Predictions = round.predictions.filter((p) => p.predicted_rank <= 3);

      top3Predictions.forEach((prediction) => {
        const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
        if (actualResult) {
          const rankDifference = Math.abs(prediction.predicted_rank - actualResult.actual_rank);

          detailedData.push({
            key: `${round.round_id}-${prediction.symbol}`,
            round_id: round.round_id,
            symbol: prediction.symbol,
            predicted_rank: prediction.predicted_rank,
            actual_rank: actualResult.actual_rank,
            is_exact_match: rankDifference === 0,
            is_better_than_expected: actualResult.actual_rank < prediction.predicted_rank, // 实际名次更好
            rank_difference: rankDifference,
            settled_at: round.settled_at || '-'
          });
        }
      });
    });

    // 按轮次倒序排列，最新的在前面
    return detailedData.sort((a, b) => b.round_id.localeCompare(a.round_id));
  });

  // 获取预测行的样式类 - 根据新逻辑判断颜色
  const getPredictionRowClass = (detail: DetailedPredictionItem) => {
    if (detail.is_exact_match || detail.is_better_than_expected) {
      return 'bg-green-500/20 border-l-4 border-green-500';
    } else {
      return 'bg-red-500/20 border-l-4 border-red-500';
    }
  };

  // DataTable行属性
  const rowProps = (row: PredictionComparisonRow) => {
    return {
      style: getPredictionRowClass(row)
    };
  };

  // 获取单个代币的预测分析结果（按您的逻辑：实际前三就保本，否则亏本）
  const getTokenPredictionAnalysis = (predictedRank: number, actualRank: number) => {
    // 精准预测：预测排名和实际排名完全一致
    if (predictedRank === actualRank) {
      return {
        status: 'exact',
        text: '精准预测',
        icon: '🎯',
        color: 'text-green-400',
        bgColor: 'bg-green-500/20'
      };
    }

    // 保本：实际排名在前三名
    if (actualRank <= 3) {
      return {
        status: 'breakeven',
        text: '保本',
        icon: '💰',
        color: 'text-blue-400',
        bgColor: 'bg-blue-500/20'
      };
    }

    // 亏本：实际排名不在前三名
    return {
      status: 'loss',
      text: '亏本',
      icon: '📉',
      color: 'text-red-400',
      bgColor: 'bg-red-500/20'
    };
  };

  // 前三名预测对比表格列定义
  const predictionComparisonColumns: DataTableColumn<PredictionComparisonRow>[] = [
    {
      title: '轮次',
      key: 'round_id',
      width: 100,
      render: (row: PredictionComparisonRow) => row.round_id
    },
    {
      title: '代币',
      key: 'symbol',
      width: 80,
      render: (row: PredictionComparisonRow) => row.symbol
    },
    {
      title: '预测排名',
      key: 'predicted_rank',
      width: 100,
      render: (row: PredictionComparisonRow) =>
        h('div', { class: 'flex items-center justify-center' }, [
          h('span', { class: 'text-lg mr-1' }, getPredictionRankIcon(row.predicted_rank)),
          h('span', { class: 'font-medium' }, `#${row.predicted_rank}`)
        ])
    },
    {
      title: '实际排名',
      key: 'actual_rank',
      width: 100,
      render: (row: PredictionComparisonRow) =>
        h('div', { class: 'flex items-center justify-center' }, [
          h('span', { class: 'text-lg mr-1' }, getPredictionRankIcon(row.actual_rank)),
          h('span', { class: 'font-medium' }, `#${row.actual_rank}`)
        ])
    },
    {
      title: '预测分析',
      key: 'analysis',
      width: 160,
      render: (row: PredictionComparisonRow) => {
        const analysis = getTokenPredictionAnalysis(row.predicted_rank, row.actual_rank);

        return h(
          'div',
          {
            class: `px-3 py-1 rounded-full text-sm font-medium ${analysis.color} ${analysis.bgColor}`
          },
          [h('span', { class: 'mr-1' }, analysis.icon), h('span', {}, analysis.text)]
        );
      }
    },
    {
      title: '结算时间',
      key: 'settled_at',
      width: 140,
      render: (row: PredictionComparisonRow) => row.settled_at
    }
  ];

  // 基于单个代币预测的统计函数
  const calculateRoundBasedStats = () => {
    if (predictionHistoryData.value.length === 0) {
      return {
        totalPredictions: 0,
        exactPredictions: 0,
        breakevenPredictions: 0,
        lossPredictions: 0,
        exactRate: 0,
        breakevenRate: 0,
        lossRate: 0
      };
    }

    let exactPredictions = 0;
    let breakevenPredictions = 0;
    let lossPredictions = 0;
    let totalPredictions = 0;

    predictionHistoryData.value.forEach((round) => {
      // 只统计前三名的预测
      const top3Predictions = round.predictions.filter((p) => p.predicted_rank <= 3);

      top3Predictions.forEach((prediction) => {
        const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
        if (actualResult) {
          totalPredictions++;
          const analysis = getTokenPredictionAnalysis(prediction.predicted_rank, actualResult.actual_rank);

          if (analysis.status === 'exact') {
            exactPredictions++;
          } else if (analysis.status === 'breakeven') {
            breakevenPredictions++;
          } else if (analysis.status === 'loss') {
            lossPredictions++;
          }
        }
      });
    });

    return {
      totalPredictions,
      exactPredictions,
      breakevenPredictions,
      lossPredictions,
      exactRate: totalPredictions > 0 ? (exactPredictions / totalPredictions) * 100 : 0,
      breakevenRate: totalPredictions > 0 ? (breakevenPredictions / totalPredictions) * 100 : 0,
      lossRate: totalPredictions > 0 ? (lossPredictions / totalPredictions) * 100 : 0
    };
  };

  // 按预测排名分别统计保本/亏本率
  const calculateRankBasedStats = () => {
    const rankStats = {
      rank1: { total: 0, breakeven: 0, loss: 0, breakevenRate: 0, lossRate: 0 },
      rank2: { total: 0, breakeven: 0, loss: 0, breakevenRate: 0, lossRate: 0 },
      rank3: { total: 0, breakeven: 0, loss: 0, breakevenRate: 0, lossRate: 0 }
    };

    if (predictionHistoryData.value.length === 0) {
      return rankStats;
    }

    predictionHistoryData.value.forEach((round) => {
      [1, 2, 3].forEach((predictedRank) => {
        const predictions = round.predictions.filter((p) => p.predicted_rank === predictedRank);

        predictions.forEach((prediction) => {
          const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
          if (actualResult) {
            const key = `rank${predictedRank}` as keyof typeof rankStats;
            rankStats[key].total++;

            const analysis = getTokenPredictionAnalysis(prediction.predicted_rank, actualResult.actual_rank);

            if (analysis.status === 'exact' || analysis.status === 'breakeven') {
              rankStats[key].breakeven++;
            } else if (analysis.status === 'loss') {
              rankStats[key].loss++;
            }
          }
        });
      });
    });

    // 计算百分比
    Object.keys(rankStats).forEach((key) => {
      const stats = rankStats[key as keyof typeof rankStats];
      if (stats.total > 0) {
        stats.breakevenRate = (stats.breakeven / stats.total) * 100;
        stats.lossRate = (stats.loss / stats.total) * 100;
      }
    });

    return rankStats;
  };
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
