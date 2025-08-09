<template>
  <n-card class="border border-white/20 bg-white/10 shadow-2xl backdrop-blur-lg" title="📈 条件回测 (历史)">
    <template #header-extra>
      <div class="flex items-center gap-2">
        <n-button size="small" :disabled="maxRounds === 0" @click="runBacktest">计算回测</n-button>
        <n-button size="small" tertiary :disabled="!calculated" @click="clearResult">清除结果</n-button>
      </div>
    </template>
    <div class="space-y-4">
      <!-- 顶部控制：回测局数 + TopN -->
      <div v-if="maxRounds > 0" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3 flex-1 min-w-0">
          <div class="flex items-center gap-3 w-full text-xs">
            <span class="whitespace-nowrap text-white/80">回测局数</span>
            <div class="flex-1 min-w-0">
              <n-slider
                v-model:value="recentRounds"
                :min="1"
                :max="Math.min(1000, maxRounds)"
                :step="1"
                class="w-full"
              />
            </div>
            <span class="whitespace-nowrap text-cyan-400 font-semibold">{{ recentRounds }}</span>
            <span class="whitespace-nowrap text-white/50">/ {{ Math.min(1000, maxRounds) }}</span>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2 whitespace-nowrap text-xs">
            <span class="text-white/80">Top N</span>
            <n-input-number v-model:value="topN" :min="1" :max="10" :step="1" size="small" class="w-[100px]" />
          </div>
        </div>
      </div>
      <div v-else class="text-xs text-white/60">暂无历史数据，待加载后显示回测控件</div>

      <!-- 名次条件设置（简化版，与V3一致口径） -->
      <n-card size="small" class="border border-white/10 bg-white/5">
        <div class="mb-3 flex items-center justify-between">
          <div class="text-xs text-white/70">按名次筛选（1 为最佳）</div>
          <div class="text-xs text-white/50">每个特征可设置一个名次条件；可选“第一名数量下限”</div>
        </div>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <div v-for="f in featureList" :key="`cond-${f}`" class="flex items-center gap-2">
            <span class="w-40 truncate text-xs text-white/70" :title="f">{{ f }}</span>
            <div class="w-full flex flex-nowrap items-center gap-2">
              <span class="whitespace-nowrap text-xs text-white/50">名次</span>
              <n-select
                v-model:value="ensureRule(f).operator"
                :options="rankOperatorOptions"
                size="small"
                :style="{ width: '120px' }"
              />
              <n-input-number
                v-model:value="ensureRule(f).value"
                :min="1"
                :precision="0"
                size="small"
                :style="{ width: '120px' }"
              />
              <n-button size="tiny" tertiary class="shrink-0" @click="clearRule(f)">清除</n-button>
            </div>
          </div>
        </div>

        <div class="mt-3 flex items-center gap-2">
          <span class="text-xs text-white/70">满足第一名(=1)的特征数量 ≥</span>
          <n-input-number v-model:value="firstPlaceMinCount" :min="1" :precision="0" size="small" class="w-[100px]" />
          <span class="text-xs text-white/50">（留空表示不限制）</span>
        </div>
      </n-card>

      <!-- 结果摘要 -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-4">
          <div class="text-xs text-emerald-300/80">总下注次数</div>
          <div class="mt-1 text-2xl font-bold text-emerald-300">{{ displayResults.totalBets }}</div>
          <div class="mt-1 text-xs text-emerald-200/70">共 {{ displayResults.roundsTriggered }} 局触发下注</div>
        </div>
        <div class="rounded-lg border border-cyan-500/20 bg-cyan-500/5 p-4">
          <div class="text-xs text-cyan-300/80">保本率 (实际Top3)</div>
          <div class="mt-1 text-2xl font-bold text-cyan-300">{{ (displayResults.breakevenRate || 0).toFixed(1) }}%</div>
          <div class="mt-1 text-xs text-cyan-200/70">
            保本 {{ displayResults.breakeven }} / {{ displayResults.totalBets }}
          </div>
        </div>
        <div class="rounded-lg border border-amber-500/20 bg-amber-500/5 p-4">
          <div class="text-xs text-amber-300/80">胜率 (第一名)</div>
          <div class="mt-1 text-2xl font-bold text-amber-300">{{ (displayResults.firstRate || 0).toFixed(1) }}%</div>
          <div class="mt-1 text-xs text-amber-200/70">
            第一 {{ displayResults.first }} / {{ displayResults.totalBets }}
          </div>
        </div>
        <div class="rounded-lg border border-rose-500/20 bg-rose-500/5 p-4">
          <div class="text-xs text-rose-300/80">亏损率</div>
          <div class="mt-1 text-2xl font-bold text-rose-300">{{ (displayResults.lossRate || 0).toFixed(1) }}%</div>
          <div class="mt-1 text-xs text-rose-200/70">
            亏损 {{ displayResults.loss }} / {{ displayResults.totalBets }}
          </div>
        </div>
      </div>

      <!-- 预览：最近一局命中的Token（可选） -->
      <div v-if="displayPreview.lastSelected.length" class="rounded-lg border border-white/10 bg-white/5 p-3">
        <div class="text-xs text-white/60">最近一局选出 Token：</div>
        <div class="mt-2 flex flex-wrap gap-2">
          <n-tag v-for="s in displayPreview.lastSelected" :key="`last-${s}`" size="small" type="info" round>
            {{ s }}
          </n-tag>
        </div>
      </div>

      <div v-if="!calculated && maxRounds > 0" class="text-xs text-white/60">
        提示：设置名次条件或第一名数量，然后点击“计算回测”。
      </div>
    </div>
  </n-card>
