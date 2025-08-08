### 全新“面向特征”的预测系统重构计划（v3）

- 目标: 以“最小单位特征”为核心，重塑预测流水线，实现可回溯、可组合、可回测、可灰度的体系，优先提升 Top3 命中率（保本率 = 押中前3的概率）。
- 原则: 特征先行、标准化统一、组合可配、评估可对比、上线可灰度、旧方案可随时回退。
- 责任边界: 后端负责“产出/缓存每局×每币×每特征”的快照，不再输出官方综合排名；前端基于特征与用户自定义权重自行聚合、排序与下注。
- 重要说明: 先前的综合/混分预测（如 `ScoreMixer` 输出、`HybridRoundPredict`）将转为历史对照，不再作为官方推荐来源。

### 业务背景与指标定义

- 游戏规则摘要: 每局从池子中随机抽取5个代币，玩家在30秒下注窗口内选择目标，结算以“前3名分走后2名的池子”。
- 核心业务指标:
  - 保本率（Top3 率）: 下注代币进入该局前3名的比例。目标直观、与亏损控制直接相关。
  - 第一名率（可选观测）: 作为补充指标，但不作为优化核心。
  - 聚合延迟: 从“新局进入 bet”到“特征可用”的时间预算 < 300ms（不含网络）。
  - 稳定性: 特征快照完整率 ≥ 99.9%；API 99p 响应 < 150ms。

### 一、总体架构（自下而上）

- 数据源（沿用）：`token_prices` 分钟级行情；`game_rounds`、`round_results`、`token_ratings`。
- 特征层（新增）：按“每局×每币×每特征”生成并入库（含标准化），缓存当局矩阵。
- 聚合层（默认在前端）：前端按用户/策略权重向量（feature→weight）本地计算 `y = X·w` 并排序；后端仅提供可选聚合API（用于一致性/校验/慢设备）。
- 回测层（已移除）：不再提供内置回测系统；平台不输出官方策略，前端由用户自定义权重并自行承担选择结果。
- 前端（改造）：展示特征矩阵、权重滑条、即时聚合、保存配置、实时解释（贡献拆分）。

### 二、数据模型（新表）

- `features`（特征注册表）
  - 字段: `key(pk varchar)`, `name`, `description`, `type(enum: numeric|probability|rank)`, `default_normalization(enum: zscore|minmax|identity|robust)`, `default_weight(float)`, `enabled(bool)`, `meta(json)`, `created_at`, `updated_at`
- `feature_snapshots`（核心事实表）
  - 粒度: 每局/每币/每特征
  - 字段: `game_round_id(bigint)`, `token_symbol(varchar)`, `feature_key(varchar)`, `raw_value(double)`, `normalized_value(double)`, `meta(json)`, `computed_at(datetime)`, `created_at`
  - 约束: 唯一 `(game_round_id, token_symbol, feature_key)`
  - 索引: `(game_round_id)`, `(feature_key, game_round_id)`, `(token_symbol, game_round_id)`
- `user_strategy_profiles`（用户权重配置）
  - 字段: `id`, `user_id`, `name`, `weights_json(json {feature_key: weight})`, `normalization_overrides(json {feature_key: scaler})`, `is_default(bool)`, `created_at`, `updated_at`
  - 索引: `(user_id, is_default)`
- `prediction_aggregates`（可选，聚合结果历史/审计）
  - 字段: `id`, `game_round_id`, `token_symbol`, `profile_id|null`, `final_score(double)`, `rank(int)`, `details(json: {contrib_by_feature, rules})`, `computed_at`, `created_at`
  - 唯一: `(game_round_id, profile_id, token_symbol)`（或 `(game_round_id, hash(weights))`）
- `h2h_stats_cache`（可选，近窗对战缓存）
  - 字段: `window_key`, `token_a`, `token_b`, `wins`, `losses`, `games`, `posterior_win_rate(double)`, `computed_at`
  - 索引: `(window_key, token_a, token_b)`

保留与清理: 旧表 `prediction_results`、`hybrid_round_predicts` 等初期保留（只读、用于对比），稳定后迁移指标到新分析接口并下线。

