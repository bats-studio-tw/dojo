<template>
  <NCard
    class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg"
    title="📋 前三名预测对比表格"
    size="large"
  >
    <template #header-extra>
      <n-button :loading="loading" @click="$emit('refresh')" type="primary" size="small">🔄 刷新表格</n-button>
    </template>

    <NSpin :show="loading">
      <div v-if="hasData">
        <NDataTable
          :columns="columns"
          :data="tableData"
          :pagination="{ pageSize: 10 }"
          :scroll-x="800"
          striped
          :row-props="getRowProps"
          size="small"
        />
      </div>
      <NEmpty v-else description="暂无预测历史对比数据" class="py-8" />
    </NSpin>
  </NCard>
</template>

<script setup lang="ts">
  import { computed, h } from 'vue';
  import { NEmpty, NSpin, type DataTableColumn } from 'naive-ui';
  import type { DetailedPredictionItem } from '@/composables/usePredictionStats';
  import { usePredictionStats } from '@/composables/usePredictionStats';
  import { usePredictionDisplay } from '@/composables/usePredictionDisplay';

  // 扩展表格行数据类型
  interface PredictionComparisonRow extends DetailedPredictionItem {
    key: string;
  }

  // Props
  interface Props {
    predictionData: DetailedPredictionItem[];
    loading?: boolean;
  }

  const props = withDefaults(defineProps<Props>(), {
    loading: false
  });

  // Emits
  defineEmits<{
    refresh: [];
  }>();

  // 使用composables
  const { getTokenPredictionAnalysis } = usePredictionStats(
    // 这里传入空的ref，因为我们直接使用传入的数据
    // eslint-disable-next-line vue/no-ref-as-operand
    { value: [] } as any
  );
  const { getPredictionRankIcon, getPredictionRowClass } = usePredictionDisplay();

  // 计算属性
  const hasData = computed(() => props.predictionData && props.predictionData.length > 0);

  const tableData = computed((): PredictionComparisonRow[] => {
    return props.predictionData.map((item) => ({
      ...item,
      key: `${item.round_id}-${item.symbol}`
    }));
  });

  // 表格列定义
  const columns: DataTableColumn<PredictionComparisonRow>[] = [
    {
      title: '轮次',
      key: 'round_id',
      width: 100,
      render: (row: PredictionComparisonRow) => row.round_id
    },
    {
      title: '代币',
      key: 'symbol',
      width: 80,
      render: (row: PredictionComparisonRow) => row.symbol
    },
    {
      title: '预测排名',
      key: 'predicted_rank',
      width: 100,
      render: (row: PredictionComparisonRow) =>
        h('div', { class: 'flex items-center justify-center' }, [
          h('span', { class: 'text-lg mr-1' }, getPredictionRankIcon(row.predicted_rank)),
          h('span', { class: 'font-medium' }, `#${row.predicted_rank}`)
        ])
    },
    {
      title: '实际排名',
      key: 'actual_rank',
      width: 100,
      render: (row: PredictionComparisonRow) =>
        h('div', { class: 'flex items-center justify-center' }, [
          h('span', { class: 'text-lg mr-1' }, getPredictionRankIcon(row.actual_rank)),
          h('span', { class: 'font-medium' }, `#${row.actual_rank}`)
        ])
    },
    {
      title: '预测分析',
      key: 'analysis',
      width: 160,
      render: (row: PredictionComparisonRow) => {
        const analysis = getTokenPredictionAnalysis(row.predicted_rank, row.actual_rank);

        return h(
          'div',
          {
            class: `px-3 py-1 rounded-full text-sm font-medium ${analysis.color} ${analysis.bgColor}`
          },
          [h('span', { class: 'mr-1' }, analysis.icon), h('span', {}, analysis.text)]
        );
      }
    },
    {
      title: '结算时间',
      key: 'settled_at',
      width: 140,
      render: (row: PredictionComparisonRow) => row.settled_at
    }
  ];

  // 获取表格行属性
  const getRowProps = (row: PredictionComparisonRow) => {
    return {
      style: getPredictionRowClass(row.is_exact_match, row.is_better_than_expected)
    };
  };
</script>

<style scoped>
  /* 可以添加一些自定义样式 */
  .font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  }
</style>
