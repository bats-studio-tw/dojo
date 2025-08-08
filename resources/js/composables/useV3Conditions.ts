import { computed, reactive, toRefs, watch } from 'vue';
import type { RoundFeatureMatrixResponse } from '@/types/prediction';

export interface V3ConditionsState {
  topN: number;
  minScore?: number | null; // 聚合分阈值（若使用）
  featureMin: Record<string, number | null>; // 每个特征的最小阈值（针对 norm/raw 二选一，默认用 norm）
  featureMax: Record<string, number | null>; // 可选最大阈值
  whitelist: string[]; // 仅允许
  blacklist: string[]; // 禁止
}

export function useV3Conditions(matrix: () => RoundFeatureMatrixResponse | null) {
  const state = reactive<V3ConditionsState>({
    topN: 1,
    minScore: null,
    featureMin: {},
    featureMax: {},
    whitelist: [],
    blacklist: []
  });

  // 当矩阵特征变化时，自动为每个特征初始化阈值键位
  watch(
    () => matrix()?.features || [],
    (features) => {
      for (const f of features) {
        if (!(f in state.featureMin)) state.featureMin[f] = null;
        if (!(f in state.featureMax)) state.featureMax[f] = null;
      }
      // 清理已不存在的键
      for (const k of Object.keys(state.featureMin)) if (!features.includes(k)) delete state.featureMin[k];
      for (const k of Object.keys(state.featureMax)) if (!features.includes(k)) delete state.featureMax[k];
    },
    { immediate: true }
  );

  const allTokens = computed(() => matrix()?.tokens || []);

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
    isTokenEligible,
    filterTokens,
    reset,
    saveToLocalStorage,
    loadFromLocalStorage
  };
}
