<template>
  <DefaultLayout>
    <Head title="游戏数据中心" />

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

          <NSpin :show="analysisLoading">
            <div v-if="analysisData.length > 0" class="space-y-6">
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
                      <div class="flex justify-between">
                        <span class="text-gray-700">价格:</span>
                        <span class="text-xs text-gray-800 font-mono">${{ parseFloat(token.price).toFixed(6) }}</span>
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
                            <div
                              v-else
                              class="h-8 w-8 flex items-center justify-center rounded-full bg-gray-300 text-xs"
                            >
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
                          <span class="text-blue-400 font-medium">{{ token.prediction_score.toFixed(1) }}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            <NEmpty v-else description="暂无当前局数据" class="py-8" />
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

        <!-- 第四部分：预测历史数据表格 -->
        <NCard
          class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="🔮 预测历史数据 (最近50局)"
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
                  <div class="text-sm text-gray-300">平均精确匹配率</div>
                  <div class="text-2xl text-green-400 font-bold">
                    {{
                      (
                        predictionHistoryData.reduce((sum, round) => sum + round.accuracy.exact_accuracy, 0) /
                        predictionHistoryData.length
                      ).toFixed(1)
                    }}%
                  </div>
                </div>
                <div class="border border-white/20 rounded-lg bg-white/5 p-4">
                  <div class="text-sm text-gray-300">平均接近匹配率</div>
                  <div class="text-2xl text-blue-400 font-bold">
                    {{
                      (
                        predictionHistoryData.reduce((sum, round) => sum + round.accuracy.close_accuracy, 0) /
                        predictionHistoryData.length
                      ).toFixed(1)
                    }}%
                  </div>
                </div>
                <div class="border border-white/20 rounded-lg bg-white/5 p-4">
                  <div class="text-sm text-gray-300">平均排名差</div>
                  <div class="text-2xl text-yellow-400 font-bold">
                    {{
                      (
                        predictionHistoryData.reduce((sum, round) => sum + round.accuracy.avg_rank_difference, 0) /
                        predictionHistoryData.length
                      ).toFixed(2)
                    }}
                  </div>
                </div>
                <div class="border border-white/20 rounded-lg bg-white/5 p-4">
                  <div class="text-sm text-gray-300">总预测轮次</div>
                  <div class="text-2xl text-purple-400 font-bold">{{ predictionHistoryData.length }}</div>
                </div>
              </div>

              <!-- 预测历史表格 -->
              <NDataTable
                :columns="predictionHistoryColumns"
                :data="predictionHistoryTableData"
                :pagination="{ pageSize: 5 }"
                :scroll-x="1200"
                striped
              />
            </div>
            <NEmpty v-else description="暂无预测历史数据" class="py-8" />
          </NSpin>
        </NCard>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { ref, onMounted, computed } from 'vue';
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

  interface PredictionHistoryTableRow extends PredictionHistoryRound {
    key: number;
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

  // 预测历史数据表格列定义
  const predictionHistoryColumns: DataTableColumn<PredictionHistoryTableRow>[] = [
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
      title: '精确匹配',
      key: 'exact_accuracy',
      width: 100,
      render: (row: PredictionHistoryTableRow) => `${row.accuracy.exact_accuracy}%`
    },
    {
      title: '接近匹配',
      key: 'close_accuracy',
      width: 100,
      render: (row: PredictionHistoryTableRow) => `${row.accuracy.close_accuracy}%`
    },
    {
      title: '平均排名差',
      key: 'avg_rank_difference',
      width: 110,
      render: (row: PredictionHistoryTableRow) => row.accuracy.avg_rank_difference.toString()
    },
    {
      title: '预测详情',
      key: 'details',
      width: 300,
      render: (row: PredictionHistoryTableRow) => {
        const predictions = row.predictions.map((p) => `${p.symbol}(预测#${p.predicted_rank})`).join(', ');
        return predictions || '-';
      }
    },
    {
      title: '实际结果',
      key: 'actual_results',
      width: 300,
      render: (row: PredictionHistoryTableRow) => {
        const results = row.results.map((r) => `${r.symbol}(实际#${r.actual_rank})`).join(', ');
        return results || '-';
      }
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

  const predictionHistoryTableData = computed((): PredictionHistoryTableRow[] => {
    return predictionHistoryData.value.map(
      (item: PredictionHistoryRound): PredictionHistoryTableRow => ({
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
    }, 30000);

    setInterval(() => {
      fetchPredictionHistoryData();
    }, 60000);
  });
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
