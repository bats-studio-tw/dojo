import axios from 'axios';

// 创建axios实例
const api = axios.create({
  baseURL: '/api',
  timeout: 10000,
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
      // 处理未授权错误，例如重定向到登录页
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// 创建 dojo quest API 实例
const dojoQuestApi = axios.create({
  baseURL: '/dojo-quest',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    Accept: '*/*'
  }
});

// 创建 dojo game API 实例
const dojoGameApi = axios.create({
  baseURL: '/dojo-api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
});

// 获取用户信息的方法
export const getUserInfo = async (jwtToken: string) => {
  try {
    const res = await dojoGameApi.get('/ladders/me', {
      headers: {
        jwt_token: jwtToken
      }
    });
    console.log(res.data);

    const response = await dojoQuestApi.get('/customer/me?businessType=ojo,asset', {
      headers: {
        jwt_token: jwtToken
      }
    });
    return response.data;
  } catch (error) {
    console.error('获取用户信息失败:', error);
    throw error;
  }
};

// 自动下注相关API
export const autoBettingApi = {
  // 获取自动下注状态
  getStatus: (walletAddress: string) => {
    return api.get('/auto-betting/status', {
      params: { wallet_address: walletAddress }
    });
  },

  // 启动/停止自动下注
  toggle: (action: 'start' | 'stop', walletAddress: string) => {
    return api.post('/auto-betting/toggle', {
      action,
      wallet_address: walletAddress
    });
  },

  // 模拟下注
  simulate: (config: any) => {
    return api.post('/auto-betting/simulate', {
      config
    });
  },

  // 执行自动下注
  execute: (walletAddress: string, config: any) => {
    return api.post('/auto-betting/execute', {
      wallet_address: walletAddress,
      config
    });
  },

  // 记录下注结果
  recordResult: (data: {
    wallet_address: string;
    round_id: string;
    token_symbol: string;
    amount: number;
    bet_id: string;
    success: boolean;
    result_data: any;
  }) => {
    return api.post('/auto-betting/record-result', data);
  }
};

// 游戏数据相关API
export const gameApi = {
  // 获取当前分析数据
  getCurrentAnalysis: () => {
    return api.get('/game/current-analysis');
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
      {
        betId,
        token,
        amount
      },
      {
        headers: {
          jwt_token: jwtToken,
          'Content-Type': 'application/json'
        }
      }
    );
  }
};

export default api;
