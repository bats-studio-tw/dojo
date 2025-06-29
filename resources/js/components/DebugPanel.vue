<template>
  <div class="space-y-4">
    <!-- 调试控制按钮 -->
    <div v-if="!showDebugPanel" class="text-center space-y-3">
      <n-button @click="showDebugPanel = true" type="warning" size="small">
        <template #icon><span>🐛</span></template>
        显示调试信息
      </n-button>

      <div>
        <n-button @click="$emit('runDiagnostics')" :loading="diagnosticsLoading" type="info" size="small">
          <template #icon><span>🔬</span></template>
          运行API连接诊断
        </n-button>
      </div>
    </div>

    <!-- 调试信息面板 -->
    <div v-if="showDebugPanel" class="border border-yellow-500/30 rounded-lg bg-yellow-500/10 p-4">
      <div class="mb-3 flex items-center justify-between">
        <h3 class="text-lg text-yellow-400 font-semibold">🐛 调试信息面板</h3>
        <n-button @click="showDebugPanel = false" type="tertiary" size="tiny">隐藏调试</n-button>
      </div>

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
      <div v-if="debugInfo.lastBetResults.length > 0" class="mt-4 border-t border-yellow-500/30 pt-3">
        <div class="mb-2 flex items-center justify-between">
          <span class="text-xs text-yellow-400 font-medium">🎯 最近下注结果 (最近10条)</span>
          <n-button @click="debugInfo.lastBetResults = []" type="tertiary" size="tiny">清空记录</n-button>
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
</template>

<script setup lang="ts">
  interface Props {
    debugInfo: any;
    isMonitoringRounds: boolean;
    lastKnownRoundId: string | null;
    autoBettingStatus: any;
    strategyValidation: any;
    diagnosticsLoading: boolean;
  }

  interface Emits {
    (e: 'runDiagnostics'): void;
  }

  import { ref } from 'vue';

  defineProps<Props>();
  defineEmits<Emits>();

  const showDebugPanel = ref(false);
</script>