### 三、特征体系（首批上线）

- 概率/排名类
  - `elo_prob_decayed`: 对当前5币，时间衰减 Elo 计算 pairwise 胜率并汇总为均值（或 Bradley–Terry 矩阵解）
  - `p_top3_from_elo`: 用 Elo pairwise 蒙特卡洛排序仿真（5k~20k 次）得到 `P(Top3)`
  - `h2h_bayes`: 最近 N 局的币对胜率，贝叶斯平滑（alpha/beta 可配）
- 动能/趋势类
  - `momentum_24h`: 24h 涨跌幅映射
  - `st_return`, `st_trend`, `st_volatility`: 短窗收益/趋势（线性回归斜率）/波动（标准差）
- 稳定/风控类
  - `volatility_short`, `volatility_long`: 短/长窗波动分
  - `drawdown_short`: 短窗回撤分
- 流动性/量化类（若数据可得）
  - `volume_recent`: 近窗成交量映射
- 时间衰减统计类（历史）
  - `decayed_top3_rate`, `decayed_win_rate`, `decayed_avg_rank`

标准化与缺失值策略:

- 标准化器: `z-score`、`min-max`、`identity`、（可选）`robust-scaler`。
- 方向一致性: 若“越小越好”的特征，归一时反转使“越大越好”。
- 缺失值: 缺失时采取“当局均值”或“全局中位数”回填；`meta.missing=true` 标注；前端可选择“剔除缺失特征”或“以0权重处理”。
- 归一范围: 建议将 `normalized_value` 控制在 [-3, +3]（zscore）或 [0, 1]（minmax），聚合器负责兼容。

### 四、服务与作业

- 下注期（bet）特征提取
  - `ExtractFeaturesJob(roundId, tokens)`：并发执行启用的 FeatureProvider（上限并发 = 特征数，5币×十余特征可在 <300ms 完成）
  - Provider 接口: `extract(roundId, symbols): array { feature_key: { symbol: raw_value, meta } }`
  - 幂等写入 `feature_snapshots`；并缓存当局特征矩阵 30~60s（cache key: `feature_matrix:{roundId}`）
- 聚合计算（默认前端）
  - 前端聚合: 基于 `normalized_value` 的矩阵与权重 `w` 计算 `y = X·w`；提供特征贡献 `contrib = X[:,k]*w_k` 解释。
  - 后端可选: `AggregateService` 仅用于一致性校验/低算力设备；结果可落表 `prediction_aggregates` 备查。
- 结算流
  - 保持：`round_results`、`token_ratings`（Elo 更新）不变；由现有结算处理链路触发 EloRatingEngine。
    （回测已移除：不再提供 Backtest 相关 Job/接口）

Provider 代码约束（PHP, PSR-12）:

- 放置于 `app/Services/Prediction/Features/`（或 `App\Contracts\Prediction\FeatureProviderInterface` 已有目录结构）。
- 每个 Provider 仅负责单一 `feature_key`，具备:
  - `supports(): string` 返回 `feature_key`
  - `extract(int $roundId, array $symbols): array`
  - 可选 `warmup()` 以复用缓存（如 Elo/H2H 窗口统计）。

### 五、API v3（面向特征与组合）

- `GET /api/v3/features/round/{round_id}`
  - 返回：tokens×features 的 `raw/normalized/meta`（默认当局 round 若未指定）。
- `POST /api/v3/predict/aggregate`（可选，默认前端做聚合）
  - 入参：`round_id(默认当前)`，`weights({feature_key: weight})`，`rules(阈值/白名单等, 可选)`
  - 出参：`ranking[]`，`contrib_by_feature`，`explainability`
  - 可选：落表 `prediction_aggregates` 以便审计/复盘
- `GET /api/v3/strategy-profiles` / `POST/PUT/DELETE`
  - 管理用户的特征权重配置（Sanctum 保护）
    （分析/回测接口已取消，平台不再提供官方策略回测服务）

数据契约（TypeScript）示例（前端 axios 仅作说明）：

