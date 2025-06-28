<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center from-slate-900 via-purple-900 to-slate-900 bg-gradient-to-br p-4"
  >
    <div class="mx-auto w-full max-w-md">
      <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" size="large">
        <template #header>
          <div class="text-center">
            <h2 class="text-2xl text-white font-bold">🔐 钱包验证</h2>
            <p class="mt-1 text-sm text-gray-300">请输入您的钱包地址和JWT Token以开始使用自动下注功能</p>
          </div>
        </template>

        <div class="space-y-6">
          <!-- 钱包地址输入 -->
          <div class="space-y-2">
            <label class="text-sm text-gray-300 font-medium">钱包地址 *</label>
            <n-input
              v-model:value="form.wallet_address"
              placeholder="请输入您的钱包地址 (如: 0x...)"
              :disabled="loading"
              @keydown.enter="validateAndProceed"
            />
            <div class="text-xs text-gray-400">用于记录和追踪您的自动下注历史</div>
          </div>

          <!-- JWT Token输入 -->
          <div class="space-y-2">
            <label class="text-sm text-gray-300 font-medium">JWT Token *</label>
            <n-input
              v-model:value="form.jwt_token"
              placeholder="请输入您的JWT Token"
              type="password"
              show-password-on="click"
              :disabled="loading"
              @keydown.enter="validateAndProceed"
            />
            <div class="text-xs text-gray-400">用于执行自动下注操作的授权令牌</div>
          </div>

          <!-- 错误信息 -->
          <div v-if="error" class="border border-red-500/30 rounded-lg bg-red-500/10 p-3">
            <div class="text-sm text-red-400">
              {{ error }}
            </div>
          </div>

          <!-- 用户资金信息 -->
          <div v-if="userInfo" class="border border-green-500/20 rounded-lg bg-green-500/10 p-3">
            <h4 class="mb-2 text-sm text-green-400 font-semibold">💰 账户资金</h4>
            <div class="grid grid-cols-2 gap-2 text-xs">
              <div class="flex justify-between">
                <span class="text-gray-400">OJO余额:</span>
                <span class="text-green-400 font-bold">{{ userInfo.ojoValue?.toFixed(2) || '0.00' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">可用资金:</span>
                <span class="text-green-400 font-bold">{{ userInfo.available?.toFixed(2) || '0.00' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">排名百分比:</span>
                <span class="text-yellow-400 font-medium">{{ userInfo.rankPercent || 'N/A' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">排名分值:</span>
                <span class="text-yellow-400 font-medium">{{ userInfo.rankValue || 'N/A' }}</span>
              </div>
            </div>
          </div>

          <!-- 用户历史统计 (如果有) -->
          <div
            v-if="userStats && userStats.total_bets > 0"
            class="border border-blue-500/20 rounded-lg bg-blue-500/10 p-3"
          >
            <h4 class="mb-2 text-sm text-blue-400 font-semibold">📊 下注历史记录</h4>
            <div class="grid grid-cols-2 gap-2 text-xs">
              <div class="flex justify-between">
                <span class="text-gray-400">总下注次数:</span>
                <span class="text-white font-medium">{{ userStats.total_bets }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">成功率:</span>
                <span class="text-white font-medium">{{ userStats.success_rate.toFixed(1) }}%</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">总盈亏:</span>
                <span class="font-medium" :class="userStats.total_profit_loss >= 0 ? 'text-green-400' : 'text-red-400'">
                  ${{ userStats.total_profit_loss.toFixed(2) }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">今日盈亏:</span>
                <span
                  class="font-medium"
                  :class="(todayStats?.today_profit_loss || 0) >= 0 ? 'text-green-400' : 'text-red-400'"
                >
                  ${{ (todayStats?.today_profit_loss || 0).toFixed(2) }}
                </span>
              </div>
            </div>
          </div>

          <!-- 操作按钮 -->
          <div class="flex space-x-3">
            <n-button
              @click="validateAndProceed"
              :loading="loading"
              :disabled="!form.wallet_address || !form.jwt_token"
              type="primary"
              size="large"
              class="flex-1"
            >
              {{ userStats && userStats.total_bets > 0 ? '继续使用' : '开始使用' }}
            </n-button>
          </div>

          <!-- 免责声明 -->
          <div class="border-t border-white/10 pt-4 text-xs text-gray-500">
            <p>⚠️ 请确保您的JWT Token安全，不要与他人分享。所有下注操作将使用您提供的Token执行。</p>
          </div>
        </div>
      </NCard>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ref, watch, onMounted } from 'vue';
  import { useMessage } from 'naive-ui';
  import api, { getUserInfo } from '@/utils/api';

  // Props
  interface Props {
    visible: boolean;
  }

  defineProps<Props>();

  // Emits
  const emit = defineEmits<{
    validated: [
      data: {
        wallet_address: string;
        jwt_token: string;
        user_stats: any;
        today_stats: any;
        user_info?: any;
      }
    ];
  }>();

  // 获取message实例
  const getMessageInstance = () => {
    try {
      return useMessage();
    } catch {
      console.warn('Message provider not ready yet');
      return null;
    }
  };

  // 表单数据
  const form = ref({
    wallet_address: '',
    jwt_token: ''
  });

  // 状态
  const loading = ref(false);
  const error = ref('');
  const userStats = ref<any>(null);
  const todayStats = ref<any>(null);
  const userInfo = ref<any>(null);

  // 从localStorage读取保存的数据
  const loadSavedData = () => {
    try {
      const saved = localStorage.getItem('walletSetupData');
      if (saved) {
        const data = JSON.parse(saved);
        form.value.wallet_address = data.wallet_address || '';
        form.value.jwt_token = data.jwt_token || '';

        // 如果有钱包地址，尝试获取统计信息
        if (data.wallet_address) {
          loadUserStats(data.wallet_address);
        }
      }
    } catch (err) {
      console.error('读取保存的数据失败:', err);
    }
  };

  // 获取用户统计信息
  const loadUserStats = async (walletAddress: string) => {
    try {
      const response = await api.get('/auto-betting/user-stats', {
        params: { wallet_address: walletAddress }
      });

      if (response.data.success) {
        userStats.value = response.data.data.user_stats;
        todayStats.value = response.data.data.today_stats;
      }
    } catch (err) {
      console.error('获取用户统计失败:', err);
    }
  };

  // 验证并继续
  const validateAndProceed = async () => {
    if (!form.value.wallet_address || !form.value.jwt_token) {
      error.value = '请填写所有必填字段';
      return;
    }

    loading.value = true;
    error.value = '';

    try {
      const response = await api.post('/auto-betting/validate-wallet', {
        wallet_address: form.value.wallet_address,
        jwt_token: form.value.jwt_token
      });

      if (response.data.success) {
        console.log('钱包验证成功，响应数据:', response.data);

        // 获取用户信息
        try {
          const userInfoResponse = await getUserInfo(form.value.jwt_token);
          console.log('获取用户信息成功:', userInfoResponse);
          userInfo.value = userInfoResponse.obj || userInfoResponse;
        } catch (userInfoError) {
          console.warn('获取用户信息失败，但继续验证流程:', userInfoError);
        }

        // 保存到localStorage
        localStorage.setItem(
          'walletSetupData',
          JSON.stringify({
            wallet_address: form.value.wallet_address,
            jwt_token: form.value.jwt_token
          })
        );

        const validatedData = {
          wallet_address: form.value.wallet_address,
          jwt_token: form.value.jwt_token,
          user_stats: response.data.data.user_stats,
          today_stats: response.data.data.today_stats,
          user_info: userInfo.value
        };

        console.log('准备发送验证事件，数据:', validatedData);

        getMessageInstance()?.success('验证成功！');

        // 延迟一点发送事件，确保消息显示后再切换界面
        setTimeout(() => {
          console.log('发送验证成功事件...');
          emit('validated', validatedData);
        }, 500);
      } else {
        error.value = response.data.message || '验证失败';
      }
    } catch (err: any) {
      console.error('验证失败:', err);
      error.value = err.response?.data?.message || '验证失败，请检查网络连接';
    } finally {
      loading.value = false;
    }
  };

  // 监听钱包地址变化，获取统计信息
  watch(
    () => form.value.wallet_address,
    (newAddress) => {
      if (newAddress && newAddress.length > 10) {
        loadUserStats(newAddress);
      } else {
        userStats.value = null;
        todayStats.value = null;
      }
    }
  );

  // 组件挂载时读取保存的数据
  onMounted(() => {
    loadSavedData();
  });
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
</style>
