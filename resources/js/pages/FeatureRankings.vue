<template>
  <DefaultLayout>
    <Head title="特征排名 (本局)" />

    <div class="min-h-screen from-slate-900 via-slate-800 to-slate-900 bg-gradient-to-br">
      <!-- 漂亮版 顶部状态栏（对齐 AutoBetting 风格） -->
      <div class="status-bar">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <!-- 左侧：标题与副标题 -->
            <div class="flex-1">
              <div class="flex items-center gap-3">
                <div class="icon-container float-animation h-10 w-10 flex items-center justify-center rounded-lg">
                  <span class="text-xl">🎯</span>
                </div>
                <div>
                  <h1 class="gradient-text text-xl font-bold sm:text-2xl">特征排名（本局，自动刷新）</h1>
                  <div class="mt-1 text-sm text-white/60">特征驱动 · 本地聚合</div>
                </div>
              </div>
            </div>

            <!-- 右侧：状态指示器 -->
            <div class="flex flex-wrap items-center gap-3 lg:flex-nowrap">
              <!-- WebSocket状态 -->
              <div
                class="status-indicator flex items-center gap-2 border rounded-lg px-3 py-2 text-sm transition-all duration-300"
                :class="getWebSocketStatusClass()"
              >
                <span>{{ getWebSocketStatusIcon() }}</span>
                <span>{{ websocketStatus.message }}</span>
                <button
                  v-if="!isConnected"
                  @click="websocketManager.manualReconnect()"
                  class="text-xs underline opacity-80 transition-opacity hover:opacity-100"
                >
                  重连
                </button>
              </div>

              <!-- 游戏状态与轮次 -->
              <div class="status-indicator border border-blue-500/20 rounded-lg bg-blue-500/5 px-3 py-2 text-sm">
                <div class="flex items-center gap-2">
                  <span class="text-blue-400">状态</span>
                  <span :class="gameStatus === 'bet' ? 'text-green-400' : 'text-white/70'">{{ gameStatus }}</span>
                </div>
                <div class="mt-0.5 text-xs text-blue-300 font-mono">轮次 {{ roundId }}</div>
              </div>

              <!-- 用户信息 -->
              <div
                v-if="userInfo"
                class="status-indicator border border-blue-500/20 rounded-lg bg-blue-500/5 px-3 py-2 text-sm"
              >
                <div class="text-xs text-blue-400">UID</div>
                <div class="flex items-center gap-2">
                  <span class="text-blue-300 font-mono">{{ String(userInfo.uid).slice(0, 12) }}...</span>
                  <div class="flex gap-1">
                    <NButton @click="reconnectToken" type="tertiary" size="tiny" class="!h-5 !text-xs">登出</NButton>
                    <NButton
                      @click="refreshUserInfo"
                      :loading="userInfoRefreshing"
                      type="tertiary"
                      size="tiny"
                      class="!h-5 !text-xs"
                    >
                      刷新
                    </NButton>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mx-auto max-w-7xl p-4 sm:p-6">
        <!-- 紧凑榜 -->
        <FeatureCompactBoard :matrix="matrix || null" class="mb-4" />
        <!-- 自动下注状态面板（与AutoBetting一致的摘要卡片） -->
        <div class="mb-6">
          <AutoBettingStatusPanel
            :betting-mode="bettingMode"
            :user-info="displayUserInfo"
            :auto-betting-status="autoBettingStatus"
            :strategy-validation="strategyValidation"
            :is-running="autoBettingStatus?.is_running || false"
            :toggle-loading="toggleLoading"
            :enable-mode-switch="true"
            @start="startAutoBetting"
            @stop="stopAutoBetting"
            @change-mode="onChangeBettingMode"
          />
        </div>

        <!-- 条件面板  -->
        <V3ConditionPanel :matrix="matrix || null" />

        <!-- V3 一键下注（基于当前名次条件与 TopN） -->
        <div class="mt-4 flex items-center gap-3">
          <NButton type="primary" :disabled="placingBets" :loading="placingBets" @click="placeBetsByV3()">
            <template #icon>
              <span>🤖</span>
            </template>
            按当前条件下注
          </NButton>
          <div class="text-xs text-white/60">将根据当前名次条件筛选并截取 Top {{ v3TopN }}，逐个下单</div>
        </div>

        <!-- 登录/账户设置复用组件 -->
        <WalletSetup :visible="showWalletSetup" @validated="onWalletValidated" />

        <div class="space-y-6">
          <NEmpty
            v-if="!(matrix && matrix.features && matrix.features.length)"
            description="暂无特征数据"
            class="py-8"
          />
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue';
  import { Head } from '@inertiajs/vue3';
  import { NEmpty, NButton } from 'naive-ui';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import FeatureCompactBoard from '@/components/FeatureCompactBoard.vue';
  import V3ConditionPanel from '@/components/V3ConditionPanel.vue';
  import AutoBettingStatusPanel from '@/components/AutoBettingStatusPanel.vue';
  import { useFeatureStore } from '@/stores/featureStore';
  import { websocketManager } from '@/utils/websocketManager';
  import { jwtTokenUtils, getUserInfo } from '@/utils/api';
  import { useGamePredictionStore } from '@/stores/gamePrediction';
  import WalletSetup from '@/components/WalletSetup.vue';
  import { storeToRefs } from 'pinia';
  import type { UserInfo, GetUserInfoResponse } from '@/types';
  import type { WebSocketStatus as WS } from '@/utils/websocketManager';
  import type { GameDataUpdateEvent } from '@/stores/gamePrediction';
  import { useAutoBettingControl } from '@/composables/useAutoBettingControl';
  import { useV3Conditions } from '@/composables/useV3Conditions';

  const store = useFeatureStore();
  const matrix = computed(() => store.matrix);
  // 页面自动刷新，不使用loading状态显示
  // const loading = computed(() => store.loading);
  // 紧凑榜已覆盖主用例，下面列表已移除
  const websocketStatus = websocketManager.websocketStatus;
  const isConnected = websocketManager.isConnected;

  // JWT 门控（复用 WalletSetup）
  const tokenValidated = ref<boolean>(!!localStorage.getItem('tokenValidated'));
  const showWalletSetup = ref<boolean>(!tokenValidated.value);
  const jwtToken = ref<string>('');
  const userInfo = ref<UserInfo | null>(null);

  type WalletValidatedPayload = { jwt_token?: string; user_info?: UserInfo };
  function onWalletValidated(e: WalletValidatedPayload) {
    tokenValidated.value = true;
    showWalletSetup.value = false;
    jwtToken.value = e?.jwt_token ?? '';
    userInfo.value = e?.user_info ?? null;
  }

  // 顶部状态（参考 AutoBetting）
  const predictionStore = useGamePredictionStore();
  const { currentGameStatus, currentRoundId } = storeToRefs(predictionStore);
  const gameStatus = computed(() => currentGameStatus.value || 'unknown');
  // 预留：如需映射中文可在此处扩展
  const roundId = computed(() => currentRoundId.value || '—');

  // 自动下注状态（读取与控制）
  const {
    userInfo: abUserInfo,
    autoBettingStatus,
    toggleLoading,
    startAutoBetting,
    stopAutoBetting,
    restoreAuthState,
    loadStatus,
    executeSingleBet
  } = useAutoBettingControl();
  const bettingMode = computed<'real' | 'dummy'>(() => {
    // 简化：若有存储的配置则读取，否则默认real
    const cfg = localStorage.getItem('autoBettingConfig');
    try {
      const parsed = cfg ? JSON.parse(cfg) : null;
      return (parsed?.betting_mode as 'real' | 'dummy') || 'real';
    } catch {
      return 'real';
    }
  });
  function onChangeBettingMode(mode: 'real' | 'dummy') {
    const cfg = localStorage.getItem('autoBettingConfig');
    const parsed = cfg ? JSON.parse(cfg) : {};
    parsed.betting_mode = mode;
    localStorage.setItem('autoBettingConfig', JSON.stringify(parsed));
  }
  const displayUserInfo = computed<UserInfo | null>(() => abUserInfo.value || userInfo.value);
  const strategyValidation = ref<{
    total_matched?: number;
    required_balance?: number;
    balance_sufficient?: boolean;
  } | null>(null);

  // =============== V3 条件（名次驱动）===============
  const v3 = useV3Conditions(() => store.matrix);
  v3.loadFromLocalStorage();
  const v3TopN = computed(() => Math.max(1, v3.topN.value || 1));
  const eligibleTokens = computed<string[]>(() => v3.filterTokens());
  const selectedTokens = computed<string[]>(() => eligibleTokens.value.slice(0, v3TopN.value));

  // 下单开关
  const placingBets = ref(false);
  // 本地下注金额规则（与自动下注页一致）
  const calculateBetAmount = (): number => (bettingMode.value === 'real' ? 200 : 5);

  // 一键下注（基于V3）
  const placeBetsByV3 = async () => {
    if (!tokenValidated.value || !jwtToken.value) {
      window.$message?.warning('请先完成身份验证');
      return;
    }

    const roundIdVal = currentRoundId.value;
    if (!roundIdVal) {
      window.$message?.warning('暂无当前轮次，稍后再试');
      return;
    }

    const tokens = selectedTokens.value;
    if (!tokens.length) {
      window.$message?.warning('当前条件没有可下注的Token');
      return;
    }

    // 余额校验
    const betAmount = calculateBetAmount();
    const total = betAmount * tokens.length;
    const balance =
      bettingMode.value === 'real' ? displayUserInfo.value?.ojoValue || 0 : displayUserInfo.value?.available || 0;
    if (total > balance) {
      window.$message?.error(`余额不足：需要 $${total.toFixed(0)}，当前 $${(balance || 0).toFixed(0)}`);
      return;
    }

    // 更新摘要面板
    strategyValidation.value = {
      total_matched: tokens.length,
      required_balance: total,
      balance_sufficient: true
    };

    placingBets.value = true;
    let success = 0;
    let fail = 0;
    for (const symbol of tokens) {
      try {
        const ok = await executeSingleBet(roundIdVal, symbol, betAmount, jwtToken.value, bettingMode.value);
        if (ok) success++;
        else fail++;
        await new Promise((r) => setTimeout(r, 400));
      } catch {
        fail++;
      }
    }
    placingBets.value = false;
    if (success) window.$message?.success(`下注完成：成功 ${success}，失败 ${fail}`);
    else window.$message?.error('下注失败');
    // 刷新状态
    loadStatus();
  };

  function reconnectToken() {
    localStorage.removeItem('tokenValidated');
    localStorage.removeItem('currentUID');
    localStorage.removeItem('tokenSetupData');
    localStorage.removeItem('userInfo');
    tokenValidated.value = false;
    userInfo.value = null;
    jwtToken.value = '';
    showWalletSetup.value = true;
  }

  const userInfoRefreshing = ref(false);
  async function refreshUserInfo() {
    if (!jwtToken.value) return;
    try {
      userInfoRefreshing.value = true;
      const res = (await getUserInfo(jwtToken.value)) as GetUserInfoResponse;
      if (res?.obj) {
        userInfo.value = res.obj;
        localStorage.setItem('userInfo', JSON.stringify(userInfo.value));
      }
    } finally {
      userInfoRefreshing.value = false;
    }
  }

  const refresh = () => store.maybeFetchAfterTimeout();

  // WebSocket状态样式与图标（与 AutoBetting 保持一致）
  const getWebSocketStatusClass = () => {
    const status = (websocketManager.websocketStatus as unknown as { value: WS }).value.status;
    switch (status) {
      case 'connected':
        return 'bg-green-500/10 border border-green-500/20 text-green-400';
      case 'connecting':
        return 'bg-yellow-500/10 border border-yellow-500/20 text-yellow-400';
      case 'disconnected':
        return 'bg-gray-500/10 border border-gray-500/20 text-gray-400';
      case 'error':
        return 'bg-red-500/10 border border-red-500/20 text-red-400';
      default:
        return 'bg-gray-500/10 border border-gray-500/20 text-gray-400';
    }
  };

  const getWebSocketStatusIcon = () => {
    const status = (websocketManager.websocketStatus as unknown as { value: WS }).value.status;
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

  // 旧的单特征卡片已移除

  onMounted((): void => {
    if (!websocketManager.isInitialized) websocketManager.initialize();
    // 恢复本地存储的token
    const saved = jwtTokenUtils.getStoredToken();
    if (saved) jwtToken.value = saved;
    // 初始化预测相关数据，保证有轮次/状态
    predictionStore.fetchInitialData().catch(() => {});
    // 首次拉取（若短时间内未收到推送将兜底请求）
    refresh();
    // 订阅特征矩阵推送，减少HTTP压力
    store.subscribeFeatureMatrixPush();
    // 监听游戏事件，进入bet或新轮次变化时刷新特征矩阵
    websocketManager.listenToGameUpdates((event: GameDataUpdateEvent) => {
      if (event?.data) {
        predictionStore.updateGameData(event.data);
        if (event.data.status === 'bet' && event.data.rdId) {
          refresh();
        }
      }
    });

    // 尝试恢复自动下注的本地登录态并拉取最新状态/用户信息
    restoreAuthState().then(() => {
      loadStatus();
      if (!userInfo.value && jwtToken.value) {
        refreshUserInfo();
      }
    });
  });
</script>

<style scoped>
  /* 与 AutoBetting 对齐的美化样式 */
  .status-bar {
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.1));
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .icon-container {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    box-shadow:
      0 4px 6px -1px rgba(59, 130, 246, 0.25),
      0 2px 4px -1px rgba(59, 130, 246, 0.06);
    transition: all 0.3s ease;
  }

  .icon-container:hover {
    transform: scale(1.05);
    box-shadow:
      0 8px 25px -5px rgba(59, 130, 246, 0.25),
      0 10px 10px -5px rgba(59, 130, 246, 0.04);
  }

  .gradient-text {
    background: linear-gradient(135deg, #60a5fa, #a78bfa, #f472b6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .float-animation {
    animation: float 3s ease-in-out infinite;
  }

  @keyframes float {
    0%,
    100% {
      transform: translateY(0px);
    }
    50% {
      transform: translateY(-5px);
    }
  }

  .status-indicator {
    position: relative;
    overflow: hidden;
  }

  .status-indicator::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s;
  }

  .status-indicator:hover::before {
    left: 100%;
  }

  .pulse-dot {
    animation: pulse 2s infinite;
  }
  @keyframes pulse {
    0%,
    100% {
      opacity: 1;
    }
    50% {
      opacity: 0.5;
    }
  }
</style>
