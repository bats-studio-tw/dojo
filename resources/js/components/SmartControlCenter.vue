<template>
  <div class="space-y-6">
    <!-- 🎯 统一预测展示区域 -->
    <div class="space-y-6">
      <!-- 🔮 AI预测排名面板 -->
      <AIPredictionRanking
        :current-analysis="currentAnalysis"
        :analysis-meta="analysisMeta"
        :current-round-id="currentRoundId"
        :current-game-status="currentGameStatus"
        :current-game-tokens-with-ranks="currentGameTokensWithRanks"
        :analysis-loading="analysisLoading"
        @refresh-analysis="refreshAnalysis"
      />

      <!-- ⚡ AI动能预测排名面板 -->
      <MomentumPredictionDisplay
        :hybrid-predictions="hybridPredictions || []"
        :analysis-meta="hybridAnalysisMeta"
        :current-round-id="currentRoundId"
        :current-game-status="currentGameStatus"
        :current-game-tokens-with-ranks="currentGameTokensWithRanks"
        :analysis-loading="hybridAnalysisLoading"
        @refresh-analysis="refreshHybridAnalysis"
      />
    </div>

    <!-- 🤖 自动下注状态面板 -->
    <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="🤖 自动下注状态" size="large">
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
            v-for="(token, index) in displayAnalysisData.slice(0, 5)"
            :key="`analysis-${index}-${token.symbol}`"
            class="border rounded-lg p-3 transition-all duration-200 hover:shadow-lg"
            :class="getTokenDebugClass(token)"
          >
            <!-- Token头部 - 横向紧凑布局 -->
            <div class="mb-3">
              <!-- 第一行：图标 + Token符号 + 排名 -->
              <div class="mb-2 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                  <span class="text-lg">{{ getPredictionIcon(index + 1) }}</span>
                  <span class="text-sm text-white font-bold">{{ token.symbol }}</span>
                  <span class="text-xs text-gray-400">#{{ token.predicted_rank || index + 1 }}</span>
                </div>
              </div>
              <!-- 第二行：匹配状态 + 下注金额 -->
              <div class="flex items-center justify-between">
                <span class="text-xs font-medium" :class="isTokenMatching(token) ? 'text-green-400' : 'text-red-400'">
                  {{ isTokenMatching(token) ? '✅ 匹配' : '❌ 不匹配' }}
                </span>
                <span v-if="isTokenMatching(token)" class="text-xs text-green-400 font-medium">
                  下注 ${{ config.bet_amount }}
                </span>
              </div>
              <!-- 🆕 复合型策略：显示两种排名 -->
              <div
                v-if="props.config.strategy_type === 'hybrid_rank'"
                class="mt-2 flex items-center justify-between text-xs"
              >
                <span class="text-blue-300">AI: #{{ token.predicted_rank || 'N/A' }}</span>
                <span class="text-green-300">动能: #{{ token.momentum_rank || 'N/A' }}</span>
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
                <span class="text-gray-400">胜率:</span>
                <span
                  :class="
                    getMetricClass(getTokenHistoricalAccuracy(token) * 100, config.historical_accuracy_threshold, 'gte')
                  "
                >
                  {{ (getTokenHistoricalAccuracy(token) * 100).toFixed(1) }}%
                </span>
              </div>
            </div>

            <!-- 🆕 高级过滤器数值显示 -->
            <div v-if="hasActiveAdvancedFilters()" class="mt-3 border-t border-gray-600 pt-2">
              <div class="text-xs space-y-2">
                <!-- 历史表现过滤器 -->
                <div v-if="config.enable_win_rate_filter" class="flex justify-between">
                  <span class="text-gray-400">胜率:</span>
                  <span :class="checkWinRateFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ (token.win_rate || 0).toFixed(1) }}%
                  </span>
                </div>
                <div v-if="config.enable_top3_rate_filter" class="flex justify-between">
                  <span class="text-gray-400">保本率:</span>
                  <span :class="checkTop3RateFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ (token.top3_rate || 0).toFixed(1) }}%
                  </span>
                </div>
                <div v-if="config.enable_avg_rank_filter" class="flex justify-between">
                  <span class="text-gray-400">平均排名:</span>
                  <span :class="checkAvgRankFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ (token.avg_rank || 3).toFixed(1) }}
                  </span>
                </div>
                <div v-if="config.enable_stability_filter" class="flex justify-between">
                  <span class="text-gray-400">稳定性:</span>
                  <span :class="checkStabilityFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ (token.value_stddev || 0).toFixed(2) }}
                  </span>
                </div>

                <!-- 评分过滤器 -->
                <div v-if="config.enable_absolute_score_filter" class="flex justify-between">
                  <span class="text-gray-400">绝对分数:</span>
                  <span :class="checkAbsoluteScoreFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ (token.absolute_score || 0).toFixed(1) }}
                  </span>
                </div>
                <div v-if="config.enable_relative_score_filter" class="flex justify-between">
                  <span class="text-gray-400">相对分数:</span>
                  <span :class="checkRelativeScoreFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ (token.relative_score || 0).toFixed(1) }}
                  </span>
                </div>
                <div v-if="config.enable_h2h_score_filter" class="flex justify-between">
                  <span class="text-gray-400">H2H分数:</span>
                  <span :class="checkH2HScoreFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ (token.h2h_score || 0).toFixed(1) }}
                  </span>
                </div>

                <!-- 市场动态过滤器 -->
                <div v-if="config.enable_change_5m_filter" class="flex justify-between">
                  <span class="text-gray-400">5分钟涨跌:</span>
                  <span :class="checkChange5mFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ formatPriceChange(token.change_5m) }}
                  </span>
                </div>
                <div v-if="config.enable_change_1h_filter" class="flex justify-between">
                  <span class="text-gray-400">1小时涨跌:</span>
                  <span :class="checkChange1hFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ formatPriceChange(token.change_1h) }}
                  </span>
                </div>
                <div v-if="config.enable_change_4h_filter" class="flex justify-between">
                  <span class="text-gray-400">4小时涨跌:</span>
                  <span :class="checkChange4hFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ formatPriceChange(token.change_4h) }}
                  </span>
                </div>
                <div v-if="config.enable_change_24h_filter" class="flex justify-between">
                  <span class="text-gray-400">24小时涨跌:</span>
                  <span :class="checkChange24hFilter(token) ? 'text-green-400' : 'text-red-400'">
                    {{ formatPriceChange(token.change_24h) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 显示更多提示 -->
        <div v-if="displayAnalysisData.length > 5" class="mt-4 text-center">
          <span class="text-xs text-gray-400">显示前5个Token，总共{{ displayAnalysisData.length }}个</span>
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

    <!-- 🎛️ 智能控制中心：策略配置区域 -->
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
            <!-- 🆕 策略说明 -->
            <div class="mb-4">
              <NTooltip trigger="hover" placement="top">
                <template #trigger>
                  <label class="inline-flex cursor-help items-center text-sm text-gray-300 font-medium space-x-1">
                    <span>策略说明</span>
                    <span class="text-blue-400">ℹ️</span>
                  </label>
                </template>
                <div class="space-y-1">
                  <div><strong>策略类型说明：</strong></div>
                  <div>
                    •
                    <strong>v8.3 H2H保本预测：</strong>
                    基于历史对战数据的传统预测模型
                  </div>
                  <div>
                    •
                    <strong>Hybrid-Edge 动能预测：</strong>
                    结合价格动能的混合预测模型
                  </div>
                  <div>
                    •
                    <strong>复合型策略：</strong>
                    AI+动能排名交集，双重验证提高准确性
                  </div>
                  <div>• 点击任意策略模板，参数配置区域会自动显示对应的可调整参数</div>
                </div>
              </NTooltip>
            </div>

            <!-- 策略网格布局 -->
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div
                v-for="(template, key) in strategyTemplatesWithCustom as Record<string, any>"
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
                  <!-- 策略类型标签 -->
                  <span
                    class="rounded px-1.5 py-0.5 text-xs"
                    :class="
                      template.strategy_type === 'h2h_breakeven'
                        ? 'bg-blue-600 text-blue-200'
                        : template.strategy_type === 'momentum'
                          ? 'bg-green-600 text-green-200'
                          : template.strategy_type === 'hybrid_rank'
                            ? 'bg-purple-600 text-purple-200'
                            : 'bg-gray-600 text-gray-300'
                    "
                  >
                    {{
                      template.strategy_type === 'h2h_breakeven'
                        ? 'H2H'
                        : template.strategy_type === 'momentum'
                          ? '动能'
                          : template.strategy_type === 'hybrid_rank'
                            ? '复合'
                            : '通用'
                    }}
                  </span>
                  <!-- 下注策略标签 -->
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
        <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="⚙️ 策略参数配置">
          <div class="space-y-4">
            <!-- 基础配置 -->
            <div class="grid grid-cols-1 gap-4">
              <div class="space-y-2">
                <NTooltip trigger="hover" placement="top">
                  <template #trigger>
                    <label class="inline-flex cursor-help items-center text-xs text-gray-300 font-medium space-x-1">
                      <span>下注金额</span>
                      <span class="text-blue-400">ℹ️</span>
                    </label>
                  </template>
                  每次下注的固定金额，范围在 $200-$2000
                  之间。金额越高收益越大，但风险也相应增加。建议根据个人资金情况合理设置。
                </NTooltip>
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

            <!-- 🆕 动态条件构建器 -->
            <div class="border-t border-gray-600 pt-4">
              <div class="mb-4 flex items-center justify-between">
                <NTooltip trigger="hover" placement="top">
                  <template #trigger>
                    <span class="inline-flex cursor-help items-center text-sm text-gray-300 font-medium space-x-1">
                      <span>动态条件构建器</span>
                      <span class="text-blue-400">ℹ️</span>
                    </span>
                  </template>
                  <div class="space-y-1">
                    <div><strong>动态条件构建器说明：</strong></div>
                    <div>• 通过可视化界面组合多个下注条件</div>
                    <div>• 点击 + 号添加新条件</div>
                    <div>• 选择 AND/OR 逻辑连接条件</div>
                    <div>• 支持删除和重新排序条件</div>
                  </div>
                </NTooltip>
                <n-button @click="addCondition" :disabled="isRunning" size="tiny" type="primary">
                  <template #icon>
                    <span>➕</span>
                  </template>
                  添加条件
                </n-button>
              </div>

              <!-- 条件列表 -->
              <div class="space-y-3">
                <div
                  v-for="(condition, index) in config.dynamic_conditions || []"
                  :key="condition.id"
                  class="border border-gray-500/30 rounded-lg bg-gray-500/10 p-3"
                >
                  <!-- 条件头部 -->
                  <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                      <span class="text-xs text-gray-400">条件 {{ index + 1 }}</span>
                      <!-- 逻辑连接符（除第一个条件外） -->
                      <div v-if="index > 0" class="flex items-center space-x-2">
                        <n-select
                          v-model:value="condition.logic"
                          :options="[
                            { label: 'AND', value: 'and' },
                            { label: 'OR', value: 'or' }
                          ]"
                          size="tiny"
                          class="w-16"
                        />
                      </div>
                    </div>
                    <n-button
                      @click="removeCondition(condition.id)"
                      :disabled="isRunning"
                      size="tiny"
                      type="error"
                      ghost
                    >
                      <template #icon>
                        <span>🗑️</span>
                      </template>
                    </n-button>
                  </div>

                  <!-- 条件内容 -->
                  <div class="grid grid-cols-3 gap-2">
                    <!-- 条件类型选择 -->
                    <n-select
                      v-model:value="condition.type"
                      :options="getConditionTypeOptions()"
                      placeholder="选择条件"
                      size="small"
                      @update:value="onConditionTypeChange(condition)"
                    />

                    <!-- 操作符选择 -->
                    <n-select
                      v-model:value="condition.operator"
                      :options="getOperatorOptions(condition.type)"
                      placeholder="操作符"
                      size="small"
                    />

                    <!-- 数值输入 -->
                    <n-input-number
                      v-model:value="condition.value"
                      :min="getMinValue(condition.type)"
                      :max="getMaxValue(condition.type)"
                      :step="getStepValue(condition.type)"
                      :precision="getPrecision(condition.type)"
                      :placeholder="getPlaceholder(condition.type)"
                      size="small"
                    />
                  </div>

                  <!-- 条件说明 -->
                  <div class="mt-2 text-xs text-gray-400">
                    {{ getConditionDescription(condition) }}
                  </div>
                </div>

                <!-- 无条件时的提示 -->
                <div v-if="(config.dynamic_conditions || []).length === 0" class="py-8 text-center">
                  <NEmpty description="暂无条件，点击上方按钮添加条件" size="small" />
                </div>
              </div>

              <!-- 条件预览 -->
              <div v-if="(config.dynamic_conditions || []).length > 0" class="mt-4 border-t border-gray-600 pt-4">
                <div class="mb-2 text-sm text-gray-300 font-medium">条件预览：</div>
                <div class="rounded-lg bg-gray-800/50 p-3 text-xs text-gray-300">
                  <div v-for="(condition, index) in config.dynamic_conditions || []" :key="condition.id">
                    <span v-if="index > 0" class="mx-2 text-blue-400 font-bold">
                      {{ condition.logic === 'and' ? 'AND' : 'OR' }}
                    </span>
                    <span>{{ getConditionPreview(condition) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- 下注策略选择 -->
            <div class="grid grid-cols-1 gap-4">
              <div class="space-y-2">
                <NTooltip trigger="hover" placement="top">
                  <template #trigger>
                    <label class="inline-flex cursor-help items-center text-xs text-gray-300 font-medium space-x-1">
                      <span>下注策略</span>
                      <span class="text-blue-400">ℹ️</span>
                    </label>
                  </template>
                  <div class="space-y-1">
                    <div>
                      <strong>单项:</strong>
                      只下注最优的一个 Token
                    </div>
                    <div>
                      <strong>多项:</strong>
                      下注所有符合条件的 Token
                    </div>
                    <div>
                      <strong>对冲:</strong>
                      同时下注多个以分散风险
                    </div>
                    <div>
                      <strong>排名:</strong>
                      固定下注指定排名的 Token
                    </div>
                  </div>
                </NTooltip>
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
              <NTooltip trigger="hover" placement="top">
                <template #trigger>
                  <label
                    class="mb-2 block inline-flex cursor-help items-center text-xs text-gray-300 font-medium space-x-1"
                  >
                    <span>选择排名</span>
                    <span class="text-blue-400">ℹ️</span>
                  </label>
                </template>
                <div class="space-y-1">
                  <div><strong>排名策略说明：</strong></div>
                  <div>• 自动下注 AI 预测排名为指定位置的 Token</div>
                  <div>• 忽略置信度、分数等其他条件，只看排名</div>
                  <div>• TOP1-3 通常有奖励，风险相对较低</div>
                  <div>• 可以选择多个排名进行组合下注</div>
                </div>
              </NTooltip>
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
  import { onMounted, watch, computed, onUnmounted } from 'vue';
  import { NEmpty, NTag, NInputNumber, NTooltip } from 'naive-ui';
  import AIPredictionRanking from '@/components/AIPredictionRanking.vue';
  import MomentumPredictionDisplay from '@/components/MomentumPredictionDisplay.vue';
  import type { AutoBettingStatus, DebugInfo } from '@/composables/useAutoBettingControl';
  import type { AutoBettingConfig } from '@/composables/useAutoBettingConfig';
  import { usePredictionDisplay } from '@/composables/usePredictionDisplay';
  import { useGamePredictionStore } from '@/stores/gamePrediction';

  // 🆕 动态条件构建器相关类型定义
  interface DynamicCondition {
    id: string;
    type: string;
    operator: string;
    value: number;
    logic: 'and' | 'or';
  }

  // 🆕 条件类型配置
  const conditionTypes = {
    // H2H策略条件
    confidence: {
      label: '置信度',
      unit: '%',
      min: 0,
      max: 100,
      step: 1,
      precision: 0,
      placeholder: '85',
      description: 'AI预测结果的可信程度，数值越高表示算法对预测结果越有把握'
    },
    score: {
      label: '分数',
      unit: '',
      min: 0,
      max: 100,
      step: 1,
      precision: 1,
      placeholder: '60',
      description: '预测分数的最低要求，分数越高表示该Token在预测中表现越突出'
    },
    sample_count: {
      label: '最少样本数',
      unit: '',
      min: 1,
      max: 200,
      step: 1,
      precision: 0,
      placeholder: '10',
      description: '预测所需的最少历史数据量，样本数越多预测结果越可靠'
    },
    win_rate: {
      label: '胜率',
      unit: '%',
      min: 0,
      max: 100,
      step: 1,
      precision: 1,
      placeholder: '65',
      description: '该Token在历史预测中的成功率，数值越高表示过往表现越好'
    },
    top3_rate: {
      label: '保本率',
      unit: '%',
      min: 0,
      max: 100,
      step: 1,
      precision: 1,
      placeholder: '50',
      description: 'Token排名进入前三的比例，通常前三有奖励可以保本或盈利'
    },
    avg_rank: {
      label: '平均排名',
      unit: '',
      min: 1,
      max: 5,
      step: 0.01,
      precision: 2,
      placeholder: '3.0',
      description: 'Token在历史预测中的平均排名，数值越小表示平均表现越好'
    },
    stability: {
      label: '波动性',
      unit: '',
      min: 0,
      max: 2,
      step: 0.01,
      precision: 2,
      placeholder: '0.8',
      description: 'Token价格波动的标准差，数值越小表示价格越稳定风险越低'
    },
    absolute_score: {
      label: '绝对分数',
      unit: '',
      min: 0,
      max: 1,
      step: 0.01,
      precision: 2,
      placeholder: '0.7',
      description: 'AI算法给出的绝对评分，反映Token的综合表现潜力'
    },
    relative_score: {
      label: '相对分数',
      unit: '',
      min: 0,
      max: 1,
      step: 0.01,
      precision: 2,
      placeholder: '0.5',
      description: '该Token相对于其他Token的评分优势'
    },
    h2h_score: {
      label: 'H2H分数',
      unit: '',
      min: 0,
      max: 1,
      step: 0.01,
      precision: 2,
      placeholder: '0.7',
      description: 'Head-to-Head对战分数，反映该Token与其他Token直接竞争时的胜率'
    },
    change_5m: {
      label: '5分钟涨跌',
      unit: '%',
      min: -10,
      max: 10,
      step: 0.01,
      precision: 2,
      placeholder: '2.0',
      description: 'Token在过去5分钟的价格变动百分比'
    },
    change_1h: {
      label: '1小时涨跌',
      unit: '%',
      min: -20,
      max: 20,
      step: 0.01,
      precision: 2,
      placeholder: '5.0',
      description: 'Token在过去1小时的价格变动百分比'
    },
    change_4h: {
      label: '4小时涨跌',
      unit: '%',
      min: -30,
      max: 30,
      step: 0.01,
      precision: 2,
      placeholder: '10.0',
      description: 'Token在过去4小时的价格变动百分比'
    },
    change_24h: {
      label: '24小时涨跌',
      unit: '%',
      min: -50,
      max: 50,
      step: 0.01,
      precision: 2,
      placeholder: '20.0',
      description: 'Token在过去24小时的价格变动百分比'
    },
    // 动能策略条件
    momentum_score: {
      label: '动能分数',
      unit: '',
      min: -5,
      max: 5,
      step: 0.1,
      precision: 1,
      placeholder: '1.5',
      description: '基于价格动能的综合评分，数值越高表示上涨动能越强'
    },
    elo_win_rate: {
      label: 'Elo胜率',
      unit: '',
      min: 0,
      max: 1,
      step: 0.01,
      precision: 2,
      placeholder: '0.55',
      description: '基于Elo评分系统的胜率预测，数值越高表示获胜概率越大'
    },
    momentum_confidence: {
      label: '动能置信度',
      unit: '',
      min: 0,
      max: 1,
      step: 0.01,
      precision: 2,
      placeholder: '0.65',
      description: '动能预测模型对结果的置信程度，数值越高表示预测越可靠'
    }
  };

  // 🆕 条件类型变化处理
  const onConditionTypeChange = (condition: DynamicCondition) => {
    const typeConfig = conditionTypes[condition.type as keyof typeof conditionTypes];
    if (typeConfig) {
      // 重置为默认值
      condition.value = parseFloat(typeConfig.placeholder);
      // 根据条件类型设置合适的操作符
      if (['avg_rank', 'stability'].includes(condition.type)) {
        condition.operator = 'lte'; // 平均排名和波动性使用小于等于
      } else {
        condition.operator = 'gte'; // 其他条件使用大于等于
      }
    }
  };

  // 🆕 获取条件类型选项
  const getConditionTypeOptions = () => {
    return Object.entries(conditionTypes).map(([key, config]) => ({
      label: config.label,
      value: key
    }));
  };

  // 🆕 获取操作符选项
  const getOperatorOptions = (type: string) => {
    const baseOperators = [
      { label: '≥', value: 'gte' },
      { label: '≤', value: 'lte' },
      { label: '=', value: 'eq' },
      { label: '≠', value: 'ne' }
    ];

    // 对于排名和波动性，优先显示小于等于
    if (['avg_rank', 'stability'].includes(type)) {
      return [
        { label: '≤', value: 'lte' },
        { label: '≥', value: 'gte' },
        { label: '=', value: 'eq' },
        { label: '≠', value: 'ne' }
      ];
    }

    return baseOperators;
  };

  // 🆕 获取数值范围配置
  const getMinValue = (type: string) => conditionTypes[type as keyof typeof conditionTypes]?.min || 0;
  const getMaxValue = (type: string) => conditionTypes[type as keyof typeof conditionTypes]?.max || 100;
  const getStepValue = (type: string) => conditionTypes[type as keyof typeof conditionTypes]?.step || 1;
  const getPrecision = (type: string) => conditionTypes[type as keyof typeof conditionTypes]?.precision || 0;
  const getPlaceholder = (type: string) => conditionTypes[type as keyof typeof conditionTypes]?.placeholder || '0';

  // 🆕 获取条件描述
  const getConditionDescription = (condition: DynamicCondition) => {
    const typeConfig = conditionTypes[condition.type as keyof typeof conditionTypes];
    return typeConfig?.description || '请选择条件类型';
  };

  // 🆕 获取条件预览文本
  const getConditionPreview = (condition: DynamicCondition) => {
    const typeConfig = conditionTypes[condition.type as keyof typeof conditionTypes];
    if (!typeConfig) return '未知条件';

    const operatorText =
      {
        gte: '≥',
        lte: '≤',
        eq: '=',
        ne: '≠'
      }[condition.operator] || '≥';

    return `${typeConfig.label} ${operatorText} ${condition.value}${typeConfig.unit}`;
  };

  // 🆕 根据条件类型获取Token值
  const getTokenValueByType = (token: any, type: string): number => {
    switch (type) {
      case 'confidence':
        return token.rank_confidence || token.confidence || 0;
      case 'score':
        return token.predicted_final_value || token.score || 0;
      case 'sample_count':
        return token.total_games || token.sample_count || 0;
      case 'win_rate':
        return (token.win_rate || 0) * 100; // 转换为百分比
      case 'top3_rate':
        return (token.top3_rate || 0) * 100; // 转换为百分比
      case 'avg_rank':
        return token.avg_rank || 3;
      case 'stability':
        return token.value_stddev || 0;
      case 'absolute_score':
        return token.absolute_score || 0;
      case 'relative_score':
        return token.relative_score || 0;
      case 'h2h_score':
        return token.h2h_score || 0;
      case 'change_5m':
        return (token.change_5m || 0) * 100; // 转换为百分比
      case 'change_1h':
        return (token.change_1h || 0) * 100; // 转换为百分比
      case 'change_4h':
        return (token.change_4h || 0) * 100; // 转换为百分比
      case 'change_24h':
        return (token.change_24h || 0) * 100; // 转换为百分比
      case 'momentum_score':
        return token.momentum_score || 0;
      case 'elo_win_rate':
        return token.elo_win_rate || 0;
      case 'momentum_confidence':
        return token.confidence || 0;
      default:
        return 0;
    }
  };

  // 🆕 评估单个条件
  const evaluateSingleCondition = (token: any, condition: DynamicCondition): boolean => {
    const tokenValue = getTokenValueByType(token, condition.type);

    switch (condition.operator) {
      case 'gte':
        return tokenValue >= condition.value;
      case 'lte':
        return tokenValue <= condition.value;
      case 'eq':
        return Math.abs(tokenValue - condition.value) < 0.001; // 浮点数比较
      case 'ne':
        return Math.abs(tokenValue - condition.value) >= 0.001;
      default:
        return true;
    }
  };

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
    hybridPredictions?: any[]; // 新增：Hybrid-Edge v1.0 動能預測數據
    hybridAnalysisMeta?: any; // 新增：Hybrid预测元数据
    hybridAnalysisLoading?: boolean; // 新增：Hybrid预测加载状态
    refreshHybridAnalysis?: () => void; // 新增：刷新Hybrid预测方法
  }

  const props = withDefaults(defineProps<Props>(), {
    hybridAnalysisLoading: false
  });

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

  // ==================== 动态条件构建器 ====================

  // 🆕 生成唯一ID
  const generateId = () => `condition_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

  // 🆕 添加条件
  const addCondition = () => {
    const newCondition: DynamicCondition = {
      id: generateId(),
      type: 'confidence',
      operator: 'gte',
      value: 85,
      logic: 'and'
    };
    const currentConditions = [...(props.config.dynamic_conditions || [])];
    currentConditions.push(newCondition);
    props.config.dynamic_conditions = currentConditions;
  };

  // 🆕 删除条件
  const removeCondition = (id: string) => {
    const currentConditions = [...(props.config.dynamic_conditions || [])];
    const index = currentConditions.findIndex((c) => c.id === id);
    if (index > -1) {
      currentConditions.splice(index, 1);
      props.config.dynamic_conditions = currentConditions;
    }
  };

  // 🆕 动态条件评估函数 (修正后)
  const evaluateDynamicConditions = (token: any): boolean => {
    const conditions = props.config.dynamic_conditions || [];
    // 如果没有条件，默认所有 Token 都通过
    if (conditions.length === 0) return true;

    // 1. 先计算第一个条件的结果作为初始值
    let result = evaluateSingleCondition(token, conditions[0]);

    // 2. 从第二个条件开始遍历
    for (let i = 1; i < conditions.length; i++) {
      const condition = conditions[i];
      const currentResult = evaluateSingleCondition(token, condition);

      // 3. 使用当前条件自身的 logic (and/or) 来与之前的结果进行组合
      if (condition.logic === 'and') {
        result = result && currentResult;
      } else {
        // or
        result = result || currentResult;
      }
    }

    return result;
  };

  // ==================== 工具函数 ====================

  const { getPredictionIcon } = usePredictionDisplay();

  // 🔍 检查是否有激活的高级过滤器
  const hasActiveAdvancedFilters = (): boolean => {
    return (
      props.config.enable_win_rate_filter ||
      props.config.enable_top3_rate_filter ||
      props.config.enable_avg_rank_filter ||
      props.config.enable_stability_filter ||
      props.config.enable_absolute_score_filter ||
      props.config.enable_relative_score_filter ||
      props.config.enable_h2h_score_filter ||
      props.config.enable_change_5m_filter ||
      props.config.enable_change_1h_filter ||
      props.config.enable_change_4h_filter ||
      props.config.enable_change_24h_filter
    );
  };

  // 🔍 各个过滤器的检查函数
  const checkWinRateFilter = (token: any): boolean => {
    return !props.config.enable_win_rate_filter || (token.win_rate || 0) >= props.config.min_win_rate_threshold;
  };

  const checkTop3RateFilter = (token: any): boolean => {
    return !props.config.enable_top3_rate_filter || (token.top3_rate || 0) >= props.config.min_top3_rate_threshold;
  };

  const checkAvgRankFilter = (token: any): boolean => {
    return !props.config.enable_avg_rank_filter || (token.avg_rank || 3) <= props.config.max_avg_rank_threshold;
  };

  const checkStabilityFilter = (token: any): boolean => {
    return !props.config.enable_stability_filter || (token.value_stddev || 0) <= props.config.max_stability_threshold;
  };

  const checkAbsoluteScoreFilter = (token: any): boolean => {
    return (
      !props.config.enable_absolute_score_filter ||
      (token.absolute_score || 0) >= props.config.min_absolute_score_threshold
    );
  };

  const checkRelativeScoreFilter = (token: any): boolean => {
    return (
      !props.config.enable_relative_score_filter ||
      (token.relative_score || 0) >= props.config.min_relative_score_threshold
    );
  };

  const checkH2HScoreFilter = (token: any): boolean => {
    return !props.config.enable_h2h_score_filter || (token.h2h_score || 0) >= props.config.min_h2h_score_threshold;
  };

  const checkChange5mFilter = (token: any): boolean => {
    if (!props.config.enable_change_5m_filter) return true;
    const change5m = token.change_5m || 0;
    return change5m >= props.config.min_change_5m_threshold && change5m <= props.config.max_change_5m_threshold;
  };

  const checkChange1hFilter = (token: any): boolean => {
    if (!props.config.enable_change_1h_filter) return true;
    const change1h = token.change_1h || 0;
    return change1h >= props.config.min_change_1h_threshold && change1h <= props.config.max_change_1h_threshold;
  };

  const checkChange4hFilter = (token: any): boolean => {
    if (!props.config.enable_change_4h_filter) return true;
    const change4h = token.change_4h || 0;
    return change4h >= props.config.min_change_4h_threshold && change4h <= props.config.max_change_4h_threshold;
  };

  const checkChange24hFilter = (token: any): boolean => {
    if (!props.config.enable_change_24h_filter) return true;
    const change24h = token.change_24h || 0;
    return change24h >= props.config.min_change_24h_threshold && change24h <= props.config.max_change_24h_threshold;
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

  // ==================== 本地状态管理 ====================

  // ==================== 数据获取函数 ====================

  // 获取初始预测数据
  const fetchInitialPredictionData = async () => {
    console.log('🔮 SmartControlCenter: 获取初始预测数据...');

    // 🔧 优化：检查store的初始化状态
    const predictionStore = useGamePredictionStore();
    if (predictionStore.isInitialized) {
      console.log('📦 SmartControlCenter: Store已初始化，跳过重复请求');
      return;
    }

    // 🔧 关键修复：如果store正在初始化，等待完成而不是重复请求
    if (predictionStore.initializationPromise) {
      console.log('⏳ SmartControlCenter: Store正在初始化，等待完成...');
      await predictionStore.initializationPromise;
      return;
    }

    // 🔧 关键修复：使用store的方法而不是直接调用API
    try {
      await predictionStore.fetchCurrentAnalysis();
      console.log(`✅ SmartControlCenter: 通过store成功获取初始预测数据`);
    } catch (error) {
      console.error('❌ SmartControlCenter: 获取初始预测数据失败:', error);
    }
  };

  // 刷新分析数据
  const refreshAnalysis = () => {
    console.log('🔄 SmartControlCenter: 手动刷新分析数据');
    emit('refreshAnalysis');
  };

  // 刷新动能预测数据
  const refreshHybridAnalysis = () => {
    console.log('⚡ SmartControlCenter: 手动刷新动能预测数据');
    if (props.refreshHybridAnalysis) {
      props.refreshHybridAnalysis();
    } else {
      emit('refreshAnalysis'); // 如果没有专门的动能刷新方法，使用通用刷新
    }
  };

  // ==================== 生命周期钩子 ====================

  onMounted(() => {
    console.log('🎛️ SmartControlCenter: 组件已挂载');

    // 🔧 优化：检查store的初始化状态
    const predictionStore = useGamePredictionStore();

    // 🔧 关键修复：增加更长的延迟，确保父组件的数据获取完成
    setTimeout(() => {
      // 如果store已初始化，直接检查数据
      if (predictionStore.isInitialized) {
        if (!props.currentAnalysis || props.currentAnalysis.length === 0) {
          console.log('🔮 SmartControlCenter: Store已初始化但无数据，主动获取中...');
          fetchInitialPredictionData();
        } else {
          console.log(`✅ SmartControlCenter: Store已初始化且有数据: ${props.currentAnalysis.length} 个Token`);
        }
      } else {
        // 如果store未初始化，等待初始化完成后再检查
        console.log('⏳ SmartControlCenter: Store未初始化，等待初始化完成...');
        let waitCount = 0;
        const maxWaitCount = 50; // 最大等待5秒 (50 * 100ms)
        const checkData = () => {
          if (predictionStore.isInitialized) {
            if (!props.currentAnalysis || props.currentAnalysis.length === 0) {
              console.log('🔮 SmartControlCenter: Store初始化完成但无数据，主动获取中...');
              fetchInitialPredictionData();
            } else {
              console.log(`✅ SmartControlCenter: Store初始化完成且有数据: ${props.currentAnalysis.length} 个Token`);
            }
          } else {
            // 继续等待，但增加最大等待时间限制
            waitCount++;
            if (waitCount < maxWaitCount) {
              setTimeout(checkData, 100);
            } else {
              console.warn('⚠️ SmartControlCenter: 等待store初始化超时，跳过数据获取');
            }
          }
        };
        checkData();
      }
    }, 500); // 🔧 关键修复：延迟500ms，确保父组件的数据获取完成
  });

  // 🔧 优化：监听预测数据变化，当数据清空时主动重新获取
  // 但增加防抖机制，避免频繁触发
  let dataCheckTimeout: NodeJS.Timeout | null = null;
  watch(
    () => props.currentAnalysis,
    (newAnalysis, oldAnalysis) => {
      // 清除之前的定时器
      if (dataCheckTimeout) {
        clearTimeout(dataCheckTimeout);
      }

      // 延迟检查，避免与父组件的数据获取冲突
      dataCheckTimeout = setTimeout(() => {
        // 如果从有数据变为无数据，或者一直没有数据，则主动获取
        if ((!newAnalysis || newAnalysis.length === 0) && (!oldAnalysis || oldAnalysis.length === 0)) {
          console.log('🔮 SmartControlCenter: 检测到预测数据缺失，尝试获取...');
          fetchInitialPredictionData();
        }
      }, 200); // 延迟200ms，确保父组件的数据获取完成
    },
    { immediate: false }
  );

  // 组件卸载时清理定时器
  onUnmounted(() => {
    if (dataCheckTimeout) {
      clearTimeout(dataCheckTimeout);
    }
  });

  // ==================== 调试面板状态和函数 ====================

  // 数据映射函数（复制自AutoBetting.vue）
  const mapPredictionData = (rawPrediction: any): any => {
    return {
      ...rawPrediction,
      confidence: rawPrediction.rank_confidence || rawPrediction.confidence || 0,
      score: rawPrediction.predicted_final_value || rawPrediction.score || 0,
      sample_count: rawPrediction.total_games || rawPrediction.sample_count || 0,
      historical_accuracy: (rawPrediction.win_rate || 0) / 100,
      symbol: rawPrediction.symbol,
      predicted_rank: rawPrediction.predicted_rank,
      // 🆕 复合型策略需要的数据
      momentum_rank: rawPrediction.momentum_rank || rawPrediction.predicted_rank || 999
    };
  };

  // 🆕 H2H策略评估逻辑
  const evaluateH2HPrediction = (prediction: any): boolean => {
    // 对于排名下注策略，首先检查排名是否在选中范围内
    if (props.config.strategy === 'rank_betting') {
      if (!props.config.rank_betting_enabled_ranks.includes(prediction.predicted_rank)) {
        return false;
      }
      // 即使是排名下注，也可以应用额外的过滤条件进行精细筛选
    } else {
      // 非排名下注策略的基础条件检查
      if (prediction.confidence < props.config.confidence_threshold) return false;
      if (prediction.score < props.config.score_gap_threshold) return false;
      if (prediction.sample_count < props.config.min_sample_count) return false;
      if (prediction.historical_accuracy * 100 < props.config.historical_accuracy_threshold) return false;
    }

    // 🔧 历史表现过滤器 - 修复数据单位统一问题
    // 胜率过滤器：如果胜率 < 门槛，则排除（保留胜率 >= 门槛的Token）
    if (props.config.enable_win_rate_filter && (prediction.win_rate || 0) < props.config.min_win_rate_threshold)
      return false;
    // 保本率过滤器：如果保本率 < 门槛，则排除（保留保本率 >= 门槛的Token）
    if (props.config.enable_top3_rate_filter && (prediction.top3_rate || 0) < props.config.min_top3_rate_threshold)
      return false;
    // 平均排名过滤器：如果平均排名 > 门槛，则排除（保留平均排名 <= 门槛的Token，排名越小越好）
    if (props.config.enable_avg_rank_filter && (prediction.avg_rank || 3) > props.config.max_avg_rank_threshold)
      return false;
    // 稳定性过滤器：如果波动性 > 门槛，则排除（保留波动性 <= 门槛的Token，波动越小越稳定）
    if (props.config.enable_stability_filter && (prediction.value_stddev || 0) > props.config.max_stability_threshold)
      return false;

    // 🔧 评分过滤器 - 修复数据单位统一问题
    // 绝对分数过滤器：如果绝对分数 < 门槛，则排除（保留绝对分数 >= 门槛的Token）
    if (
      props.config.enable_absolute_score_filter &&
      (prediction.absolute_score || 0) < props.config.min_absolute_score_threshold
    )
      return false;
    // 相对分数过滤器：如果相对分数 < 门槛，则排除（保留相对分数 >= 门槛的Token）
    if (
      props.config.enable_relative_score_filter &&
      (prediction.relative_score || 0) < props.config.min_relative_score_threshold
    )
      return false;
    // H2H分数过滤器：如果H2H分数 < 门槛，则排除（保留H2H分数 >= 门槛的Token）
    if (props.config.enable_h2h_score_filter && (prediction.h2h_score || 0) < props.config.min_h2h_score_threshold)
      return false;

    // 🔧 市场动态过滤器 - 范围检查逻辑正确
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

  // 🆕 动能策略评估逻辑
  const evaluateMomentumPrediction = (prediction: any): boolean => {
    // 动能策略使用不同的数据字段和评估标准
    const momentumScore = prediction.momentum_score || 0;
    const eloWinRate = prediction.elo_win_rate || 0;
    const confidence = prediction.confidence || 0;

    // 检查动能策略的三个核心条件
    if (momentumScore < props.config.min_momentum_score) return false;
    if (eloWinRate < props.config.min_elo_win_rate) return false;
    if (confidence < props.config.min_confidence) return false;

    return true;
  };

  // 🆕 复合型策略评估逻辑
  const evaluateHybridRankPrediction = (prediction: any): boolean => {
    // 获取AI预测排名和动能预测排名
    const h2hRank = prediction.predicted_rank || 999;
    const momentumRank = prediction.momentum_rank || 999;

    // 检查AI预测排名是否在选中范围内
    const h2hRankMatch = props.config.h2h_rank_enabled_ranks.includes(h2hRank);

    // 检查动能预测排名是否在选中范围内
    const momentumRankMatch = props.config.momentum_rank_enabled_ranks.includes(momentumRank);

    // 根据逻辑条件判断
    if (props.config.hybrid_rank_logic === 'and') {
      // "且"逻辑：必须同时满足两个条件
      return h2hRankMatch && momentumRankMatch;
    } else {
      // "或"逻辑：满足任一条件即可
      return h2hRankMatch || momentumRankMatch;
    }
  };

  // 🔧 评估预测是否符合策略条件 - 支持多策略类型
  const evaluatePredictionMatch = (prediction: any): boolean => {
    // 🆕 优先使用动态条件构建器
    if ((props.config.dynamic_conditions || []).length > 0) {
      return evaluateDynamicConditions(prediction);
    }

    // 🆕 如果没有动态条件，则使用原来的策略类型评估逻辑
    if (props.config.strategy_type === 'momentum') {
      return evaluateMomentumPrediction(prediction);
    } else if (props.config.strategy_type === 'hybrid_rank') {
      return evaluateHybridRankPrediction(prediction);
    } else {
      return evaluateH2HPrediction(prediction);
    }
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
        props.config.score_gap_threshold = 1; // 极低分数要求
        props.config.min_sample_count = 1; // 最少样本数
        props.config.historical_accuracy_threshold = 1; // 极低胜率 1%

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

  // 格式化价格变化显示
  const formatPriceChange = (change: number | null | undefined): string => {
    if (change === null || change === undefined) return '-';

    const prefix = change > 0 ? '+' : '';
    return `${prefix}${change.toFixed(2)}%`;
  };

  // Methods
  const startAutoBetting = () => emit('startAutoBetting');
  const stopAutoBetting = () => emit('stopAutoBetting');
  const executeManualBetting = () => emit('executeManualBetting');
  const applyStrategyTemplate = (key: string) => {
    // 获取选中的模板
    const template = props.strategyTemplatesWithCustom[key];
    if (template && template.strategy_type) {
      // 自动设置策略类型
      props.config.strategy_type = template.strategy_type;
    }
    // 应用模板配置
    emit('applyStrategyTemplate', key);
  };
  const executeStrategyBetting = () => emit('executeStrategyBetting');
  const manualSaveConfig = () => emit('manualSaveConfig');

  // [新增] 创建计算属性来动态选择数据源
  const displayAnalysisData = computed(() => {
    if (props.config.strategy_type === 'momentum') {
      return props.hybridPredictions || [];
    } else if (props.config.strategy_type === 'hybrid_rank') {
      // 🆕 复合型策略：需要同时有AI预测和动能预测数据
      const h2hData = props.currentAnalysis || [];
      const momentumData = props.hybridPredictions || [];

      // 合并数据，确保每个Token都有两种预测的排名信息
      const combinedData = h2hData.map((h2hToken: any) => {
        const momentumToken = momentumData.find((m: any) => m.symbol === h2hToken.symbol);
        return {
          ...h2hToken,
          momentum_rank: momentumToken?.predicted_rank || 999
        };
      });

      return combinedData;
    }
    return props.currentAnalysis || [];
  });

  // 直接显示所有策略模板，不进行过滤
  const strategyTemplatesWithCustom = computed(() => {
    return props.strategyTemplatesWithCustom;
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
</style>