```ts
// types: resources/js/types/prediction.ts
export interface FeatureSnapshot {
  token: string;
  feature: string; // feature_key
  raw: number | null;
  norm: number | null;
  meta?: Record<string, any>;
}

export interface RoundFeatureMatrixResponse {
  round_id: number | string;
  tokens: string[];
  features: string[];
  matrix: Record<string, Record<string, { raw: number | null; norm: number | null }>>; // token -> feature -> values
  computed_at: string;
}

export interface AggregateRequest {
  round_id?: number | string;
  weights: Record<string, number>;
  rules?: { threshold?: number; whitelist?: string[]; blacklist?: string[] };
}

export interface AggregateResponse {
  round_id: string;
  ranking: Array<{ token: string; score: number; rank: number }>;
  contrib_by_feature: Record<string, number>;
}
```

前端聚合（默认）:

```ts
// utils: resources/js/utils/featureAggregate.ts
export function aggregateScores(matrix: RoundFeatureMatrixResponse, weights: Record<string, number>) {
  const scores: Record<string, number> = {};
  for (const token of matrix.tokens) {
    let s = 0;
    for (const [f, w] of Object.entries(weights)) {
      const v = matrix.matrix[token]?.[f]?.norm ?? null;
      if (v !== null && w !== 0) s += v * w;
    }
    scores[token] = s;
  }
  return Object.entries(scores)
    .map(([token, score]) => ({ token, score }))
    .sort((a, b) => b.score - a.score)
    .map((x, i) => ({ ...x, rank: i + 1 }));
}
```

### 六、前端改造（Vue 3 + TS）

- 新组件
  - `FeatureMatrixTable.vue`: 展示 tokens×features 矩阵（raw/normalized）
  - `FeatureMixerPanel.vue`: 权重滑条，实时聚合结果 & 特征贡献条形图
  - `StrategyProfileManager.vue`: 保存/切换/导出配置
- 交互
  - 默认本地聚合: 拉取 `/features/round` → 本地 `X·w` 排序 → 显示排名与贡献；
  - 提供“用服务器聚合校验”按钮（可选，调用 `/predict/aggregate` 比对一致性）；
  - 支持本地暂存权重、预设模板（趋稳/平衡/短期/进攻）。

Store 与工具:

- `featureStore`: 当前局 `RoundFeatureMatrixResponse` 的缓存、更新时间戳、失效处理（30~60s）。
- `featureAggregate.ts`: 前端聚合、贡献计算、阈值/白名单规则。
- 与现有 `useConditionBuilder` 兼容：允许在“权重聚合排序后”再应用条件过滤（如最低保本率等）。

### 七、上线节奏与回退

- 一步到位切换（按你的意愿）
  - 关停旧预测入库（`CalculateMomentumJob` 混分写库停用；仅保留 Elo 更新与结算链路）
  - 启动 v3 特征抽取与快照；前端默认本地聚合
  - 首页切换到 v3 特征矩阵 + 权重面板
- 回退策略
  - 预留开关 `config('prediction_v3.enabled')`，可一键切回 v2 展示
  - 所有 v2 表保留一段时间（只读，便于对比）

### 八、清理与废弃（成功稳定后）

- 废弃 Job：`CalculateMomentumJob`（或改造成单一特征的 Provider）
- 废弃混分器随机因子（`ScoreMixer`），保持确定性
- 废弃 `prediction_results`、`hybrid_round_predicts`、`strategy_predictions`（迁移指标到新分析接口）

### 九、性能与可靠性

- 并行提取：每局仅 5 币，特征 Provider 可并发，计算预算 < 300ms（Elo 仿真可降采样或复用缓存）
- 索引优化：见表设计
- 缓存：当局特征矩阵 30~60s；H2H/Elo 概率 1–5 分钟滑窗缓存
- 日志与监控：独立 channel `prediction_v3`；指标（特征耗时、聚合耗时、命中率估计）
- 失败处理：Provider 任一失败不阻断整体，按缺失值策略回填并标注；整体失败时返回最小可用集。
- SLA：API 99p < 150ms；缓存命中率 ≥ 95%。

### 十、测试与验收

