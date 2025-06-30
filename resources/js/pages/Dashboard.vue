<template>
  <DefaultLayout>
    <Head title="Dashboard" />

    <div class="min-h-screen from-slate-900 via-purple-900 to-slate-900 bg-gradient-to-br p-3 sm:p-6">
      <div class="mx-auto max-w-7xl">
        <!-- 导航栏 -->
        <div class="mb-6 flex items-center justify-between">
          <h1 class="text-2xl text-white font-bold">📊 数据分析面板</h1>
          <div class="flex space-x-3">
            <!-- WebSocket状态指示器 -->
            <div class="flex items-center rounded-lg px-3 py-2 text-sm space-x-2" :class="getWebSocketStatusClass()">
              <span>{{ getWebSocketStatusIcon() }}</span>
              <span>{{ gamePredictionStore.websocketStatus.message }}</span>
              <button
                v-if="!gamePredictionStore.isConnected"
                @click="gamePredictionStore.reconnectWebSocket()"
                class="text-xs underline"
              >
                重连
              </button>
            </div>
            <a
              href="#"
              class="flex items-center rounded-lg bg-purple-600 px-4 py-2 text-white transition-colors duration-200 disabled:cursor-not-allowed space-x-2 hover:bg-purple-700 disabled:opacity-50"
            >
              <span>🤖</span>
              <span>自动下注控制</span>
            </a>
          </div>
        </div>

        <!-- 调试信息面板 -->
        <NCard
          class="mb-4 border border-blue-500/30 bg-blue-500/5 shadow-lg backdrop-blur-lg"
          title="🐛 调试信息"
          size="small"
        >
          <div class="grid grid-cols-1 gap-3 text-xs lg:grid-cols-4 sm:grid-cols-2">
            <div class="space-y-1">
              <div class="text-blue-300 font-medium">数据状态</div>
              <div class="text-gray-300">分析数据: {{ analysisData.length }} 个</div>
              <div class="text-gray-300">历史数据: {{ predictionHistoryData.length }} 局</div>
              <div class="text-gray-300">游戏数据: {{ latestGameData ? '有' : '无' }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-blue-300 font-medium">WebSocket状态</div>
              <div class="text-gray-300">状态: {{ gamePredictionStore.websocketStatus.status }}</div>
              <div class="text-gray-300">重连次数: {{ gamePredictionStore.websocketStatus.reconnectAttempts }}</div>
              <div class="text-gray-300">
                最后连接: {{ formatTime(gamePredictionStore.websocketStatus.lastConnectedAt) }}
              </div>
            </div>
            <div class="space-y-1">
              <div class="text-blue-300 font-medium">轮次信息</div>
              <div class="text-gray-300">轮次ID: {{ currentRoundId || '无' }}</div>
              <div class="text-gray-300">状态: {{ currentGameStatus || '无' }}</div>
              <div class="text-gray-300">更新时间: {{ formatTime(analysisMeta?.updated_at) }}</div>
              <div class="flex items-center gap-2 text-gray-300">
                <div v-if="canBet" class="h-2 w-2 rounded-full bg-green-500"></div>
                <div v-else-if="isSettling" class="h-2 w-2 rounded-full bg-yellow-500"></div>
                <div v-else-if="isSettled" class="h-2 w-2 rounded-full bg-blue-500"></div>
                <div v-else class="h-2 w-2 rounded-full bg-gray-500"></div>
                <span class="text-xs">
                  {{ canBet ? '可下注' : isSettling ? '结算中' : isSettled ? '已结算' : '等待中' }}
                </span>
              </div>
            </div>
            <div class="space-y-1">
              <div class="text-blue-300 font-medium">加载状态</div>
              <div class="text-gray-300">分析加载: {{ analysisLoading ? '是' : '否' }}</div>
              <div class="text-gray-300">历史加载: {{ predictionHistoryLoading ? '是' : '否' }}</div>
              <div class="text-gray-300">游戏加载: {{ historyLoading ? '是' : '否' }}</div>
              <div class="mt-2">
                <n-button size="tiny" @click="testStoreUpdate" type="warning">🧪 测试Store更新</n-button>
                <n-button size="tiny" @click="manualRefresh" type="info" class="ml-1">🔄 手动刷新</n-button>
                <n-button size="tiny" @click="testWebSocket" type="error" class="ml-1">🔍 测试WebSocket</n-button>
                <n-button size="tiny" @click="simulatePredictionEvent" type="success" class="ml-1">
                  🎯 模拟预测事件
                </n-button>
                <n-button size="tiny" @click="checkWebSocketBinding" type="primary" class="ml-1">🔧 检查绑定</n-button>
                <n-button size="tiny" @click="testDirectEvent" type="tertiary" class="ml-1">🔬 直接事件</n-button>
              </div>
            </div>
          </div>
        </NCard>

        <!-- v8 H2H 对战关系分析 -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="名次預測"
          size="large"
        >
          <template #header-extra>
            <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-x-3 sm:space-y-0">
              <div
                v-if="analysisMeta"
                class="flex flex-wrap items-center gap-1 text-xs text-gray-300 sm:gap-2 sm:text-sm"
              >
                <span class="font-medium">轮次:</span>
                <span class="text-red">{{ analysisMeta.round_id }}</span>
                <span class="font-medium">状态:</span>
                <NTag :type="getStatusTagType(analysisMeta.status)" size="small">
                  {{ analysisMeta.status }}
                </NTag>
              </div>
              <n-button
                :loading="analysisLoading"
                @click="refreshAnalysis"
                type="primary"
                size="small"
                class="self-end sm:self-auto"
              >
                🔄 刷新分析
              </n-button>
            </div>
          </template>

          <div v-if="analysisData && analysisData.length > 0" class="space-y-4">
            <!-- 横向名次預測展示 -->
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5">
              <div
                v-for="(token, index) in analysisData"
                :key="`unified-${index}-${token.symbol}-${token.name}`"
                class="relative overflow-hidden border rounded-lg p-3 transition-all duration-300 hover:shadow-lg"
                :class="getUnifiedCardClass(index)"
              >
                <!-- 简洁头部 -->
                <div class="mb-2 flex items-center justify-between">
                  <div class="flex items-center space-x-2">
                    <div class="text-lg">{{ getPredictionIcon(index) }}</div>
                    <div class="text-sm text-white font-bold">{{ token.symbol }}</div>
                  </div>
                  <div class="text-xs text-gray-400">#{{ index + 1 }}</div>
                </div>

                <!-- 核心评分 -->
                <div class="mb-3 text-center">
                  <div class="text-xs text-gray-400">最终评分</div>
                  <div class="text-lg font-bold" :class="getScoreTextClass(index)">
                    {{
                      (
                        token.risk_adjusted_score ||
                        token.final_prediction_score ||
                        token.prediction_score ||
                        0
                      ).toFixed(1)
                    }}
                  </div>
                  <div v-if="token.rank_confidence" class="text-xs text-gray-400">
                    置信度 {{ (token.rank_confidence || 0).toFixed(0) }}%
                  </div>
                </div>

                <!-- 所有数据参数 -->
                <div class="text-xs space-y-1">
                  <div class="flex justify-between">
                    <span class="text-gray-400">绝对分数:</span>
                    <span class="text-purple-400 font-bold">{{ (token.absolute_score || 0).toFixed(1) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-400">H2H分数:</span>
                    <span class="text-orange-400 font-bold">
                      {{ (token.relative_score || token.h2h_score || 0).toFixed(1) }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-400">保本率:</span>
                    <span class="text-green-400 font-bold">{{ (token.top3_rate || 0).toFixed(1) }}%</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-400">稳定性:</span>
                    <span class="text-yellow-400 font-bold">
                      <span v-if="token.value_stddev !== undefined">{{ (token.value_stddev || 0).toFixed(3) }}</span>
                      <span v-else class="text-gray-500">-</span>
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-400">市场动量:</span>
                    <span class="text-teal-400 font-bold">
                      <span v-if="token.market_momentum_score">
                        {{ (token.market_momentum_score || 0).toFixed(1) }}
                      </span>
                      <span v-else class="text-gray-500">-</span>
                    </span>
                  </div>

                  <!-- 价格变化数据 -->
                  <div class="mt-2 border-t border-gray-600/30 pt-1">
                    <div class="flex justify-between">
                      <span class="text-gray-400">5分钟:</span>
                      <span class="font-bold" :class="formatPriceChange(token.change_5m).color">
                        {{ formatPriceChange(token.change_5m).text }}
                      </span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-400">1小时:</span>
                      <span class="font-bold" :class="formatPriceChange(token.change_1h).color">
                        {{ formatPriceChange(token.change_1h).text }}
                      </span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-400">4小时:</span>
                      <span class="font-bold" :class="formatPriceChange(token.change_4h).color">
                        {{ formatPriceChange(token.change_4h).text }}
                      </span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-400">24小时:</span>
                      <span class="font-bold" :class="formatPriceChange(token.change_24h).color">
                        {{ formatPriceChange(token.change_24h).text }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <NEmpty v-else description="暂无 H2H 对战分析数据" class="py-8" />
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
              class="self-end sm:self-auto"
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

              <!-- 预测准确度总结 -->
              <div class="grid grid-cols-1 gap-3 lg:grid-cols-4 md:grid-cols-3 sm:grid-cols-2 xl:grid-cols-5 sm:gap-4">
                <!-- 精准预测率 -->
                <div
                  class="relative overflow-hidden border border-green-500/30 rounded-xl from-green-500/10 to-emerald-600/5 bg-gradient-to-br p-4 transition-all duration-300 hover:border-green-400/50 sm:p-6 hover:shadow-green-500/20 hover:shadow-lg"
                >
                  <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🎯</div>
                  <div class="relative">
                    <div class="text-xs text-green-300 font-medium sm:text-sm">精准预测率</div>
                    <div class="mt-2 text-2xl text-green-400 font-bold sm:text-3xl">
                      {{ (calculateRoundBasedStats().exactRate || 0).toFixed(1) }}
                      <span class="text-base sm:text-lg">%</span>
                    </div>
                    <div class="mt-2 text-xs text-green-200/70">预测与实际完全相同</div>
                  </div>
                </div>

                <!-- 预测总局数 -->
                <div
                  class="relative overflow-hidden border border-purple-500/30 rounded-xl from-purple-500/10 to-indigo-600/5 bg-gradient-to-br p-4 transition-all duration-300 hover:border-purple-400/50 sm:p-6 hover:shadow-lg hover:shadow-purple-500/20"
                >
                  <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">📊</div>
                  <div class="relative">
                    <div class="text-xs text-purple-300 font-medium sm:text-sm">预测总局数</div>
                    <div class="mt-2 text-2xl text-purple-400 font-bold sm:text-3xl">
                      {{ calculatePortfolioStats().totalRounds }}
                    </div>
                    <div class="mt-2 text-xs text-purple-200/70">模型运行总局数</div>
                  </div>
                </div>

                <!-- 预测第一名 -->
                <div
                  class="relative overflow-hidden border border-yellow-500/30 rounded-xl from-yellow-500/10 to-amber-600/5 bg-gradient-to-br p-4 transition-all duration-300 hover:border-yellow-400/50 sm:p-6 hover:shadow-lg hover:shadow-yellow-500/20"
                >
                  <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🥇</div>
                  <div class="relative">
                    <div class="text-xs text-yellow-300 font-medium sm:text-sm">预测第一名</div>
                    <div class="mt-2 space-y-1">
                      <!-- 全部历史数据 -->
                      <div class="border-b border-yellow-400/20 pb-2">
                        <div class="mb-1 text-xs text-yellow-200/50">
                          最新{{ calculateRankBasedStats().rank1.total }}局
                        </div>

                        <div class="flex items-center justify-between">
                          <span class="text-base text-yellow-400 font-bold sm:text-lg">
                            {{ (calculateRankBasedStats().rank1.breakevenRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-yellow-200/70">保本率</span>
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-amber-300 font-bold sm:text-lg">
                            {{ (calculateRankBasedStats().rank1.firstPlaceRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-amber-200/70">第一名率</span>
                        </div>
                      </div>
                      <!-- 最新N局数据 -->
                      <div class="pt-1">
                        <div class="mb-1 text-xs text-cyan-300/70">最新{{ recentRoundsCount }}局</div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-cyan-400 font-bold">
                            {{ (calculateRecentRankBasedStats.rank1.breakevenRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-cyan-200/70">保本率</span>
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-teal-300 font-bold">
                            {{ (calculateRecentRankBasedStats.rank1.firstPlaceRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-teal-200/70">第一名率</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 预测第二名 -->
                <div
                  class="relative overflow-hidden border border-slate-500/30 rounded-xl from-slate-500/10 to-gray-600/5 bg-gradient-to-br p-4 transition-all duration-300 hover:border-slate-400/50 sm:p-6 hover:shadow-lg hover:shadow-slate-500/20"
                >
                  <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🥈</div>
                  <div class="relative">
                    <div class="text-xs text-slate-300 font-medium sm:text-sm">预测第二名</div>
                    <div class="mt-2 space-y-1">
                      <!-- 全部历史数据 -->
                      <div class="border-b border-slate-400/20 pb-2">
                        <div class="mb-1 text-xs text-slate-200/50">
                          最新{{ calculateRankBasedStats().rank2.total }}局
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-slate-400 font-bold sm:text-lg">
                            {{ (calculateRankBasedStats().rank2.breakevenRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-slate-200/70">保本率</span>
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-gray-300 font-bold sm:text-lg">
                            {{ (calculateRankBasedStats().rank2.firstPlaceRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-gray-200/70">第一名率</span>
                        </div>
                      </div>
                      <!-- 最新N局数据 -->
                      <div class="pt-1">
                        <div class="mb-1 text-xs text-cyan-300/70">最新{{ recentRoundsCount }}局</div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-cyan-400 font-bold">
                            {{ (calculateRecentRankBasedStats.rank2.breakevenRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-cyan-200/70">保本率</span>
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-teal-300 font-bold">
                            {{ (calculateRecentRankBasedStats.rank2.firstPlaceRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-teal-200/70">第一名率</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 预测第三名 -->
                <div
                  class="relative overflow-hidden border border-orange-500/30 rounded-xl from-orange-500/10 to-red-600/5 bg-gradient-to-br p-4 transition-all duration-300 hover:border-orange-400/50 sm:p-6 hover:shadow-lg hover:shadow-orange-500/20"
                >
                  <div class="absolute right-2 top-2 text-xl opacity-20 sm:text-2xl">🥉</div>
                  <div class="relative">
                    <div class="text-xs text-orange-300 font-medium sm:text-sm">预测第三名</div>
                    <div class="mt-2 space-y-1">
                      <!-- 全部历史数据 -->
                      <div class="border-b border-orange-400/20 pb-2">
                        <div class="mb-1 text-xs text-orange-200/50">
                          最新{{ calculateRankBasedStats().rank3.total }}局
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-orange-400 font-bold sm:text-lg">
                            {{ (calculateRankBasedStats().rank3.breakevenRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-orange-200/70">保本率</span>
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-red-300 font-bold sm:text-lg">
                            {{ (calculateRankBasedStats().rank3.firstPlaceRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-red-200/70">第一名率</span>
                        </div>
                      </div>
                      <!-- 最新N局数据 -->
                      <div class="pt-1">
                        <div class="mb-1 text-xs text-cyan-300/70">最新{{ recentRoundsCount }}局</div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-cyan-400 font-bold">
                            {{ (calculateRecentRankBasedStats.rank3.breakevenRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-cyan-200/70">保本率</span>
                        </div>
                        <div class="flex items-center justify-between">
                          <span class="text-base text-teal-300 font-bold">
                            {{ (calculateRecentRankBasedStats.rank3.firstPlaceRate || 0).toFixed(1) }}%
                          </span>
                          <span class="text-xs text-teal-200/70">第一名率</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 前三名预测对比表格 -->
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

        <!-- 第三部分：历史数据表格 -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="📊 历史游戏数据 (最近50局)"
          size="large"
        >
          <template #header-extra>
            <n-button
              :loading="historyLoading"
              @click="refreshHistoryData"
              type="primary"
              size="small"
              class="self-end sm:self-auto"
            >
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
  import { ref, onMounted, computed, h, watch } from 'vue';
  import { NEmpty, type DataTableColumn } from 'naive-ui';
  import { Head } from '@inertiajs/vue3';
  import api from '@/utils/api';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';

  // 导入游戏预测store - 统一的数据管理
  import { useGamePredictionStore } from '@/stores/gamePrediction';

  // 注意：TokenAnalysis类型现在从store导入，不需要重复定义

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

  // 注意：预测相关接口现在从store导入，不需要重复定义

  // 注意：PredictionHistoryRound类型现在从store导入

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

  // 使用游戏预测store - 统一的数据管理，支持WebSocket实时更新
  const gamePredictionStore = useGamePredictionStore();

  // 从store中获取数据 - 直接使用store的响应式数据
  const analysisData = computed(() => gamePredictionStore.currentAnalysis);
  const analysisMeta = computed(() => gamePredictionStore.analysisMeta);
  const predictionHistoryData = computed(() => gamePredictionStore.predictionHistory);
  const analysisLoading = computed(() => gamePredictionStore.analysisLoading);
  const latestGameData = computed(() => gamePredictionStore.latestGameData);

  // 🆕 新增计算属性 - 使用store的增强功能
  const currentRoundId = computed(() => gamePredictionStore.currentRoundId);
  const currentGameStatus = computed(() => gamePredictionStore.currentGameStatus);
  const canBet = computed(() => gamePredictionStore.canBet);
  const isSettled = computed(() => gamePredictionStore.isSettled);
  const isSettling = computed(() => gamePredictionStore.isSettling);

  // 历史游戏数据仍然通过API获取（这部分数据更新频率较低）
  const historyData = ref<HistoryRound[]>([]);
  const historyLoading = ref(false);
  const predictionHistoryLoading = ref(false);

  // 最新N局分析的局数选择器
  const recentRoundsCount = ref(50);

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
  const getUnifiedCardClass = (index: number) => {
    if (index === 0)
      return 'border-yellow-400/30 bg-gradient-to-br from-yellow-500/10 to-amber-600/5 hover:border-yellow-400/50 hover:shadow-yellow-500/20';
    if (index === 1)
      return 'border-slate-400/30 bg-gradient-to-br from-slate-500/10 to-gray-600/5 hover:border-slate-400/50 hover:shadow-slate-500/20';
    if (index === 2)
      return 'border-orange-400/30 bg-gradient-to-br from-orange-500/10 to-red-600/5 hover:border-orange-400/50 hover:shadow-orange-500/20';
    if (index === 3)
      return 'border-blue-400/30 bg-gradient-to-br from-blue-500/10 to-indigo-600/5 hover:border-blue-400/50 hover:shadow-blue-500/20';
    return 'border-purple-400/30 bg-gradient-to-br from-purple-500/10 to-pink-600/5 hover:border-purple-400/50 hover:shadow-purple-500/20';
  };

  // 格式化价格变化百分比
  const formatPriceChange = (change: number | null) => {
    if (change === null || change === undefined) return { text: '-', color: 'text-gray-500' };

    const value = change.toFixed(2);
    if (change > 0) {
      return { text: `+${value}%`, color: 'text-green-400' };
    } else if (change < 0) {
      return { text: `${value}%`, color: 'text-red-400' };
    } else {
      return { text: '0.00%', color: 'text-gray-400' };
    }
  };

  const getScoreTextClass = (index: number) => {
    if (index === 0) return 'text-yellow-400';
    if (index === 1) return 'text-slate-400';
    if (index === 2) return 'text-orange-400';
    if (index === 3) return 'text-blue-400';
    return 'text-purple-400';
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

  // v8 注释：移除了价格变化和交易量格式化函数，专注于 H2H 战术分析数据
  // 如需要市场数据展示，可在未来版本重新加入

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

  // API调用函数 - 现在使用store的方法
  const fetchAnalysisData = async () => {
    // 使用store的方法获取预测分析数据（主要用于初始化，平时通过WebSocket更新）
    await gamePredictionStore.fetchCurrentAnalysis();
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
    // 使用store的方法获取预测历史数据
    await gamePredictionStore.fetchPredictionHistory();
  };

  // 刷新函数
  const refreshAnalysis = () => {
    // 手动触发预测分析数据刷新（备用方法，平时依赖WebSocket实时更新）
    console.log('📡 手动刷新预测分析数据（备用方法）');
    fetchAnalysisData();
  };
  const refreshHistoryData = () => fetchHistoryData();
  const refreshPredictionHistoryData = () => {
    // 手动触发预测历史数据刷新
    fetchPredictionHistoryData();
  };

  // WebSocket状态相关函数
  const getWebSocketStatusClass = () => {
    const status = gamePredictionStore.websocketStatus.status;
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
    const status = gamePredictionStore.websocketStatus.status;
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

  // 格式化时间函数
  const formatTime = (timeString: string | null | undefined) => {
    if (!timeString) return '无';
    try {
      const date = new Date(timeString);
      return date.toLocaleTimeString('zh-CN', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    } catch {
      return '无效';
    }
  };

  // 测试函数
  const testStoreUpdate = () => {
    console.log('🧪 测试Store更新开始');
    console.log('🧪 更新前 - currentAnalysis长度:', gamePredictionStore.currentAnalysis.length);

    // 模拟新数据 - 使用完整的TokenAnalysis类型
    const testData = [
      {
        symbol: 'TEST1',
        name: 'Test Token 1',
        predicted_rank: 1,
        prediction_score: 95,
        change_5m: null,
        change_1h: null,
        change_4h: null,
        change_24h: null,
        volume_24h: '0',
        market_cap: null,
        logo: null,
        win_rate: 50,
        top3_rate: 75,
        avg_rank: 2,
        total_games: 10,
        wins: 5,
        top3: 8
      },
      {
        symbol: 'TEST2',
        name: 'Test Token 2',
        predicted_rank: 2,
        prediction_score: 85,
        change_5m: null,
        change_1h: null,
        change_4h: null,
        change_24h: null,
        volume_24h: '0',
        market_cap: null,
        logo: null,
        win_rate: 40,
        top3_rate: 65,
        avg_rank: 2.5,
        total_games: 10,
        wins: 4,
        top3: 7
      },
      {
        symbol: 'TEST3',
        name: 'Test Token 3',
        predicted_rank: 3,
        prediction_score: 75,
        change_5m: null,
        change_1h: null,
        change_4h: null,
        change_24h: null,
        volume_24h: '0',
        market_cap: null,
        logo: null,
        win_rate: 30,
        top3_rate: 55,
        avg_rank: 3,
        total_games: 10,
        wins: 3,
        top3: 6
      }
    ];

    // 直接更新store数据
    gamePredictionStore.currentAnalysis.length = 0;
    gamePredictionStore.currentAnalysis.push(...testData);

    console.log('🧪 更新后 - currentAnalysis长度:', gamePredictionStore.currentAnalysis.length);
    console.log('🧪 更新后 - analysisData长度:', analysisData.value.length);

    // 2秒后还原
    setTimeout(() => {
      gamePredictionStore.fetchCurrentAnalysis();
      console.log('🧪 已还原数据');
    }, 2000);
  };

  const manualRefresh = () => {
    console.log('🔄 手动刷新所有数据');
    gamePredictionStore.fetchCurrentAnalysis();
    gamePredictionStore.fetchPredictionHistory();
    fetchHistoryData();
  };

  const testWebSocket = () => {
    console.log('🔍 WebSocket连接测试开始');
    console.log('🔍 Echo实例:', window.Echo);
    console.log('🔍 WebSocket状态:', gamePredictionStore.websocketStatus);

    if (window.Echo?.connector?.pusher) {
      const pusher = window.Echo.connector.pusher;
      console.log('🔍 Pusher连接状态:', pusher.connection.state);
      console.log('🔍 已订阅的频道:', Object.keys(pusher.channels.channels));

      // 检查predictions频道
      const predictionsChannel = pusher.channels.channels['predictions'];
      if (predictionsChannel) {
        console.log('🔍 predictions频道存在:', predictionsChannel);
        console.log('🔍 频道绑定的事件:', predictionsChannel.callbacks);

        // 手动触发一个测试监听器
        console.log('🔍 添加临时测试监听器...');
        predictionsChannel.bind('prediction.updated', (data: any) => {
          console.log('🔍 临时监听器收到数据:', data);
        });
      } else {
        console.log('❌ predictions频道不存在');
      }
    } else {
      console.log('❌ Echo或Pusher未初始化');
    }

    // 尝试重新初始化WebSocket
    console.log('🔍 尝试重新连接WebSocket...');
    gamePredictionStore.reconnectWebSocket();
  };

  // 模拟预测事件来测试事件处理
  const simulatePredictionEvent = () => {
    console.log('🎯 模拟预测事件开始');

    const testPredictionData = {
      success: true,
      data: [
        {
          symbol: 'SIM1',
          name: 'Simulated Token 1',
          predicted_rank: 1,
          prediction_score: 95.5,
          absolute_score: 90,
          relative_score: 85,
          risk_adjusted_score: 88,
          change_5m: 2.5,
          change_1h: 1.8,
          change_4h: -0.5,
          change_24h: 3.2,
          volume_24h: '1000000',
          market_cap: 50000000,
          logo: null,
          win_rate: 75,
          top3_rate: 85,
          avg_rank: 1.5,
          total_games: 20,
          wins: 15,
          top3: 17
        },
        {
          symbol: 'SIM2',
          name: 'Simulated Token 2',
          predicted_rank: 2,
          prediction_score: 82.3,
          absolute_score: 80,
          relative_score: 78,
          risk_adjusted_score: 81,
          change_5m: 1.2,
          change_1h: 0.8,
          change_4h: 1.5,
          change_24h: 2.1,
          volume_24h: '800000',
          market_cap: 30000000,
          logo: null,
          win_rate: 65,
          top3_rate: 75,
          avg_rank: 2.2,
          total_games: 20,
          wins: 13,
          top3: 15
        }
      ],
      meta: {
        round_id: `sim_test_${Date.now()}`,
        status: 'bet',
        updated_at: new Date().toISOString(),
        source: 'simulation'
      }
    };

    console.log('🎯 模拟数据:', testPredictionData);

    // 直接调用store的数据更新逻辑
    try {
      gamePredictionStore.currentAnalysis.length = 0;
      gamePredictionStore.currentAnalysis.push(...testPredictionData.data);
      gamePredictionStore.analysisMeta = testPredictionData.meta;

      console.log('✅ 模拟事件处理完成，当前数据长度:', gamePredictionStore.currentAnalysis.length);

      // 3秒后还原
      setTimeout(() => {
        gamePredictionStore.fetchCurrentAnalysis();
        console.log('🔄 已还原模拟数据');
      }, 3000);
    } catch (error) {
      console.error('❌ 模拟事件处理失败:', error);
    }
  };

  // 检查WebSocket绑定状态
  const checkWebSocketBinding = () => {
    console.log('🔧 检查WebSocket绑定状态');

    if (!window.Echo) {
      console.log('❌ Echo未初始化');
      return;
    }

    const pusher = window.Echo.connector?.pusher;
    if (!pusher) {
      console.log('❌ Pusher连接不存在');
      return;
    }

    console.log('🔧 Pusher连接状态:', pusher.connection.state);
    console.log('🔧 已订阅的频道:', Object.keys(pusher.channels.channels));

    // 检查predictions频道
    const predictionsChannel = pusher.channels.channels['predictions'];
    if (predictionsChannel) {
      console.log('✅ predictions频道存在');
      console.log('🔧 频道状态:', predictionsChannel.state);
      console.log('🔧 频道绑定的事件callbacks:', predictionsChannel.callbacks);

      // 检查是否有prediction.updated事件绑定
      if (predictionsChannel.callbacks['prediction.updated']) {
        console.log('✅ prediction.updated事件已绑定');
        console.log('🔧 绑定的回调函数数量:', predictionsChannel.callbacks['prediction.updated'].length);
      } else {
        console.log('❌ prediction.updated事件未绑定');
      }

      // 手动触发一个测试事件
      console.log('🔧 手动触发测试事件...');
      predictionsChannel.trigger('client-test', { message: 'test from client' });
    } else {
      console.log('❌ predictions频道不存在');
    }

    // 检查game-updates频道
    const gameUpdatesChannel = pusher.channels.channels['game-updates'];
    if (gameUpdatesChannel) {
      console.log('✅ game-updates频道存在');
      console.log('🔧 game-updates频道状态:', gameUpdatesChannel.state);
    } else {
      console.log('❌ game-updates频道不存在');
    }
  };

  // 直接测试事件监听器
  const testDirectEvent = () => {
    console.log('🔬 直接测试事件监听器');

    if (!window.Echo?.connector?.pusher) {
      console.log('❌ Pusher连接不存在');
      return;
    }

    const predictionsChannel = window.Echo.connector.pusher.channels.channels['predictions'];
    if (!predictionsChannel) {
      console.log('❌ predictions频道不存在');
      return;
    }

    // 创建测试数据
    const testEventData = {
      success: true,
      data: [
        {
          symbol: 'TEST',
          name: 'Test Token',
          predicted_rank: 1,
          prediction_score: 88.8,
          absolute_score: 85,
          relative_score: 80,
          risk_adjusted_score: 83,
          change_5m: 1.5,
          change_1h: 2.3,
          change_4h: -0.8,
          change_24h: 4.2,
          volume_24h: '500000',
          market_cap: 25000000,
          logo: null,
          win_rate: 70,
          top3_rate: 80,
          avg_rank: 1.8,
          total_games: 10,
          wins: 7,
          top3: 8
        }
      ],
      meta: {
        round_id: `direct_test_${Date.now()}`,
        status: 'test',
        updated_at: new Date().toISOString(),
        source: 'direct_test'
      }
    };

    console.log('🔬 准备发送测试数据:', testEventData);

    // 手动触发prediction.updated事件
    try {
      predictionsChannel.emit('prediction.updated', testEventData);
      console.log('✅ 已手动触发prediction.updated事件');
    } catch (error) {
      console.error('❌ 手动触发事件失败:', error);
    }

    // 也试试直接调用所有绑定的回调函数
    if (predictionsChannel.callbacks['prediction.updated']) {
      console.log('🔬 直接调用绑定的回调函数...');
      predictionsChannel.callbacks['prediction.updated'].forEach((callback: any, index: number) => {
        try {
          console.log(`🔬 调用回调函数 ${index + 1}...`);
          callback(testEventData);
          console.log(`✅ 回调函数 ${index + 1} 执行成功`);
        } catch (error) {
          console.error(`❌ 回调函数 ${index + 1} 执行失败:`, error);
        }
      });
    } else {
      console.log('❌ 没有找到prediction.updated的回调函数');
    }

    console.log('🔬 直接事件测试完成');
  };

  // 初始化数据
  onMounted(async () => {
    console.log('📊 Dashboard页面正在初始化...');

    // 确保store已经初始化（如果还没有初始化的话）
    if (!gamePredictionStore.isConnected) {
      console.log('🔄 Store未连接，开始初始化...');
      await gamePredictionStore.initialize();
    }

    // 获取历史游戏数据（更新频率较低，继续使用API）
    fetchHistoryData();

    // 设置历史数据的定时刷新（10秒间隔）- 历史数据更新频率较低，继续使用轮询
    setInterval(() => {
      fetchHistoryData();
    }, 10000);

    console.log('📊 Dashboard页面已加载，使用WebSocket实时数据 + 历史数据轮询模式');

    // 添加调试信息
    console.log('🐛 Dashboard初始化时的状态:');
    console.log('🐛 - 当前分析数据数量:', analysisData.value.length);
    console.log('🐛 - 分析数据内容:', analysisData.value);
    console.log('🐛 - WebSocket状态:', gamePredictionStore.websocketStatus);
    console.log('🐛 - 是否已连接:', gamePredictionStore.isConnected);
    console.log('🐛 - Store currentAnalysis长度:', gamePredictionStore.currentAnalysis.length);
    console.log('🐛 - Store currentAnalysis内容:', gamePredictionStore.currentAnalysis);

    // 监听store状态变化
    watch(
      () => gamePredictionStore.currentAnalysis.length,
      (newLength: number, oldLength: number) => {
        console.log('🔥 Store currentAnalysis数量变化:', oldLength, '->', newLength);
        console.log('🔥 新的分析数据:', gamePredictionStore.currentAnalysis);
      }
    );

    watch(
      () => gamePredictionStore.websocketStatus.status,
      (newStatus: string, oldStatus: string) => {
        console.log('🔥 WebSocket状态变化:', oldStatus, '->', newStatus);
      }
    );

    // 定期输出调试信息
    setInterval(() => {
      console.log(
        '🐛 定期检查 - 分析数据数量:',
        analysisData.value.length,
        '/ Store数量:',
        gamePredictionStore.currentAnalysis.length,
        'WebSocket状态:',
        gamePredictionStore.websocketStatus.status,
        '最后连接时间:',
        gamePredictionStore.websocketStatus.lastConnectedAt
      );
    }, 5000);
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

  // 获取预测总局数统计
  const calculatePortfolioStats = () => {
    return {
      totalRounds: predictionHistoryData.value.length
    };
  };

  // 修改：此函数现在专门用于计算基于「单次预测」的精准率
  const calculateRoundBasedStats = () => {
    if (predictionHistoryData.value.length === 0) {
      return { exactRate: 0 };
    }

    let exactPredictions = 0;
    let totalPredictions = 0;

    predictionHistoryData.value.forEach((round) => {
      const top3Predictions = round.predictions.filter((p) => p.predicted_rank <= 3);

      top3Predictions.forEach((prediction) => {
        const actualResult = round.results.find((r) => r.symbol === prediction.symbol);
        if (actualResult) {
          totalPredictions++;
          const analysis = getTokenPredictionAnalysis(prediction.predicted_rank, actualResult.actual_rank);

          if (analysis.status === 'exact') {
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

            // 计算第一名率：实际排名是第一名的情况
            if (actualResult.actual_rank === 1) {
              rankStats[key].firstPlace++;
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

    // 获取最新N局数据（按轮次ID倒序排列后取前N个）
    const recentRounds = predictionHistoryData.value
      .slice()
      .sort((a, b) => b.round_id.localeCompare(a.round_id))
      .slice(0, recentRoundsCount.value);

    recentRounds.forEach((round) => {
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

            // 计算第一名率：实际排名是第一名的情况
            if (actualResult.actual_rank === 1) {
              rankStats[key].firstPlace++;
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
        stats.firstPlaceRate = (stats.firstPlace / stats.total) * 100;
      }
    });

    return rankStats;
  });
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
