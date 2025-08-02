import axios from 'axios';
import type { GetUserInfoResponse } from '@/types';

// 创建axios实例
const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
});

// 请求拦截器
api.interceptors.request.use(
  (config) => {
    // 在发送请求之前做些什么
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
      config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// 响应拦截器
api.interceptors.response.use(
  (response) => {
    // 对响应数据做些什么
    return response;
  },
  (error) => {
    // 对响应错误做些什么
    if (error.response?.status === 401) {
      // 🔧 临时修改：改为console输出而不是重定向
      console.error('🔐 [API拦截器] 检测到401未授权错误:', error);
      console.log('🔐 [API拦截器] 当前错误详情:', {
        status: error.response?.status,
        data: error.response?.data,
        message: error.message
      });
      // window.location.href = '/login'; // 暂时注释掉重定向
    }
    return Promise.reject(error);
  }
);

// 创建 dojo quest API 实例
const dojoQuestApi = axios.create({
  baseURL: '/dojo-quest',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    Accept: '*/*',
    saas_id: 'dojo3-tg' // 添加必需的saas_id header
  }
});

// 创建 dojo game API 实例
const dojoGameApi = axios.create({
  baseURL: '/dojo-api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
});

// 🔧 新增：JWT Token同步和验证工具
export const jwtTokenUtils = {
  // 获取当前存储的JWT Token
  getStoredToken: (): string | null => {
    try {
      const savedData = localStorage.getItem('tokenSetupData');
      if (savedData) {
        const tokenData = JSON.parse(savedData);
        return tokenData.jwt_token || null;
      }
    } catch (error) {
      console.error('获取存储的JWT Token失败:', error);
    }
    return null;
  },

  // 同步JWT Token到localStorage
  syncTokenToStorage: (jwtToken: string): void => {
    try {
      const currentData = localStorage.getItem('tokenSetupData');
      let tokenData = {};

      if (currentData) {
        tokenData = JSON.parse(currentData);
      }

      tokenData = { ...tokenData, jwt_token: jwtToken };
      localStorage.setItem('tokenSetupData', JSON.stringify(tokenData));

      console.log('✅ JWT Token已同步到localStorage');
    } catch (error) {
      console.error('同步JWT Token到localStorage失败:', error);
    }
  },

  // 验证JWT Token是否一致
  validateTokenConsistency: (
    jwtToken: string
  ): {
    isConsistent: boolean;
    storedToken: string | null;
    differences: string[];
  } => {
    const storedToken = jwtTokenUtils.getStoredToken();
    const differences: string[] = [];

    if (!storedToken) {
      differences.push('localStorage中没有存储的Token');
      return { isConsistent: false, storedToken: null, differences };
    }

    if (storedToken !== jwtToken) {
      differences.push('Token内容不一致');
      differences.push(`存储Token长度: ${storedToken.length}`);
      differences.push(`传入Token长度: ${jwtToken.length}`);
      differences.push(`存储Token前缀: ${storedToken.slice(0, 20)}...`);
      differences.push(`传入Token前缀: ${jwtToken.slice(0, 20)}...`);
    }

    return {
      isConsistent: storedToken === jwtToken,
      storedToken,
      differences
    };
  },

  // 清理所有Token相关数据
  clearAllTokenData: (): void => {
    localStorage.removeItem('tokenValidated');
    localStorage.removeItem('currentUID');
    localStorage.removeItem('tokenSetupData');
    localStorage.removeItem('userInfo');
    console.log('🧹 所有Token相关数据已清理');
  },

  // 🔧 新增：全面检查Token一致性
  checkSystemTokenConsistency: (
    currentConfigToken: string
  ): {
    isConsistent: boolean;
    report: string[];
    issues: string[];
  } => {
    const report: string[] = [];
    const issues: string[] = [];

    // 检查localStorage中的Token
    const storedToken = jwtTokenUtils.getStoredToken();
    report.push(`📱 localStorage Token: ${storedToken ? `${storedToken.slice(0, 20)}...` : 'null'}`);
    report.push(`⚙️ Config Token: ${currentConfigToken ? `${currentConfigToken.slice(0, 20)}...` : 'null'}`);

    // 检查一致性
    if (!storedToken) {
      issues.push('localStorage中没有JWT Token');
    }

    if (!currentConfigToken) {
      issues.push('配置中没有JWT Token');
    }

    if (storedToken && currentConfigToken && storedToken !== currentConfigToken) {
      issues.push('localStorage和配置中的Token不一致');
      report.push(`❌ Token不匹配:`);
      report.push(`   localStorage: ${storedToken.slice(0, 30)}...`);
      report.push(`   Config:       ${currentConfigToken.slice(0, 30)}...`);
    }

    const isConsistent = issues.length === 0 && storedToken === currentConfigToken;

    if (isConsistent) {
      report.push('✅ 所有Token一致');
    } else {
      report.push(`❌ 发现 ${issues.length} 个Token一致性问题`);
    }

    return { isConsistent, report, issues };
  }
};

// 🔧 新增：网络状态检测工具
export const networkUtils = {
  // 检测是否为网络错误
  isNetworkError: (error: any): boolean => {
    return (
      !error.response ||
      error.code === 'NETWORK_ERROR' ||
      error.code === 'ECONNABORTED' ||
      error.message.includes('timeout') ||
      error.message.includes('Network Error')
    );
  },

  // 检测是否为认证错误
  isAuthError: (error: any): boolean => {
    // 检查HTTP状态码
    if (error.response?.status === 401 || error.response?.status === 403) {
      return true;
    }

    // 🔧 重要修复：检查API响应体中的认证错误
    // JWT有问题时可能返回200状态码，但success为false且有特定错误码
    if (error.response?.status === 200 && error.response?.data) {
      const data = error.response.data;
      if (data.success === false) {
        // 检查特定的认证错误码和消息
        const authErrorCodes = ['1000', '1001', '1002']; // 根据实际情况调整
        const authErrorMsgKeys = ['customer.login.required', 'token.expired', 'token.invalid'];

        if (authErrorCodes.includes(data.code) || authErrorMsgKeys.includes(data.msgKey)) {
          return true;
        }
      }
    }

    return false;
  },

  // 检测是否为服务器错误
  isServerError: (error: any): boolean => {
    return error.response?.status >= 500 && error.response?.status < 600;
  },

  // 带重试的API调用
  retryApiCall: async <T>(apiCall: () => Promise<T>, maxRetries: number = 5, retryDelay: number = 1000): Promise<T> => {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        console.log(`🔄 [retryApiCall] 尝试第 ${attempt}/${maxRetries} 次...`);
        return await apiCall();
      } catch (error: any) {
        console.warn(`⚠️ [retryApiCall] 第 ${attempt}/${maxRetries} 次尝试失败:`, error.message);

        const isNetworkError = networkUtils.isNetworkError(error);
        const isServerError = networkUtils.isServerError(error);
        const isAuthError = networkUtils.isAuthError(error);

        // 认证错误不重试
        if (isAuthError) {
          throw error;
        }

        // 网络或服务器错误且还有重试机会，则重试
        if ((isNetworkError || isServerError) && attempt < maxRetries) {
          const delay = retryDelay * attempt; // 递增延迟
          console.log(`🔄 [retryApiCall] ${delay}ms后重试...`);
          await new Promise((resolve) => setTimeout(resolve, delay));
          continue;
        }

        // 最后一次尝试或其他错误
        throw error;
      }
    }

    throw new Error('所有重试都失败了');
  }
};

