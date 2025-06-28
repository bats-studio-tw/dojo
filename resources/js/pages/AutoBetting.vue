<template>
  <DefaultLayout>
    <Head title="自动下注控制" />

    <!-- 身份验证模态框 -->
    <WalletSetup :visible="!isTokenValidated" @validated="onTokenValidated" />

    <div
      v-if="isTokenValidated"
      class="min-h-screen from-slate-900 via-purple-900 to-slate-900 bg-gradient-to-br p-3 sm:p-6"
    >
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

        <!-- 系统状态监控 -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="📈 系统状态监控"
          size="large"
        >
          <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- 用户信息卡片 -->
            <div class="border border-blue-500/30 rounded-lg bg-blue-500/10 p-4">
              <div class="mb-2 flex items-center space-x-2">
                <span class="text-lg">👤</span>
                <span class="text-sm text-blue-400 font-medium">用户信息</span>
              </div>

              <div v-if="userInfo" class="text-sm text-gray-300 space-y-2">
                <div class="flex justify-between">
                  <span>用户ID:</span>
                  <span class="text-xs text-blue-400 font-mono">{{ userInfo.uid }}</span>
                </div>
                <div class="flex justify-between">
                  <span>可用余额:</span>
                  <span class="text-green-400 font-semibold">${{ userInfo.available.toFixed(2) }}</span>
                </div>
                <div class="flex justify-between">
                  <span>排名:</span>
                  <span class="text-blue-400">{{ userInfo.rankPercent }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span>状态:</span>
                  <n-button
                    @click="reconnectToken"
                    :disabled="autoBettingStatus.is_running"
                    type="tertiary"
                    size="tiny"
                  >
                    重新验证
                  </n-button>
                </div>
              </div>
            </div>

            <!-- 自动下注状态 -->
            <div class="border border-green-500/30 rounded-lg bg-green-500/10 p-4">
              <div class="mb-2 flex items-center space-x-2">
                <span class="text-lg">⚙️</span>
                <span class="text-sm text-green-400 font-medium">自动下注状态</span>
              </div>

              <div class="text-sm text-gray-300 space-y-2">
                <div class="flex items-center justify-between">
                  <span>运行状态:</span>
                  <n-tag :type="autoBettingStatus.is_running ? 'success' : 'default'" size="small">
                    {{ autoBettingStatus.is_running ? '运行中' : '已停止' }}
                  </n-tag>
                </div>
                <div class="flex justify-between">
                  <span>总下注次数:</span>
                  <span class="text-green-400 font-semibold">{{ autoBettingStatus.total_bets }}</span>
                </div>
                <div class="flex justify-between">
                  <span>总盈亏:</span>
                  <span
                    class="font-semibold"
                    :class="autoBettingStatus.total_profit_loss >= 0 ? 'text-green-400' : 'text-red-400'"
                  >
                    ${{ autoBettingStatus.total_profit_loss.toFixed(2) }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span>今日盈亏:</span>
                  <span
                    class="font-semibold"
                    :class="autoBettingStatus.today_profit_loss >= 0 ? 'text-green-400' : 'text-red-400'"
                  >
                    ${{ autoBettingStatus.today_profit_loss.toFixed(2) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- 当前分析数据 -->
            <div class="border border-purple-500/30 rounded-lg bg-purple-500/10 p-4">
              <div class="mb-2 flex items-center space-x-2">
                <span class="text-lg">🎯</span>
                <span class="text-sm text-purple-400 font-medium">当前分析数据</span>
              </div>

              <div v-if="currentAnalysis" class="text-sm text-gray-300 space-y-2">
                <div class="flex justify-between">
                  <span>当前轮次:</span>
                  <span class="text-purple-400 font-mono">{{ currentAnalysis.round_id }}</span>
                </div>
                <div class="flex justify-between">
                  <span>游戏数量:</span>
                  <span class="text-purple-400">{{ currentAnalysis.predictions?.length || 0 }}</span>
                </div>
                <div class="flex justify-between">
                  <span>数据状态:</span>
                  <n-tag :type="getStatusTagType(currentAnalysis.status)" size="small">
                    {{ currentAnalysis.status }}
                  </n-tag>
                </div>
                <div class="flex justify-between">
                  <span>更新时间:</span>
                  <span class="text-xs text-purple-400">
                    {{ new Date(currentAnalysis.updated_at).toLocaleTimeString() }}
                  </span>
                </div>
              </div>
              <div v-else class="text-center text-gray-400">
                <NEmpty description="暂无分析数据" />
              </div>
            </div>
          </div>

          <!-- 控制按钮 -->
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
              @click="executeAutoBetting"
              :loading="executeLoading"
              :disabled="!autoBettingStatus.is_running"
              type="warning"
              size="large"
              class="shadow-lg shadow-orange-500/25 hover:shadow-orange-500/40"
            >
              <template #icon>
                <span>🎯</span>
              </template>
              手动执行一次
            </n-button>

            <n-button
              @click="refreshAnalysis"
              :loading="analysisLoading"
              type="info"
              size="large"
              class="shadow-blue-500/25 shadow-lg hover:shadow-blue-500/40"
            >
              <template #icon>
                <span>🔄</span>
              </template>
              刷新数据
            </n-button>
          </div>
        </NCard>

        <!-- 配置面板 -->
        <NCard
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="⚙️ 自动下注配置"
          size="large"
        >
          <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- 基础配置 -->
            <div class="space-y-4">
              <h3 class="mb-4 text-lg text-white font-semibold">📊 基础配置</h3>

              <!-- JWT Token -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">JWT Token</label>
                <n-input
                  v-model:value="config.jwt_token"
                  placeholder="JWT Token会自动填入"
                  type="password"
                  show-password-on="click"
                  :disabled="autoBettingStatus.is_running"
                >
                  <template #prefix>
                    <span class="text-gray-400">🔑</span>
                  </template>
                </n-input>
                <div class="text-xs text-gray-400">用于执行下注操作的授权令牌</div>
              </div>

              <!-- 资金池 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">资金池 (USD)</label>
                <n-input-number
                  v-model:value="config.bankroll"
                  :min="100"
                  :max="50000"
                  :step="100"
                  :disabled="autoBettingStatus.is_running"
                  class="w-full"
                />
                <div class="text-xs text-gray-400">总可用资金，用于计算下注金额比例</div>
              </div>

              <!-- 单次下注金额 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">单次下注金额 (USD)</label>
                <n-input-number
                  v-model:value="config.bet_amount"
                  :min="10"
                  :max="2000"
                  :step="10"
                  :disabled="autoBettingStatus.is_running"
                  class="w-full"
                />
                <div class="text-xs text-gray-400">每次下注的固定金额</div>
              </div>

              <!-- 每日止损百分比 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">每日止损百分比 (%)</label>
                <n-input-number
                  v-model:value="config.daily_stop_loss_percentage"
                  :min="5"
                  :max="50"
                  :step="5"
                  :disabled="autoBettingStatus.is_running"
                  class="w-full"
                />
                <div class="text-xs text-gray-400">达到此损失比例时停止当日下注</div>
              </div>
            </div>

            <!-- 高级配置 -->
            <div class="space-y-4">
              <h3 class="mb-4 text-lg text-white font-semibold">🎯 策略配置</h3>

              <!-- 置信度阈值 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">置信度阈值 (%)</label>
                <n-input-number
                  v-model:value="config.confidence_threshold"
                  :min="70"
                  :max="99"
                  :step="1"
                  :disabled="autoBettingStatus.is_running"
                  class="w-full"
                />
                <div class="text-xs text-gray-400">只有当预测置信度高于此值时才下注</div>
              </div>

              <!-- 分数差距阈值 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">分数差距阈值</label>
                <n-input-number
                  v-model:value="config.score_gap_threshold"
                  :min="3.0"
                  :max="20.0"
                  :step="0.5"
                  :disabled="autoBettingStatus.is_running"
                  :precision="1"
                  class="w-full"
                />
                <div class="text-xs text-gray-400">预测分数与次高分数的最小差距要求</div>
              </div>

              <!-- 最小游戏数量 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">最小游戏数量</label>
                <n-input-number
                  v-model:value="config.min_total_games"
                  :min="10"
                  :max="100"
                  :step="5"
                  :disabled="autoBettingStatus.is_running"
                  class="w-full"
                />
                <div class="text-xs text-gray-400">当前轮次至少需要的游戏数量才触发下注</div>
              </div>

              <!-- 下注策略 -->
              <div class="space-y-2">
                <label class="text-sm text-gray-300 font-medium">下注策略</label>
                <n-select
                  v-model:value="config.strategy"
                  :options="[
                    { label: '单项下注 - 只下注最高置信度选项', value: 'single_bet' },
                    { label: '多项下注 - 下注所有符合条件的选项', value: 'multi_bet' },
                    { label: '对冲下注 - 下注前两个最高置信度选项', value: 'hedge_bet' }
                  ]"
                  :disabled="autoBettingStatus.is_running"
                />
                <div class="text-xs text-gray-400">选择自动下注的执行策略</div>
              </div>
            </div>
          </div>

          <!-- 保存配置按钮 -->
          <div class="mt-6 text-center">
            <n-button @click="saveConfig" :disabled="autoBettingStatus.is_running" type="primary" size="large">
              <template #icon>
                <span>💾</span>
              </template>
              保存配置
            </n-button>
          </div>
        </NCard>

        <!-- 当前分析详情 -->
        <NCard
          v-if="currentAnalysis?.predictions && currentAnalysis.predictions.length > 0"
          class="mb-6 border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
          title="🎮 当前轮次游戏分析"
          size="large"
        >
          <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 sm:grid-cols-2">
            <div
              v-for="(prediction, index) in currentAnalysis.predictions"
              :key="index"
              class="border border-gray-500/30 rounded-lg bg-gray-500/10 p-4"
            >
              <div class="mb-2 flex items-center justify-between">
                <span class="text-sm text-gray-300 font-medium">{{ prediction.symbol }}</span>
                <n-tag :type="prediction.confidence > config.confidence_threshold ? 'success' : 'default'" size="small">
                  {{ prediction.confidence.toFixed(1) }}%
                </n-tag>
              </div>

              <div class="text-xs text-gray-400 space-y-1">
                <div>预测方向: {{ prediction.direction }}</div>
                <div>分数: {{ prediction.score.toFixed(2) }}</div>
                <div>历史胜率: {{ (prediction.historical_accuracy * 100).toFixed(1) }}%</div>
                <div>样本数量: {{ prediction.sample_count }}</div>
              </div>

              <div v-if="prediction.confidence > config.confidence_threshold" class="mt-2">
                <n-tag type="success" size="small">符合下注条件</n-tag>
              </div>
            </div>
          </div>
        </NCard>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { ref, onMounted, watch } from 'vue';
  import { NEmpty, useMessage } from 'naive-ui';
  import { Head } from '@inertiajs/vue3';
  import { getUserInfo, autoBettingApi, gameApi } from '@/utils/api';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import WalletSetup from '@/components/WalletSetup.vue';
  import type { UserInfo } from '@/types';

  // 延迟获取message实例，避免在providers还未准备好时调用
  const getMessageInstance = () => {
    try {
      return useMessage();
    } catch {
      console.warn('Message provider not ready yet');
      return null;
    }
  };

  // 预设配置
  const defaultConfig = {
    jwt_token: '',
    bankroll: 1000,
    bet_amount: 200,
    daily_stop_loss_percentage: 15,
    confidence_threshold: 88,
    score_gap_threshold: 6.0,
    min_total_games: 25,
    strategy: 'single_bet' as const
  };

  // 身份验证状态
  const isTokenValidated = ref(false);
  const currentUID = ref('');
  const userInfo = ref<UserInfo | null>(null);

  // 自动下注配置 - 使用localStorage
  const config = ref({ ...defaultConfig });

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
  const currentAnalysis = ref<any>(null);

  // 加载状态
  const statusLoading = ref(false);
  const toggleLoading = ref(false);
  const executeLoading = ref(false);
  const analysisLoading = ref(false);

  // 获取分析数据
  const fetchAnalysisData = async () => {
    analysisLoading.value = true;
    try {
      const response = await gameApi.getCurrentAnalysis();
      if (response.data.success) {
        currentAnalysis.value = response.data.data;
      } else {
        console.error('获取分析数据失败:', response.data.message);
      }
    } catch (error) {
      console.error('获取分析数据失败:', error);
    } finally {
      analysisLoading.value = false;
    }
  };

  // 获取状态标签类型
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

  // 执行单次下注的通用方法
  const executeSingleBet = async (
    roundId: string,
    tokenSymbol: string,
    amount: number,
    jwtToken: string
  ): Promise<boolean> => {
    try {
      // 第一步：获取betId
      const betIdResponse = await gameApi.getBetId(roundId, jwtToken);

      if (!betIdResponse.data.success) {
        console.error('获取betId失败:', betIdResponse.data);
        return false;
      }

      const betId = betIdResponse.data.data;

      // 第二步：执行下注
      const betResponse = await gameApi.placeBet(roundId, betId, tokenSymbol, amount, jwtToken);

      if (betResponse.data.success) {
        // 记录下注结果到后端
        await autoBettingApi.recordResult({
          uid: currentUID.value,
          round_id: roundId,
          token_symbol: tokenSymbol,
          amount,
          bet_id: betId,
          success: true,
          result_data: betResponse.data.data
        });
        return true;
      } else {
        console.error('下注失败:', betResponse.data);
        // 记录失败结果
        await autoBettingApi.recordResult({
          uid: currentUID.value,
          round_id: roundId,
          token_symbol: tokenSymbol,
          amount,
          bet_id: betId,
          success: false,
          result_data: betResponse.data
        });
        return false;
      }
    } catch (error) {
      console.error('下注过程出错:', error);
      return false;
    }
  };

  // API调用函数
  const loadStatus = async () => {
    if (!currentUID.value) return;

    statusLoading.value = true;
    try {
      const response = await autoBettingApi.getStatus(currentUID.value);
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
      const response = await autoBettingApi.toggle('start', currentUID.value);
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
      const response = await autoBettingApi.toggle('stop', currentUID.value);
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

  const executeAutoBetting = async () => {
    executeLoading.value = true;
    try {
      // 先获取下注建议
      const response = await autoBettingApi.execute(currentUID.value, config.value);
      if (response.data.success) {
        const { recommended_bets, round_id, jwt_token } = response.data.data;

        getMessageInstance()?.info('开始执行自动下注...');

        let successCount = 0;
        let failCount = 0;

        // 对每个推荐下注执行API调用
        for (const bet of recommended_bets) {
          try {
            const betSuccess = await executeSingleBet(round_id, bet.symbol, bet.bet_amount, jwt_token);
            if (betSuccess) {
              successCount++;
            } else {
              failCount++;
            }
          } catch (error) {
            console.error(`下注失败 ${bet.symbol}:`, error);
            failCount++;
          }
        }

        if (successCount > 0) {
          getMessageInstance()?.success(`自动下注完成：成功 ${successCount} 个，失败 ${failCount} 个`);
        } else {
          getMessageInstance()?.error('自动下注全部失败');
        }

        await loadStatus();
      } else {
        getMessageInstance()?.error(response.data.message || '获取下注建议失败');
      }
    } catch (error) {
      console.error('执行自动下注失败:', error);
      getMessageInstance()?.error('执行自动下注失败');
    } finally {
      executeLoading.value = false;
    }
  };

  const refreshAnalysis = () => fetchAnalysisData();

  // 重新验证Token
  const reconnectToken = () => {
    // 清除所有保存的验证状态
    localStorage.removeItem('tokenValidated');
    localStorage.removeItem('currentUID');
    localStorage.removeItem('tokenSetupData');
    localStorage.removeItem('userInfo');

    // 重置状态
    isTokenValidated.value = false;
    currentUID.value = '';
    userInfo.value = null;
    config.value.jwt_token = '';

    getMessageInstance()?.info('已清除验证状态，请重新验证');
  };

  // Token验证成功回调
  const onTokenValidated = (data: {
    uid: string;
    jwt_token: string;
    user_stats: any;
    today_stats: any;
    user_info: UserInfo;
  }) => {
    console.log('接收到Token验证成功事件:', data);

    currentUID.value = data.uid;
    config.value.jwt_token = data.jwt_token;
    userInfo.value = data.user_info;
    isTokenValidated.value = true;

    console.log('设置状态:', {
      currentUID: currentUID.value,
      isTokenValidated: isTokenValidated.value,
      userInfo: userInfo.value
    });

    // 保存验证状态到localStorage
    localStorage.setItem('tokenValidated', 'true');
    localStorage.setItem('currentUID', data.uid);
    if (data.user_info) {
      localStorage.setItem('userInfo', JSON.stringify(data.user_info));
    }

    // 刷新状态和数据
    loadStatus();
    fetchAnalysisData();

    console.log('Token验证完成，界面应该切换了');
  };

  // 从localStorage读取配置
  const loadConfigFromLocalStorage = () => {
    const savedConfig = localStorage.getItem('autoBettingConfig');
    if (savedConfig) {
      try {
        const parsed = JSON.parse(savedConfig);
        config.value = { ...defaultConfig, ...parsed };
      } catch (error) {
        console.error('加载保存的配置失败:', error);
        config.value = { ...defaultConfig };
      }
    }
  };

  // 保存配置到localStorage
  const saveConfig = () => {
    localStorage.setItem('autoBettingConfig', JSON.stringify(config.value));
    getMessageInstance()?.success('配置已保存');
  };

  // 监听配置变化，自动保存
  watch(
    config,
    () => {
      localStorage.setItem('autoBettingConfig', JSON.stringify(config.value));
    },
    { deep: true, flush: 'post' }
  );

  onMounted(async () => {
    console.log('AutoBetting组件初始化');

    // 从localStorage读取配置
    loadConfigFromLocalStorage();

    // 检查Token验证状态
    const savedTokenValidated = localStorage.getItem('tokenValidated');
    const savedUID = localStorage.getItem('currentUID');
    const savedTokenData = localStorage.getItem('tokenSetupData');

    console.log('检查保存的验证状态:', {
      savedTokenValidated,
      savedUID,
      savedTokenData
    });

    if (savedTokenValidated === 'true' && savedUID && savedTokenData) {
      try {
        const tokenData = JSON.parse(savedTokenData);
        currentUID.value = savedUID;
        config.value.jwt_token = tokenData.jwt_token;
        isTokenValidated.value = true;

        // 恢复用户信息
        const savedUserInfo = localStorage.getItem('userInfo');
        if (savedUserInfo) {
          try {
            userInfo.value = JSON.parse(savedUserInfo);
          } catch (error) {
            console.error('恢复用户信息失败:', error);
          }
        }

        // 重新获取最新的用户信息
        if (tokenData.jwt_token) {
          try {
            const userInfoResponse = await getUserInfo(tokenData.jwt_token);
            userInfo.value = userInfoResponse.obj || userInfoResponse;
            localStorage.setItem('userInfo', JSON.stringify(userInfo.value));
          } catch (error) {
            console.warn('获取最新用户信息失败:', error);
          }
        }

        console.log('自动恢复Token验证状态:', {
          currentUID: currentUID.value,
          isTokenValidated: isTokenValidated.value,
          userInfo: userInfo.value
        });

        loadStatus();
        fetchAnalysisData();

        // 定时刷新状态和分析数据
        setInterval(() => {
          loadStatus();
          fetchAnalysisData();
        }, 5000);
      } catch (error) {
        console.error('恢复验证状态失败:', error);
        // 清除错误的保存状态
        localStorage.removeItem('tokenValidated');
        localStorage.removeItem('currentUID');
        localStorage.removeItem('tokenSetupData');
        localStorage.removeItem('userInfo');
      }
    }
  });
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
