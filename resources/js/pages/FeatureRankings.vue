<template>
  <DefaultLayout>
    <Head title="特征排名 (本局)" />

    <div class="min-h-screen from-slate-900 via-slate-800 to-slate-900 bg-gradient-to-br">
      <div class="mx-auto max-w-7xl p-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between">
          <h1 class="text-xl text-white font-bold">特征排名（本局，自动刷新）</h1>
          <div class="text-xs opacity-70">WebSocket: {{ websocketStatus.message }}</div>
        </div>

        <!-- 顶部状态条（复用AutoBetting风格的简化版） -->
        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <NTag type="success" size="small">V3</NTag>
            <div class="text-sm text-white/80">特征驱动 · 本地聚合</div>
          </div>
          <div class="flex flex-wrap items-center gap-3 text-xs">
            <div class="hidden items-center gap-2 text-white/70 sm:flex">
              <span>
                轮次:
                <span class="font-mono">{{ roundId }}</span>
              </span>
              <span>
                状态:
                <span :class="gameStatus === 'bet' ? 'text-green-400' : 'text-white/80'">{{ gameStatus }}</span>
              </span>
              <span>
                WS:
                <span :class="websocketStatus.status === 'connected' ? 'text-green-400' : 'text-red-400'">
                  {{ websocketStatus.status }}
                </span>
              </span>
              <NButton size="tiny" tertiary @click="websocketManager.manualReconnect">重连</NButton>
            </div>
            <div v-if="userInfo" class="hidden items-center gap-2 text-white/80 sm:flex">
              <span>
                UID:
                <span class="text-green-400 font-mono">{{ userInfo.uid }}</span>
              </span>
              <NButton size="tiny" tertiary :loading="userInfoRefreshing" @click="refreshUserInfo">刷新</NButton>
              <NButton size="tiny" tertiary @click="reconnectToken">登出</NButton>
            </div>
            <NButton v-if="!tokenValidated" size="small" type="primary" @click="showWalletSetup = true">
              登录以启用
            </NButton>
            <NButton v-else size="small" @click="showWalletSetup = true">账户</NButton>
          </div>
        </div>

        <!-- 条件面板 + 紧凑榜 -->
        <V3ConditionPanel :matrix="matrix || null" class="mb-4" />
        <FeatureCompactBoard :matrix="matrix || null" />

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
  import { NEmpty, NTag, NButton } from 'naive-ui';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import FeatureCompactBoard from '@/components/FeatureCompactBoard.vue';
  import V3ConditionPanel from '@/components/V3ConditionPanel.vue';
  import { useFeatureStore } from '@/stores/featureStore';
  import { websocketManager } from '@/utils/websocketManager';
  import { jwtTokenUtils, getUserInfo } from '@/utils/api';
  import { useGamePredictionStore } from '@/stores/gamePrediction';
  import WalletSetup from '@/components/WalletSetup.vue';

  const store = useFeatureStore();
  const matrix = computed(() => store.matrix);
  // 页面自动刷新，不使用loading状态显示
  // const loading = computed(() => store.loading);
  // 紧凑榜已覆盖主用例，下面列表已移除
  const websocketStatus = websocketManager.websocketStatus;

  // JWT 门控（复用 WalletSetup）
  const tokenValidated = ref<boolean>(!!localStorage.getItem('tokenValidated'));
  const showWalletSetup = ref<boolean>(!tokenValidated.value);
  const jwtToken = ref<string>('');
  const userInfo = ref<any>(null);

  function onWalletValidated(e: any) {
    tokenValidated.value = true;
    showWalletSetup.value = false;
    jwtToken.value = e?.jwt_token ?? '';
    userInfo.value = e?.user_info ?? null;
  }

  // 顶部状态（参考 AutoBetting）
  const predictionStore = useGamePredictionStore();
  const gameStatus = computed(
    () => (predictionStore as any)?.gameStatus || (predictionStore as any)?.status || 'unknown'
  );
  const roundId = computed(() => (predictionStore as any)?.roundId || (predictionStore as any)?.currentRoundId || '');

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
      const res = await getUserInfo(jwtToken.value);
      if ((res as any)?.obj) {
        userInfo.value = (res as any).obj;
        localStorage.setItem('userInfo', JSON.stringify(userInfo.value));
      }
    } finally {
      userInfoRefreshing.value = false;
    }
  }

  const refresh = () => store.maybeFetchAfterTimeout();

  // 旧的单特征卡片已移除

  onMounted((): void => {
    if (!websocketManager.isInitialized) websocketManager.initialize();
    // 恢复本地存储的token
    const saved = jwtTokenUtils.getStoredToken();
    if (saved) jwtToken.value = saved;
    // 首次拉取（若短时间内未收到推送将兜底请求）
    refresh();
    // 订阅特征矩阵推送，减少HTTP压力
    store.subscribeFeatureMatrixPush();
    // 监听游戏事件，进入bet或新轮次变化时刷新特征矩阵
    websocketManager.listenToGameUpdates((event: { data?: { status?: string; rdId?: string } }) => {
      const status = event?.data?.status;
      const rdId = event?.data?.rdId;
      if (status === 'bet' && rdId) {
        refresh();
      }
    });
  });
</script>

<style scoped></style>
