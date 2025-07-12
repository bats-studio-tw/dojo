<template>
  <div class="prediction-score-chart">
    <n-card title="预测分数分析" class="mb-4">
      <div v-if="results.length" class="space-y-6">
        <!-- 分数分布图 -->
        <div>
          <h4 class="text-lg font-medium mb-3">分数分布</h4>
          <div class="h-64">
            <canvas ref="scoreChartRef"></canvas>
          </div>
        </div>

        <!-- 特征分数对比 -->
        <div>
          <h4 class="text-lg font-medium mb-3">特征分数对比</h4>
          <div class="h-64">
            <canvas ref="featureChartRef"></canvas>
          </div>
        </div>

        <!-- 排名分布 -->
        <div>
          <h4 class="text-lg font-medium mb-3">排名分布</h4>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div v-for="(count, rank) in rankDistribution" :key="rank" class="text-center p-3 bg-gray-50 rounded-lg">
              <div class="text-2xl font-bold text-blue-600">{{ count }}</div>
              <div class="text-sm text-gray-600">第{{ rank }}名</div>
            </div>
          </div>
        </div>

        <!-- 分数统计 -->
        <div>
          <h4 class="text-lg font-medium mb-3">分数统计</h4>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <n-statistic label="最高分" :value="scoreStats.max.toFixed(4)" :value-style="{ color: '#18a058' }" />
            <n-statistic label="最低分" :value="scoreStats.min.toFixed(4)" :value-style="{ color: '#d03050' }" />
            <n-statistic label="平均分" :value="scoreStats.avg.toFixed(4)" :value-style="{ color: '#2080f0' }" />
            <n-statistic label="标准差" :value="scoreStats.std.toFixed(4)" :value-style="{ color: '#f0a020' }" />
            <n-statistic label="中位数" :value="scoreStats.median.toFixed(4)" :value-style="{ color: '#8a2be2' }" />
            <n-statistic label="变异系数" :value="scoreStats.cv.toFixed(2)" :value-style="{ color: '#ff6b6b' }" />
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 text-gray-500">
        <n-icon size="48" class="mb-4">
          <BarChartOutline />
        </n-icon>
        <p>暂无预测数据</p>
      </div>
    </n-card>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed, onMounted, watch, nextTick } from 'vue';
  import { BarChartOutline } from '@vicons/ionicons5';
  import Chart from 'chart.js/auto';
  import type { PredictionResultDTO } from '@/types/prediction';

  // Props
  interface Props {
    results: PredictionResultDTO[];
  }

  const props = defineProps<Props>();

  // 响应式数据
  const scoreChartRef = ref<HTMLCanvasElement>();
  const featureChartRef = ref<HTMLCanvasElement>();
  let scoreChart: Chart | null = null;
  let featureChart: Chart | null = null;

  // 计算属性
  const rankDistribution = computed(() => {
    const distribution: Record<number, number> = {};
    props.results.forEach((result) => {
      const rank = result.predict_rank;
      distribution[rank] = (distribution[rank] || 0) + 1;
    });
    return distribution;
  });

  const scoreStats = computed(() => {
    if (props.results.length === 0) {
      return { max: 0, min: 0, avg: 0, std: 0, median: 0, cv: 0 };
    }

    const scores = props.results.map((r) => r.predict_score).sort((a, b) => a - b);
    const max = Math.max(...scores);
    const min = Math.min(...scores);
    const avg = scores.reduce((a, b) => a + b, 0) / scores.length;

    // 标准差
    const variance = scores.reduce((acc, score) => acc + Math.pow(score - avg, 2), 0) / scores.length;
    const std = Math.sqrt(variance);

    // 中位数
    const median =
      scores.length % 2 === 0
        ? (scores[scores.length / 2 - 1] + scores[scores.length / 2]) / 2
        : scores[Math.floor(scores.length / 2)];

    // 变异系数
    const cv = avg !== 0 ? std / avg : 0;

    return { max, min, avg, std, median, cv };
  });

  // 方法
  const createScoreChart = () => {
    if (!scoreChartRef.value) return;

    const ctx = scoreChartRef.value.getContext('2d');
    if (!ctx) return;

    // 销毁旧图表
    if (scoreChart) {
      scoreChart.destroy();
    }

    const sortedResults = [...props.results].sort((a, b) => a.predict_rank - b.predict_rank);

    scoreChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: sortedResults.map((r) => r.token),
        datasets: [
          {
            label: '预测分数',
            data: sortedResults.map((r) => r.predict_score),
            backgroundColor: sortedResults.map((_, index) => {
              if (index === 0) return '#18a058';
              if (index === 1) return '#f0a020';
              if (index === 2) return '#2080f0';
              return '#d3d3d3';
            }),
            borderColor: '#ffffff',
            borderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: (context) => `分数: ${context.parsed.y.toFixed(4)}`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: '预测分数'
            }
          },
          x: {
            title: {
              display: true,
              text: '代币'
            }
          }
        }
      }
    });
  };

  const createFeatureChart = () => {
    if (!featureChartRef.value) return;

    const ctx = featureChartRef.value.getContext('2d');
    if (!ctx) return;

    // 销毁旧图表
    if (featureChart) {
      featureChart.destroy();
    }

    const topResults = [...props.results].sort((a, b) => a.predict_rank - b.predict_rank).slice(0, 10); // 只显示前10名

    featureChart = new Chart(ctx, {
      type: 'radar',
      data: {
        labels: topResults.map((r) => r.token),
        datasets: [
          {
            label: 'Elo分数',
            data: topResults.map((r) => r.norm_elo),
            borderColor: '#18a058',
            backgroundColor: 'rgba(24, 160, 88, 0.2)',
            pointBackgroundColor: '#18a058'
          },
          {
            label: '动量分数',
            data: topResults.map((r) => r.norm_momentum),
            borderColor: '#f0a020',
            backgroundColor: 'rgba(240, 160, 32, 0.2)',
            pointBackgroundColor: '#f0a020'
          },
          {
            label: '交易量分数',
            data: topResults.map((r) => r.norm_volume),
            borderColor: '#2080f0',
            backgroundColor: 'rgba(32, 128, 240, 0.2)',
            pointBackgroundColor: '#2080f0'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top'
          }
        },
        scales: {
          r: {
            beginAtZero: true,
            title: {
              display: true,
              text: '标准化分数'
            }
          }
        }
      }
    });
  };

  // 生命周期
  onMounted(() => {
    nextTick(() => {
      if (props.results.length > 0) {
        createScoreChart();
        createFeatureChart();
      }
    });
  });

  // 监听数据变化
  watch(
    () => props.results,
    () => {
      nextTick(() => {
        if (props.results.length > 0) {
          createScoreChart();
          createFeatureChart();
        }
      });
    },
    { deep: true }
  );
</script>

<style scoped>
  .prediction-score-chart {
    /* 组件样式 */
  }
</style>
