<template>
  <div class="prediction-result-table">
    <n-card title="预测结果" class="mb-4">
      <!-- 结果统计 -->
      <div v-if="results.length" class="mb-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <n-statistic label="预测代币数" :value="results.length" />
          <n-statistic label="最高分代币" :value="topPrediction?.token || '-'" :value-style="{ color: '#18a058' }" />
          <n-statistic
            label="最高分"
            :value="topPrediction?.predict_score?.toFixed(4) || '-'"
            :value-style="{ color: '#18a058' }"
          />
          <n-statistic label="平均分" :value="averageScore.toFixed(4)" :value-style="{ color: '#2080f0' }" />
        </div>
      </div>

      <!-- 结果表格 -->
      <n-data-table
        :columns="columns"
        :data="sortedResults"
        :pagination="pagination"
        :bordered="false"
        :striped="true"
        :loading="isLoading"
        class="mt-4"
      />
    </n-card>
  </div>
</template>

<script setup lang="ts">
  import { computed, h } from 'vue';
  import { NTag, NProgress } from 'naive-ui';
  import type { PredictionResultDTO } from '@/types/prediction';

  // Props
  interface Props {
    results: PredictionResultDTO[];
    isLoading?: boolean;
  }

  const props = withDefaults(defineProps<Props>(), {
    isLoading: false
  });

  // 计算属性
  const sortedResults = computed(() => [...props.results].sort((a, b) => a.predict_rank - b.predict_rank));

  const topPrediction = computed(() => props.results.find((r) => r.predict_rank === 1));

  const averageScore = computed(() => {
    if (props.results.length === 0) return 0;
    const sum = props.results.reduce((acc, r) => acc + r.predict_score, 0);
    return sum / props.results.length;
  });

  // 表格列定义
  const columns = [
    {
      title: '排名',
      key: 'predict_rank',
      width: 80,
      align: 'center',
      render: (row: PredictionResultDTO) => {
        const rank = row.predict_rank;
        let color = 'default';
        if (rank === 1) color = 'success';
        else if (rank === 2) color = 'warning';
        else if (rank === 3) color = 'info';

        return h(NTag, { type: color as any, size: 'small' }, { default: () => rank });
      }
    },
    {
      title: '代币',
      key: 'token',
      width: 100,
      align: 'center',
      render: (row: PredictionResultDTO) => {
        return h('span', { class: 'font-mono font-bold' }, row.token);
      }
    },
    {
      title: '预测分数',
      key: 'predict_score',
      width: 120,
      align: 'center',
      render: (row: PredictionResultDTO) => {
        const score = row.predict_score;
        const percentage = (score * 100).toFixed(1);
        return h('div', { class: 'text-center' }, [
          h('div', { class: 'font-bold text-lg' }, score.toFixed(4)),
          h('div', { class: 'text-xs text-gray-500' }, `${percentage}%`)
        ]);
      }
    },
    {
      title: '特征分数',
      key: 'features',
      width: 200,
      render: (row: PredictionResultDTO) => {
        return h('div', { class: 'space-y-1' }, [
          h('div', { class: 'flex items-center gap-2' }, [
            h('span', { class: 'text-xs text-gray-500 w-12' }, 'Elo:'),
            h(NProgress, {
              type: 'line',
              percentage: Math.abs(row.norm_elo) * 100,
              color: row.norm_elo > 0 ? '#18a058' : '#d03050',
              showIndicator: false,
              height: 8
            }),
            h('span', { class: 'text-xs font-mono' }, row.norm_elo.toFixed(3))
          ]),
          h('div', { class: 'flex items-center gap-2' }, [
            h('span', { class: 'text-xs text-gray-500 w-12' }, '动量:'),
            h(NProgress, {
              type: 'line',
              percentage: Math.abs(row.norm_momentum) * 100,
              color: row.norm_momentum > 0 ? '#18a058' : '#d03050',
              showIndicator: false,
              height: 8
            }),
            h('span', { class: 'text-xs font-mono' }, row.norm_momentum.toFixed(3))
          ]),
          h('div', { class: 'flex items-center gap-2' }, [
            h('span', { class: 'text-xs text-gray-500 w-12' }, '交易量:'),
            h(NProgress, {
              type: 'line',
              percentage: Math.abs(row.norm_volume) * 100,
              color: row.norm_volume > 0 ? '#18a058' : '#d03050',
              showIndicator: false,
              height: 8
            }),
            h('span', { class: 'text-xs font-mono' }, row.norm_volume.toFixed(3))
          ])
        ]);
      }
    },
    {
      title: '权重配置',
      key: 'weights',
      width: 150,
      render: (row: PredictionResultDTO) => {
        return h(
          'div',
          { class: 'space-y-1' },
          Object.entries(row.used_weights).map(([feature, weight]) =>
            h('div', { class: 'flex justify-between text-xs' }, [
              h('span', { class: 'text-gray-600' }, feature),
              h('span', { class: 'font-mono' }, `${(weight * 100).toFixed(0)}%`)
            ])
          )
        );
      }
    },
    {
      title: '策略标签',
      key: 'strategy_tag',
      width: 120,
      align: 'center',
      render: (row: PredictionResultDTO) => {
        return h(NTag, { type: 'info', size: 'small' }, { default: () => row.strategy_tag });
      }
    },
    {
      title: '时间',
      key: 'created_at',
      width: 160,
      align: 'center',
      render: (row: PredictionResultDTO) => {
        return h('span', { class: 'text-xs text-gray-500' }, new Date(row.created_at).toLocaleString('zh-CN'));
      }
    }
  ];

  // 分页配置
  const pagination = {
    pageSize: 10,
    showSizePicker: true,
    pageSizes: [10, 20, 50],
    showQuickJumper: true
  };
</script>

<style scoped>
  .prediction-result-table {
    /* 组件样式 */
  }
</style>