// 获取用户信息的方法
export const getUserInfo = async (jwtToken: string): Promise<GetUserInfoResponse> => {
  try {
    return await networkUtils.retryApiCall(async () => {
      console.log('🔄 [getUserInfo] 开始获取用户信息...');

      // 🔧 新增：JWT Token来源追踪和一致性验证
      const tokenValidation = jwtTokenUtils.validateTokenConsistency(jwtToken);
      console.log('🔑 [getUserInfo] JWT Token来源追踪:', {
        tokenLength: jwtToken?.length || 0,
        tokenPrefix: `${jwtToken?.slice(0, 20)}...`,
        tokenSuffix: jwtToken?.slice(-10),
        isConsistent: tokenValidation.isConsistent,
        storedTokenExists: !!tokenValidation.storedToken,
        differences: tokenValidation.differences
      });

      // 🔧 如果Token不一致，输出警告
      if (!tokenValidation.isConsistent) {
        console.warn('⚠️ [getUserInfo] JWT Token不一致:', tokenValidation.differences);
      }

      const res = await dojoGameApi.get('ladders/me', {
        headers: {
          jwt_token: jwtToken
        }
      });
      console.log('📡 [getUserInfo] dojoGameApi响应:', res.data);

      // 🔧 重要修复：检查第一个API的响应
      if (res.data.success === false) {
        // 创建包含响应数据的错误对象
        const authError = new Error(res.data.msgKey || res.data.message || '认证失败');
        (authError as any).response = {
          status: 200,
          data: res.data
        };
        throw authError;
      }

      const response = await dojoQuestApi.get('/customer/me?businessType=ojo,asset', {
        headers: {
          jwt_token: jwtToken
        }
      });
      console.log('📡 [getUserInfo] dojoQuestApi响应:', response.data);

      // 🔧 重要修复：检查第二个API的响应
      if (response.data.success === false) {
        // 创建包含响应数据的错误对象
        const authError = new Error(response.data.msgKey || response.data.message || '认证失败');
        (authError as any).response = {
          status: 200,
          data: response.data
        };
        throw authError;
      }

      console.log('✅ [getUserInfo] 获取用户信息成功');
      return response.data;
    });
  } catch (error: any) {
    console.error('❌ [getUserInfo] 获取用户信息失败:', error.message);

    // 临时注释掉状态清除和重定向逻辑;
    if (networkUtils.isAuthError(error)) {
      console.error('🔐 [getUserInfo] 认证失败，Token无效，清除验证状态');
      console.log('🔐 [getUserInfo] 错误详情:', {
        status: error.response?.status,
        data: error.response?.data
      });
      localStorage.removeItem('tokenValidated');
      localStorage.removeItem('currentUID');
      localStorage.removeItem('tokenSetupData');
      localStorage.removeItem('userInfo');
      window.location.reload();
    } else {
      // 网络或其他错误，显示友好提示但不清除状态
      console.log('🌐 [getUserInfo] 可能是网络问题，保留验证状态');
      window.$message?.warning('网络连接不稳定，请检查网络后重试');
    }

    throw error;
  }
};

