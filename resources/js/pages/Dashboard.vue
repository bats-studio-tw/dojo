<template>
  <DefaultLayout>
    <Head title="游戏数据中心" />

    <div class="min-h-screen bg-gray-900 p-6">
      <div class="mx-auto max-w-7xl">
        <!-- 页面标题 -->
        <div class="mb-8">
          <h1 class="mb-2 text-3xl text-white font-bold">🎯 游戏数据中心</h1>
          <p class="text-gray-300">实时游戏数据分析与预测系统</p>
        </div>

        <!-- 第一部分：预测当前局排名 -->
        <n-card class="mb-6" title="🔮 预测排名" size="large">
          <template #header-extra>
            <n-button :loading="predictionLoading" @click="refreshPrediction" type="primary" size="small">
              🔄 刷新预测
            </n-button>
          </template>

          <n-spin :show="predictionLoading">
            <div v-if="predictionData.length > 0" class="grid grid-cols-1 gap-4 lg:grid-cols-3 md:grid-cols-2">
              <div
                v-for="(token, index) in predictionData"
                :key="token.symbol"
                class="relative border-2 rounded-lg p-4 transition-all duration-200 hover:shadow-lg"
                :class="getPredictionCardClass(index)"
              >
                <div class="mb-2 flex items-center justify-between">
                  <span class="text-lg font-bold">{{ token.symbol }}</span>
                  <div class="flex items-center space-x-1">
                    <span class="text-2xl">{{ getPredictionIcon(index) }}</span>
                    <span class="text-sm text-gray-600 font-medium">预测#{{ index + 1 }}</span>
                  </div>
                </div>

                <div class="text-sm space-y-1">
                  <div class="flex justify-between">
                    <span class="text-gray-600">预测评分:</span>
                    <span class="font-medium">{{ token.prediction_score.toFixed(1) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">胜率:</span>
                    <span class="text-green-600 font-medium">{{ token.win_rate.toFixed(1) }}%</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">前三率:</span>
                    <span class="text-blue-600 font-medium">{{ token.top3_rate.toFixed(1) }}%</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">平均排名:</span>
                    <span class="font-medium">{{ token.avg_rank.toFixed(1) }}</span>
                  </div>
                </div>
              </div>
            </div>
            <n-empty v-else description="暂无预测数据" class="py-8" />
          </n-spin>
        </n-card>

        <!-- 第二部分：代币市场信息 -->
        <n-card class="mb-6" title="💰 代币市场信息" size="large">
          <template #header-extra>
            <div class="flex items-center space-x-3">
              <div v-if="marketMeta" class="text-sm text-gray-600">
                <span class="font-medium">轮次:</span>
                {{ marketMeta.round_id }} |
                <span class="font-medium">状态:</span>
                <n-tag :type="getStatusTagType(marketMeta.status)" size="small">
                  {{ getStatusText(marketMeta.status) }}
                </n-tag>
              </div>
              <n-button :loading="marketLoading" @click="refreshMarketData" type="primary" size="small">
                🔄 刷新市场
              </n-button>
            </div>
          </template>

          <n-spin :show="marketLoading">
            <div v-if="marketData.length > 0" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-gray-700 font-medium">代币</th>
                    <th class="px-4 py-3 text-right text-gray-700 font-medium">价格 (USD)</th>
                    <th class="px-4 py-3 text-right text-gray-700 font-medium">5分钟</th>
                    <th class="px-4 py-3 text-right text-gray-700 font-medium">1小时</th>
                    <th class="px-4 py-3 text-right text-gray-700 font-medium">4小时</th>
                    <th class="px-4 py-3 text-right text-gray-700 font-medium">24小时</th>
                    <th class="px-4 py-3 text-right text-gray-700 font-medium">成交量 24h</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="token in marketData" :key="token.symbol" class="border-b border-gray-100 hover:bg-gray-50">
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
                          <div class="font-medium">
                            {{ token.symbol }}
                          </div>
                          <div class="text-xs text-gray-500">
                            {{ token.name }}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3 text-right font-mono">${{ parseFloat(token.price).toFixed(6) }}</td>
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
                    <td class="px-4 py-3 text-right text-xs font-mono">${{ formatVolume(token.volume_24h) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <n-empty v-else description="暂无市场数据" class="py-8" />
          </n-spin>
        </n-card>

        <!-- 第三部分：历史数据表格 -->
        <n-card title="📊 历史游戏数据 (最近100局)" size="large">
          <template #header-extra>
            <n-button :loading="historyLoading" @click="refreshHistoryData" type="primary" size="small">
              🔄 刷新历史
            </n-button>
          </template>

          <n-spin :show="historyLoading">
            <n-data-table
              v-if="historyData.length > 0"
              :columns="historyColumns"
              :data="historyTableData"
              :pagination="{ pageSize: 10 }"
              :scroll-x="800"
              striped
            />
            <n-empty v-else description="暂无历史数据" class="py-8" />
          </n-spin>
        </n-card>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { ref, onMounted, computed } from 'vue';
  import { useMessage, type DataTableColumn } from 'naive-ui';
  import { Head } from '@inertiajs/vue3';
  import api from '@/utils/api';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';

  // 定义接口类型
  interface TokenPrediction {
    symbol: string;
    prediction_score: number;
    win_rate: number;
    top3_rate: number;
    avg_rank: number;
    total_games: number;
    wins: number;
    top3: number;
  }

  interface TokenMarketData {
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

  // 响应式数据
  const predictionData = ref<TokenPrediction[]>([]);
  const marketData = ref<TokenMarketData[]>([]);
  const historyData = ref<HistoryRound[]>([]);
  const marketMeta = ref<any>(null);

  const predictionLoading = ref(false);
  const marketLoading = ref(false);
  const historyLoading = ref(false);

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
    },
    {
      title: '代币详情',
      key: 'tokens_detail',
      width: 200,
      render: (row: HistoryTableRow) => {
        return row.tokens
          .map((token: RoundToken) => `${token.symbol}(#${token.rank}:$${parseFloat(token.value).toFixed(4)})`)
          .join(', ');
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

  // 工具函数
  const getPredictionCardClass = (index: number) => {
    if (index === 0) return 'border-yellow-400 bg-yellow-50';
    if (index === 1) return 'border-gray-400 bg-gray-50';
    if (index === 2) return 'border-orange-400 bg-orange-50';
    return 'border-gray-200 bg-white';
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
      case 'starting':
      case 'running':
      case 'active':
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
  const fetchPredictionData = async () => {
    predictionLoading.value = true;
    try {
      const response = await api.get('/game/prediction');
      if (response.data.success) {
        predictionData.value = response.data.data;
      } else {
        getMessageInstance()?.error(response.data.message || '获取预测数据失败');
      }
    } catch (error) {
      console.error('获取预测数据失败:', error);
      getMessageInstance()?.error('获取预测数据失败');
    } finally {
      predictionLoading.value = false;
    }
  };

  const fetchMarketData = async () => {
    marketLoading.value = true;
    try {
      const response = await api.get('/game/market-data');
      if (response.data.success) {
        marketData.value = response.data.data;
        marketMeta.value = response.data.meta || null;
      } else {
        getMessageInstance()?.error(response.data.message || '获取市场数据失败');
      }
    } catch (error) {
      console.error('获取市场数据失败:', error);
      getMessageInstance()?.error('获取市场数据失败');
    } finally {
      marketLoading.value = false;
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

  // 刷新函数
  const refreshPrediction = () => fetchPredictionData();
  const refreshMarketData = () => fetchMarketData();
  const refreshHistoryData = () => fetchHistoryData();

  // 初始化数据
  onMounted(() => {
    fetchPredictionData();
    fetchMarketData();
    fetchHistoryData();

    // 设置定时刷新（每60秒刷新预测数据）
    setInterval(() => {
      fetchPredictionData();
      fetchHistoryData();
      fetchMarketData();
    }, 10000);
  });
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