</template>

<script setup lang="ts">
  import { computed, reactive, ref, watch } from 'vue';
  import type { FeatureHistoryRound } from '@/composables/useFeaturePredictionStats';
  import { NCard, NSelect, NInputNumber, NTag, NSlider, NButton } from 'naive-ui';

  interface RankRule {
    operator: 'lt' | 'lte' | 'eq' | 'gte' | 'gt';
    value: number | null;
  }

  const props = defineProps<{ historyList: FeatureHistoryRound[] }>();

  // 整理可用特征列表
  const featureList = computed<string[]>(() => {
    const set = new Set<string>();
    for (const r of props.historyList || []) if (r?.feature) set.add(r.feature);
    return Array.from(set).sort();
  });

  // 轮次降序列表
  const sortedRounds = computed<FeatureHistoryRound[]>(() => {
    const arr = (props.historyList || []).slice();
    arr.sort((a, b) => Number(b.round_id) - Number(a.round_id));
    return arr;
  });

  // 最大可回测局数
  const maxRounds = computed<number>(() => sortedRounds.value.length || 0);
  const recentRounds = ref<number>(1);
  watch(
    maxRounds,
    (m) => {
      // 历史数据就绪时，将回测局数设置为可用最大值（上限1000）
      if (m > 0) {
        recentRounds.value = Math.min(1000, Math.max(1, m));
      }
    },
    { immediate: true }
  );

  // 条件状态（与V3口径一致）
  const topN = ref<number>(1);
  const firstPlaceMinCount = ref<number | null>(null);
  const featureRankRules = reactive<Record<string, RankRule | null>>({});

  // 初始化/保证规则对象
  const ensureRule = (feature: string): RankRule => {
    if (!featureRankRules[feature]) featureRankRules[feature] = { operator: 'lte', value: null } as RankRule;
    return featureRankRules[feature] as RankRule;
  };
  const clearRule = (feature: string) => {
    featureRankRules[feature] = null;
  };

  // 操作符选项
  const rankOperatorOptions = [
    { label: '<', value: 'lt' as const },
    { label: '≤', value: 'lte' as const },
    { label: '=', value: 'eq' as const },
    { label: '≥', value: 'gte' as const },
    { label: '>', value: 'gt' as const }
  ];

  // 构建 per-round 映射：round -> feature -> token -> predicted_rank；以及结果映射
  type RoundMaps = Record<
    string,
    {
      featureRanks: Record<string, Record<string, number>>;
      results: Record<string, number>;
      tokens: Set<string>;
    }
  >;

  const roundMaps = computed<RoundMaps>(() => {
    const map: RoundMaps = {};
    for (const r of props.historyList || []) {
      const key = String(r.round_id);
      if (!map[key]) {
        map[key] = { featureRanks: {}, results: {}, tokens: new Set<string>() };
      }
      const fm = map[key];
      // 结果
      for (const res of r.results || []) {
        // 强制数字比较口径
        fm.results[res.symbol] = Number((res as any).actual_rank);
        fm.tokens.add(res.symbol);
      }
      // 预测名次（该特征）
      const rankMap: Record<string, number> = fm.featureRanks[r.feature] || (fm.featureRanks[r.feature] = {});
      for (const p of r.predictions || []) {
        // 强制数字比较口径
        rankMap[p.symbol] = Number((p as any).predicted_rank);
        fm.tokens.add(p.symbol);
      }
    }
    return map;
  });

  function opCompare(rank: number | string, rule: RankRule): boolean {
    const v = Number(rule.value as number); // 已确保不为null时调用
    const r = Number(rank);
    switch (rule.operator) {
      case 'lt':
        return r < v;
      case 'lte':
        return r <= v;
      case 'eq':
        return r === v;
      case 'gte':
        return r >= v;
      case 'gt':
        return r > v;
      default:
        return true;
    }
  }

  // 计算某轮符合条件的Token列表
  function filterTokensForRound(roundKey: string): string[] {
    const rm = roundMaps.value[roundKey];
    if (!rm) return [];

    const tokens = Array.from(rm.tokens);
    const eligible: Array<{ token: string; firstCount: number; avgRank: number }> = [];

    outer: for (const token of tokens) {
      // 名次条件
      for (const [feature, rule] of Object.entries(featureRankRules)) {
        if (!rule || rule.value == null) continue;
        const rank = rm.featureRanks?.[feature]?.[token];
        if (rank == null) continue outer; // 缺少该特征名次则不满足
        if (!opCompare(rank, rule)) continue outer;
      }

      // 第一名数量下限
      if (firstPlaceMinCount.value && firstPlaceMinCount.value > 0) {
        let count = 0;
        for (const feature of Object.keys(rm.featureRanks)) {
          const rank = rm.featureRanks[feature]?.[token];
          if (Number(rank) === 1) count++;
        }
        if (count < firstPlaceMinCount.value) continue;
      }

      // 排序依据：第一名数量优先，其次平均名次
      let firstCount = 0;
      let sum = 0;
      let n = 0;
      for (const feature of Object.keys(rm.featureRanks)) {
        const rank = rm.featureRanks[feature]?.[token];
        if (rank != null) {
          sum += rank;
          n++;
          if (rank === 1) firstCount++;
        }
      }
      const avgRank = n > 0 ? sum / n : 9999;
      eligible.push({ token, firstCount, avgRank });
    }

    eligible.sort((a, b) => {
      if (b.firstCount !== a.firstCount) return b.firstCount - a.firstCount;
      if (a.avgRank !== b.avgRank) return a.avgRank - b.avgRank;
      return a.token.localeCompare(b.token);
    });

    return eligible.slice(0, Math.max(1, topN.value)).map((x) => x.token);
  }

  // 回测计算（实时计算，但仅在点击按钮后采纳为展示结果）
  // 以 sortedRounds 的顺序作为权威顺序，避免 round_id 非纯数字时的排序/键问题
  const roundKeysDesc = computed<string[]>(() => {
    const out: string[] = [];
    const seen = new Set<string>();
    for (const r of sortedRounds.value) {
      const key = String(r.round_id);
      if (!seen.has(key)) {
        seen.add(key);
        out.push(key);
      }
    }
    return out;
  });
  const selectedByRound = computed<Record<string, string[]>>(() => {
    const out: Record<string, string[]> = {};
    const keys = roundKeysDesc.value.slice(0, recentRounds.value);
    for (const key of keys) out[key] = filterTokensForRound(key);
    return out;
  });

  const computedResults = computed(() => {
    let totalBets = 0;
    let breakeven = 0;
    let loss = 0;
    let first = 0;
    let roundsTriggered = 0;

    const keys = Object.keys(selectedByRound.value);
    for (const key of keys) {
      const rm = roundMaps.value[key];
      const picks = selectedByRound.value[key] || [];
      if (picks.length) roundsTriggered++;
      for (const token of picks) {
        totalBets++;
        const actual = rm?.results?.[token];
        if (actual != null) {
          const a = Number(actual);
          if (a <= 3) breakeven++;
          if (a === 1) first++;
          if (a > 3) loss++;
        } else {
          // 缺失结果视为无效，不计
        }
      }
    }

    const breakevenRate = totalBets > 0 ? (breakeven / totalBets) * 100 : 0;
    const firstRate = totalBets > 0 ? (first / totalBets) * 100 : 0;
    const lossRate = totalBets > 0 ? (loss / totalBets) * 100 : 0;

    return { totalBets, breakeven, loss, first, breakevenRate, firstRate, lossRate, roundsTriggered };
  });

  const computedPreview = computed(() => {
    const latestKey = roundKeysDesc.value.length ? roundKeysDesc.value[0] : '';
    return { lastSelected: latestKey ? selectedByRound.value[latestKey] || [] : [] };
  });

  // 手动触发：仅在点击后更新展示结果
  type ResultSummary = {
    totalBets: number;
    breakeven: number;
    loss: number;
    first: number;
    breakevenRate: number;
    firstRate: number;
    lossRate: number;
    roundsTriggered: number;
  };

  const zeroSummary: ResultSummary = {
    totalBets: 0,
    breakeven: 0,
    loss: 0,
    first: 0,
    breakevenRate: 0,
    firstRate: 0,
    lossRate: 0,
    roundsTriggered: 0
  };

  const calculated = ref(false);
  const lastResults = ref<ResultSummary>(zeroSummary);
  const lastPreview = ref<{ lastSelected: string[] }>({ lastSelected: [] });

  function runBacktest(): void {
    lastResults.value = { ...computedResults.value };
    lastPreview.value = { ...computedPreview.value };
    calculated.value = true;
  }

  function clearResult(): void {
    lastResults.value = { ...zeroSummary };
    lastPreview.value = { lastSelected: [] };
    calculated.value = false;
  }

  const displayResults = computed<ResultSummary>(() => (calculated.value ? lastResults.value : zeroSummary));
  const displayPreview = computed(() => (calculated.value ? lastPreview.value : { lastSelected: [] }));
</script>

<style scoped></style>
