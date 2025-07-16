import { ref, reactive, computed } from 'vue';
import { getUserInfo, autoBettingApi, gameApi } from '@/utils/api';
import { getGameStatusTagType } from '@/utils/statusUtils';
import type { UserInfo } from '@/types';
import api from '@/utils/api';

export interface AutoBettingStatus {
  is_running: boolean;
  current_round_id: string | null;
  last_bet_at: string | null;
  total_bets: number;
  total_profit_loss: number;
  today_profit_loss: number;
  consecutive_losses: number;
  last_error: string | null;
}

export interface DebugInfo {
  showDebugPanel: boolean;
  roundCheckCount: number;
  lastRoundCheckTime: string;
  autoTriggerCount: number;
  lastAutoTriggerTime: string;
  lastExecutionTime: string;
  strategyValidationCount: number;
  lastValidationTime: string;
  lastBetResults: Array<{
    time: string;
    symbol: string;
    amount: number;
    success: boolean;
    error?: string;
  }>;
}

export const useAutoBettingControl = () => {
  // 认证状态
  const isTokenValidated = ref(false);
  const currentUID = ref('');
  const userInfo = ref<UserInfo | null>(null);

  // 自动下注状态
  const autoBettingStatus = ref<AutoBettingStatus>({
    is_running: false,
    current_round_id: null,
    last_bet_at: null,
    total_bets: 0,
    total_profit_loss: 0,
    today_profit_loss: 0,
    consecutive_losses: 0,
    last_error: null
  });

  // 当前分析数据
  const currentAnalysis = ref<any>(null);
  const lastKnownRoundId = ref<string | null>(null);
  const isMonitoringRounds = ref(false);

  // 加载状态
  const statusLoading = ref(false);
  const toggleLoading = ref(false);
  const executeLoading = ref(false);
  const analysisLoading = ref(false);
  const diagnosticsLoading = ref(false);

  // 调试信息
  const debugInfo = reactive<DebugInfo>({
    showDebugPanel: false,
    roundCheckCount: 0,
    lastRoundCheckTime: '',
    autoTriggerCount: 0,
    lastAutoTriggerTime: '',
    lastExecutionTime: '',
    strategyValidationCount: 0,
    lastValidationTime: '',
    lastBetResults: []
  });

  // 调试日志功能
  const addDebugLog = (level: 'info' | 'warn' | 'error' | 'success', message: string) => {
    const time = new Date().toLocaleTimeString();
    const consoleMessage = `[AutoBetting ${time}] ${message}`;

    switch (level) {
      case 'error':
        console.error(consoleMessage);
        break;
      case 'warn':
        console.warn(consoleMessage);
        break;
      case 'success':
      case 'info':
      default:
        console.log(consoleMessage);
        break;
    }
  };

  // 状态标签类型（使用统一的状态工具）
  const getStatusTagType = getGameStatusTagType;

  // 加载自动下注状态
  const loadStatus = async () => {
    if (!currentUID.value) return;

    statusLoading.value = true;
    try {
      const response = await autoBettingApi.getStatus(currentUID.value);
      if (response.data.success) {
        autoBettingStatus.value = response.data.data;
      } else {
        window.$message?.error(response.data.message || '加载状态失败');
      }
    } catch (error) {
      console.error('加载状态失败:', error);
    } finally {
      statusLoading.value = false;
    }
  };

  // 获取分析数据
  const fetchAnalysisData = async () => {
    addDebugLog('info', '📡 手动刷新分析数据...');
    analysisLoading.value = true;
    try {
      const response = await gameApi.getCurrentAnalysis();
      addDebugLog('info', `📡 手动刷新API响应: status=${response.status}, success=${response.data?.success}`);

      if (response.data.success) {
        currentAnalysis.value = {
          predictions: response.data.data,
          meta: response.data.meta
        };
        addDebugLog('success', '✅ 手动刷新分析数据成功');

        if (response.data.meta?.round_id && !lastKnownRoundId.value) {
          lastKnownRoundId.value = response.data.meta.round_id;
          addDebugLog('info', `🎮 初始化轮次监控: ${lastKnownRoundId.value}`);
        }

        if (response.data.data && Array.isArray(response.data.data)) {
          addDebugLog('info', `🎯 手动刷新获取到${response.data.data.length}个预测`);
        }
      } else {
        addDebugLog('error', `❌ 手动刷新失败: ${response.data.message || '未知原因'}`);
      }
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : String(error);
      addDebugLog('error', `❌ 手动刷新异常: ${errorMsg}`);
    } finally {
      analysisLoading.value = false;
    }
  };

  // 启动自动下注
  const startAutoBetting = async () => {
    addDebugLog('info', '🎬 用户点击启动自动下注');
    toggleLoading.value = true;
    try {
      const response = await autoBettingApi.toggle('start', currentUID.value);
      if (response.data.success) {
        addDebugLog('success', '✅ 自动下注启动成功');
        window.$message?.success('自动下注已启动');
        await loadStatus();
      } else {
        addDebugLog('error', `❌ 自动下注启动失败: ${response.data.message || '未知错误'}`);
        window.$message?.error(response.data.message || '启动失败');
      }
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : String(error);
      addDebugLog('error', `❌ 启动自动下注时出错: ${errorMsg}`);
      window.$message?.error('启动失败');
    } finally {
      toggleLoading.value = false;
    }
  };

  // 停止自动下注
  const stopAutoBetting = async () => {
    addDebugLog('info', '🛑 用户点击停止自动下注');
    toggleLoading.value = true;
    try {
      const response = await autoBettingApi.toggle('stop', currentUID.value);
      if (response.data.success) {
        addDebugLog('success', '✅ 自动下注停止成功');
        window.$message?.success('自动下注已停止');
        await loadStatus();
      } else {
        addDebugLog('error', `❌ 自动下注停止失败: ${response.data.message || '未知错误'}`);
        window.$message?.error(response.data.message || '停止失败');
      }
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : String(error);
      addDebugLog('error', `❌ 停止自动下注时出错: ${errorMsg}`);
      window.$message?.error('停止失败');
    } finally {
      toggleLoading.value = false;
    }
  };

  // 执行单次下注
  const executeSingleBet = async (
    roundId: string,
    tokenSymbol: string,
    amount: number,
    jwtToken: string
  ): Promise<boolean> => {
    try {
      const betIdResponse = await gameApi.getBetId(roundId, jwtToken);
      if (!betIdResponse.data.success) {
        console.error('获取betId失败:', betIdResponse.data);
        return false;
      }

      const betId = betIdResponse.data.data;
      const betResponse = await gameApi.placeBet(roundId, betId, tokenSymbol, amount, jwtToken);

      if (betResponse.data.success) {
        await autoBettingApi.recordResult({
          uid: currentUID.value,
          round_id: roundId,
          token_symbol: tokenSymbol,
          amount,
          bet_id: betId,
          success: true,
          result_data: betResponse.data.data
        });

        try {
          const userInfoResponse = await getUserInfo(jwtToken);
          if (userInfoResponse.success && userInfoResponse.obj) {
            userInfo.value = userInfoResponse.obj;
            localStorage.setItem('userInfo', JSON.stringify(userInfo.value));
          }
        } catch (error) {
          console.warn('下注后更新用户信息失败，JWT Token可能已过期:', error);
          // 🔧 重要修复：当下注后更新用户信息失败时，清除验证状态并触发重新验证
          localStorage.removeItem('tokenValidated');
          localStorage.removeItem('currentUID');
          localStorage.removeItem('tokenSetupData');
          localStorage.removeItem('userInfo');

          isTokenValidated.value = false;
          currentUID.value = '';
          userInfo.value = null;

          // 显示提示信息
          window.$message?.warning('JWT Token已过期，请重新验证');

          // 触发页面重新加载以显示JWT输入界面
          window.location.reload();
        }

        return true;
      } else {
        console.error('下注失败:', betResponse.data);
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

  // 执行一次自动下注
  const executeAutoBetting = async (config: any) => {
    executeLoading.value = true;
    try {
      const response = await autoBettingApi.execute(currentUID.value, config);
      if (response.data.success) {
        const { recommended_bets, round_id, jwt_token } = response.data.data;

        const totalBetAmount = recommended_bets.reduce((sum: number, bet: any) => sum + bet.bet_amount, 0);
        const actualBalance = userInfo.value?.ojoValue || 0;

        if (totalBetAmount > actualBalance) {
          window.$message?.error(`余额不足！需要 $${totalBetAmount.toFixed(2)}，当前余额 $${actualBalance.toFixed(2)}`);
          return;
        }

        window.$message?.info('开始执行自动下注...');
        let successCount = 0;
        let failCount = 0;

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
          window.$message?.success(`自动下注完成：成功 ${successCount} 个，失败 ${failCount} 个`);
        } else {
          window.$message?.error('自动下注全部失败');
        }

        await loadStatus();
      } else {
        window.$message?.error(response.data.message || '获取下注建议失败');
      }
    } catch (error) {
      console.error('执行自动下注失败:', error);
      window.$message?.error('执行自动下注失败');
    } finally {
      executeLoading.value = false;
    }
  };

  // API连接诊断
  const runApiDiagnostics = async () => {
    addDebugLog('info', '🔬 开始运行API连接诊断...');
    diagnosticsLoading.value = true;

    try {
      addDebugLog('info', '📡 测试基本API连接...');
      const basicResponse = await api.get('/api/v2/predictions/current-analysis');
      addDebugLog('info', `📡 基本连接状态: ${basicResponse.status} ${basicResponse.statusText}`);

      if (basicResponse.status === 200) {
        const data = basicResponse.data;
        addDebugLog('info', `📊 JSON解析成功: success=${data.success}, message=${data.message || '无'}`);

        if (data.success && data.data) {
          addDebugLog('success', `✅ API响应正常: 获取到${data.data.length || 0}条数据`);
          if (data.meta) {
            addDebugLog('info', `🎮 元数据: round_id=${data.meta.round_id}, status=${data.meta.status}`);
          }
        } else {
          addDebugLog('warn', `⚠️ API返回失败: ${data.message || '未知原因'}`);
        }
      }

      addDebugLog('success', '🔬 API诊断完成！');
      window.$message?.success('API诊断完成，请查看调试日志');
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : String(error);
      addDebugLog('error', `❌ 诊断过程出错: ${errorMsg}`);
      window.$message?.error('诊断过程出错');
    } finally {
      diagnosticsLoading.value = false;
    }
  };

  // 重新验证Token
  const reconnectToken = () => {
    localStorage.removeItem('tokenValidated');
    localStorage.removeItem('currentUID');
    localStorage.removeItem('tokenSetupData');
    localStorage.removeItem('userInfo');

    isTokenValidated.value = false;
    currentUID.value = '';
    userInfo.value = null;

    window.$message?.info('已清除验证状态，请重新验证');
  };

  // Token验证成功回调
  const onTokenValidated = async (data: {
    uid: string;
    jwt_token: string;
    user_stats: any;
    today_stats: any;
    user_info: UserInfo;
  }) => {
    currentUID.value = data.uid;
    userInfo.value = data.user_info;
    isTokenValidated.value = true;

    localStorage.setItem('tokenValidated', 'true');
    localStorage.setItem('currentUID', data.uid);
    if (data.user_info) {
      localStorage.setItem('userInfo', JSON.stringify(data.user_info));
    }

    // 🔧 重要修复：在Token验证成功时记录，由外部组件负责同步
    if (data.jwt_token) {
      console.log('🔑 Token验证成功，JWT Token:', `${data.jwt_token.slice(0, 20)}...`);
      console.log('⚠️ 需要外部组件将JWT Token同步到自动下注配置中');
    }

    await loadStatus();
    // 🔧 关键修复：移除重复的数据获取，让父组件统一管理
    // await fetchAnalysisData();
  };

  // 检查并恢复认证状态
  const restoreAuthState = async () => {
    const savedTokenValidated = localStorage.getItem('tokenValidated');
    const savedUID = localStorage.getItem('currentUID');
    const savedTokenData = localStorage.getItem('tokenSetupData');

    if (savedTokenValidated === 'true' && savedUID && savedTokenData) {
      try {
        const tokenData = JSON.parse(savedTokenData);
        currentUID.value = savedUID;
        isTokenValidated.value = true;

        const savedUserInfo = localStorage.getItem('userInfo');
        if (savedUserInfo) {
          userInfo.value = JSON.parse(savedUserInfo);
        }

        if (tokenData.jwt_token) {
          try {
            const userInfoResponse = await getUserInfo(tokenData.jwt_token);
            userInfo.value = userInfoResponse.obj || userInfoResponse;
            localStorage.setItem('userInfo', JSON.stringify(userInfo.value));
          } catch (error) {
            console.warn('获取最新用户信息失败，JWT Token可能已过期:', error);
            // 🔧 重要修复：当JWT Token验证失败时，清除所有验证状态
            localStorage.removeItem('tokenValidated');
            localStorage.removeItem('currentUID');
            localStorage.removeItem('tokenSetupData');
            localStorage.removeItem('userInfo');

            isTokenValidated.value = false;
            currentUID.value = '';
            userInfo.value = null;

            // 显示提示信息
            window.$message?.warning('JWT Token已过期，请重新验证');

            // 抛出错误，让调用方知道验证失败
            throw error;
          }
        }

        await loadStatus();
        // 🔧 关键修复：移除重复的数据获取，让父组件统一管理
        // await fetchAnalysisData();
        return true;
      } catch (error) {
        console.error('恢复验证状态失败:', error);
        localStorage.removeItem('tokenValidated');
        localStorage.removeItem('currentUID');
        localStorage.removeItem('tokenSetupData');
        localStorage.removeItem('userInfo');
        return false;
      }
    }
    return false;
  };

  // 计算属性：当前策略摘要
  const currentStrategyInfo = computed(() => {
    // 这个计算属性可以由使用此composable的组件传入具体的策略信息
    return {
      name: '当前策略',
      confidence: 88
    };
  });

  return {
    // 状态
    isTokenValidated,
    currentUID,
    userInfo,
    autoBettingStatus,
    currentAnalysis,
    lastKnownRoundId,
    isMonitoringRounds,
    debugInfo,

    // 加载状态
    statusLoading,
    toggleLoading,
    executeLoading,
    analysisLoading,
    diagnosticsLoading,

    // 计算属性
    currentStrategyInfo,

    // 方法
    addDebugLog,
    getStatusTagType,
    loadStatus,
    fetchAnalysisData,
    startAutoBetting,
    stopAutoBetting,
    executeAutoBetting,
    executeSingleBet,
    runApiDiagnostics,
    reconnectToken,
    onTokenValidated,
    restoreAuthState
  };
};
