import type { RoundFeatureMatrixResponse } from '@/types/prediction';

export interface AggregateRules {
  threshold?: number;
  whitelist?: string[];
  blacklist?: string[];
}

export interface RankedScore {
  token: string;
  score: number;
  rank: number;
}

/**
 * 基于 normalized 矩阵与权重执行本地聚合 X·w
 */
export function aggregateScores(matrix: RoundFeatureMatrixResponse, weights: Record<string, number>): RankedScore[] {
  const scores: Record<string, number> = {};
  for (const token of matrix.tokens) {
    let s = 0;
    for (const [f, w] of Object.entries(weights)) {
      if (!w) continue;
      const v = matrix.matrix[token]?.[f]?.norm ?? null;
      if (v !== null) s += v * w;
    }
    scores[token] = s;
  }

  return Object.entries(scores)
    .map(([token, score]) => ({ token, score }))
    .sort((a, b) => b.score - a.score)
    .map((x, i) => ({ ...x, rank: i + 1 }));
}

/**
 * 计算特征贡献（按特征汇总）
 */
export function computeFeatureContributions(
  matrix: RoundFeatureMatrixResponse,
  weights: Record<string, number>
): Record<string, number> {
  const contrib: Record<string, number> = {};
  for (const [f, w] of Object.entries(weights)) {
    if (!w) continue;
    let sum = 0;
    for (const token of matrix.tokens) {
      const v = matrix.matrix[token]?.[f]?.norm ?? null;
      if (v !== null) sum += v * w;
    }
    contrib[f] = sum;
  }
  return contrib;
}

/**
 * 应用简单规则过滤
 */
export function applyRules(ranking: RankedScore[], rules?: AggregateRules): RankedScore[] {
  if (!rules) return ranking;
  const { threshold, whitelist, blacklist } = rules;

  let result = ranking;
  if (typeof threshold === 'number') {
    result = result.filter((x) => x.score >= threshold);
  }
  if (Array.isArray(whitelist) && whitelist.length > 0) {
    const set = new Set(whitelist.map((s) => s.toUpperCase()));
    result = result.filter((x) => set.has(x.token.toUpperCase()));
  }
  if (Array.isArray(blacklist) && blacklist.length > 0) {
    const set = new Set(blacklist.map((s) => s.toUpperCase()));
    result = result.filter((x) => !set.has(x.token.toUpperCase()));
  }
  return result;
}
