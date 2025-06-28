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

// 获取用户信息的方法
export const getUserInfo = async (jwtToken: string) => {
  try {
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

export default api;
