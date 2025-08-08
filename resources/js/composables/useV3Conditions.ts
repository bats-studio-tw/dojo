import { computed, reactive, toRefs, watch } from 'vue';
import type { RoundFeatureMatrixResponse } from '@/types/prediction';

export interface V3ConditionsState {
  topN: number;
  minScore?: number | null; // 聚合分阈值（若使用）
  featureMin: Record<string, number | null>; // 每个特征的最小阈值（针对 norm/raw 二选一，默认用 norm）
  featureMax: Record<string, number | null>; // 可选最大阈值
  whitelist: string[]; // 仅允许
  blacklist: string[]; // 禁止
  // 新增：按特征的“名次条件”，例如 rank > 3、rank = 1 等
  featureRank: Record<string, { operator: 'lt' | 'lte' | 'eq' | 'gte' | 'gt'; value: number | null } | null>;
  // 新增：满足“第一名(=1名次)”的特征数量至少为多少
  firstPlaceMinCount: number | null;
}

export function useV3Conditions(matrix: () => RoundFeatureMatrixResponse | null) {
  const state = reactive<V3ConditionsState>({
    topN: 1,
    minScore: null,
    featureMin: {},
    featureMax: {},
    whitelist: [],
    blacklist: [],
    featureRank: {},
    firstPlaceMinCount: null
  });

  // 当矩阵特征变化时，自动为每个特征初始化阈值键位
  watch(
    () => matrix()?.features || [],
    (features) => {
      for (const f of features) {
        if (!(f in state.featureMin)) state.featureMin[f] = null;
        if (!(f in state.featureMax)) state.featureMax[f] = null;
        if (!(f in state.featureRank)) state.featureRank[f] = null;
      }
      // 清理已不存在的键
      for (const k of Object.keys(state.featureMin)) if (!features.includes(k)) delete state.featureMin[k];
      for (const k of Object.keys(state.featureMax)) if (!features.includes(k)) delete state.featureMax[k];
      for (const k of Object.keys(state.featureRank)) if (!features.includes(k)) delete state.featureRank[k];
    },
    { immediate: true }
  );

  const allTokens = computed(() => matrix()?.tokens || []);

  // 计算每个特征的名次映射：feature -> (token -> rank)
  const featureRankMaps = computed<Record<string, Record<string, number>>>(() => {
    const m = matrix();
    const result: Record<string, Record<string, number>> = {};
    if (!m) return result;

    for (const feature of m.features || []) {
      const pairs: Array<{ token: string; value: number }> = [];
      for (const token of m.tokens) {
        const cell = m.matrix?.[token]?.[feature];
        const value = (cell?.norm ?? cell?.raw ?? null) as number | null;
        if (value !== null) {
          pairs.push({ token, value });
        }
      }
      // 值越大名次越靠前（1 为最佳）。如有需要可在此按特征自定义升降序。
      pairs.sort((a, b) => b.value - a.value);
      const rankMap: Record<string, number> = {};
      pairs.forEach((p, idx) => (rankMap[p.token] = idx + 1));
      result[feature] = rankMap;
    }
    return result;
  });

  function isTokenEligible(symbol: string): boolean {
    const m = matrix();
    if (!m) return false;

    // 黑白名单
    if (state.whitelist.length > 0 && !state.whitelist.includes(symbol)) return false;
    if (state.blacklist.length > 0 && state.blacklist.includes(symbol)) return false;

    // 特征阈值
    for (const [feature, minVal] of Object.entries(state.featureMin)) {
      if (minVal == null) continue;
      const cell = m.matrix?.[symbol]?.[feature];
      const value = (cell?.norm ?? cell?.raw ?? null) as number | null;
      if (value === null) return false;
      if (value < (minVal as number)) return false;
    }
    for (const [feature, maxVal] of Object.entries(state.featureMax)) {
      if (maxVal == null) continue;
      const cell = m.matrix?.[symbol]?.[feature];
      const value = (cell?.norm ?? cell?.raw ?? null) as number | null;
      if (value === null) return false;
      if (value > (maxVal as number)) return false;
    }

    // 名次条件（例如：rank > 3、rank = 1）
    for (const [feature, rule] of Object.entries(state.featureRank)) {
      if (!rule || rule.value == null) continue;
      const rank = featureRankMaps.value?.[feature]?.[symbol] ?? null;
      if (rank == null) return false;
      const v = rule.value as number;
      let ok = true;
      switch (rule.operator) {
        case 'lt':
          ok = rank < v;
          break;
        case 'lte':
          ok = rank <= v;
          break;
        case 'eq':
          ok = rank === v;
          break;
        case 'gte':
          ok = rank >= v;
          break;
        case 'gt':
          ok = rank > v;
          break;
        default:
          ok = true;
      }
      if (!ok) return false;
    }

    // 满足名次第一(=1)的特征数量下限
    if (state.firstPlaceMinCount && state.firstPlaceMinCount > 0) {
      let firstCount = 0;
      for (const feature of Object.keys(featureRankMaps.value)) {
        const rank = featureRankMaps.value[feature]?.[symbol];
        if (rank === 1) firstCount++;
      }
      if (firstCount < state.firstPlaceMinCount) return false;
    }

    return true;
  }

  function filterTokens(tokens?: string[]): string[] {
    const list = tokens && tokens.length ? tokens : allTokens.value;
    return list.filter((t) => isTokenEligible(t));
  }

  function reset(): void {
    state.topN = 1;
    state.minScore = null;
    state.featureMin = {};
    state.featureMax = {};
    state.whitelist = [];
    state.blacklist = [];
    state.featureRank = {};
    state.firstPlaceMinCount = null;
  }

  // 持久化（本地存储）
  const STORAGE_KEY = 'v3Conditions';
  function saveToLocalStorage(): void {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {}
  }
  function loadFromLocalStorage(): void {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      const obj = JSON.parse(raw);
      Object.assign(state, obj);
    } catch {}
  }

  return {
    ...toRefs(state),
    featureRankMaps,
    isTokenEligible,
    filterTokens,
    reset,
    saveToLocalStorage,
    loadFromLocalStorage
  };
}
