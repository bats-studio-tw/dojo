<template>
  <DefaultLayout>
    <Head title="数据分析面板" />

    <div class="min-h-screen from-slate-900 via-purple-900 to-slate-900 bg-gradient-to-br p-3 sm:p-6">
      <div class="mx-auto max-w-7xl">
        <!-- 导航栏 -->
        <div class="mb-6 flex items-center justify-between">
          <h1 class="text-2xl text-white font-bold">📊 数据分析面板</h1>
          <div class="flex space-x-3">
            <!-- WebSocket状态指示器 -->
            <div class="flex items-center rounded-lg px-3 py-2 text-sm space-x-2" :class="getWebSocketStatusClass()">
              <span>{{ getWebSocketStatusIcon() }}</span>
              <span>{{ websocketStatus.message }}</span>
              <button v-if="!isConnected" @click="reconnectWebSocket()" class="text-xs underline">重连</button>
            </div>
          </div>
        </div>

        <!-- AI预测分析面板 -->
        <AIPredictionRanking
          :current-analysis="currentAnalysis"
          :analysis-meta="analysisMeta"
          :current-round-id="currentRoundId"
          :current-game-status="currentGameStatus"
          :current-game-tokens-with-ranks="currentGameTokensWithRanks"
          :analysis-loading="analysisLoading"
          @refresh-analysis="refreshAnalysis"
        />

        <!-- 预测历史数据表格 -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="🔮 预测历史数据"
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
            <div v-if="predictionHistoryData && predictionHistoryData.length > 0" class="space-y-4">
              <!-- 局数选择器 -->
              <div
                class="mb-3 border border-white/20 rounded-lg from-gray-500/10 to-slate-600/5 bg-gradient-to-br px-3 py-2"
              >
                <div class="mb-1 flex items-center justify-between">
                  <div class="py-1 text-sm text-white font-medium">📊 最新N局分析设置</div>
                  <div class="text-xs text-gray-300">
                    当前:
                    <span class="text-cyan-400 font-bold">{{ recentRoundsCount }}</span>
                    局
                  </div>
                </div>
                <div class="flex items-center space-x-3">
                  <span class="whitespace-nowrap text-xs text-gray-300 font-medium">局数:</span>
                  <div class="min-w-0 flex-1">
                    <n-slider
                      v-model:value="recentRoundsCount"
                      :min="1"
                      :max="Math.min(300, predictionHistoryData?.length || 0)"
                      :step="1"
                      :tooltip="true"
                    />
                  </div>
                  <div class="whitespace-nowrap text-xs text-gray-400">
                    1-{{ Math.min(300, predictionHistoryData?.length || 0) }}局
                  </div>
                </div>
              </div>

              <!-- 预测准确度统计卡片 -->
              <div class="grid grid-cols-1 gap-3 lg:grid-cols-4 md:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5 sm:gap-4">
                <!-- 精准预测率 -->
                <div
                  class="prediction-stat-card border-green-500/30 from-green-500/10 to-emerald-600/5 bg-gradient-to-br hover:border-green-400/50 hover:shadow-green-500/20"
                >
                  <div class="stat-icon">🎯</div>
                  <div class="stat-content">
                    <div class="stat-label text-green-300">精准预测率</div>
                    <div class="stat-value text-green-400">
                      {{ (calculateRoundBasedStats().exactRate || 0).toFixed(1) }}
                      <span>%</span>
                    </div>
                    <div class="stat-desc text-green-200/70">预测与实际完全相同</div>
                  </div>
                </div>

                <!-- 预测总局数 -->
                <div
                  class="prediction-stat-card border-purple-500/30 from-purple-500/10 to-indigo-600/5 bg-gradient-to-br hover:border-purple-400/50 hover:shadow-purple-500/20"
                >
                  <div class="stat-icon">📊</div>
                  <div class="stat-content">
                    <div class="stat-label text-purple-300">预测总局数</div>
                    <div class="stat-value text-purple-400">{{ calculatePortfolioStats().totalRounds }}</div>
                    <div class="stat-desc text-purple-200/70">模型运行总局数</div>
                  </div>
                </div>

                <!-- 预测排名统计 -->
                <div
                  v-for="rank in [1, 2, 3]"
                  :key="`rank-${rank}`"
                  class="prediction-stat-card"
                  :class="getRankCardClass(rank)"
                >
                  <div class="stat-icon">{{ getRankIcon(rank) }}</div>
                  <div class="stat-content">
                    <div class="stat-label" :class="getRankLabelClass(rank)">预测第{{ rank }}名</div>
                    <div class="stat-multi-value">
                      <!-- 全部历史数据 -->
                      <div class="mb-2 border-b border-opacity-20 pb-2" :class="getRankBorderClass(rank)">
                        <div class="mb-1 text-xs opacity-50" :class="getRankTextClass(rank)">
                          全部{{ getRankStats(rank).total }}局
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base font-bold" :class="getRankValueClass(rank)">
                            {{ (getRankStats(rank).breakevenRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs opacity-70" :class="getRankTextClass(rank)">保本率</span>
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base font-bold" :class="getRankSecondaryClass(rank)">
                            {{ (getRankStats(rank).firstPlaceRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs opacity-70" :class="getRankSecondaryTextClass(rank)">第一名率</span>
                        </div>
                      </div>
                      <!-- 最新N局数据 -->
                      <div class="pt-1">
                        <div class="mb-1 text-xs text-cyan-300/70">最新{{ recentRoundsCount }}局</div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-cyan-400 font-bold">
                            {{ (getRecentRankStats(rank).breakevenRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-cyan-200/70">保本率</span>
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-teal-300 font-bold">
                            {{ (getRecentRankStats(rank).firstPlaceRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-teal-200/70">第一名率</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 预测对比表格 -->
              <NDataTable
                :columns="predictionComparisonColumns"
                :data="predictionComparisonTableData"
                :pagination="{ pageSize: 10 }"
                :scroll-x="800"
                striped
                :row-props="rowProps"
                size="small"
              />
            </div>
            <NEmpty v-else description="暂无预测历史数据" class="py-8" />
          </NSpin>
        </NCard>

        <!-- 历史游戏数据表格 -->
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
              v-if="historyData && historyData.length > 0"
              :columns="historyColumns"
              :data="historyTableData"
              :pagination="{ pageSize: 5 }"
              :scroll-x="700"
              striped
              size="small"
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
  import { NEmpty, type DataTableColumn } from 'naive-ui';
  import { Head } from '@inertiajs/vue3';
  import { storeToRefs } from 'pinia';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import AIPredictionRanking from '@/components/AIPredictionRanking.vue';
  import api from '@/utils/api';

  // 导入游戏预测store - 统一的数据管理
  import { useGamePredictionStore } from '@/stores/gamePrediction';

  // 历史数据相关接口
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

  interface HistoryTableRow extends HistoryRound {
    key: number;
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

  interface PredictionComparisonRow extends DetailedPredictionItem {
    key: string;
  }

  // 使用游戏预测store - 统一的数据管理，支持WebSocket实时更新
  const gamePredictionStore = useGamePredictionStore();

  // 从store中获取响应式数据
  const {
    websocketStatus,
    isConnected,
    currentAnalysis,
    analysisMeta,
    predictionHistory,
    analysisLoading,
    historyLoading,
    currentRoundId,
    currentGameStatus,
    currentGameTokensWithRanks
  } = storeToRefs(gamePredictionStore);

  // 从store中获取方法
  const { reconnectWebSocket } = gamePredictionStore;

  // 本地状态管理
  const historyData = ref<HistoryRound[]>([]);
  const predictionHistoryLoading = ref(false);
  const predictionHistoryData = computed(() => predictionHistory.value);
  const recentRoundsCount = ref(50);

  // ==================== 工具函数 ====================

  // WebSocket状态样式
  const getWebSocketStatusClass = () => {
    const status = websocketStatus.value.status;
    switch (status) {
      case 'connected':
        return 'bg-green-500/20 border border-green-500/30 text-green-400';
      case 'connecting':
        return 'bg-yellow-500/20 border border-yellow-500/30 text-yellow-400';
      case 'disconnected':
        return 'bg-gray-500/20 border border-gray-500/30 text-gray-400';
      case 'error':
        return 'bg-red-500/20 border border-red-500/30 text-red-400';
      default:
        return 'bg-gray-500/20 border border-gray-500/30 text-gray-400';
    }
  };

  const getWebSocketStatusIcon = () => {
    const status = websocketStatus.value.status;
    switch (status) {
      case 'connected':
        return '🟢';
      case 'connecting':
        return '🟡';
      case 'disconnected':
        return '⚪';
      case 'error':
        return '🔴';
      default:
        return '⚪';
    }
  };

  const getRankIcon = (rank: number) => {
    if (rank === 1) return '🥇';
    if (rank === 2) return '🥈';
    if (rank === 3) return '🥉';
    return '📊';
  };

  // 排名卡片样式
  const getRankCardClass = (rank: number) => {
    if (rank === 1)
      return 'bg-gradient-to-br from-yellow-500/10 to-amber-600/5 border-yellow-500/30 hover:border-yellow-400/50 hover:shadow-yellow-500/20';
    if (rank === 2)
      return 'bg-gradient-to-br from-slate-500/10 to-gray-600/5 border-slate-500/30 hover:border-slate-400/50 hover:shadow-slate-500/20';
    if (rank === 3)
      return 'bg-gradient-to-br from-orange-500/10 to-red-600/5 border-orange-500/30 hover:border-orange-400/50 hover:shadow-orange-500/20';
    return 'bg-gradient-to-br from-purple-500/10 to-indigo-600/5 border-purple-500/30 hover:border-purple-400/50 hover:shadow-purple-500/20';
  };

  const getRankLabelClass = (rank: number) => {
    if (rank === 1) return 'text-yellow-300';
    if (rank === 2) return 'text-slate-300';
    if (rank === 3) return 'text-orange-300';
    return 'text-purple-300';
  };

  const getRankValueClass = (rank: number) => {
    if (rank === 1) return 'text-yellow-400';
    if (rank === 2) return 'text-slate-400';
    if (rank === 3) return 'text-orange-400';
    return 'text-purple-400';
  };

  const getRankSecondaryClass = (rank: number) => {
    if (rank === 1) return 'text-amber-300';
    if (rank === 2) return 'text-gray-300';
    if (rank === 3) return 'text-red-300';
    return 'text-pink-300';
  };

  const getRankTextClass = (rank: number) => {
    if (rank === 1) return 'text-yellow-200';
    if (rank === 2) return 'text-slate-200';
    if (rank === 3) return 'text-orange-200';
    return 'text-purple-200';
  };

  const getRankSecondaryTextClass = (rank: number) => {
    if (rank === 1) return 'text-amber-200';
    if (rank === 2) return 'text-gray-200';
    if (rank === 3) return 'text-red-200';
    return 'text-pink-200';
  };

  const getRankBorderClass = (rank: number) => {
    if (rank === 1) return 'border-yellow-400';
    if (rank === 2) return 'border-slate-400';
    if (rank === 3) return 'border-orange-400';
    return 'border-purple-400';
  };

  // ==================== API调用函数 ====================

  const fetchInitialPredictionData = async () => {
    // 在页面初始化时获取预测数据，避免等待WebSocket
    console.log('🔮 获取初始预测数据...');
    try {
      const response = await api.get('/game/current-analysis');
      if (response.data.success) {
        currentAnalysis.value = response.data.data || [];
        analysisMeta.value = response.data.meta || null;
        console.log(`✅ 成功获取初始预测数据: ${currentAnalysis.value.length} 个Token`);
      } else {
        console.warn('⚠️ 获取初始预测数据失败:', response.data.message);
      }
    } catch (error) {
      console.error('❌ 获取初始预测数据失败:', error);
    }
  };

  const fetchHistoryData = async () => {
    historyLoading.value = true;
    try {
      const response = await api.get('/game/history');
      if (response.data.success) {
        historyData.value = response.data.data;
      } else {
        window.$message?.error(response.data.message || '获取历史数据失败');
      }
    } catch (error) {
      console.error('获取历史数据失败:', error);
      window.$message?.error('获取历史数据失败');
    } finally {
      historyLoading.value = false;
    }
  };

  const fetchPredictionHistoryData = async () => {
    predictionHistoryLoading.value = true;
    try {
      const response = await api.get('/game/prediction-history');
      if (response.data.success) {
        // 更新store中的预测历史数据
        predictionHistory.value = response.data.data;
      } else {
        window.$message?.error(response.data.message || '获取预测历史数据失败');
      }
    } catch (error) {
      console.error('获取预测历史数据失败:', error);
      window.$message?.error('获取预测历史数据失败');
    } finally {
      predictionHistoryLoading.value = false;
    }
  };

  // ==================== 刷新函数 ====================

  const refreshAnalysis = () => {
    // 手动刷新预测分析数据
    fetchInitialPredictionData();
  };

  const refreshHistoryData = () => fetchHistoryData();
  const refreshPredictionHistoryData = () => fetchPredictionHistoryData();

  // ==================== 历史数据表格 ====================

  const getTokensByRank = (tokens: RoundToken[], rank: number): string => {
    const tokensAtRank = tokens.filter((t) => t.rank === rank);
    if (tokensAtRank.length === 0) return '-';
    if (tokensAtRank.length === 1) return tokensAtRank[0].symbol;
    return tokensAtRank.map((t) => t.symbol).join(' / ');
  };

  const historyColumns: DataTableColumn<HistoryTableRow>[] = [
    { title: '轮次ID', key: 'round_id', width: 120 },
    { title: '结算时间', key: 'settled_at', width: 160 },
    { title: '第1名', key: 'rank_1', width: 100, render: (row: HistoryTableRow) => getTokensByRank(row.tokens, 1) },
    { title: '第2名', key: 'rank_2', width: 100, render: (row: HistoryTableRow) => getTokensByRank(row.tokens, 2) },
    { title: '第3名', key: 'rank_3', width: 100, render: (row: HistoryTableRow) => getTokensByRank(row.tokens, 3) },
    { title: '第4名', key: 'rank_4', width: 100, render: (row: HistoryTableRow) => getTokensByRank(row.tokens, 4) },
    { title: '第5名', key: 'rank_5', width: 100, render: (row: HistoryTableRow) => getTokensByRank(row.tokens, 5) }
  ];

  const historyTableData = computed((): HistoryTableRow[] => {
    return historyData.value.map(
      (item: HistoryRound): HistoryTableRow => ({
        ...item,
        key: item.id
      })
    );
  });

  // ==================== 预测统计计算 ====================

  // 获取预测总局数统计
  const calculatePortfolioStats = () => {
    return {
      totalRounds: predictionHistoryData.value.length
    };
  };

  // 计算基于单次预测的精准率
  const calculateRoundBasedStats = () => {
    if (predictionHistoryData.value.length === 0) {
      return { exactRate: 0 };
    }

    let exactPredictions = 0;
    let totalPredictions = 0;

    predictionHistoryData.value.forEach((round) => {
      // 🔧 修复：确保 predictions 是数组
      if (!Array.isArray(round.predictions)) {
        console.warn(`⚠️ 轮次 ${round.round_id} 的 predictions 不是数组:`, round.predictions);
        return;
      }

      const top3Predictions = round.predictions.filter((p) => p.predicted_rank <= 3);

      top3Predictions.forEach((prediction) => {
        const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
        if (actualResult) {
          totalPredictions++;
          if (prediction.predicted_rank === actualResult.actual_rank) {
            exactPredictions++;
          }
        }
      });
    });

    return {
      exactRate: totalPredictions > 0 ? (exactPredictions / totalPredictions) * 100 : 0
    };
  };

  // 按预测排名分别统计保本/亏本率和第一名率
  const calculateRankBasedStats = () => {
    const rankStats = {
      rank1: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank2: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank3: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 }
    };

    if (predictionHistoryData.value.length === 0) {
      return rankStats;
    }

    predictionHistoryData.value.forEach((round) => {
      // 🔧 修复：确保 predictions 是数组
      if (!Array.isArray(round.predictions)) {
        console.warn(`⚠️ 轮次 ${round.round_id} 的 predictions 不是数组:`, round.predictions);
        return;
      }

      [1, 2, 3].forEach((predictedRank) => {
        const predictions = round.predictions.filter((p) => p.predicted_rank === predictedRank);

        predictions.forEach((prediction) => {
          const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
          if (actualResult) {
            if (predictedRank === 1) {
              rankStats.rank1.total++;
              if (actualResult.actual_rank <= 3) {
                rankStats.rank1.breakeven++;
              } else {
                rankStats.rank1.loss++;
              }
              if (actualResult.actual_rank === 1) {
                rankStats.rank1.firstPlace++;
              }
            } else if (predictedRank === 2) {
              rankStats.rank2.total++;
              if (actualResult.actual_rank <= 3) {
                rankStats.rank2.breakeven++;
              } else {
                rankStats.rank2.loss++;
              }
              if (actualResult.actual_rank === 1) {
                rankStats.rank2.firstPlace++;
              }
            } else if (predictedRank === 3) {
              rankStats.rank3.total++;
              if (actualResult.actual_rank <= 3) {
                rankStats.rank3.breakeven++;
              } else {
                rankStats.rank3.loss++;
              }
              if (actualResult.actual_rank === 1) {
                rankStats.rank3.firstPlace++;
              }
            }
          }
        });
      });
    });

    // 计算百分比
    [rankStats.rank1, rankStats.rank2, rankStats.rank3].forEach((stats) => {
      if (stats.total > 0) {
        stats.breakevenRate = (stats.breakeven / stats.total) * 100;
        stats.lossRate = (stats.loss / stats.total) * 100;
        stats.firstPlaceRate = (stats.firstPlace / stats.total) * 100;
      }
    });

    return rankStats;
  };

  // 按预测排名分别统计最新N局的保本/亏本率和第一名率
  const calculateRecentRankBasedStats = computed(() => {
    const rankStats = {
      rank1: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank2: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank3: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 }
    };

    if (predictionHistoryData.value.length === 0) {
      return rankStats;
    }

    // 获取最新N局数据
    const recentRounds = predictionHistoryData.value
      .slice()
      .sort((a, b) => b.round_id.localeCompare(a.round_id))
      .slice(0, recentRoundsCount.value);

    recentRounds.forEach((round) => {
      // 🔧 修复：确保 predictions 是数组
      if (!Array.isArray(round.predictions)) {
        console.warn(`⚠️ 轮次 ${round.round_id} 的 predictions 不是数组:`, round.predictions);
        return;
      }

      [1, 2, 3].forEach((predictedRank) => {
        const predictions = round.predictions.filter((p) => p.predicted_rank === predictedRank);

        predictions.forEach((prediction) => {
          const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
          if (actualResult) {
            if (predictedRank === 1) {
              rankStats.rank1.total++;
              if (actualResult.actual_rank <= 3) {
                rankStats.rank1.breakeven++;
              } else {
                rankStats.rank1.loss++;
              }
              if (actualResult.actual_rank === 1) {
                rankStats.rank1.firstPlace++;
              }
            } else if (predictedRank === 2) {
              rankStats.rank2.total++;
              if (actualResult.actual_rank <= 3) {
                rankStats.rank2.breakeven++;
              } else {
                rankStats.rank2.loss++;
              }
              if (actualResult.actual_rank === 1) {
                rankStats.rank2.firstPlace++;
              }
            } else if (predictedRank === 3) {
              rankStats.rank3.total++;
              if (actualResult.actual_rank <= 3) {
                rankStats.rank3.breakeven++;
              } else {
                rankStats.rank3.loss++;
              }
              if (actualResult.actual_rank === 1) {
                rankStats.rank3.firstPlace++;
              }
            }
          }
        });
      });
    });

    // 计算百分比
    [rankStats.rank1, rankStats.rank2, rankStats.rank3].forEach((stats) => {
      if (stats.total > 0) {
        stats.breakevenRate = (stats.breakeven / stats.total) * 100;
        stats.lossRate = (stats.loss / stats.total) * 100;
        stats.firstPlaceRate = (stats.firstPlace / stats.total) * 100;
      }
    });

    return rankStats;
  });

  // ==================== 辅助函数 for template ====================

  // 获取指定排名的统计数据
  const getRankStats = (rank: number) => {
    const stats = calculateRankBasedStats();
    if (rank === 1) return stats.rank1;
    if (rank === 2) return stats.rank2;
    if (rank === 3) return stats.rank3;
    return { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 };
  };

  // 获取指定排名的最新N局统计数据
  const getRecentRankStats = (rank: number) => {
    const stats = calculateRecentRankBasedStats.value;
    if (rank === 1) return stats.rank1;
    if (rank === 2) return stats.rank2;
    if (rank === 3) return stats.rank3;
    return { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 };
  };

  // ==================== 预测对比表格 ====================

  const predictionComparisonTableData = computed((): PredictionComparisonRow[] => {
    const detailedData: PredictionComparisonRow[] = [];

    predictionHistoryData.value.forEach((round) => {
      // 🔧 修复：确保 predictions 是数组
      if (!Array.isArray(round.predictions)) {
        console.warn(`⚠️ 轮次 ${round.round_id} 的 predictions 不是数组:`, round.predictions);
        return;
      }

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
            is_better_than_expected: actualResult.actual_rank < prediction.predicted_rank,
            rank_difference: rankDifference,
            settled_at: round.settled_at || '-'
          });
        }
      });
    });

    return detailedData.sort((a, b) => b.round_id.localeCompare(a.round_id));
  });

  const getPredictionRankIcon = (rank: number) => {
    if (rank === 1) return '🥇';
    if (rank === 2) return '🥈';
    if (rank === 3) return '🥉';
    if (rank === 4) return '4️⃣';
    if (rank === 5) return '5️⃣';
    return '📊';
  };

  const getTokenPredictionAnalysis = (predictedRank: number, actualRank: number) => {
    if (predictedRank === actualRank) {
      return { status: 'exact', text: '精准预测', icon: '🎯', color: 'text-green-400', bgColor: 'bg-green-500/20' };
    }
    if (actualRank <= 3) {
      return { status: 'breakeven', text: '保本', icon: '💰', color: 'text-blue-400', bgColor: 'bg-blue-500/20' };
    }
    return { status: 'loss', text: '亏本', icon: '📉', color: 'text-red-400', bgColor: 'bg-red-500/20' };
  };

  const getPredictionRowClass = (detail: DetailedPredictionItem) => {
    const analysis = getTokenPredictionAnalysis(detail.predicted_rank, detail.actual_rank);
    if (analysis.status === 'exact' || analysis.status === 'breakeven') {
      return 'bg-green-500/20 border-l-4 border-green-500';
    } else {
      return 'bg-red-500/20 border-l-4 border-red-500';
    }
  };

  const rowProps = (row: PredictionComparisonRow) => {
    return { style: getPredictionRowClass(row) };
  };

  const predictionComparisonColumns: DataTableColumn<PredictionComparisonRow>[] = [
    { title: '轮次', key: 'round_id', width: 100 },
    { title: '代币', key: 'symbol', width: 80 },
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
        return h('div', { class: `px-3 py-1 rounded-full text-sm font-medium ${analysis.color} ${analysis.bgColor}` }, [
          h('span', { class: 'mr-1' }, analysis.icon),
          h('span', {}, analysis.text)
        ]);
      }
    },
    { title: '结算时间', key: 'settled_at', width: 140 }
  ];

  // ==================== 页面初始化 ====================

  onMounted(() => {
    console.log('📊 Dashboard页面初始化，加载历史数据...');

    // 获取初始预测数据（优先执行，避免等待WebSocket）
    fetchInitialPredictionData();

    // 获取历史数据
    fetchHistoryData();
    fetchPredictionHistoryData();

    // 设置定时刷新（历史数据更新频率较低）
    setInterval(() => {
      fetchHistoryData();
      fetchPredictionHistoryData();
    }, 30000); // 30秒刷新一次
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

  .stat-value span {
    @apply text-base sm:text-lg;
  }

  .stat-desc {
    @apply mt-2 text-xs;
  }

  .stat-multi-value {
    @apply mt-2 space-y-1;
  }

  kbd {
    @apply bg-gray-700 text-gray-200 px-1.5 py-0.5 rounded text-xs font-mono;
  }

  code {
    @apply bg-gray-800 text-gray-200 px-1 py-0.5 rounded text-xs font-mono;
  }
</style>
