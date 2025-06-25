# Laravel Cache 配置指南

## 🚀 缓存系统配置

### 支持的缓存驱动

Laravel Cache 支持多种驱动，可根据部署环境灵活选择：

#### 1. **文件缓存 (file)** - 默认，零配置

```env
CACHE_DRIVER=file
```

- ✅ **优势**: 无需额外服务，开箱即用
- ✅ **适用**: 开发环境、小型应用
- 📁 **存储**: `storage/framework/cache/data/`

#### 2. **数据库缓存 (database)** - 当前配置

```env
CACHE_DRIVER=database
```

- ✅ **优势**: 利用现有数据库，持久化存储
- ✅ **适用**: 中型应用，需要持久化缓存
- 📋 **要求**: 运行 `php artisan cache:table && php artisan migrate`

#### 3. **Redis 缓存 (redis)** - 高性能

```env
CACHE_DRIVER=redis
REDIS_CLIENT=predis
```

- ✅ **优势**: 极高性能，内存存储
- ✅ **适用**: 高并发应用，生产环境
- 📋 **要求**: Redis 服务器 + Predis 包

#### 4. **内存缓存 (array)** - 仅测试

```env
CACHE_DRIVER=array
```

- ⚠️ **注意**: 仅在单个请求内有效，测试用

### 安装和配置

#### Redis 安装 (可选，高性能)

```bash
# Docker 方式 - 推荐
docker run -d --name redis-game -p 6379:6379 redis:latest

# WSL 方式
sudo apt update && sudo apt install redis-server
sudo service redis-server start

# 安装 PHP Redis 客户端
composer require predis/predis
```

#### 数据库缓存设置 (当前使用)

```bash
# 创建缓存表
php artisan cache:table
php artisan migrate
```

### 验证缓存配置

运行测试脚本：

```bash
php test_cache.php
```

**预期输出**：

```
测试 Laravel Cache 连接...
当前缓存驱动: database

✅ 写入测试数据成功
✅ 读取测试数据: Hello Cache from Laravel!
✅ 游戏数据存储成功
✅ 游戏数据读取成功:
   轮次ID: test_round_123
   代币: BTC, ETH, ONDO, SOL, DOGE
   状态: starting
   数据类型: array
✅ 过期测试数据已存储 (2秒后过期)
✅ 过期测试结果: not found
✅ 清理测试数据完成

🎉 Laravel Cache 测试全部通过！
```

## 🎯 游戏系统中的缓存使用

### 1. 当前局数据存储

- **键名**: `game:current_round`
- **数据结构**:

```php
[
    'round_id' => 'round_12345',
    'tokens' => ['BTC', 'ETH', 'ONDO', 'SOL', 'DOGE'],
    'status' => 'starting|running|settling|settled',
    'timestamp' => '2025-01-19T10:30:00.000Z',
    'token_count' => 5
]
```

- **过期时间**: 2小时
- **存储方式**: `Cache::put('game:current_round', $data, now()->addHours(2))`

### 2. WebSocket 监听器行为

- 检测到新局开始时自动存储到缓存
- 控制台输出：`🚀 新局开始: round_xxx (状态: starting)`
- 缓存存储：`💾 存储当前局代币: BTC, ETH, ONDO, SOL, DOGE`

### 3. API 行为优化

- **优先级**: 先从缓存获取当前局数据
- **备用方案**: 缓存无数据时使用最新已结算局数据
- **响应格式**:

```json
{
  "success": true,
  "data": [...],
  "meta": {
    "round_id": "round_12345",
    "status": "starting",
    "source": "current_round_cache",
    "timestamp": "2025-01-19T10:30:00.000Z"
  }
}
```

## 🔧 开发和部署

### 开发环境启动

```bash
# 1. 启动 Laravel 开发环境
composer run dev

# 2. 启动 WebSocket 监听器
php artisan game:listen

# 3. 访问 Dashboard 查看实时数据
```

### 生产环境优化

```bash
# 切换到 Redis (推荐)
echo "CACHE_DRIVER=redis" >> .env
docker run -d --name redis-prod -p 6379:6379 redis:latest

# 或者优化数据库缓存
php artisan config:cache
php artisan route:cache
```

## 📊 性能对比

| 驱动     | 读写速度 | 持久化 | 部署复杂度 | 适用场景        |
| -------- | -------- | ------ | ---------- | --------------- |
| file     | 中等     | ✅     | 极低       | 开发/小型应用   |
| database | 中等     | ✅     | 低         | 中型应用        |
| redis    | 极快     | ✅     | 中等       | 大型/高并发应用 |
| array    | 极快     | ❌     | 极低       | 测试环境        |

## 🎯 业务流程优化

### 原有问题

- ❌ 获取已结算局数据，失去实时性
- ❌ 新局开始时无法立即获取代币信息

### 优化后流程

1. ✅ WebSocket 监听到新局开始
2. ✅ 立即将代币列表存储到缓存
3. ✅ 前端调用 API 获取实时市场数据
4. ✅ 用户看到当前局的实时代币价格

### 状态显示

- 🟢 **进行中**: 当前局正在运行，显示实时数据
- 🟡 **结算中**: 游戏正在结算
- 🔵 **已结算**: 显示最新已结算局数据（备用）

## 🛠️ 故障排除

### 常见问题

1. **缓存写入失败**: 检查存储目录权限或数据库连接
2. **数据格式错误**: 检查 WebSocket 数据解析
3. **API 返回备用数据**: 缓存中无当前局数据

### 调试命令

```bash
# 检查缓存配置
php artisan tinker
>>> config('cache.default')
>>> Cache::get('game:current_round')

# 清除缓存
php artisan cache:clear

# 查看 WebSocket 日志
php artisan pail --filter=websocket

# 测试缓存连接
php test_cache.php
```

### 切换缓存驱动

```bash
# 切换到文件缓存
echo "CACHE_DRIVER=file" >> .env

# 切换到 Redis
echo "CACHE_DRIVER=redis" >> .env

# 应用配置
php artisan config:clear
```

## 💡 最佳实践

1. **开发环境**: 使用 `file` 或 `database` 缓存
2. **生产环境**: 使用 `redis` 缓存获得最佳性能
3. **定期清理**: 使用过期时间避免缓存膨胀
4. **监控**: 监控缓存命中率和性能指标
5. **备用方案**: 始终有降级策略，如缓存失效时使用数据库