// 自动下注相关API
export const autoBettingApi = {
  // 获取自动下注状态
  getStatus: (uid: string) => {
    return api.get('/auto-betting/status', {
      params: { uid }
    });
  },

  // 启动/停止自动下注
  toggle: (action: 'start' | 'stop', uid: string) => {
    return api.post('/auto-betting/toggle', {
      action,
      uid
    });
  },

  // 执行自动下注
  execute: (uid: string, config: any) => {
    return api.post('/auto-betting/execute', {
      uid,
      config
    });
  },

  // 记录下注结果
  recordResult: (data: {
    uid: string;
    round_id: string;
    token_symbol: string;
    amount: number;
    bet_id: string;
    success: boolean;
    result_data: any;
  }) => {
    return api.post('/auto-betting/record-result', data);
  },

  // 新增：获取用户配置
  getConfig: (uid: string) => {
    return api.get('/auto-betting/config', {
      params: { uid }
    });
  },

  // 新增：保存用户配置
  saveConfig: (uid: string, configData: any) => {
    return api.post('/auto-betting/config', {
      uid,
      ...configData
    });
  },

  // 新增：检查指定轮次是否已经下过注
  checkRoundBet: (uid: string, roundId: string) => {
    return api.get('/auto-betting/check-round-bet', {
      params: { uid, round_id: roundId }
    });
  }
};

// 投注表现分析API
export const bettingAnalysisApi = {
  // 获取用户投注表现分析（包含实际保本率）
  getPerformanceAnalysis: (
    uid: string,
    options?: {
      days?: number;
      limit?: number;
      limitRounds?: number;
      filterType?: 'days' | 'rounds';
    }
  ) => {
    // 🔧 修复：确保参数正确传递，支持按局数和按天数两种筛选方式
    const params: any = { uid };

    if (options?.filterType) {
      params.filter_type = options.filterType;
    }

    if (options?.days !== undefined && options?.days !== null) {
      params.days = options.days;
    }

    if (options?.limit !== undefined && options?.limit !== null) {
      params.limit = options.limit;
    }

    if (options?.limitRounds !== undefined && options?.limitRounds !== null) {
      params.limit_rounds = options.limitRounds;
    }

    console.log('🔧 API实际发送的参数:', params);

    return api.get('/v2/analysis/betting-performance', {
      params
    });
  }
};

// 游戏数据相关API
export const gameApi = {
  // 获取当前分析数据
  getCurrentAnalysis: () => {
    return api.get('/v2/predictions/current-analysis');
  },

  // 获取 Hybrid-Edge 動能預測數據
  getHybridPredictions: () => {
    return api.get('/v2/predictions/hybrid');
  },

  // 获取动能预测统计数据
  getMomentumPredictionStats: (recentRounds?: number) => {
    return api.get('/v2/predictions/momentum-stats', {
      params: { recent_rounds: recentRounds }
    });
  },

  // 获取动能预测历史数据
  getMomentumPredictionHistory: (params?: { limit?: number; offset?: number }) => {
    return api.get('/v2/predictions/momentum-history', {
      params
    });
  },

  // 获取下注ID
  getBetId: (roundId: string, jwtToken: string) => {
    return dojoGameApi.post(
      `/games/battle/${roundId}/bets/id`,
      {},
      {
        headers: {
          jwt_token: jwtToken,
          'Content-Type': 'application/json'
        }
      }
    );
  },

  // 执行实际下注
  placeBet: (roundId: string, betId: string, token: string, amount: number, jwtToken: string) => {
    return dojoGameApi.post(
      `/games/battle/${roundId}/real/bets`,
      {},
      {
        params: {
          betId,
          token,
          amount
        },
        headers: {
          jwt_token: jwtToken,
          'Content-Type': 'application/json'
        }
      }
    );
  },

  // 代幣下注
  placeDummyBet: (roundId: string, betId: string, token: string, amount: number, jwtToken: string) => {
    return dojoGameApi.post(
      `/games/battle/${roundId}/dummy/bets`,
      {},
      {
        params: {
          betId,
          token,
          amount
        },
        headers: {
          jwt_token: jwtToken,
          'Content-Type': 'application/json'
        }
      }
    );
  }
};

export default api;
