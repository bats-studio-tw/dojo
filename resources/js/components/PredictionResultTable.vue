<template>
  <NCard title="预测结果" class="mb-6">
    <NTable
      :data="results"
      :pagination="pagination"
      :loading="isLoading"
      :row-key="(row: any) => row.id"
      class="w-full"
    >
      <template #header>
        <tr>
          <th class="text-left">排名</th>
          <th class="text-left">代币</th>
          <th class="text-left">预测分数</th>
          <th class="text-left">Elo分数</th>
          <th class="text-left">动量分数</th>
          <th class="text-left">成交量分数</th>
          <th class="text-left">策略</th>
          <th class="text-left">操作</th>
        </tr>
      </template>

      <template #default="{ row }">
        <tr>
          <td>
            <NTag :type="getRankTagType(row.predict_rank)" size="small">
              {{ row.predict_rank }}
            </NTag>
          </td>
          <td class="font-medium">{{ row.token }}</td>
          <td>
            <span class="font-mono text-sm">{{ row.predict_score.toFixed(4) }}</span>
          </td>
          <td>
            <div class="text-sm">
              <div>原始: {{ row.elo_score.toFixed(2) }}</div>
              <div class="text-gray-500">标准化: {{ row.norm_elo.toFixed(4) }}</div>
            </div>
          </td>
          <td>
            <div class="text-sm">
              <div>原始: {{ row.momentum_score.toFixed(2) }}</div>
              <div class="text-gray-500">标准化: {{ row.norm_momentum.toFixed(4) }}</div>
            </div>
          </td>
          <td>
            <div class="text-sm">
              <div>原始: {{ row.volume_score.toFixed(2) }}</div>
              <div class="text-gray-500">标准化: {{ row.norm_volume.toFixed(4) }}</div>
            </div>
          </td>
          <td>
            <NTag type="info" size="small">{{ row.strategy_tag }}</NTag>
          </td>
          <td>
            <NButton size="small" type="info" ghost @click="showDetails(row)">详情</NButton>
          </td>
        </tr>
      </template>
    </NTable>

    <!-- 详情弹窗 -->
    <NModal v-model:show="showDetailModal" preset="card" title="预测详情" style="width: 600px">
      <div v-if="selectedRow" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <h4 class="font-medium text-gray-900 mb-2">基本信息</h4>
            <div class="space-y-2 text-sm">
              <div>
                <span class="text-gray-500">代币:</span>
                {{ selectedRow.token }}
              </div>
              <div>
                <span class="text-gray-500">排名:</span>
                {{ selectedRow.predict_rank }}
              </div>
              <div>
                <span class="text-gray-500">策略:</span>
                {{ selectedRow.strategy_tag }}
              </div>
              <div>
                <span class="text-gray-500">预测分数:</span>
                {{ selectedRow.predict_score.toFixed(4) }}
              </div>
            </div>
          </div>
          <div>
            <h4 class="font-medium text-gray-900 mb-2">权重配置</h4>
            <div class="space-y-1 text-sm">
              <div v-for="(weight, feature) in selectedRow.used_weights" :key="feature">
                <span class="text-gray-500">{{ feature }}:</span>
                {{ (weight * 100).toFixed(1) }}%
              </div>
            </div>
          </div>
        </div>

        <div>
          <h4 class="font-medium text-gray-900 mb-2">标准化方式</h4>
          <div class="space-y-1 text-sm">
            <div v-for="(method, feature) in selectedRow.used_normalization" :key="feature">
              <span class="text-gray-500">{{ feature }}:</span>
              {{ method }}
            </div>
          </div>
        </div>

        <div>
          <h4 class="font-medium text-gray-900 mb-2">分数详情</h4>
          <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
              <div class="font-medium">Elo分数</div>
              <div>原始: {{ selectedRow.elo_score.toFixed(2) }}</div>
              <div class="text-gray-500">标准化: {{ selectedRow.norm_elo.toFixed(4) }}</div>
            </div>
            <div>
              <div class="font-medium">动量分数</div>
              <div>原始: {{ selectedRow.momentum_score.toFixed(2) }}</div>
              <div class="text-gray-500">标准化: {{ selectedRow.norm_momentum.toFixed(4) }}</div>
            </div>
            <div>
              <div class="font-medium">成交量分数</div>
              <div>原始: {{ selectedRow.volume_score.toFixed(2) }}</div>
              <div class="text-gray-500">标准化: {{ selectedRow.norm_volume.toFixed(4) }}</div>
            </div>
          </div>
        </div>
      </div>
    </NModal>
  </NCard>
</template>

<script setup lang="ts">
  import { ref, computed } from 'vue';
  import { NTable, NButton, NTag, NCard, NModal } from 'naive-ui';

  // Props
  interface Props {
    results: any[];
    isLoading?: boolean;
  }

  const props = withDefaults(defineProps<Props>(), {
    isLoading: false
  });

  // 响应式数据
  const showDetailModal = ref(false);
  const selectedRow = ref<any | null>(null);

  // 计算属性
  const pagination = computed(() => ({
    page: 1,
    pageSize: 10,
    showSizePicker: true,
    pageSizes: [10, 20, 50],
    onChange: (page: number) => {
      console.log('Page changed:', page);
    },
    onUpdatePageSize: (pageSize: number) => {
      console.log('Page size changed:', pageSize);
    }
  }));

  // 方法
  const getRankTagType = (rank: number) => {
    if (rank === 1) return 'success';
    if (rank <= 3) return 'warning';
    if (rank <= 5) return 'info';
    return 'default';
  };

  const showDetails = (row: any) => {
    selectedRow.value = row;
    showDetailModal.value = true;
  };
</script>

<style scoped>
  .prediction-result-table {
    /* 组件样式 */
  }
</style>
