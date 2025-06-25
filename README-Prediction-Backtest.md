# 预测回测分析功能文档

## 功能概述

本系统现在支持**预测数据存储**和**回测分析**功能，可以将每轮次的预测数据保存到数据库中，并提供详细的预测准确度分析。

## 核心组件

### 1. RoundPredict 模型

存储每轮次的预测数据：

- `game_round_id`: 关联的游戏轮次ID
- `token_symbol`: 代币符号
- `predicted_rank`: 预测排名
- `prediction_score`: 预测评分
- `prediction_data`: 完整预测分析数据（JSON格式）
- `predicted_at`: 预测时间

### 2. GamePredictionService 增强

- ✅ 自动保存预测数据到数据库
- ✅ 支持缓存和数据库双重存储
- ✅ 避免重复数据，自动覆盖旧预测

### 3. PredictionAnalysisService 新服务

提供多种预测分析功能：

- **整体准确度分析**: 分析所有轮次的预测准确性
- **代币表现分析**: 各个代币的预测准确度统计
- **单个代币历史**: 查看特定代币的预测历史记录
- **性能摘要**: 预测算法整体性能概览

### 4. API 接口

#### 预测性能摘要

```bash
GET /api/prediction-analysis/performance
```

#### 整体准确度分析

```bash
GET /api/prediction-analysis/accuracy
```

#### 单个代币预测历史

```bash
GET /api/prediction-analysis/token/{tokenSymbol}/history?limit=20
```

## 使用方法

### 1. 测试预测存储功能

```bash
# 测试最新轮次的预测生成和存储
php artisan test:prediction-storage

# 测试指定轮次
php artisan test:prediction-storage --round-id=your_round_id

# 包含分析功能测试
php artisan test:prediction-storage --analyze
```

### 2. API 调用示例

```bash
# 获取预测性能摘要
curl http://localhost:8000/api/prediction-analysis/performance

# 获取整体准确度分析
curl http://localhost:8000/api/prediction-analysis/accuracy

# 获取BTC的预测历史
curl http://localhost:8000/api/prediction-analysis/token/BTC/history
```

### 3. 在代码中使用

```php
// 生成预测并自动保存到数据库
$predictionService = app(GamePredictionService::class);
$result = $predictionService->generateAndCachePrediction($tokens, $roundId);

// 分析预测准确度
$analysisService = app(PredictionAnalysisService::class);
$accuracy = $analysisService->analyzeOverallAccuracy();
$performance = $analysisService->getPredictionPerformanceSummary();
$tokenHistory = $analysisService->getTokenPredictionHistory('BTC', 20);
```

## 准确度评估指标

### 1. 精确匹配率

预测排名与实际排名完全一致的比例

### 2. 接近匹配率

预测排名与实际排名差距在1以内的比例

### 3. 平均排名差距

所有预测的排名误差平均值

### 4. 准确度评分

基于排名差距计算的0-100分评分，差距越小分数越高

## 数据表结构

### round_predicts 表

```sql
CREATE TABLE round_predicts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_round_id BIGINT UNSIGNED NOT NULL,
    token_symbol VARCHAR(255) NOT NULL,
    predicted_rank TINYINT UNSIGNED NOT NULL,
    prediction_score DECIMAL(5,2) NOT NULL,
    prediction_data JSON NULL,
    predicted_at TIMESTAMP NOT NULL,

    FOREIGN KEY (game_round_id) REFERENCES game_rounds(id) ON DELETE CASCADE,
    INDEX idx_round_rank (game_round_id, predicted_rank),
    INDEX idx_token_rank (token_symbol, predicted_rank),
    INDEX idx_predicted_at (predicted_at)
);
```

## 预测流程

1. **游戏开始时**: 系统自动生成预测数据
2. **数据存储**: 预测数据同时保存到缓存和数据库
3. **轮次结算**: 实际结果保存到 `round_results` 表
4. **准确度分析**: 对比预测和实际结果，计算准确度指标

## 回测分析示例

```php
// 获取整体预测表现
$analysis = $analysisService->analyzeOverallAccuracy();

echo "总轮次: " . $analysis['overall_stats']['total_rounds'];
echo "精确匹配率: " . $analysis['overall_stats']['exact_match_rate'] . "%";
echo "接近匹配率: " . $analysis['overall_stats']['close_match_rate'] . "%";

// 查看代币预测表现排行
foreach ($analysis['token_accuracy'] as $token) {
    echo "{$token['symbol']}: {$token['exact_match_rate']}% 准确率";
}
```

## 注意事项

1. **数据一致性**: 预测数据会在每次重新生成时清理旧数据
2. **性能考虑**: 分析大量历史数据时可能需要较长时间
3. **API权限**: 目前API未设置权限限制，生产环境需要考虑访问控制
4. **缓存策略**: 预测数据有2小时缓存时间，数据库数据为永久存储

## 未来改进方向

- [ ] 添加更多预测算法对比
- [ ] 实现预测准确度趋势分析
- [ ] 支持不同时间段的分析
- [ ] 添加可视化图表支持
- [ ] 实现预测模型的自动优化