- 单元：FeatureProvider 输入/输出（边界/异常）
- 集成：下注期完整链路（bet→特征→聚合）
- 线上对照观测：灰度期间对比 v2 展示结果与用户自定义权重的 Top3 表现，仅作可视化对照，不提供离线回测
- 验收：线上灰度 24–72 小时观察（若需），无回归则全量

### 十一、首周交付清单

- 建表与迁移：`features`、`feature_snapshots`、`user_strategy_profiles`、（可选）`prediction_aggregates`、`h2h_stats_cache`
- Provider（首批）：`elo_prob_decayed`、`p_top3_from_elo`、`h2h_bayes`、`momentum_24h`、`st_return`、`st_trend`、`st_volatility`
- 作业：`ExtractFeaturesJob`；服务：`AggregateService`
- API v3：`/features/round`、`/predict/aggregate`、`/strategy-profiles`
- 前端：`FeatureMatrixTable`、`FeatureMixerPanel`、`StrategyProfileManager`
- 文档：特征规范、标准化规则、权重范例与建议组合

里程碑拆分（建议）：

- D1–D2: 迁移DDL + Provider接口骨架 + Elo/H2H缓存层
- D3: `ExtractFeaturesJob` 并行与缓存、API `/features/round`
- D4: 前端 `featureStore` + `FeatureMatrixTable` + 本地聚合工具
- D5: `FeatureMixerPanel` + `StrategyProfileManager` + profiles API
- D6: 清理 Backtest 系统（删除回测相关命令/作业/模型/配置/迁移，数据归档后下线表）
- D7: 灰度开关、监控、清理计划与应急回退

### 十二、风险与对策

- 计算时延：Provider 并发 + 缓存；Elo 仿真支持降采样
- 数据一致性：所有入库幂等，唯一键约束严格，使用锁盘前数据
- 过拟合：滑窗评估 + 样本下限 + 权重上下限 + 更新冷却周期
- 用户体验：默认提供 3–5 套预设权重（保守/平衡/短期/进攻）快速上手
- 权重误用导致收益不稳：
  - 提供“保本率（Top3）即时估计”与“特征贡献解释”，并提示权重上/下限；
  - 提供官方“保守/平衡/进攻”模板与最近窗口在线对照曲线（不提供离线回测）。
- 旧数据依赖：保留 v2 结果作为对照，确保任意时间可切回。

### 十三、P(Top3) 计算说明（供实现参考）

- 基于 Elo 的 pairwise 胜率矩阵 P(i>j) 构建 Plackett–Luce/Bradley–Terry 模型，计算前3概率：
  - 近似法1（推荐）：蒙特卡洛抽样 5k–20k 次生成排序，估计 P(Top3)。
  - 近似法2：小集合精确枚举（5! = 120）按 PL 概率累加得到各币进入前三的概率。
- 结果作为 `p_top3_from_elo` 特征入库（type=probability, 归一可用 identity）。

### 备注

你确认后，下一步我会提交：

- 新表迁移草案（DDL）
- Provider 接口草案与首批实现清单
- API v3 的请求/响应示例
- 前端页面的路由与组件骨架
- Backtest 系统下线与清理清单

### 附录：Backtest 系统移除计划

- 标记并移除以下代码（先归档到分支后物理删除）：
  - 模型：`app/Models/BacktestResult.php`
  - 作业：`app/Jobs/EvaluateBacktestParameters.php`
  - 命令：`app/Console/Commands/BacktestPredictionParameters.php`、`DebugBacktestResults.php`、`CleanupBacktestResults.php`、`ExportPredictionAnalysis.php`、`PromoteBestStrategy.php`
  - 配置：`config/backtest.php`
  - 迁移：`2025_07_18_132311_create_backtest_results_table.php`、`2025_07_22_042858_add_weighted_accuracy_to_backtest_results_table.php`、`2025_08_02_113147_add_top3_metrics_to_backtest_results_table.php`
- 数据处理：
  - 导出并归档 `backtest_results` 表数据（如需保留对照）；
  - 灰度确认后执行 drop table（通过新增迁移或清理命令）；
- 文档与路由：删除 `/api/v3/analysis/*` 与 `/api/v3/backtest/*` 相关描述与代码引用；
- 监控：移除回测相关指标与仪表卡片。
