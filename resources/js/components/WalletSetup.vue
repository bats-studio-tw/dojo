<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center from-slate-900 via-slate-800 to-slate-900 bg-gradient-to-br p-4"
  >
    <div class="mx-auto max-w-md w-full">
      <NCard class="min-h-[500px] border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" size="large">
        <template #header>
          <div class="text-center">
            <h2 class="text-2xl text-white font-bold">🔐 身份验证</h2>
            <p class="mt-1 text-sm text-gray-300">请输入您的JWT Token以开始使用自动下注功能</p>
          </div>
        </template>

        <div class="space-y-6">
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
          <div v-if="error" class="border border-red-500/20 rounded-lg bg-red-500/5 p-3">
            <div class="text-sm text-red-400">
              {{ error }}
            </div>
          </div>

          <!-- 用户信息显示 -->
          <div v-if="userInfo" class="border border-green-500/20 rounded-lg bg-green-500/5 p-4">
            <div class="mb-2 flex items-center space-x-2">
              <span class="text-lg">👤</span>
              <span class="text-sm text-green-400 font-medium">用户信息</span>
            </div>

            <div class="text-sm text-gray-300 space-y-2">
              <div class="flex justify-between">
                <span>用户ID:</span>
                <span class="text-green-400 font-mono">{{ userInfo.uid }}</span>
              </div>
              <div class="flex justify-between">
                <span>可用余额:</span>
                <span class="text-green-400 font-semibold">${{ userInfo.ojoValue.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>排名:</span>
                <span class="text-green-400">{{ userInfo.rankPercent }}</span>
              </div>
              <div class="flex justify-between">
                <span>OJO价值:</span>
                <span class="text-green-400">${{ userInfo.ojoValue.toFixed(2) }}</span>
              </div>
            </div>
          </div>

          <!-- 用户资金信息 -->
          <div v-if="userStats || todayStats" class="border border-blue-500/20 rounded-lg bg-blue-500/5 p-4">
            <div class="mb-2 flex items-center space-x-2">
              <span class="text-lg">📊</span>
              <span class="text-sm text-blue-400 font-medium">下注统计</span>
            </div>

            <div class="grid grid-cols-2 gap-3 text-sm">
              <div v-if="userStats" class="space-y-1">
                <div class="text-gray-400">总体统计</div>
                <div class="text-blue-400">下注次数: {{ userStats.total_bets }}</div>
                <div class="text-blue-400">成功率: {{ userStats.success_rate.toFixed(1) }}%</div>
                <div
                  class="font-semibold"
                  :class="userStats.total_profit_loss >= 0 ? 'text-green-400' : 'text-red-400'"
                >
                  总盈亏: ${{ userStats.total_profit_loss.toFixed(2) }}
                </div>
              </div>

              <div v-if="todayStats" class="space-y-1">
                <div class="text-gray-400">今日统计</div>
                <div class="text-blue-400">今日下注: {{ todayStats.today_bets }}</div>
                <div
                  class="font-semibold"
                  :class="todayStats.today_profit_loss >= 0 ? 'text-green-400' : 'text-red-400'"
                >
                  今日盈亏: ${{ todayStats.today_profit_loss.toFixed(2) }}
                </div>
              </div>
            </div>
          </div>

          <!-- 操作按钮 -->
          <div class="flex flex-col space-y-3">
            <n-button
              @click="validateAndProceed"
              :loading="loading"
              :disabled="!form.jwt_token"
              type="primary"
              size="large"
              class="h-12"
            >
              <template #icon>
                <span>🚀</span>
              </template>
              {{ userInfo ? '进入控制台' : '验证Token' }}
            </n-button>

            <n-button @click="goToGuestView" type="tertiary" size="large" class="h-12">Guest 预览</n-button>
          </div>
        </div>
      </NCard>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ref, defineProps, defineEmits } from 'vue';
  import { router } from '@inertiajs/vue3';
  import { NCard, NInput, NButton } from 'naive-ui';
  import { getUserInfo } from '@/utils/api';
  import type { UserInfo } from '@/types';
  import api from '@/utils/api';

  // Props和Events
  defineProps<{
    visible: boolean;
  }>();

  const emit = defineEmits<{
    validated: [
      data: {
        uid: string;
        jwt_token: string;
        user_stats: any;
        today_stats: any;
        user_info: UserInfo;
      }
    ];
  }>();

  // 响应式数据
  const loading = ref(false);
  const error = ref('');
  const form = ref({
    jwt_token: ''
  });

  const userInfo = ref<UserInfo | null>(null);
  const userStats = ref<any>(null);
  const todayStats = ref<any>(null);

  // 加载用户统计数据
  const loadUserStats = async (uid: string) => {
    try {
      const response = await api.get('/auto-betting/user-stats', {
        params: { uid }
      });

      if (response.data.success) {
        userStats.value = response.data.data.user_stats;
        todayStats.value = response.data.data.today_stats;
      }
    } catch (err) {
      console.error('获取用户统计失败:', err);
    }
  };

  // 跳转到游客预览页面
  const goToGuestView = () => {
    router.visit('/views');
  };

  // 验证并继续
  const validateAndProceed = async () => {
    if (!form.value.jwt_token) {
      error.value = '请输入JWT Token';
      return;
    }

    loading.value = true;
    error.value = '';

    try {
      // 获取用户信息（同时验证JWT Token）
      const userInfoResponse = await getUserInfo(form.value.jwt_token);

      if (!userInfoResponse.success || !userInfoResponse.obj) {
        throw new Error('获取用户信息失败');
      }

      userInfo.value = userInfoResponse.obj;

      // 加载用户统计数据
      await loadUserStats(userInfo.value.uid);

      // 保存验证状态到localStorage
      localStorage.setItem('tokenValidated', 'true');
      localStorage.setItem('currentUID', userInfo.value.uid);
      localStorage.setItem(
        'tokenSetupData',
        JSON.stringify({
          jwt_token: form.value.jwt_token
        })
      );
      localStorage.setItem('userInfo', JSON.stringify(userInfo.value));

      // 发送验证成功事件
      emit('validated', {
        uid: userInfo.value.uid,
        jwt_token: form.value.jwt_token,
        user_stats: userStats.value,
        today_stats: todayStats.value,
        user_info: userInfo.value
      });
    } catch (err: any) {
      console.error('验证失败:', err);
      error.value = err.response?.data?.message || err.message || '验证失败，请检查JWT Token是否正确';
      userInfo.value = null;
      userStats.value = null;
      todayStats.value = null;
    } finally {
      loading.value = false;
    }
  };
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
</style>
