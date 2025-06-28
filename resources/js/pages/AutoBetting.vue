<template>
  <DefaultLayout>
    <Head title="自动下注控制" />

    <div class="min-h-screen from-slate-900 via-purple-900 to-slate-900 bg-gradient-to-br p-3 sm:p-6">
      <div class="mx-auto max-w-7xl">
        <!-- 导航栏 -->
        <div class="mb-6 flex items-center justify-between">
          <a
            href="/"
            class="flex items-center rounded-lg bg-slate-600 px-4 py-2 text-white transition-colors duration-200 space-x-2 hover:bg-slate-700"
          >
            <span>📊</span>
            <span>返回数据面板</span>
          </a>
          <div class="flex-1 text-center">
            <h1 class="text-3xl text-white font-bold">🤖 自动下注控制中心</h1>
            <p class="text-gray-300">基于数据驱动的智能下注系统</p>
          </div>
          <div class="w-32"></div>
          <!-- 占位符保持标题居中 -->
        </div>

        <!-- 自动下注控制面板 -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="🎯 自动下注控制面板"
          size="large"
        >
          <template #header-extra>
            <div class="flex items-center space-x-3">
              <div
                v-if="autoBettingStatus.is_running"
                class="flex items-center border border-green-500/30 rounded-full bg-green-500/20 px-3 py-1 space-x-2"
              >
                <div class="h-2 w-2 animate-pulse rounded-full bg-green-400"></div>
                <span class="text-sm text-green-400 font-medium">运行中</span>
              </div>
              <div
                v-else
                class="flex items-center border border-gray-500/30 rounded-full bg-gray-500/20 px-3 py-1 space-x-2"
              >
                <div class="h-2 w-2 rounded-full bg-gray-400"></div>
                <span class="text-sm text-gray-400 font-medium">已停止</span>
              </div>
            </div>
          </template>

          <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- 基础配置 -->
            <div class="space-y-4">
              <h3 class="mb-4 text-lg text-white font-semibold">📊 基础配置</h3>

              <!-- JWT Token -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">JWT Token *</label>
                <div class="flex space-x-2">
                  <n-input
                    v-model:value="config.jwt_token"
                    placeholder="请输入您的JWT Token"
                    type="password"
                    show-password-on="click"
                    :disabled="autoBettingStatus.is_running"
                    class="flex-1"
                  />
                  <n-button
                    @click="testJwtConnection"
                    :loading="connectionTesting"
                    :disabled="!config.jwt_token || autoBettingStatus.is_running"
                    type="primary"
                    size="medium"
                  >
                    测试连接
                  </n-button>
                </div>
                <div
                  v-if="connectionResult"
                  class="text-xs"
                  :class="connectionResult.success ? 'text-green-400' : 'text-red-400'"
                >
                  {{ connectionResult.message }}
                </div>
              </div>

              <!-- 资金池 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">资金池 ($)</label>
                <n-input-number
                  v-model:value="config.bankroll"
                  :min="1"
                  :max="1000000"
                  :disabled="autoBettingStatus.is_running"
                  class="w-full"
                >
                  <template #prefix>$</template>
                </n-input-number>
              </div>

              <!-- 单位大小百分比 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">单位大小 (% of 资金池)</label>
                <n-slider
                  v-model:value="config.unit_size_percentage"
                  :min="0.1"
                  :max="10"
                  :step="0.1"
                  :disabled="autoBettingStatus.is_running"
                  :tooltip="true"
                />
                <div class="text-xs text-gray-400">
                  当前单位大小: ${{ ((config.bankroll * config.unit_size_percentage) / 100).toFixed(2) }}
                </div>
              </div>

              <!-- 每日停损 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">每日停损 (% of 资金池)</label>
                <n-slider
                  v-model:value="config.daily_stop_loss_percentage"
                  :min="1"
                  :max="50"
                  :step="1"
                  :disabled="autoBettingStatus.is_running"
                  :tooltip="true"
                />
                <div class="text-xs text-gray-400">
                  停损金额: ${{ ((config.bankroll * config.daily_stop_loss_percentage) / 100).toFixed(2) }}
                </div>
              </div>
            </div>

            <!-- 策略配置 -->
            <div class="space-y-4">
              <h3 class="mb-4 text-lg text-white font-semibold">🎲 策略配置</h3>

              <!-- 信赖度阈值 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">信赖度阈值 (%)</label>
                <n-slider
                  v-model:value="config.confidence_threshold"
                  :min="50"
                  :max="100"
                  :step="1"
                  :disabled="autoBettingStatus.is_running"
                  :tooltip="true"
                />
              </div>

              <!-- 分数差距阈值 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">分数差距阈值</label>
                <n-slider
                  v-model:value="config.score_gap_threshold"
                  :min="0.1"
                  :max="20"
                  :step="0.1"
                  :disabled="autoBettingStatus.is_running"
                  :tooltip="true"
                />
              </div>

              <!-- 最小历史局数 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">最小历史局数</label>
                <n-input-number
                  v-model:value="config.min_total_games"
                  :min="1"
                  :max="1000"
                  :disabled="autoBettingStatus.is_running"
                  class="w-full"
                />
              </div>

              <!-- 策略选择 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">下注策略</label>
                <n-radio-group
                  v-model:value="config.strategy"
                  :disabled="autoBettingStatus.is_running"
                  class="flex flex-col space-y-2"
                >
                  <n-radio value="single_bet" class="text-white">
                    <span class="text-white">单点突破 - 只下注预测第一名</span>
                  </n-radio>
                  <n-radio value="portfolio_hedging" class="text-white">
                    <span class="text-white">保本对冲组合 - 分散下注前三名</span>
                  </n-radio>
                </n-radio-group>
              </div>

              <!-- 投资组合分配 (仅在对冲策略时显示) -->
              <div v-if="config.strategy === 'portfolio_hedging'" class="space-y-3">
                <label class="text-sm text-gray-300 font-medium">投资组合分配 (%)</label>
                <div class="space-y-2">
                  <div class="flex items-center space-x-3">
                    <span class="w-12 text-xs text-yellow-400">第1名:</span>
                    <n-slider
                      v-model:value="config.portfolio_allocation.rank1"
                      :min="0"
                      :max="100"
                      :step="1"
                      :disabled="autoBettingStatus.is_running"
                      class="flex-1"
                    />
                    <span class="w-12 text-xs text-gray-400">{{ config.portfolio_allocation.rank1 }}%</span>
                  </div>
                  <div class="flex items-center space-x-3">
                    <span class="w-12 text-xs text-slate-400">第2名:</span>
                    <n-slider
                      v-model:value="config.portfolio_allocation.rank2"
                      :min="0"
                      :max="100"
                      :step="1"
                      :disabled="autoBettingStatus.is_running"
                      class="flex-1"
                    />
                    <span class="w-12 text-xs text-gray-400">{{ config.portfolio_allocation.rank2 }}%</span>
                  </div>
                  <div class="flex items-center space-x-3">
                    <span class="w-12 text-xs text-orange-400">第3名:</span>
                    <n-slider
                      v-model:value="config.portfolio_allocation.rank3"
                      :min="0"
                      :max="100"
                      :step="1"
                      :disabled="autoBettingStatus.is_running"
                      class="flex-1"
                    />
                    <span class="w-12 text-xs text-gray-400">{{ config.portfolio_allocation.rank3 }}%</span>
                  </div>
                  <div class="text-xs text-gray-400">
                    总计:
                    {{
                      config.portfolio_allocation.rank1 +
                      config.portfolio_allocation.rank2 +
                      config.portfolio_allocation.rank3
                    }}%
                    <span
                      v-if="
                        Math.abs(
                          config.portfolio_allocation.rank1 +
                            config.portfolio_allocation.rank2 +
                            config.portfolio_allocation.rank3 -
                            100
                        ) > 0.1
                      "
                      class="ml-2 text-red-400"
                    >
                      (必须为100%)
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 控制按钮 -->
          <div class="mt-6 flex flex-wrap justify-center gap-3">
            <n-button
              @click="saveConfig"
              :loading="configSaving"
              :disabled="autoBettingStatus.is_running"
              type="primary"
              size="large"
            >
              💾 保存配置
            </n-button>
            <n-button @click="simulateBetting" :loading="simulateLoading" type="warning" size="large">
              🎮 模拟下注
            </n-button>
            <n-button
              v-if="!autoBettingStatus.is_running"
              @click="startAutoBetting"
              :loading="toggleLoading"
              :disabled="!config.jwt_token"
              type="success"
              size="large"
            >
              🚀 启动自动下注
            </n-button>
            <n-button v-else @click="stopAutoBetting" :loading="toggleLoading" type="error" size="large">
              🛑 停止自动下注
            </n-button>
          </div>
        </NCard>

        <!-- 当前分析数据展示 (复用Dashboard的逻辑) -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="📊 当前预测分析"
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

          <div v-if="analysisData.length > 0" class="space-y-4">
            <!-- 触发条件检查 -->
            <div v-if="simulationResult" class="mb-4 border border-blue-500/30 rounded-lg bg-blue-500/10 p-4">
              <h4 class="mb-3 text-lg text-blue-400 font-semibold">🎯 下注条件检查</h4>
              <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="flex items-center justify-between rounded-lg bg-white/5 p-3">
                  <span class="text-sm text-gray-300">信赖度</span>
                  <div class="flex items-center space-x-2">
                    <span
                      class="text-sm font-medium"
                      :class="simulationResult.trigger_details.confidence?.met ? 'text-green-400' : 'text-red-400'"
                    >
                      {{ (simulationResult.trigger_details.confidence?.value || 0).toFixed(1) }}%
                    </span>
                    <span class="text-xs text-gray-500">
                      ≥ {{ simulationResult.trigger_details.confidence?.threshold }}%
                    </span>
                    <span class="text-lg">{{ simulationResult.trigger_details.confidence?.met ? '✅' : '❌' }}</span>
                  </div>
                </div>
                <div class="flex items-center justify-between rounded-lg bg-white/5 p-3">
                  <span class="text-sm text-gray-300">分数差距</span>
                  <div class="flex items-center space-x-2">
                    <span
                      class="text-sm font-medium"
                      :class="simulationResult.trigger_details.score_gap?.met ? 'text-green-400' : 'text-red-400'"
                    >
                      {{ (simulationResult.trigger_details.score_gap?.value || 0).toFixed(1) }}
                    </span>
                    <span class="text-xs text-gray-500">
                      ≥ {{ simulationResult.trigger_details.score_gap?.threshold }}
                    </span>
                    <span class="text-lg">{{ simulationResult.trigger_details.score_gap?.met ? '✅' : '❌' }}</span>
                  </div>
                </div>
                <div class="flex items-center justify-between rounded-lg bg-white/5 p-3">
                  <span class="text-sm text-gray-300">历史局数</span>
                  <div class="flex items-center space-x-2">
                    <span
                      class="text-sm font-medium"
                      :class="simulationResult.trigger_details.total_games?.met ? 'text-green-400' : 'text-red-400'"
                    >
                      {{ simulationResult.trigger_details.total_games?.value || 0 }}
                    </span>
                    <span class="text-xs text-gray-500">
                      ≥ {{ simulationResult.trigger_details.total_games?.threshold }}
                    </span>
                    <span class="text-lg">{{ simulationResult.trigger_details.total_games?.met ? '✅' : '❌' }}</span>
                  </div>
                </div>
              </div>

              <!-- 推荐下注 -->
              <div
                v-if="simulationResult.recommended_bets && simulationResult.recommended_bets.length > 0"
                class="mt-4"
              >
                <h5 class="mb-2 text-sm text-green-400 font-semibold">💰 推荐下注方案</h5>
                <div class="grid grid-cols-1 gap-2 lg:grid-cols-3 sm:grid-cols-2">
                  <div
                    v-for="bet in simulationResult.recommended_bets"
                    :key="bet.symbol"
                    class="flex items-center justify-between border border-green-500/20 rounded-lg bg-green-500/10 p-3"
                  >
                    <div>
                      <span class="text-sm text-white font-medium">{{ bet.symbol }}</span>
                      <div class="text-xs text-gray-400">预测 #{{ bet.predicted_rank }}</div>
                    </div>
                    <div class="text-right">
                      <div class="text-sm text-green-400 font-bold">${{ bet.bet_amount }}</div>
                      <div class="text-xs text-gray-400">{{ bet.confidence.toFixed(1) }}%</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 横向名次預測展示 (复用Dashboard组件) -->
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

                <!-- 关键数据参数 -->
                <div class="text-xs space-y-1">
                  <div class="flex justify-between">
                    <span class="text-gray-400">保本率:</span>
                    <span class="text-green-400 font-bold">{{ (token.top3_rate || 0).toFixed(1) }}%</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-400">总局数:</span>
                    <span class="text-purple-400 font-bold">{{ token.total_games || 0 }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-400">稳定性:</span>
                    <span class="text-yellow-400 font-bold">
                      <span v-if="token.value_stddev !== undefined">{{ (token.value_stddev || 0).toFixed(3) }}</span>
                      <span v-else class="text-gray-500">-</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <NEmpty v-else description="暂无分析数据" class="py-8" />
        </NCard>

        <!-- 系统状态监控 -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="📈 系统状态监控"
          size="large"
        >
          <div class="grid grid-cols-1 gap-4 lg:grid-cols-4 md:grid-cols-2">
            <!-- 运行状态 -->
            <div class="border border-white/10 rounded-lg bg-white/5 p-4">
              <div class="mb-2 flex items-center justify-between">
                <span class="text-sm text-gray-300">运行状态</span>
                <div class="text-xl">{{ autoBettingStatus.is_running ? '🟢' : '🔴' }}</div>
              </div>
              <div class="text-lg font-bold" :class="autoBettingStatus.is_running ? 'text-green-400' : 'text-gray-400'">
                {{ autoBettingStatus.is_running ? '运行中' : '已停止' }}
              </div>
            </div>

            <!-- 总下注次数 -->
            <div class="border border-white/10 rounded-lg bg-white/5 p-4">
              <div class="mb-2 flex items-center justify-between">
                <span class="text-sm text-gray-300">总下注次数</span>
                <div class="text-xl">🎲</div>
              </div>
              <div class="text-lg text-blue-400 font-bold">{{ autoBettingStatus.total_bets || 0 }}</div>
            </div>

            <!-- 总盈亏 -->
            <div class="border border-white/10 rounded-lg bg-white/5 p-4">
              <div class="mb-2 flex items-center justify-between">
                <span class="text-sm text-gray-300">总盈亏</span>
                <div class="text-xl">💰</div>
              </div>
              <div
                class="text-lg font-bold"
                :class="(autoBettingStatus.total_profit_loss || 0) >= 0 ? 'text-green-400' : 'text-red-400'"
              >
                ${{ (autoBettingStatus.total_profit_loss || 0).toFixed(2) }}
              </div>
            </div>

            <!-- 今日盈亏 -->
            <div class="border border-white/10 rounded-lg bg-white/5 p-4">
              <div class="mb-2 flex items-center justify-between">
                <span class="text-sm text-gray-300">今日盈亏</span>
                <div class="text-xl">📊</div>
              </div>
              <div
                class="text-lg font-bold"
                :class="(autoBettingStatus.today_profit_loss || 0) >= 0 ? 'text-green-400' : 'text-red-400'"
              >
                ${{ (autoBettingStatus.today_profit_loss || 0).toFixed(2) }}
              </div>
            </div>
          </div>

          <!-- 错误信息 -->
          <div v-if="autoBettingStatus.last_error" class="mt-4 border border-red-500/30 rounded-lg bg-red-500/10 p-3">
            <div class="text-sm text-red-400">
              <strong>最新错误:</strong>
              {{ autoBettingStatus.last_error }}
            </div>
          </div>
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

  // 延迟获取message实例，避免在providers还未准备好时调用
  const getMessageInstance = () => {
    try {
      return useMessage();
    } catch {
      console.warn('Message provider not ready yet');
      return null;
    }
  };

  // 自动下注配置
  const config = ref({
    enabled: false,
    jwt_token: '',
    bankroll: 1000,
    unit_size_percentage: 1.5,
    daily_stop_loss_percentage: 15,
    confidence_threshold: 88,
    score_gap_threshold: 6.0,
    min_total_games: 25,
    strategy: 'portfolio_hedging' as 'single_bet' | 'portfolio_hedging',
    portfolio_allocation: {
      rank1: 50,
      rank2: 30,
      rank3: 20
    }
  });

  // 自动下注状态
  const autoBettingStatus = ref({
    is_running: false,
    current_round_id: null,
    last_bet_at: null,
    total_bets: 0,
    total_profit_loss: 0,
    today_profit_loss: 0,
    consecutive_losses: 0,
    last_error: null
  });

  // 当前分析数据 (复用Dashboard的接口类型)
  interface TokenAnalysis {
    symbol: string;
    name: string;
    change_5m: number | null;
    change_1h: number | null;
    change_4h: number | null;
    change_24h: number | null;
    volume_24h: string;
    market_cap: number | null;
    logo: string | null;
    absolute_score?: number;
    relative_score?: number;
    h2h_score?: number;
    risk_adjusted_score?: number;
    predicted_final_value?: number;
    rank_confidence?: number;
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
    value_stddev?: number;
    recent_avg_value?: number;
    avg_value?: number;
  }

  const analysisData = ref<TokenAnalysis[]>([]);
  const analysisMeta = ref<any>(null);

  // 加载状态
  const configLoading = ref(false);
  const configSaving = ref(false);
  const statusLoading = ref(false);
  const toggleLoading = ref(false);
  const connectionTesting = ref(false);
  const analysisLoading = ref(false);
  const simulateLoading = ref(false);

  // 连接测试结果
  const connectionResult = ref<{ success: boolean; message: string } | null>(null);

  // 模拟结果
  const simulationResult = ref<any>(null);

  // 工具函数 (复用Dashboard的函数)
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

  // API调用函数
  const loadConfig = async () => {
    configLoading.value = true;
    try {
      const response = await api.get('/auto-betting/config');
      if (response.data.success) {
        config.value = response.data.data;
      } else {
        getMessageInstance()?.error(response.data.message || '加载配置失败');
      }
    } catch (error) {
      console.error('加载配置失败:', error);
      getMessageInstance()?.error('加载配置失败');
    } finally {
      configLoading.value = false;
    }
  };

  const saveConfig = async () => {
    configSaving.value = true;
    try {
      const response = await api.post('/auto-betting/config', config.value);
      if (response.data.success) {
        getMessageInstance()?.success('配置已保存');
      } else {
        getMessageInstance()?.error(response.data.message || '保存配置失败');
      }
    } catch (error) {
      console.error('保存配置失败:', error);
      getMessageInstance()?.error('保存配置失败');
    } finally {
      configSaving.value = false;
    }
  };

  const loadStatus = async () => {
    statusLoading.value = true;
    try {
      const response = await api.get('/auto-betting/status');
      if (response.data.success) {
        autoBettingStatus.value = response.data.data;
      } else {
        getMessageInstance()?.error(response.data.message || '加载状态失败');
      }
    } catch (error) {
      console.error('加载状态失败:', error);
    } finally {
      statusLoading.value = false;
    }
  };

  const startAutoBetting = async () => {
    toggleLoading.value = true;
    try {
      const response = await api.post('/auto-betting/toggle', { action: 'start' });
      if (response.data.success) {
        getMessageInstance()?.success('自动下注已启动');
        await loadStatus();
      } else {
        getMessageInstance()?.error(response.data.message || '启动失败');
      }
    } catch (error) {
      console.error('启动失败:', error);
      getMessageInstance()?.error('启动失败');
    } finally {
      toggleLoading.value = false;
    }
  };

  const stopAutoBetting = async () => {
    toggleLoading.value = true;
    try {
      const response = await api.post('/auto-betting/toggle', { action: 'stop' });
      if (response.data.success) {
        getMessageInstance()?.success('自动下注已停止');
        await loadStatus();
      } else {
        getMessageInstance()?.error(response.data.message || '停止失败');
      }
    } catch (error) {
      console.error('停止失败:', error);
      getMessageInstance()?.error('停止失败');
    } finally {
      toggleLoading.value = false;
    }
  };

  const testJwtConnection = async () => {
    connectionTesting.value = true;
    connectionResult.value = null;
    try {
      const response = await api.post('/auto-betting/test-connection', {
        jwt_token: config.value.jwt_token
      });
      connectionResult.value = {
        success: response.data.success,
        message: response.data.message
      };
      if (response.data.success) {
        getMessageInstance()?.success('JWT Token连接测试成功');
      } else {
        getMessageInstance()?.error(response.data.message || 'JWT Token连接测试失败');
      }
    } catch (error) {
      console.error('连接测试失败:', error);
      connectionResult.value = {
        success: false,
        message: '连接测试失败'
      };
      getMessageInstance()?.error('连接测试失败');
    } finally {
      connectionTesting.value = false;
    }
  };

  const fetchAnalysisData = async () => {
    analysisLoading.value = true;
    try {
      const response = await api.get('/game/current-analysis');
      if (response.data.success) {
        analysisData.value = response.data.data;
        analysisMeta.value = response.data.meta || null;
      } else {
        getMessageInstance()?.error(response.data.message || '获取分析数据失败');
      }
    } catch (error) {
      console.error('获取分析数据失败:', error);
      getMessageInstance()?.error('获取分析数据失败');
    } finally {
      analysisLoading.value = false;
    }
  };

  const simulateBetting = async () => {
    simulateLoading.value = true;
    try {
      const response = await api.post('/auto-betting/simulate');
      if (response.data.success) {
        simulationResult.value = response.data.data;
        if (simulationResult.value.trigger_met) {
          getMessageInstance()?.success('触发条件满足，可以进行下注');
        } else {
          getMessageInstance()?.warning('当前条件不满足下注要求');
        }
      } else {
        getMessageInstance()?.error(response.data.message || '模拟下注失败');
      }
    } catch (error) {
      console.error('模拟下注失败:', error);
      getMessageInstance()?.error('模拟下注失败');
    } finally {
      simulateLoading.value = false;
    }
  };

  const refreshAnalysis = () => fetchAnalysisData();

  // 初始化
  onMounted(() => {
    loadConfig();
    loadStatus();
    fetchAnalysisData();

    // 定时刷新状态和分析数据
    setInterval(() => {
      loadStatus();
      fetchAnalysisData();
    }, 5000);
  });
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
