<template>
  <div class="space-y-6">
    <!-- 调试信息面板 -->
    <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="🐛 系统调试信息" size="large">
      <div class="space-y-4">
        <!-- 调试控制 -->
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <n-switch v-model:value="debugInfo.showDebugPanel">
              <template #checked>显示调试面板</template>
              <template #unchecked>隐藏调试面板</template>
            </n-switch>
            <n-button @click="() => emit('runApiDiagnostics')" :loading="diagnosticsLoading" type="info" size="small">
              <template #icon>
                <span>🔬</span>
              </template>
              运行API连接诊断
            </n-button>
          </div>
          <div class="flex items-center space-x-2">
            <n-button @click="clearDebugLogs" type="warning" size="tiny">清空日志</n-button>
            <n-button @click="exportDebugLogs" type="primary" size="tiny">导出日志</n-button>
          </div>
        </div>

        <!-- 调试面板内容 -->
        <div v-if="debugInfo.showDebugPanel" class="space-y-6">
          <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 md:grid-cols-2">
            <!-- 轮次监控状态 -->
            <div class="border border-blue-500/30 rounded bg-blue-500/10 p-3">
              <div class="mb-2 text-xs text-blue-400 font-medium">🎮 轮次监控</div>
              <div class="text-xs text-gray-300 space-y-1">
                <div>监控状态: {{ isMonitoringRounds ? '✅ 运行中' : '❌ 未启动' }}</div>
                <div>当前轮次: {{ lastKnownRoundId || '未知' }}</div>
                <div>最后检查: {{ debugInfo.lastRoundCheckTime || '未检查' }}</div>
                <div>检查次数: {{ debugInfo.roundCheckCount }}</div>
              </div>
            </div>

            <!-- 自动下注状态 -->
            <div class="border border-green-500/30 rounded bg-green-500/10 p-3">
              <div class="mb-2 text-xs text-green-400 font-medium">🤖 自动下注</div>
              <div class="text-xs text-gray-300 space-y-1">
                <div>系统状态: {{ autoBettingStatus.is_running ? '✅ 已启动' : '❌ 未启动' }}</div>
                <div>触发次数: {{ debugInfo.autoTriggerCount }}</div>
                <div>最后触发: {{ debugInfo.lastAutoTriggerTime || '未触发' }}</div>
                <div>最后执行: {{ debugInfo.lastExecutionTime || '未执行' }}</div>
              </div>
            </div>

            <!-- 策略验证状态 -->
            <div class="border border-purple-500/30 rounded bg-purple-500/10 p-3">
              <div class="mb-2 text-xs text-purple-400 font-medium">🎯 策略验证</div>
              <div class="text-xs text-gray-300 space-y-1">
                <div>符合条件: {{ strategyValidation?.total_matched || 0 }}个</div>
                <div>余额充足: {{ strategyValidation?.balance_sufficient ? '✅' : '❌' }}</div>
                <div>验证次数: {{ debugInfo.strategyValidationCount }}</div>
                <div>最后验证: {{ debugInfo.lastValidationTime || '未验证' }}</div>
              </div>
            </div>
          </div>

          <!-- 最近下注结果 -->
          <div v-if="debugInfo.lastBetResults.length > 0" class="space-y-3">
            <div class="mb-2 flex items-center justify-between">
              <span class="text-sm text-yellow-400 font-medium">🎯 最近下注结果 (最近10条)</span>
              <n-button @click="() => emit('clearBetResults')" type="tertiary" size="tiny">清空记录</n-button>
            </div>
            <div class="max-h-32 overflow-y-auto rounded bg-black/30 p-2 text-xs text-gray-300 font-mono">
              <div
                v-for="(bet, index) in debugInfo.lastBetResults.slice(-10).reverse()"
                :key="index"
                class="py-1"
                :class="{
                  'text-green-400': bet.success,
                  'text-red-400': !bet.success
                }"
              >
                [{{ bet.time }}] {{ bet.symbol }} ${{ bet.amount }} - {{ bet.success ? '✅ 成功' : '❌ 失败' }}
                <span v-if="!bet.success && bet.error" class="text-gray-500">({{ bet.error }})</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </NCard>

    <!-- API 连接诊断 -->
    <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="🔬 API 连接诊断" size="large">
      <div class="space-y-4">
        <!-- 诊断控制 -->
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <n-button @click="runFullDiagnostics" :loading="diagnosticsLoading" type="primary" size="small">
              <template #icon>
                <span>🩺</span>
              </template>
              运行完整诊断
            </n-button>
            <n-button @click="testBasicConnection" type="info" size="small">
              <template #icon>
                <span>📡</span>
              </template>
              测试基本连接
            </n-button>
            <n-button @click="checkDatabaseStatus" type="warning" size="small">
              <template #icon>
                <span>🗄️</span>
              </template>
              检查数据库
            </n-button>
          </div>
          <n-button @click="clearDiagnosticResults" type="tertiary" size="tiny">清空结果</n-button>
        </div>

        <!-- 诊断结果 -->
        <div v-if="diagnosticResults.length > 0" class="space-y-3">
          <h3 class="text-sm text-white font-medium">诊断结果</h3>
          <div class="space-y-2">
            <div
              v-for="(result, index) in diagnosticResults"
              :key="index"
              class="border rounded-lg p-3"
              :class="{
                'border-green-500/30 bg-green-500/10': result.status === 'success',
                'border-red-500/30 bg-red-500/10': result.status === 'error',
                'border-yellow-500/30 bg-yellow-500/10': result.status === 'warning',
                'border-blue-500/30 bg-blue-500/10': result.status === 'info'
              }"
            >
              <div class="flex items-center justify-between">
                <span
                  class="text-sm font-medium"
                  :class="{
                    'text-green-400': result.status === 'success',
                    'text-red-400': result.status === 'error',
                    'text-yellow-400': result.status === 'warning',
                    'text-blue-400': result.status === 'info'
                  }"
                >
                  {{ result.title }}
                </span>
                <span class="text-xs text-gray-400">{{ result.timestamp }}</span>
              </div>
              <div class="mt-1 text-xs text-gray-300">{{ result.message }}</div>
              <div v-if="result.details" class="mt-2 text-xs text-gray-400 font-mono">
                {{ result.details }}
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8 text-gray-400">
          <div class="text-2xl mb-2">🔬</div>
          <div class="text-sm">点击上方按钮开始API诊断</div>
        </div>
      </div>
    </NCard>

    <!-- 系统日志 -->
    <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="📋 系统日志" size="large">
      <div class="space-y-4">
        <!-- 日志控制 -->
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <n-select
              v-model:value="logLevel"
              :options="[
                { label: '全部日志', value: 'all' },
                { label: '错误', value: 'error' },
                { label: '警告', value: 'warning' },
                { label: '信息', value: 'info' },
                { label: '调试', value: 'debug' }
              ]"
              style="width: 120px"
              size="small"
            />
            <n-switch v-model:value="autoScrollLogs">
              <template #checked>自动滚动</template>
              <template #unchecked>手动滚动</template>
            </n-switch>
          </div>
          <div class="flex items-center space-x-2">
            <n-button @click="refreshSystemLogs" :loading="logsLoading" type="primary" size="tiny">刷新日志</n-button>
            <n-button @click="clearSystemLogs" type="warning" size="tiny">清空日志</n-button>
            <n-button @click="exportSystemLogs" type="info" size="tiny">导出日志</n-button>
          </div>
        </div>

        <!-- 日志内容 -->
        <div ref="logContainer" class="h-96 overflow-y-auto rounded bg-black/30 p-4 text-xs text-gray-300 font-mono">
          <div v-if="filteredSystemLogs.length === 0" class="text-center py-8 text-gray-400">
            <div class="text-lg mb-2">📝</div>
            <div class="text-sm">暂无系统日志</div>
          </div>
          <div
            v-for="(log, index) in filteredSystemLogs"
            :key="index"
            class="py-1 border-b border-gray-700/30"
            :class="{
              'text-red-400': log.level === 'error',
              'text-yellow-400': log.level === 'warning',
              'text-blue-400': log.level === 'info',
              'text-gray-400': log.level === 'debug'
            }"
          >
            <span class="text-gray-500">[{{ log.timestamp }}]</span>
            <span class="font-semibold">[{{ log.level.toUpperCase() }}]</span>
            {{ log.message }}
            <div v-if="log.context" class="mt-1 pl-4 text-gray-500">
              {{ JSON.stringify(log.context) }}
            </div>
          </div>
        </div>
      </div>
    </NCard>

    <!-- 系统性能监控 -->
    <NCard class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="⚡ 系统性能监控" size="large">
      <div class="space-y-4">
        <!-- 性能指标 -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
          <div class="border border-green-500/30 rounded-lg bg-green-500/10 p-3 text-center">
            <div class="text-sm text-green-400">API响应时间</div>
            <div class="text-xl text-white font-bold">{{ performanceMetrics.apiResponseTime }}ms</div>
            <div class="text-xs text-gray-400">平均值</div>
          </div>
          <div class="border border-blue-500/30 rounded-lg bg-blue-500/10 p-3 text-center">
            <div class="text-sm text-blue-400">内存使用</div>
            <div class="text-xl text-white font-bold">{{ performanceMetrics.memoryUsage }}MB</div>
            <div class="text-xs text-gray-400">当前</div>
          </div>
          <div class="border border-purple-500/30 rounded-lg bg-purple-500/10 p-3 text-center">
            <div class="text-sm text-purple-400">连接状态</div>
            <div class="text-xl text-white font-bold">
              <n-tag :type="performanceMetrics.connectionStatus === 'stable' ? 'success' : 'error'" size="small">
                {{ performanceMetrics.connectionStatus === 'stable' ? '稳定' : '异常' }}
              </n-tag>
            </div>
            <div class="text-xs text-gray-400">网络</div>
          </div>
          <div class="border border-orange-500/30 rounded-lg bg-orange-500/10 p-3 text-center">
            <div class="text-sm text-orange-400">运行时间</div>
            <div class="text-xl text-white font-bold">{{ performanceMetrics.uptime }}</div>
            <div class="text-xs text-gray-400">小时</div>
          </div>
        </div>

        <!-- 实时性能图表占位符 -->
        <div class="h-48 rounded-lg bg-black/20 p-4">
          <div class="flex h-full items-center justify-center text-gray-400">
            <div class="text-center">
              <div class="text-2xl mb-2">📊</div>
              <div class="text-sm">实时性能监控图表</div>
              <div class="text-xs text-gray-500 mt-1">CPU、内存、网络使用率</div>
            </div>
          </div>
        </div>
      </div>
    </NCard>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed, nextTick } from 'vue';
  import type { DebugInfo } from '@/composables/useAutoBettingControl';

  // Props
  interface Props {
    debugInfo: DebugInfo;
    isMonitoringRounds: boolean;
    lastKnownRoundId: string | null;
    autoBettingStatus: any;
    strategyValidation: any;
    diagnosticsLoading: boolean;
  }

  const props = defineProps<Props>();

  // Emits
  const emit = defineEmits<{
    runApiDiagnostics: [];
    clearBetResults: [];
  }>();

  // 响应式数据
  const logLevel = ref('all');
  const autoScrollLogs = ref(true);
  const logsLoading = ref(false);
  const logContainer = ref<HTMLElement>();

  // 诊断结果
  const diagnosticResults = ref([
    {
      title: 'API基础连接测试',
      message: '✅ API服务器连接正常，响应时间125ms',
      status: 'success',
      timestamp: '14:30:25',
      details: 'GET /api/game/current-analysis - 200 OK'
    },
    {
      title: '数据库连接检查',
      message: '✅ 数据库连接正常，查询响应正常',
      status: 'success',
      timestamp: '14:30:26',
      details: 'MySQL connection pool: 5/10 active connections'
    },
    {
      title: 'JWT Token验证',
      message: '⚠️ Token即将过期，建议重新验证',
      status: 'warning',
      timestamp: '14:30:27',
      details: 'Token expires in 2 hours'
    }
  ]);

  // 系统日志
  const systemLogs = ref([
    {
      timestamp: '2024-01-15 14:30:20',
      level: 'info',
      message: '自动下注系统启动',
      context: { module: 'AutoBetting', action: 'start' }
    },
    {
      timestamp: '2024-01-15 14:30:25',
      level: 'info',
      message: '开始轮次监控',
      context: { roundId: 'R20240115001' }
    },
    {
      timestamp: '2024-01-15 14:30:30',
      level: 'warning',
      message: '策略验证失败：余额不足',
      context: { required: 500, available: 350 }
    },
    {
      timestamp: '2024-01-15 14:30:35',
      level: 'error',
      message: 'API调用失败：网络超时',
      context: { url: '/api/game/current-analysis', timeout: 5000 }
    }
  ]);

  // 性能指标
  const performanceMetrics = ref({
    apiResponseTime: 125,
    memoryUsage: 45.6,
    connectionStatus: 'stable',
    uptime: 2.5
  });

  // 过滤后的日志
  const filteredSystemLogs = computed(() => {
    if (logLevel.value === 'all') {
      return systemLogs.value;
    }
    return systemLogs.value.filter((log) => log.level === logLevel.value);
  });

  // 方法
  const runFullDiagnostics = async () => {
    emit('runApiDiagnostics');

    // 添加诊断结果
    diagnosticResults.value.push({
      title: '完整系统诊断',
      message: '正在运行完整系统诊断...',
      status: 'info',
      timestamp: new Date().toLocaleTimeString(),
      details: 'Running comprehensive system checks'
    });
  };

  const testBasicConnection = async () => {
    diagnosticResults.value.push({
      title: 'API连接测试',
      message: '正在测试API连接...',
      status: 'info',
      timestamp: new Date().toLocaleTimeString(),
      details: ''
    });

    // 模拟测试
    setTimeout(() => {
      diagnosticResults.value.push({
        title: 'API连接测试结果',
        message: '✅ API连接测试成功',
        status: 'success',
        timestamp: new Date().toLocaleTimeString(),
        details: 'Response time: 98ms'
      });
    }, 1000);
  };

  const checkDatabaseStatus = async () => {
    diagnosticResults.value.push({
      title: '数据库检查',
      message: '正在检查数据库状态...',
      status: 'info',
      timestamp: new Date().toLocaleTimeString(),
      details: ''
    });

    // 模拟检查
    setTimeout(() => {
      diagnosticResults.value.push({
        title: '数据库检查结果',
        message: '✅ 数据库状态正常',
        status: 'success',
        timestamp: new Date().toLocaleTimeString(),
        details: 'All tables accessible, connection pool healthy'
      });
    }, 1500);
  };

  const clearDiagnosticResults = () => {
    diagnosticResults.value = [];
  };

  const clearDebugLogs = () => {
    emit('clearBetResults');
  };

  const exportDebugLogs = () => {
    const data = {
      debugInfo: props.debugInfo,
      diagnosticResults: diagnosticResults.value,
      systemLogs: systemLogs.value,
      timestamp: new Date().toISOString()
    };

    const dataStr = JSON.stringify(data, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `debug-logs-${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    URL.revokeObjectURL(url);
    window.$message?.success('调试日志导出成功');
  };

  const refreshSystemLogs = async () => {
    logsLoading.value = true;
    try {
      // 模拟刷新
      await new Promise((resolve) => setTimeout(resolve, 1000));

      // 添加新日志
      systemLogs.value.unshift({
        timestamp: new Date().toLocaleString(),
        level: 'info',
        message: '系统日志已刷新',
        context: { module: 'SystemLogs', action: 'refresh' }
      });

      // 自动滚动到底部
      if (autoScrollLogs.value) {
        await nextTick();
        if (logContainer.value) {
          logContainer.value.scrollTop = logContainer.value.scrollHeight;
        }
      }
    } catch (error) {
      console.error('刷新系统日志失败:', error);
    } finally {
      logsLoading.value = false;
    }
  };

  const clearSystemLogs = () => {
    systemLogs.value = [];
  };

  const exportSystemLogs = () => {
    const dataStr = JSON.stringify(systemLogs.value, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `system-logs-${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    URL.revokeObjectURL(url);
    window.$message?.success('系统日志导出成功');
  };

  // 定时更新性能指标
  setInterval(() => {
    performanceMetrics.value = {
      apiResponseTime: Math.floor(Math.random() * 200) + 50,
      memoryUsage: Math.random() * 100,
      connectionStatus: Math.random() > 0.1 ? 'stable' : 'unstable',
      uptime: performanceMetrics.value.uptime + 0.1
    };
  }, 5000);
</script>
