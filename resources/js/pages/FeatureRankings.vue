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
        <div class="mb-6">
          <NTabs v-model:value="activeTab" type="line" size="large" class="modern-tabs">
            <!-- 智能控制中心标签页 -->
            <NTabPane name="control">
              <template #tab>
                <div class="flex items-center gap-2">
                  <span class="text-lg">🎛️</span>
                  <span>智能控制中心</span>
                </div>
              </template>

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
              <V3ConditionPanel :matrix="matrix || null" :uid="panelUid" />

              <!-- 登录/账户设置复用组件 -->
              <WalletSetup :visible="showWalletSetup" @validated="onWalletValidated" />

              <div class="space-y-6">
                <NEmpty
                  v-if="!(matrix && matrix.features && matrix.features.length)"
                  description="暂无特征数据"
                  class="py-8"
                />
              </div>
            </NTabPane>

            <!-- 特征历史分析标签页（新） -->
            <NTabPane name="feature-history">
              <template #tab>
                <div class="flex items-center gap-2">
                  <span class="text-lg">🧬</span>
                  <span>特征历史分析</span>
                </div>
              </template>

              <div class="border border-white/10 rounded-xl bg-black/20 p-6 backdrop-blur-md">
                <FeatureHistoryAnalysisTab
                  :exact-rate="featureExactRate"
                  :total-rounds="featureTotalRounds"
                  :all-stats="featureAllStats"
                  :recent-stats="featureRecentStats"
                  :recent-rounds-count="featureRecentRoundsCount"
                  :max-rounds="featureHistory.length || 0"
                  :history-loading="featureHistoryLoading"
                  :features="featureList"
                  :feature-stats-map="featureStatsMap"
                  :history-list="featureHistory"
                  @refresh-feature-history="refreshFeatureHistory"
                  @update:recent-rounds-count="(v: number) => (featureRecentRoundsCount = v)"
                />
              </div>
            </NTabPane>
          </NTabs>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref, watch } from 'vue';
  import { Head } from '@inertiajs/vue3';
  import { NEmpty, NButton, NTabs, NTabPane } from 'naive-ui';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import FeatureCompactBoard from '@/components/FeatureCompactBoard.vue';
  import V3ConditionPanel from '@/components/V3ConditionPanel.vue';
  import AutoBettingStatusPanel from '@/components/AutoBettingStatusPanel.vue';
  // import HistoryAnalysisTab from '@/components/HistoryAnalysisTab.vue';
  import FeatureHistoryAnalysisTab from '@/components/FeatureHistoryAnalysisTab.vue';
  import { useFeatureStore } from '@/stores/featureStore';
  import { websocketManager } from '@/utils/websocketManager';
  import { jwtTokenUtils, getUserInfo, featureApi } from '@/utils/api';
  import { useGamePredictionStore } from '@/stores/gamePrediction';
  import WalletSetup from '@/components/WalletSetup.vue';
  import { storeToRefs } from 'pinia';
  import type { UserInfo, GetUserInfoResponse } from '@/types';
  import type { WebSocketStatus as WS } from '@/utils/websocketManager';
  import type { GameDataUpdateEvent } from '@/stores/gamePrediction';
  import { useAutoBettingControl } from '@/composables/useAutoBettingControl';
  import { useV3Conditions } from '@/composables/useV3Conditions';
  import { type FeatureHistoryRound, type AllRankStats } from '@/composables/useFeaturePredictionStats';

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

  // 标签页状态
  const activeTab = ref('control');

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
  const panelUid = computed(() => String(localStorage.getItem('currentUID') || ''));
  const strategyValidation = ref<{
    total_matched?: number;
    required_balance?: number;
    balance_sufficient?: boolean;
  } | null>(null);

  // （移除旧统计模块，保持最简，仅保留特征历史分析）

  // =============== 特征历史与统计（新）===============
  const featureHistory = ref<FeatureHistoryRound[]>([]);
  const featureHistoryLoading = ref(false);
  const featureRecentRoundsCount = ref<number>(50);
  // 预排序全量历史，避免在子层重复排序
  const sortedHistoryAll = computed<FeatureHistoryRound[]>(() => {
    const arr = featureHistory.value.slice();
    arr.sort((a, b) => Number(b.round_id) - Number(a.round_id));
    return arr;
  });
  function computeAllStatsForList(list: FeatureHistoryRound[]): AllRankStats {
    const stats: AllRankStats = {
      rank1: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank2: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank3: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 }
    };
    if (!list || list.length === 0) return stats;
    for (let i = 0; i < list.length; i++) {
      const round = list[i];
      const resultsBySymbol: Record<string, number> = {};
      for (const r of round.results || []) resultsBySymbol[r.symbol] = r.actual_rank;
      for (let rk = 1 as 1 | 2 | 3; rk <= 3; rk = (rk + 1) as 1 | 2 | 3) {
        const preds = round.predictions || [];
        for (let j = 0; j < preds.length; j++) {
          const p = preds[j];
          if (p.predicted_rank !== rk) continue;
          const actualRank = resultsBySymbol[p.symbol];
          if (actualRank == null) continue;
          const key = `rank${rk}` as 'rank1' | 'rank2' | 'rank3';
          const s = stats[key];
          s.total++;
          if (actualRank <= 3) s.breakeven++;
          else s.loss++;
          if (actualRank === 1) s.firstPlace++;
        }
      }
    }
    for (const s of Object.values(stats)) {
      if (s.total > 0) {
        s.breakevenRate = (s.breakeven / s.total) * 100;
        s.lossRate = (s.loss / s.total) * 100;
        s.firstPlaceRate = (s.firstPlace / s.total) * 100;
      }
    }
    return stats;
  }
  function computeExactRateForList(list: FeatureHistoryRound[]): number {
    if (!list || list.length === 0) return 0;
    let exact = 0;
    let total = 0;
    for (let i = 0; i < list.length; i++) {
      const round = list[i];
      const resultsBySymbol: Record<string, number> = {};
      for (const r of round.results || []) resultsBySymbol[r.symbol] = r.actual_rank;
      const preds = round.predictions || [];
      for (let j = 0; j < preds.length; j++) {
        const p = preds[j];
        if (p.predicted_rank > 3) continue;
        const actualRank = resultsBySymbol[p.symbol];
        if (actualRank == null) continue;
        total++;
        if (actualRank === p.predicted_rank) exact++;
      }
    }
    return total > 0 ? (exact / total) * 100 : 0;
  }
  const featureExactRate = computed(() => computeExactRateForList(sortedHistoryAll.value));
  const featureTotalRounds = computed(() => featureHistory.value.length || 0);
  const featureAllStats = computed(() => computeAllStatsForList(sortedHistoryAll.value));
  const featureRecentStats = computed(() =>
    computeAllStatsForList(sortedHistoryAll.value.slice(0, featureRecentRoundsCount.value))
  );

  const refreshFeatureHistory = async () => {
    featureHistoryLoading.value = true;
    try {
      const res = await featureApi.getFeatureHistory({ limit: 1000 });
      if (res.data?.success) {
        const rows = (res.data.data || []) as Array<{
          round_id: string | number;
          settled_at?: string | null;
          results: Array<{ symbol: string; actual_rank: number }>;
          features: Record<string, Array<{ symbol: string; predicted_rank: number }>>;
        }>;
        const flattened: FeatureHistoryRound[] = [];
        for (const r of rows) {
          const featureMap = r.features || {};
          for (const [feature, preds] of Object.entries(featureMap)) {
            flattened.push({
              round_id: r.round_id,
              feature,
              predictions: preds || [],
              results: r.results || [],
              settled_at: r.settled_at || null
            });
          }
        }
        featureHistory.value = flattened;
      } else {
        window.$message?.error(res.data?.message || '获取特征历史失败');
      }
    } catch {
      window.$message?.error('获取特征历史失败');
    } finally {
      featureHistoryLoading.value = false;
    }
  };

  // 从特征历史中提取“所有特征列表”
  const featureList = computed<string[]>(() => {
    const set = new Set<string>();
    featureHistory.value.forEach((r) => {
      if (r.feature) set.add(r.feature);
    });
    return Array.from(set).sort();
  });

  // 类型别名，简化后续声明
  type StatsMapEntry = {
    exactRate: number;
    totalRounds: number;
    allStats: AllRankStats;
    recentStats: AllRankStats;
  };
  type PrecomputedFeature = {
    length: number;
    totals: [number[], number[], number[]];
    breakevens: [number[], number[], number[]];
    firsts: [number[], number[], number[]];
    exactTotals: number[];
    exactHits: number[];
  };

  // 预排序与轻量统计：按特征预先分组并按轮次降序排列（仅在 featureHistory 变更时计算一次）
  const sortedHistoryByFeature = computed((): Record<string, FeatureHistoryRound[]> => {
    const map: Record<string, FeatureHistoryRound[]> = {};
    for (const r of featureHistory.value) {
      if (!r || !r.feature) continue;
      (map[r.feature] ||= []).push(r);
    }
    for (const f of Object.keys(map)) {
      map[f].sort((a, b) => Number(b.round_id) - Number(a.round_id));
    }
    return map;
  });

  // 预计算每个特征的前缀累计（仅在历史变更时更新）
  function buildPrecomputedByFeature(): Record<string, PrecomputedFeature> {
    const map: Record<string, PrecomputedFeature> = {};
    const byFeature = sortedHistoryByFeature.value;
    for (const [f, list] of Object.entries(byFeature)) {
      const L = list.length;
      const totals: [number[], number[], number[]] = [new Array(L).fill(0), new Array(L).fill(0), new Array(L).fill(0)];
      const breakevens: [number[], number[], number[]] = [
        new Array(L).fill(0),
        new Array(L).fill(0),
        new Array(L).fill(0)
      ];
      const firsts: [number[], number[], number[]] = [new Array(L).fill(0), new Array(L).fill(0), new Array(L).fill(0)];
      const exactTotals: number[] = new Array(L).fill(0);
      const exactHits: number[] = new Array(L).fill(0);

      for (let i = 0; i < L; i++) {
        const round = list[i];
        const resultsBySymbol: Record<string, number> = {};
        for (const r of round.results || []) resultsBySymbol[r.symbol] = r.actual_rank;

        const incTotals = [0, 0, 0];
        const incBreakevens = [0, 0, 0];
        const incFirsts = [0, 0, 0];
        let incExactTotal = 0;
        let incExactHits = 0;

        const preds = round.predictions || [];
        for (let j = 0; j < preds.length; j++) {
          const p = preds[j];
          const rk = p.predicted_rank;
          if (rk < 1 || rk > 3) continue;
          const actualRank = resultsBySymbol[p.symbol];
          if (actualRank == null) continue;
          const idx = rk - 1;
          incTotals[idx]++;
          if (actualRank <= 3) incBreakevens[idx]++;
          if (actualRank === 1) incFirsts[idx]++;
          incExactTotal++;
          if (actualRank === rk) incExactHits++;
        }

        for (let k = 0; k < 3; k++) {
          const prevT = i > 0 ? totals[k][i - 1] : 0;
          const prevB = i > 0 ? breakevens[k][i - 1] : 0;
          const prevF = i > 0 ? firsts[k][i - 1] : 0;
          totals[k][i] = prevT + incTotals[k];
          breakevens[k][i] = prevB + incBreakevens[k];
          firsts[k][i] = prevF + incFirsts[k];
        }
        const prevET = i > 0 ? exactTotals[i - 1] : 0;
        const prevEH = i > 0 ? exactHits[i - 1] : 0;
        exactTotals[i] = prevET + incExactTotal;
        exactHits[i] = prevEH + incExactHits;
      }

      map[f] = { length: L, totals, breakevens, firsts, exactTotals, exactHits };
    }
    return map;
  }
  const precomputedByFeature = computed(() => buildPrecomputedByFeature());

  function emptyStats(): AllRankStats {
    return {
      rank1: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank2: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 },
      rank3: { total: 0, breakeven: 0, loss: 0, firstPlace: 0, breakevenRate: 0, lossRate: 0, firstPlaceRate: 0 }
    };
  }

  // 移除未使用的重型聚合函数，避免误用造成重复计算

  function buildFeatureStatsMap(): Record<string, StatsMapEntry> {
    const out: Record<string, StatsMapEntry> = {};
    const pre = precomputedByFeature.value;
    const recentN = Math.max(1, featureRecentRoundsCount.value);
    for (const f of featureList.value) {
      const pc = pre[f];
      if (!pc || pc.length === 0) {
        out[f] = { exactRate: 0, totalRounds: 0, allStats: emptyStats(), recentStats: emptyStats() };
        continue;
      }
      const lastIdx = pc.length - 1;
      const recentIdx = Math.min(pc.length - 1, recentN - 1);

      const buildStats = (idx: number): AllRankStats => {
        const s = emptyStats();
        for (let k = 0 as 0 | 1 | 2; k < 3; k = (k + 1) as 0 | 1 | 2) {
          type RankKey = 'rank1' | 'rank2' | 'rank3';
          const key = `rank${k + 1}` as RankKey;
          const total = pc.totals[k][idx];
          const breakeven = pc.breakevens[k][idx];
          const first = pc.firsts[k][idx];
          s[key].total = total;
          s[key].breakeven = breakeven;
          s[key].loss = Math.max(0, total - breakeven);
          s[key].firstPlace = first;
          if (total > 0) {
            s[key].breakevenRate = (breakeven / total) * 100;
            s[key].lossRate = ((total - breakeven) / total) * 100;
            s[key].firstPlaceRate = (first / total) * 100;
          }
        }
        return s;
      };

      const allStats = buildStats(lastIdx);
      const recentStats = buildStats(recentIdx);
      const exactRate = pc.exactTotals[lastIdx] > 0 ? (pc.exactHits[lastIdx] / pc.exactTotals[lastIdx]) * 100 : 0;

      out[f] = {
        exactRate,
        totalRounds: pc.length,
        allStats,
        recentStats
      };
    }
    return out;
  }
  const featureStatsMap = computed(() => buildFeatureStatsMap());

  // =============== V3 条件（名次驱动）===============
  const v3 = useV3Conditions(() => store.matrix);
  v3.loadFromLocalStorage();
  const v3TopN = computed(() => Math.max(1, v3.topN.value || 1));
  const eligibleTokens = computed<string[]>(() => v3.filterTokens());
  const selectedTokens = computed<string[]>(() => eligibleTokens.value.slice(0, v3TopN.value));

  // 下单开关
  const placingBets = ref(false);
  const isExecuting = ref(false);
  // 已下注记录（按 轮次:代币 唯一键）
  const processedBets = ref<Set<string>>(new Set());
  let executionTimeout: number | null = null;
  // 本地下注金额规则（与自动下注页一致）
  const calculateBetAmount = (): number => (bettingMode.value === 'real' ? 200 : 5);

  // 自动下注（基于V3）
  const placeBetsByV3 = async () => {
    // 只有在全局自动下注开关为开启时才允许执行
    if (!autoBettingStatus.value?.is_running) {
      return;
    }
    if (isExecuting.value) return; // 防重复并发
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

    // 过滤掉本轮已下注过的代币
    const pendingTokens = tokens.filter((s) => !processedBets.value.has(`${roundIdVal}:${s}`));
    if (!pendingTokens.length) {
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
    isExecuting.value = true;
    let success = 0;
    let fail = 0;
    for (const symbol of pendingTokens) {
      try {
        const ok = await executeSingleBet(roundIdVal, symbol, betAmount, jwtToken.value, bettingMode.value);
        if (ok) success++;
        else fail++;
        // 记录本轮此代币已处理，避免重复下注
        processedBets.value.add(`${roundIdVal}:${symbol}`);
        await new Promise((r) => setTimeout(r, 400));
      } catch {
        fail++;
        processedBets.value.add(`${roundIdVal}:${symbol}`);
      }
    }
    placingBets.value = false;
    isExecuting.value = false;
    if (success) window.$message?.success(`下注完成：成功 ${success}，失败 ${fail}`);
    else window.$message?.error('下注失败');
    // 刷新状态
    loadStatus();
  };

  // 自动触发：进入 bet 或新轮次时触发（防抖 + 去重）
  watch(
    [currentRoundId, currentGameStatus, () => autoBettingStatus.value?.is_running],
    ([rid, status, running], [prevRid, prevStatus]) => {
      if (!running) return; // 未开启自动下注则不触发
      const isBet = (status || '') === 'bet';
      const becameBet = (prevStatus || '') !== 'bet' && isBet;
      const newRound = !!rid && rid !== prevRid;
      if (!isBet || (!becameBet && !newRound)) return;
      if (!tokenValidated.value) return;

      // 稍作延迟，等矩阵/条件稳定
      if (executionTimeout) window.clearTimeout(executionTimeout);
      executionTimeout = window.setTimeout(async () => {
        if (!selectedTokens.value.length) return;
        await placeBetsByV3();
      }, 120);
    }
  );

  // 轮次变化时清理已下注集合，避免集合无限增长
  watch(currentRoundId, (rid, prevRid) => {
    if (rid && rid !== prevRid) {
      processedBets.value = new Set();
    }
  });

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

    // 历史数据初始化
    // 已移除旧统计的初始化
    refreshFeatureHistory();
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
