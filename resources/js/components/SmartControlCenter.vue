<template>
  <div class="space-y-6">
    <!-- 🔮 AI预测排名面板 (使用组件) -->
    <AIPredictionRanking
      :current-analysis="currentAnalysis"
      :analysis-meta="analysisMeta"
      :current-round-id="currentRoundId"
      :current-game-status="currentGameStatus"
      :current-game-tokens-with-ranks="currentGameTokensWithRanks"
      :analysis-loading="analysisLoading"
      @refresh-analysis="refreshAnalysis"
    />

    <!-- 🤖 自动下注状态面板 (整合自页面) -->
    <NCard
      class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
      title="🤖 自动下注状态"
      size="large"
    >
      <template #header-extra>
        <div class="flex items-center space-x-3">
          <n-button @click="setVeryLowThresholds" type="warning" size="small">🚨 紧急降低门槛</n-button>
          <n-button
            v-if="strategyValidation?.matches.length"
            :loading="executeLoading"
            @click="executeStrategyBetting"
            type="primary"
            size="small"
          >
            ⚡ 执行策略下注
          </n-button>
        </div>
      </template>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 md:grid-cols-3 sm:grid-cols-2">
        <!-- 用户余额 -->
        <div
          class="prediction-stat-card border-blue-500/30 from-blue-500/10 to-indigo-600/5 bg-gradient-to-br hover:border-blue-400/50 hover:shadow-blue-500/20"
        >
          <div class="stat-icon">👤</div>
          <div class="stat-content">
            <div class="stat-label text-blue-300">用户余额</div>
            <div class="stat-value text-blue-400">${{ (strategyValidation?.actual_balance || 0).toFixed(2) }}</div>
            <div class="stat-desc text-blue-200/70">可用于下注</div>
          </div>
        </div>

        <!-- 策略匹配 -->
        <div
          class="prediction-stat-card border-purple-500/30 from-purple-500/10 to-indigo-600/5 bg-gradient-to-br hover:border-purple-400/50 hover:shadow-purple-500/20"
        >
          <div class="stat-icon">🎯</div>
          <div class="stat-content">
            <div class="stat-label text-purple-300">策略匹配</div>
            <div class="stat-value text-purple-400">
              {{ strategyValidation?.total_matched || 0 }}
            </div>
            <div class="stat-desc text-purple-200/70">符合条件的Token</div>
          </div>
        </div>

        <!-- 下注金额 -->
        <div
          class="prediction-stat-card border-cyan-500/30 from-cyan-500/10 to-blue-600/5 bg-gradient-to-br hover:border-cyan-400/50 hover:shadow-cyan-500/20"
        >
          <div class="stat-icon">💰</div>
          <div class="stat-content">
            <div class="stat-label text-cyan-300">所需金额</div>
            <div class="stat-value text-cyan-400">${{ (strategyValidation?.required_balance || 0).toFixed(0) }}</div>
            <div class="stat-desc text-cyan-200/70">总下注金额</div>
          </div>
        </div>

        <!-- 余额状态 -->
        <div
          class="prediction-stat-card hover:shadow-lg"
          :class="
            (strategyValidation?.balance_sufficient ?? true)
              ? 'border-green-500/30 from-green-500/10 to-emerald-600/5 bg-gradient-to-br hover:border-green-400/50 hover:shadow-green-500/20'
              : 'border-red-500/30 from-red-500/10 to-pink-600/5 bg-gradient-to-br hover:border-red-400/50 hover:shadow-red-500/20'
          "
        >
          <div class="stat-icon">{{ (strategyValidation?.balance_sufficient ?? true) ? '✅' : '❌' }}</div>
          <div class="stat-content">
            <div
              class="stat-label"
              :class="(strategyValidation?.balance_sufficient ?? true) ? 'text-green-300' : 'text-red-300'"
            >
              余额状态
            </div>
            <div
              class="stat-value"
              :class="(strategyValidation?.balance_sufficient ?? true) ? 'text-green-400' : 'text-red-400'"
            >
              {{ (strategyValidation?.balance_sufficient ?? true) ? '充足' : '不足' }}
            </div>
            <div
              class="stat-desc"
              :class="(strategyValidation?.balance_sufficient ?? true) ? 'text-green-200/70' : 'text-red-200/70'"
            >
              实际余额: ${{ (strategyValidation?.actual_balance || 0).toFixed(0) }}
            </div>
          </div>
        </div>

        <!-- 总下注次数 -->
        <div
          class="prediction-stat-card border-amber-500/30 from-amber-500/10 to-yellow-600/5 bg-gradient-to-br hover:border-amber-400/50 hover:shadow-amber-500/20"
        >
          <div class="stat-icon">📊</div>
          <div class="stat-content">
            <div class="stat-label text-amber-300">总下注次数</div>
            <div class="stat-value text-amber-400">{{ autoBettingStatus.total_bets || 0 }}</div>
            <div class="stat-desc text-amber-200/70">历史累计</div>
          </div>
        </div>
      </div>

      <!-- Token详细匹配分析 -->
      <div v-if="currentAnalysis && currentAnalysis.length > 0" class="mt-6 space-y-4">
        <!-- 匹配概览 -->
        <div class="flex items-center justify-between">
          <h3 class="text-white font-medium">Token匹配分析 (共{{ currentAnalysis.length }}个)</h3>
          <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-400">符合条件:</span>
            <span class="font-bold" :class="strategyValidation?.matches.length ? 'text-green-400' : 'text-red-400'">
              {{ strategyValidation?.matches.length || 0 }}个
            </span>
          </div>
        </div>

        <!-- Token分析网格 -->
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-5 md:grid-cols-3 xl:grid-cols-5">
          <div
            v-for="(token, index) in currentAnalysis.slice(0, 5)"
            :key="`analysis-${index}-${token.symbol}`"
            class="border rounded-lg p-3 transition-all duration-200 hover:shadow-lg"
            :class="getTokenDebugClass(token)"
          >
            <!-- Token头部 -->
            <div class="mb-3 text-center">
              <div class="text-2xl mb-1">{{ getPredictionIcon(index) }}</div>
              <div class="text-white font-bold text-lg">{{ token.symbol }}</div>
              <div class="text-xs text-gray-400">#{{ token.predicted_rank || index + 1 }}</div>
              <div class="mt-2">
                <div class="text-xs font-bold" :class="isTokenMatching(token) ? 'text-green-400' : 'text-red-400'">
                  {{ isTokenMatching(token) ? '✅ 匹配' : '❌ 不匹配' }}
                </div>
                <div v-if="isTokenMatching(token)" class="text-xs text-green-400 font-bold mt-1">
                  下注 ${{ config.bet_amount }}
                </div>
              </div>
            </div>

            <!-- 核心指标 -->
            <div class="text-xs space-y-2">
              <div class="flex justify-between">
                <span class="text-gray-400">置信度:</span>
                <span :class="getMetricClass(getTokenConfidence(token), confidenceThreshold, 'gte')">
                  {{ getTokenConfidence(token).toFixed(1) }}%
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">分数:</span>
                <span :class="getMetricClass(getTokenScore(token), config.score_gap_threshold, 'gte')">
                  {{ getTokenScore(token).toFixed(1) }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">样本数:</span>
                <span :class="getMetricClass(getTokenSampleCount(token), config.min_sample_count, 'gte')">
                  {{ getTokenSampleCount(token) }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">历史准确率:</span>
                <span
                  :class="
                    getMetricClass(getTokenHistoricalAccuracy(token), config.historical_accuracy_threshold, 'gte')
                  "
                >
                  {{ (getTokenHistoricalAccuracy(token) * 100).toFixed(1) }}%
                </span>
              </div>

              <!-- 门槛显示（仅在不匹配时显示） -->
              <div v-if="!isTokenMatching(token)" class="mt-3 pt-2 border-t border-gray-600/30">
                <div class="text-xs text-gray-500 mb-1">要求门槛:</div>
                <div class="space-y-1">
                  <div class="flex justify-between">
                    <span class="text-gray-500">置信度:</span>
                    <span class="text-gray-400">≥{{ confidenceThreshold }}%</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">分数:</span>
                    <span class="text-gray-400">≥{{ config.score_gap_threshold }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">样本数:</span>
                    <span class="text-gray-400">≥{{ config.min_sample_count }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">历史准确率:</span>
                    <span class="text-gray-400">≥{{ (config.historical_accuracy_threshold * 100).toFixed(1) }}%</span>
                  </div>
                </div>
              </div>

              <!-- 失败原因（只在不匹配时显示） -->
              <div v-if="!isTokenMatching(token)" class="mt-3 rounded bg-red-500/10 p-2">
                <div class="text-xs text-red-400">
                  <strong>未通过:</strong>
                  <div class="mt-1">
                    <div v-for="reason in getTokenFailureReasons(token).slice(0, 3)" :key="reason" class="text-red-300">
                      • {{ reason }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- 成功匹配时的额外信息 -->
              <div v-if="isTokenMatching(token)" class="mt-3 rounded bg-green-500/10 p-2">
                <div class="text-xs text-green-400 text-center">
                  <strong>✅ 全部通过</strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 显示更多提示 -->
        <div v-if="currentAnalysis.length > 5" class="mt-4 text-center">
          <span class="text-xs text-gray-400">显示前5个Token，总共{{ currentAnalysis.length }}个</span>
        </div>
      </div>

      <!-- 无数据时的提示 -->
      <NEmpty v-else description="暂无预测数据" class="mt-6 py-8" />

      <!-- 核心控制按钮 -->
      <div class="mt-6 flex justify-center space-x-4">
        <n-button
          v-if="!autoBettingStatus.is_running"
          @click="startAutoBetting"
          :loading="toggleLoading"
          type="success"
          size="large"
          class="shadow-green-500/25 shadow-lg hover:shadow-green-500/40"
        >
          <template #icon>
            <span>▶️</span>
          </template>
          启动自动下注
        </n-button>

        <n-button
          v-else
          @click="stopAutoBetting"
          :loading="toggleLoading"
          type="error"
          size="large"
          class="shadow-lg shadow-red-500/25 hover:shadow-red-500/40"
        >
          <template #icon>
            <span>⏹️</span>
          </template>
          停止自动下注
        </n-button>

        <n-button
          @click="executeManualBetting"
          :loading="executeLoading"
          type="warning"
          size="large"
          class="shadow-lg shadow-orange-500/25 hover:shadow-orange-500/40"
        >
          <template #icon>
            <span>🎯</span>
          </template>
          手动执行一次
        </n-button>
      </div>
    </NCard>

    <!-- 主要工作区域：左侧策略配置，右侧快速配置 -->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <!-- 左侧：策略选择和配置区域 -->
      <div class="space-y-6">
        <!-- 策略模板选择 -->
        <NCard
          class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="🎯 智能策略选择"
          size="large"
        >
          <div class="space-y-4">
            <!-- 策略网格布局 -->
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div
                v-for="(template, key) in strategyTemplatesWithCustom"
                :key="key"
                class="cursor-pointer border border-gray-500/30 rounded-lg bg-gray-500/10 p-3 transition-all duration-200 hover:border-blue-400/60 hover:bg-blue-500/10"
                :class="{
                  'border-blue-400 bg-blue-500/20': selectedTemplate === String(key),
                  'border-orange-400 bg-orange-500/20': String(key) === 'custom' && selectedTemplate === String(key)
                }"
                @click="applyStrategyTemplate(String(key))"
              >
                <div class="mb-2 flex items-center justify-between">
                  <span class="flex items-center text-sm text-white font-medium space-x-1">
                    <span v-if="String(key) === 'custom'">🎨</span>
                    <span v-else>📋</span>
                    <span>{{ template.name }}</span>
                  </span>
                  <n-tag
                    :type="
                      selectedTemplate === String(key) ? (String(key) === 'custom' ? 'warning' : 'primary') : 'default'
                    "
                    size="small"
                  >
                    {{ String(key) === 'custom' ? '自定义' : template.confidence_threshold + '%' }}
                  </n-tag>
                </div>
                <div class="text-xs text-gray-400">{{ template.description }}</div>
                <div v-if="String(key) !== 'custom'" class="mt-2 flex flex-wrap gap-1">
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
                <div v-else class="mt-2">
                  <span class="rounded bg-orange-600 px-1.5 py-0.5 text-xs text-orange-200">完全可定制</span>
                </div>
              </div>
            </div>
          </div>
        </NCard>
      </div>

      <!-- 右侧：快速配置面板 -->
      <div class="space-y-6">
        <!-- 快速配置面板 -->
        <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="⚙️ 快速配置">
          <div class="space-y-4">
            <!-- 基础配置 -->
            <div class="grid grid-cols-1 gap-4">
              <div class="space-y-2">
                <label class="text-xs text-gray-300 font-medium">下注金额</label>
                <n-input-number
                  v-model:value="props.config.bet_amount"
                  :min="200"
                  :max="2000"
                  :step="50"
                  :disabled="isRunning"
                  size="small"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="text-xs text-gray-300 font-medium">置信度(%)</label>
                <n-input-number
                  v-model:value="props.config.confidence_threshold"
                  :min="0"
                  :max="100"
                  :step="1"
                  :disabled="isRunning || props.config.strategy === 'rank_betting'"
                  size="small"
                />
              </div>
              <div class="space-y-2">
                <label class="text-xs text-gray-300 font-medium">分数门槛</label>
                <n-input-number
                  v-model:value="props.config.score_gap_threshold"
                  :min="0"
                  :max="50"
                  :step="0.1"
                  :precision="1"
                  :disabled="isRunning || props.config.strategy === 'rank_betting'"
                  size="small"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="text-xs text-gray-300 font-medium">最少样本数</label>
                <n-input-number
                  v-model:value="props.config.min_sample_count"
                  :min="1"
                  :max="200"
                  :step="1"
                  :disabled="isRunning || props.config.strategy === 'rank_betting'"
                  size="small"
                />
              </div>
              <div class="space-y-2">
                <label class="text-xs text-gray-300 font-medium">历史准确率(%)</label>
                <n-input-number
                  v-model:value="historyAccuracyPercent"
                  :min="0"
                  :max="100"
                  :step="1"
                  :precision="0"
                  :disabled="isRunning || props.config.strategy === 'rank_betting'"
                  size="small"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4">
              <div class="space-y-2">
                <label class="text-xs text-gray-300 font-medium">下注策略</label>
                <n-select
                  v-model:value="props.config.strategy"
                  :options="[
                    { label: '单项', value: 'single_bet' },
                    { label: '多项', value: 'multi_bet' },
                    { label: '对冲', value: 'hedge_bet' },
                    { label: '排名', value: 'rank_betting' }
                  ]"
                  :disabled="isRunning"
                  size="small"
                />
              </div>
            </div>

            <!-- 指定排名下注配置 -->
            <div v-if="props.config.strategy === 'rank_betting'" class="border-t border-gray-600 pt-4">
              <label class="mb-2 block text-xs text-gray-300 font-medium">选择排名</label>
              <div class="grid grid-cols-5 gap-2">
                <div
                  v-for="rank in [1, 2, 3, 4, 5]"
                  :key="rank"
                  class="cursor-pointer border-2 rounded p-2 text-center text-xs transition-all duration-200"
                  :class="
                    props.config.rank_betting_enabled_ranks.includes(rank)
                      ? 'border-blue-400 bg-blue-500/20 text-blue-400'
                      : 'border-gray-500/30 bg-gray-500/10 text-gray-400 hover:border-gray-400/60'
                  "
                  @click="toggleRankBetting(rank, !props.config.rank_betting_enabled_ranks.includes(rank))"
                >
                  <div class="font-bold">TOP{{ rank }}</div>
                </div>
              </div>
            </div>

            <!-- 🆕 高级过滤器配置 -->
            <div class="border-t border-gray-600 pt-4">
              <!-- 🔧 过滤器工具栏 -->
              <div class="mb-3 flex items-center justify-between">
                <span class="text-sm text-gray-300 font-medium">高级过滤器</span>
                <n-button @click="resetToDefaults" :disabled="isRunning" size="tiny" type="warning">
                  <template #icon>
                    <span>🔄</span>
                  </template>
                  重置默认
                </n-button>
              </div>
              <NCollapse size="small">
                <!-- 历史表现过滤器 -->
                <NCollapseItem title="📊 历史表现过滤器" name="historical">
                  <div class="space-y-3">
                    <!-- 胜率过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch v-model:value="props.config.enable_win_rate_filter" size="small" :disabled="isRunning" />
                      <div class="grid grid-cols-2 flex-1 gap-2">
                        <label class="text-xs text-gray-300">胜率 ≥</label>
                        <NInputNumber
                          v-model:value="props.config.min_win_rate_threshold"
                          :min="0"
                          :max="1"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_win_rate_filter"
                          size="tiny"
                          placeholder="0.70"
                        />
                      </div>
                    </div>

                    <!-- 保本率过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch
                        v-model:value="props.config.enable_top3_rate_filter"
                        size="small"
                        :disabled="isRunning"
                      />
                      <div class="grid grid-cols-2 flex-1 gap-2">
                        <label class="text-xs text-gray-300">保本率 ≥</label>
                        <NInputNumber
                          v-model:value="props.config.min_top3_rate_threshold"
                          :min="0"
                          :max="1"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_top3_rate_filter"
                          size="tiny"
                          placeholder="0.50"
                        />
                      </div>
                    </div>

                    <!-- 平均排名过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch v-model:value="props.config.enable_avg_rank_filter" size="small" :disabled="isRunning" />
                      <div class="grid grid-cols-2 flex-1 gap-2">
                        <label class="text-xs text-gray-300">平均排名 ≤</label>
                        <NInputNumber
                          v-model:value="props.config.max_avg_rank_threshold"
                          :min="1"
                          :max="5"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_avg_rank_filter"
                          size="tiny"
                          placeholder="3.00"
                        />
                      </div>
                    </div>

                    <!-- 稳定性过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch
                        v-model:value="props.config.enable_stability_filter"
                        size="small"
                        :disabled="isRunning"
                      />
                      <div class="grid grid-cols-2 flex-1 gap-2">
                        <label class="text-xs text-gray-300">波动性 ≤</label>
                        <NInputNumber
                          v-model:value="props.config.max_stability_threshold"
                          :min="0"
                          :max="2"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_stability_filter"
                          size="tiny"
                          placeholder="0.80"
                        />
                      </div>
                    </div>
                  </div>
                </NCollapseItem>

                <!-- 算法评分过滤器 -->
                <NCollapseItem title="🎯 算法评分过滤器" name="scores">
                  <div class="space-y-3">
                    <!-- 绝对分数过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch
                        v-model:value="props.config.enable_absolute_score_filter"
                        size="small"
                        :disabled="isRunning"
                      />
                      <div class="grid grid-cols-2 flex-1 gap-2">
                        <label class="text-xs text-gray-300">绝对分数 ≥</label>
                        <NInputNumber
                          v-model:value="props.config.min_absolute_score_threshold"
                          :min="0"
                          :max="1"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_absolute_score_filter"
                          size="tiny"
                          placeholder="0.70"
                        />
                      </div>
                    </div>

                    <!-- 相对分数过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch
                        v-model:value="props.config.enable_relative_score_filter"
                        size="small"
                        :disabled="isRunning"
                      />
                      <div class="grid grid-cols-2 flex-1 gap-2">
                        <label class="text-xs text-gray-300">相对分数 ≥</label>
                        <NInputNumber
                          v-model:value="props.config.min_relative_score_threshold"
                          :min="0"
                          :max="1"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_relative_score_filter"
                          size="tiny"
                          placeholder="0.50"
                        />
                      </div>
                    </div>

                    <!-- H2H分数过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch
                        v-model:value="props.config.enable_h2h_score_filter"
                        size="small"
                        :disabled="isRunning"
                      />
                      <div class="grid grid-cols-2 flex-1 gap-2">
                        <label class="text-xs text-gray-300">H2H分数 ≥</label>
                        <NInputNumber
                          v-model:value="props.config.min_h2h_score_threshold"
                          :min="0"
                          :max="1"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_h2h_score_filter"
                          size="tiny"
                          placeholder="0.70"
                        />
                      </div>
                    </div>
                  </div>
                </NCollapseItem>

                <!-- 市场动态过滤器 -->
                <NCollapseItem title="📈 市场动态过滤器" name="market">
                  <div class="space-y-3">
                    <!-- 5分钟涨跌幅过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch
                        v-model:value="props.config.enable_change_5m_filter"
                        size="small"
                        :disabled="isRunning"
                      />
                      <div class="grid grid-cols-3 flex-1 gap-1">
                        <label class="text-xs text-gray-300">5分钟</label>
                        <NInputNumber
                          v-model:value="props.config.min_change_5m_threshold"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_change_5m_filter"
                          size="tiny"
                          placeholder="最小"
                        />
                        <NInputNumber
                          v-model:value="props.config.max_change_5m_threshold"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_change_5m_filter"
                          size="tiny"
                          placeholder="最大"
                        />
                      </div>
                    </div>

                    <!-- 1小时涨跌幅过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch
                        v-model:value="props.config.enable_change_1h_filter"
                        size="small"
                        :disabled="isRunning"
                      />
                      <div class="grid grid-cols-3 flex-1 gap-1">
                        <label class="text-xs text-gray-300">1小时</label>
                        <NInputNumber
                          v-model:value="props.config.min_change_1h_threshold"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_change_1h_filter"
                          size="tiny"
                          placeholder="最小"
                        />
                        <NInputNumber
                          v-model:value="props.config.max_change_1h_threshold"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_change_1h_filter"
                          size="tiny"
                          placeholder="最大"
                        />
                      </div>
                    </div>

                    <!-- 4小时涨跌幅过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch
                        v-model:value="props.config.enable_change_4h_filter"
                        size="small"
                        :disabled="isRunning"
                      />
                      <div class="grid grid-cols-3 flex-1 gap-1">
                        <label class="text-xs text-gray-300">4小时</label>
                        <NInputNumber
                          v-model:value="props.config.min_change_4h_threshold"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_change_4h_filter"
                          size="tiny"
                          placeholder="最小"
                        />
                        <NInputNumber
                          v-model:value="props.config.max_change_4h_threshold"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_change_4h_filter"
                          size="tiny"
                          placeholder="最大"
                        />
                      </div>
                    </div>

                    <!-- 24小时涨跌幅过滤器 -->
                    <div class="flex items-center space-x-3">
                      <NSwitch
                        v-model:value="props.config.enable_change_24h_filter"
                        size="small"
                        :disabled="isRunning"
                      />
                      <div class="grid grid-cols-3 flex-1 gap-1">
                        <label class="text-xs text-gray-300">24小时</label>
                        <NInputNumber
                          v-model:value="props.config.min_change_24h_threshold"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_change_24h_filter"
                          size="tiny"
                          placeholder="最小"
                        />
                        <NInputNumber
                          v-model:value="props.config.max_change_24h_threshold"
                          :step="0.01"
                          :precision="2"
                          :disabled="isRunning || !props.config.enable_change_24h_filter"
                          size="tiny"
                          placeholder="最大"
                        />
                      </div>
                    </div>
                  </div>
                </NCollapseItem>
              </NCollapse>
            </div>

            <!-- 保存按钮 -->
            <div class="text-center">
              <n-button @click="manualSaveConfig" :disabled="isRunning" :loading="configSaving" type="primary">
                <template #icon>
                  <span>💾</span>
                </template>
                {{ hasUID ? '保存到云端' : '本地保存' }}
              </n-button>
            </div>
          </div>
        </NCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { onMounted, watch, ref, computed } from 'vue';
  import { NEmpty, NTag, NCollapse, NCollapseItem, NSwitch, NInputNumber } from 'naive-ui';
  import AIPredictionRanking from '@/components/AIPredictionRanking.vue';
  import type { AutoBettingStatus, DebugInfo } from '@/composables/useAutoBettingControl';
  import type { AutoBettingConfig } from '@/composables/useAutoBettingConfig';
  import api from '@/utils/api';

  // Props
  interface Props {
    autoBettingStatus: AutoBettingStatus;
    currentAnalysis: any;
    analysisMeta: any;
    currentRoundId: string | null;
    currentGameStatus: string;
    currentGameTokensWithRanks: any[];
    debugInfo: DebugInfo;
    toggleLoading: boolean;
    executeLoading: boolean;
    analysisLoading: boolean;

    strategyName: string;
    confidenceThreshold: number;
    config: AutoBettingConfig;
    selectedTemplate: string;
    customStrategyMode: boolean;
    configSaving: boolean;
    configSyncStatus: { type: 'success' | 'error' | 'info'; message: string } | null;
    strategyTemplates: any;
    strategyTemplatesWithCustom: any;
    strategyValidation: any;

    isRunning: boolean;
    hasUID: boolean;
  }

  const props = defineProps<Props>();

  // Emits
  const emit = defineEmits<{
    startAutoBetting: [];
    stopAutoBetting: [];
    executeManualBetting: [];
    applyStrategyTemplate: [key: string];
    executeStrategyBetting: [];
    manualSaveConfig: [];
    refreshAnalysis: [];
  }>();

  // ==================== 工具函数 ====================

  // 预测图标
  const getPredictionIcon = (index: number) => {
    if (index === 0) return '🥇';
    if (index === 1) return '🥈';
    if (index === 2) return '🥉';
    return '📊';
  };

  // 匹配卡片样式
  const getMatchCardClass = (index: number) => {
    const colors = [
      'border-yellow-400/30 bg-gradient-to-br from-yellow-500/10 to-amber-600/5 hover:border-yellow-400/50 hover:shadow-yellow-500/20',
      'border-slate-400/30 bg-gradient-to-br from-slate-500/10 to-gray-600/5 hover:border-slate-400/50 hover:shadow-slate-500/20',
      'border-orange-400/30 bg-gradient-to-br from-orange-500/10 to-red-600/5 hover:border-orange-400/50 hover:shadow-orange-500/20',
      'border-blue-400/30 bg-gradient-to-br from-blue-500/10 to-indigo-600/5 hover:border-blue-400/50 hover:shadow-blue-500/20',
      'border-purple-400/30 bg-gradient-to-br from-purple-500/10 to-pink-600/5 hover:border-purple-400/50 hover:shadow-purple-500/20'
    ];
    return colors[index % colors.length];
  };

  // ==================== 计算属性 ====================

  // 排名下注相关方法 - 直接操作props中的config
  const toggleRankBetting = (rank: number, checked: boolean) => {
    if (checked) {
      if (!props.config.rank_betting_enabled_ranks.includes(rank)) {
        props.config.rank_betting_enabled_ranks.push(rank);
        props.config.rank_betting_enabled_ranks.sort((a: number, b: number) => a - b);
      }
    } else {
      const index = props.config.rank_betting_enabled_ranks.indexOf(rank);
      if (index > -1) {
        props.config.rank_betting_enabled_ranks.splice(index, 1);
      }
    }
  };

  // 🔄 重置为默认配置
  const resetToDefaults = () => {
    window.$dialog?.warning({
      title: '确认重置',
      content: '确定要重置为默认配置吗？这将恢复所有参数到初始状态，包括下注金额、策略等。',
      positiveText: '确认重置',
      negativeText: '取消',
      onPositiveClick: () => {
        // 重置所有过滤器为默认值（关闭状态）
        // 历史表现过滤器
        props.config.enable_win_rate_filter = false;
        props.config.min_win_rate_threshold = 0.65;
        props.config.enable_top3_rate_filter = false;
        props.config.min_top3_rate_threshold = 0.6;
        props.config.enable_avg_rank_filter = false;
        props.config.max_avg_rank_threshold = 2.8;
        props.config.enable_stability_filter = false;
        props.config.max_stability_threshold = 0.7;

        // 评分过滤器
        props.config.enable_absolute_score_filter = false;
        props.config.min_absolute_score_threshold = 0.7;
        props.config.enable_relative_score_filter = false;
        props.config.min_relative_score_threshold = 0.65;
        props.config.enable_h2h_score_filter = false;
        props.config.min_h2h_score_threshold = 0.65;

        // 市场动态过滤器
        props.config.enable_change_5m_filter = false;
        props.config.min_change_5m_threshold = -0.01;
        props.config.max_change_5m_threshold = 0.07;
        props.config.enable_change_1h_filter = false;
        props.config.min_change_1h_threshold = -0.03;
        props.config.max_change_1h_threshold = 0.1;
        props.config.enable_change_4h_filter = false;
        props.config.min_change_4h_threshold = -0.05;
        props.config.max_change_4h_threshold = 0.15;
        props.config.enable_change_24h_filter = false;
        props.config.min_change_24h_threshold = 0.0;
        props.config.max_change_24h_threshold = 0.0;

        // 基础参数重置为合理默认值
        props.config.confidence_threshold = 88;
        props.config.score_gap_threshold = 6.0;
        props.config.min_total_games = 25;
        props.config.historical_accuracy_threshold = 0.7;
        props.config.min_sample_count = 40;
        props.config.strategy = 'single_bet';
        props.config.bet_amount = 200;

        // 重置排名下注设置
        props.config.rank_betting_enabled_ranks = [1, 2, 3];

        window.$message?.success('🔄 已重置为默认配置，所有参数恢复初始状态');
      }
    });
  };

  // ==================== 本地状态管理 ====================

  // ==================== 数据获取函数 ====================

  // 获取初始预测数据
  const fetchInitialPredictionData = async () => {
    console.log('🔮 SmartControlCenter: 获取初始预测数据...');
    try {
      const response = await api.get('/game/current-analysis');
      if (response.data.success) {
        console.log(`✅ SmartControlCenter: 成功获取初始预测数据: ${response.data.data?.length || 0} 个Token`);
        // 通知父组件更新数据，这里我们通过emit通知父组件刷新
        emit('refreshAnalysis');
      } else {
        console.warn('⚠️ SmartControlCenter: 获取初始预测数据失败:', response.data.message);
      }
    } catch (error) {
      console.error('❌ SmartControlCenter: 获取初始预测数据失败:', error);
    }
  };

  // 刷新分析数据
  const refreshAnalysis = () => {
    console.log('🔄 SmartControlCenter: 手动刷新分析数据');
    emit('refreshAnalysis');
  };

  // ==================== 生命周期钩子 ====================

  onMounted(() => {
    console.log('🎛️ SmartControlCenter: 组件已挂载');

    // 检查是否有预测数据，如果没有则主动获取
    if (!props.currentAnalysis || props.currentAnalysis.length === 0) {
      console.log('🔮 SmartControlCenter: 未检测到预测数据，主动获取中...');
      fetchInitialPredictionData();
    } else {
      console.log(`✅ SmartControlCenter: 已有预测数据: ${props.currentAnalysis.length} 个Token`);
    }
  });

  // 监听预测数据变化，当数据清空时主动重新获取
  watch(
    () => props.currentAnalysis,
    (newAnalysis, oldAnalysis) => {
      // 如果从有数据变为无数据，或者一直没有数据，则主动获取
      if ((!newAnalysis || newAnalysis.length === 0) && (!oldAnalysis || oldAnalysis.length === 0)) {
        console.log('🔮 SmartControlCenter: 检测到预测数据缺失，尝试获取...');
        fetchInitialPredictionData();
      }
    },
    { immediate: false }
  );

  // ==================== 调试面板状态和函数 ====================

  // 调试面板状态
  const showDebugPanel = ref(false);

  // 历史准确率百分比计算属性（用于快速配置区块的显示和编辑）
  const historyAccuracyPercent = computed({
    get: () => Math.round((props.config.historical_accuracy_threshold || 0) * 100),
    set: (value: number) => {
      props.config.historical_accuracy_threshold = value / 100;
    }
  });

  // 数据映射函数（复制自AutoBetting.vue）
  const mapPredictionData = (rawPrediction: any): any => {
    return {
      ...rawPrediction,
      confidence: rawPrediction.rank_confidence || rawPrediction.confidence || 0,
      score: rawPrediction.predicted_final_value || rawPrediction.score || 0,
      sample_count: rawPrediction.total_games || rawPrediction.sample_count || 0,
      historical_accuracy: (rawPrediction.win_rate || 0) / 100,
      symbol: rawPrediction.symbol,
      predicted_rank: rawPrediction.predicted_rank
    };
  };

  // 评估预测是否符合策略条件（复制自AutoBetting.vue）
  const evaluatePredictionMatch = (prediction: any): boolean => {
    // 对于排名下注策略，首先检查排名是否在选中范围内
    if (props.config.strategy === 'rank_betting') {
      if (!props.config.rank_betting_enabled_ranks.includes(prediction.predicted_rank)) {
        return false;
      }
    } else {
      // 非排名下注策略的基础条件检查
      if (prediction.confidence < props.config.confidence_threshold) return false;
      if (prediction.score < props.config.score_gap_threshold) return false;
      if (prediction.sample_count < props.config.min_sample_count) return false;
      if (prediction.historical_accuracy < props.config.historical_accuracy_threshold) return false;
    }

    // 基础策略条件
    if (prediction.confidence < props.config.confidence_threshold) return false;
    if (prediction.score < props.config.score_gap_threshold) return false;
    if (prediction.sample_count < props.config.min_sample_count) return false;
    if (prediction.historical_accuracy < props.config.historical_accuracy_threshold) return false;

    // 历史表现过滤器
    if (props.config.enable_win_rate_filter && (prediction.win_rate || 0) < props.config.min_win_rate_threshold * 100)
      return false;
    if (
      props.config.enable_top3_rate_filter &&
      (prediction.top3_rate || 0) < props.config.min_top3_rate_threshold * 100
    )
      return false;
    if (props.config.enable_avg_rank_filter && (prediction.avg_rank || 3) > props.config.max_avg_rank_threshold)
      return false;
    if (props.config.enable_stability_filter && (prediction.value_stddev || 0) > props.config.max_stability_threshold)
      return false;

    // 评分过滤器
    if (
      props.config.enable_absolute_score_filter &&
      (prediction.absolute_score || 0) < props.config.min_absolute_score_threshold * 100
    )
      return false;
    if (
      props.config.enable_relative_score_filter &&
      (prediction.relative_score || 0) < props.config.min_relative_score_threshold * 100
    )
      return false;
    if (
      props.config.enable_h2h_score_filter &&
      (prediction.h2h_score || 0) < props.config.min_h2h_score_threshold * 100
    )
      return false;

    // 市场动态过滤器
    if (props.config.enable_change_5m_filter) {
      const change5m = prediction.change_5m || 0;
      if (change5m < props.config.min_change_5m_threshold || change5m > props.config.max_change_5m_threshold)
        return false;
    }
    if (props.config.enable_change_1h_filter) {
      const change1h = prediction.change_1h || 0;
      if (change1h < props.config.min_change_1h_threshold || change1h > props.config.max_change_1h_threshold)
        return false;
    }
    if (props.config.enable_change_4h_filter) {
      const change4h = prediction.change_4h || 0;
      if (change4h < props.config.min_change_4h_threshold || change4h > props.config.max_change_4h_threshold)
        return false;
    }
    if (props.config.enable_change_24h_filter) {
      const change24h = prediction.change_24h || 0;
      if (change24h < props.config.min_change_24h_threshold || change24h > props.config.max_change_24h_threshold)
        return false;
    }

    return true;
  };

  // 调试工具函数
  const getTokenConfidence = (token: any): number => {
    return token.rank_confidence || token.confidence || 0;
  };

  const getTokenScore = (token: any): number => {
    return token.predicted_final_value || token.score || 0;
  };

  const getTokenSampleCount = (token: any): number => {
    return token.total_games || token.sample_count || 0;
  };

  const getTokenHistoricalAccuracy = (token: any): number => {
    return (token.win_rate || 0) / 100;
  };

  const isTokenMatching = (token: any): boolean => {
    const prediction = mapPredictionData(token);
    return evaluatePredictionMatch(prediction);
  };

  const getTokenDebugClass = (token: any): string => {
    const isMatching = isTokenMatching(token);
    return isMatching ? 'border-green-500/30 bg-green-500/5' : 'border-red-500/30 bg-red-500/5';
  };

  const getMetricClass = (value: number, threshold: number, operation: 'gte' | 'lte'): string => {
    const isPass = operation === 'gte' ? value >= threshold : value <= threshold;
    return isPass ? 'text-green-400 font-bold' : 'text-red-400 font-bold';
  };

  const getTokenFailureReasons = (token: any): string[] => {
    const prediction = mapPredictionData(token);
    const reasons: string[] = [];

    if (prediction.confidence < props.config.confidence_threshold) {
      reasons.push(`置信度不足(${prediction.confidence.toFixed(1)}% < ${props.config.confidence_threshold}%)`);
    }
    if (prediction.score < props.config.score_gap_threshold) {
      reasons.push(`分数不足(${prediction.score.toFixed(1)} < ${props.config.score_gap_threshold})`);
    }
    if (prediction.sample_count < props.config.min_sample_count) {
      reasons.push(`样本数不足(${prediction.sample_count} < ${props.config.min_sample_count})`);
    }
    if (prediction.historical_accuracy < props.config.historical_accuracy_threshold) {
      reasons.push(
        `历史准确率不足(${(prediction.historical_accuracy * 100).toFixed(1)}% < ${(props.config.historical_accuracy_threshold * 100).toFixed(1)}%)`
      );
    }

    // 高级过滤器检查
    if (props.config.enable_win_rate_filter && (prediction.win_rate || 0) < props.config.min_win_rate_threshold * 100) {
      reasons.push(`胜率过滤器未通过`);
    }
    if (
      props.config.enable_top3_rate_filter &&
      (prediction.top3_rate || 0) < props.config.min_top3_rate_threshold * 100
    ) {
      reasons.push(`保本率过滤器未通过`);
    }
    if (
      props.config.enable_absolute_score_filter &&
      (prediction.absolute_score || 0) < props.config.min_absolute_score_threshold * 100
    ) {
      reasons.push(`绝对分数过滤器未通过`);
    }
    if (
      props.config.enable_relative_score_filter &&
      (prediction.relative_score || 0) < props.config.min_relative_score_threshold * 100
    ) {
      reasons.push(`相对分数过滤器未通过`);
    }

    return reasons.length > 0 ? reasons : ['未知原因'];
  };

  // 紧急降低所有门槛
  const setVeryLowThresholds = () => {
    window.$dialog?.warning({
      title: '🚨 紧急降低门槛',
      content: '这将把所有过滤条件设置为极低的门槛，可能会增加风险。确定要继续吗？',
      positiveText: '确认降低',
      negativeText: '取消',
      onPositiveClick: () => {
        // 基础门槛大幅降低
        props.config.confidence_threshold = 10; // 从70%降到10%
        props.config.score_gap_threshold = 0.1; // 极低分数要求
        props.config.min_sample_count = 1; // 最少样本数
        props.config.historical_accuracy_threshold = 0.1; // 极低历史准确率

        // 关闭所有高级过滤器
        props.config.enable_win_rate_filter = false;
        props.config.enable_top3_rate_filter = false;
        props.config.enable_avg_rank_filter = false;
        props.config.enable_stability_filter = false;
        props.config.enable_absolute_score_filter = false;
        props.config.enable_relative_score_filter = false;
        props.config.enable_h2h_score_filter = false;
        props.config.enable_change_5m_filter = false;
        props.config.enable_change_1h_filter = false;
        props.config.enable_change_4h_filter = false;
        props.config.enable_change_24h_filter = false;

        window.$message?.success('🚨 已将所有门槛设置为极低水平，请检查匹配结果');
      }
    });
  };

  // Methods
  const startAutoBetting = () => emit('startAutoBetting');
  const stopAutoBetting = () => emit('stopAutoBetting');
  const executeManualBetting = () => emit('executeManualBetting');
  const applyStrategyTemplate = (key: string) => emit('applyStrategyTemplate', key);
  const executeStrategyBetting = () => emit('executeStrategyBetting');
  const manualSaveConfig = () => emit('manualSaveConfig');
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
</style>
