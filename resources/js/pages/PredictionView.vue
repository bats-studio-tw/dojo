<template>
  <DefaultLayout>
    <Head title="预测分析展示" />

    <div class="min-h-screen from-slate-900 via-slate-800 to-slate-900 bg-gradient-to-br">
      <!-- 顶部导航栏 -->
      <div class="border-b border-white/10 bg-black/20 backdrop-blur-md">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6">
          <div class="flex items-center justify-between">
            <!-- 左侧：标题 -->
            <div class="flex items-center gap-3">
              <div class="icon-container float-animation h-10 w-10 flex items-center justify-center rounded-lg">
                <span class="text-xl">🔮</span>
              </div>
              <div>
                <h1 class="gradient-text text-xl font-bold sm:text-2xl">预测分析展示</h1>
                <p class="text-sm text-gray-400">实时预测数据展示</p>
              </div>
            </div>

            <!-- 右侧：登录按钮 -->
            <div class="flex items-center gap-3">
              <div class="text-sm text-gray-400">游客模式</div>
              <NButton @click="goToLogin" type="primary" size="medium" class="transition-all duration-200">
                <template #icon>
                  <span>🔐</span>
                </template>
                登录控制台
              </NButton>
            </div>
          </div>
        </div>
      </div>

      <!-- 主要内容区域 -->
      <div class="mx-auto max-w-7xl p-4 sm:p-6">
        <div class="grid gap-6 lg:grid-cols-2">
          <!-- 🎯 智能对战预测面板 -->
          <div class="border border-white/10 rounded-xl bg-black/20 p-6 backdrop-blur-md">
            <div class="mb-4 flex items-center gap-2">
              <span class="text-2xl">🎯</span>
              <h2 class="text-lg font-bold text-white">智能对战预测</h2>
            </div>

            <AIPredictionRanking
              :current-analysis="currentAnalysis"
              :analysis-meta="analysisMeta"
              :current-round-id="currentRoundId"
              :current-game-status="currentGameStatus"
              :current-game-tokens-with-ranks="currentGameTokensWithRanks"
              :analysis-loading="analysisLoading"
              @refresh-analysis="refreshAnalysis"
            />
          </div>

          <!-- ⚡ 动能预测排名面板 -->
          <div class="border border-white/10 rounded-xl bg-black/20 p-6 backdrop-blur-md">
            <div class="mb-4 flex items-center gap-2">
              <span class="text-2xl">⚡</span>
              <h2 class="text-lg font-bold text-white">动能预测分析</h2>
            </div>

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
        </div>

        <!-- 底部信息 -->
        <div class="mt-8 text-center">
          <div class="border border-white/10 rounded-xl bg-black/20 p-6 backdrop-blur-md">
            <p class="text-sm text-gray-400">这是一个纯展示页面，如需使用自动下注功能，请点击右上角"登录控制台"按钮</p>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>

<script setup lang="ts">
  import { ref, onMounted, onUnmounted } from 'vue';
  import { router } from '@inertiajs/vue3';
  import { NButton } from 'naive-ui';
  import { Head } from '@inertiajs/vue3';
  import DefaultLayout from '@/layouts/DefaultLayout.vue';
  import AIPredictionRanking from '@/components/AIPredictionRanking.vue';
  import MomentumPredictionDisplay from '@/components/MomentumPredictionDisplay.vue';
  import api from '@/utils/api';
  import type {
    PredictionAnalysis,
    AnalysisMeta,
    GameStatus,
    TokenWithRank,
    HybridPrediction
  } from '@/types/prediction';

  // 响应式数据
  const currentAnalysis = ref<any[]>([]);
  const analysisMeta = ref<AnalysisMeta | null>(null);
  const currentRoundId = ref<string>('');
  const currentGameStatus = ref<GameStatus>('waiting');
  const currentGameTokensWithRanks = ref<TokenWithRank[]>([]);
  const analysisLoading = ref(false);

  const hybridPredictions = ref<HybridPrediction[]>([]);
  const hybridAnalysisMeta = ref<AnalysisMeta | null>(null);
  const hybridAnalysisLoading = ref(false);

  // WebSocket连接
  let ws: WebSocket | null = null;

  // 跳转到登录页面
  const goToLogin = () => {
    router.visit('/');
  };

  // 获取预测分析
  const fetchAnalysis = async () => {
    if (analysisLoading.value) return;

    analysisLoading.value = true;
    try {
      const response = await api.get('/prediction/analysis');
      if (response.data.success) {
        // 确保返回的是数组格式
        const analysisData = response.data.data.analysis;
        if (analysisData && analysisData.predictions) {
          currentAnalysis.value = analysisData.predictions;
        } else {
          currentAnalysis.value = [];
        }
        analysisMeta.value = response.data.data.meta;
      }
    } catch (error) {
      console.error('获取预测分析失败:', error);
      currentAnalysis.value = [];
    } finally {
      analysisLoading.value = false;
    }
  };

  // 获取混合预测分析
  const fetchHybridAnalysis = async () => {
    if (hybridAnalysisLoading.value) return;

    hybridAnalysisLoading.value = true;
    try {
      const response = await api.get('/prediction/hybrid-analysis');
      if (response.data.success) {
        hybridPredictions.value = response.data.data.predictions;
        hybridAnalysisMeta.value = response.data.data.meta;
      }
    } catch (error) {
      console.error('获取混合预测分析失败:', error);
    } finally {
      hybridAnalysisLoading.value = false;
    }
  };

  // 刷新分析
  const refreshAnalysis = () => {
    fetchAnalysis();
  };

  // 刷新混合分析
  const refreshHybridAnalysis = () => {
    fetchHybridAnalysis();
  };

  // 初始化WebSocket连接
  const initWebSocket = () => {
    const wsUrl = `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.host}/ws/game`;

    ws = new WebSocket(wsUrl);

    ws.onopen = () => {
      console.log('WebSocket连接已建立');
    };

    ws.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);

        if (data.type === 'game_update') {
          currentRoundId.value = data.round_id || '';
          currentGameStatus.value = data.status || 'waiting';
          currentGameTokensWithRanks.value = data.tokens_with_ranks || [];
        }
      } catch (error) {
        console.error('解析WebSocket消息失败:', error);
      }
    };

    ws.onerror = (error) => {
      console.error('WebSocket错误:', error);
    };

    ws.onclose = () => {
      console.log('WebSocket连接已关闭');
      // 5秒后重连
      setTimeout(() => {
        if (ws?.readyState === WebSocket.CLOSED) {
          initWebSocket();
        }
      }, 5000);
    };
  };

  // 组件挂载时初始化
  onMounted(() => {
    // 获取初始数据
    fetchAnalysis();
    fetchHybridAnalysis();

    // 建立WebSocket连接
    initWebSocket();

    // 设置定时刷新（每30秒）
    const interval = setInterval(() => {
      fetchAnalysis();
      fetchHybridAnalysis();
    }, 30000);

    // 清理定时器
    onUnmounted(() => {
      clearInterval(interval);
      if (ws) {
        ws.close();
      }
    });
  });
</script>

<style scoped>
  .gradient-text {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
      transform: translateY(-10px);
    }
  }

  .icon-container {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
  }
</style>
