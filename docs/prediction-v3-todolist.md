### v3 重构 TodoList（进行中）

#### 概述

- 目标：激进移除旧综合预测与混分逻辑，全面切换到“面向特征 + 前端本地聚合”的 v3 架构，以“保本率（Top3）”为唯一核心指标优化。
- 状态：后端 v3 底座与切换点已就绪；待完成前端接入与最终清理。

#### 已完成（后端基础与切换点）

- [x] 文档完善：`docs/feature-driven-prediction-v3.md`（业务指标/数据模型/Provider规范/API契约/上线回退）
- [x] 新表迁移
  - [x] `database/migrations/2025_08_08_100000_create_features_table.php`
  - [x] `database/migrations/2025_08_08_100100_create_feature_snapshots_table.php`
  - [x] `database/migrations/2025_08_08_100200_create_user_strategy_profiles_table.php`
  - [x] `database/migrations/2025_08_08_100300_create_prediction_aggregates_table.php`
- [x] v3 API（后端仅产出特征 + 可选聚合）
  - [x] 控制器：`app/Http/Controllers/PredictionV3Controller.php`
  - [x] 路由：`GET /api/v3/features/round/{roundId?}`、`POST /api/v3/predict/aggregate`
- [x] 特征抽取作业与事件切入
  - [x] Job：`app/Jobs/ExtractFeaturesJob.php`（幂等入库 `feature_snapshots` + 缓存矩阵）
  - [x] 监听：`app/Listeners/PredictRoundJobDispatcher.php` 改为同步执行 `ExtractFeaturesJob`（停止旧混分/综合推荐）
- [x] Provider 统一输出结构与新增特征
  - [x] 契约变更：`app/Contracts/Prediction/FeatureProviderInterface.php`（新增 `getKey()`；统一返回 `raw/norm/meta`）
  - [x] 更新：`EloFeatureProvider`、`MomentumFeatureProvider`、`ShortTermMomentumFeatureProvider`
  - [x] 新增：`PTop3FromEloFeatureProvider`（近似实现，后续可替换精确/蒙特卡洛版）
- [x] 配置与清理辅助
  - [x] 配置：`config/prediction_v3.php`（启用开关、Provider 列表、缓存TTL）
  - [x] 清理命令骨架：`app/Console/Commands/DeprecateLegacyPredictionArtifacts.php`

#### 待办（按优先级/阶段）

- [ ] D1 迁移与开关
  - [ ] 运行 `php artisan migrate`
  - [ ] `.env` 设置 `PREDICTION_V3_ENABLED=true`
- [ ] D2 前端最小可用接入（默认本地聚合）
  - [x] `featureStore`：拉取 `/api/v3/features/round` 并缓存本局矩阵
  - [x] `resources/js/utils/featureAggregate.ts`：实现本地 `X·w` 聚合与特征贡献计算（文档已提供示例）
  - [ ] 複製一份 `AutoBetting.vue`/`SmartControlCenter.vue` 並改造成 v3 矩阵，替换原综合预测数据源；先跑通“特征→本地聚合→条件过滤→下注”
- [ ] D3 UI 组件与配置管理
  - [x] `FeatureMatrixTable.vue`（raw/norm 矩阵）
  - [x] `FeatureMixerPanel.vue`（权重滑条 + 实时聚合/贡献/排序）
  - [x] `StrategyProfileManager.vue`（保存/切换/导出权重配置到 `user_strategy_profiles`）
- [ ] D4 移除回测系统与清理项
  - [x] 文档与路由：删除回测/分析相关描述与接口（`/api/v3/analysis/*`、`/api/v3/backtest/*`）
  - [ ] 代码：标记并删除 `BacktestResult` 模型、`EvaluateBacktestParameters` Job、回测相关命令（`BacktestPredictionParameters`、`DebugBacktestResults`、`CleanupBacktestResults`、`ExportPredictionAnalysis`、`PromoteBestStrategy`）、`config/backtest.php`
  - [ ] 数据：归档 `backtest_results` 后新增迁移 drop 表（含相关增量迁移）
  - [ ] 监控：移除回测相关指标卡片
  - [ ] 前端：仅保留“在线对照观测”视图（对比 v2 展示/用户权重 Top3），不提供离线回测
- [ ] D5 灰度与监控
  - [ ] 接入日志与指标：特征生成时延、缓存命中率、API 延迟（99p）
  - [ ] 灰度 24–72h，确认前端完全脱离 v2 数据源
- [ ] D6 激进清理（前端切换完成后）
  - [ ] 删除或废弃：`CalculateMomentumJob`、`ScoreMixer`、`PredictionService`（或改造为单特征 Provider）
  - [ ] 移除 v2 依赖接口/数据模型引用（`PredictionController` 中历史/混合接口）
  - [ ] 下线旧表：`prediction_results`、`hybrid_round_predicts`、`strategy_predictions`（可先归档后 drop）

#### 风险与注意

- [ ] 前端未切换完成前，不要删除 v2 表与接口，避免页面空白或下注断流
- [ ] Provider 并发与缓存到位后，再将 `p_top3_from_elo` 升级为精确/蒙特卡洛版以提升Top3估计质量
- [ ] 权重误用风险：前端提供“保守/平衡/进攻”模板与最近窗口回测曲线

#### 快速验证步骤（手动）

- [ ] `php artisan migrate`
- [ ] 等待新局进入 bet，后端写入 `feature_snapshots` 并缓存 `feature_matrix:{roundId}`
- [ ] `GET /api/v3/features/round` 验证返回矩阵
- [ ] 在前端本地用 `X·w` 聚合排序（文档已给TS示例）查看排名与贡献，确认可用

#### 说明

- 旧接口/表当前仍保留（只读，对照用）；前端切换到 v3 完成后再做“硬删除”。
- 如需我继续，下一步将提交前端 `featureStore` 和 3 个组件的最小骨架，确保新链路端到端跑通。
