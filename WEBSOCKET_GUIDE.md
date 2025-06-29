# WebSocket 实时通信系统使用指南

## 概述

本项目已成功集成了基于 Laravel Reverb 的 WebSocket 实时通信系统，用于在后端和前端之间进行实时数据传输。系统支持游戏数据更新、预测分析结果和用户通知的实时推送。

## 系统架构

```
外部游戏WebSocket ──> GameWebSocketService ──> 数据处理 ──> Laravel Reverb ──> 前端Vue组件
```

### 核心组件

1. **Laravel Reverb** - WebSocket 服务器
2. **事件系统** - 广播事件到客户端
3. **Vue Echo 客户端** - 前端WebSocket连接
4. **实时数据显示组件** - 用户界面

## 快速启动

### 1. 启动Laravel Reverb服务器

```bash
# 在一个终端窗口中启动Reverb服务器
php artisan reverb:start
```

服务器将在 `localhost:8080` 启动，你会看到类似输出：

```
2024-06-29 15:30:00 Reverb server started successfully.
2024-06-29 15:30:00 Local: http://localhost:8080
```

### 2. 启动Laravel应用服务器

```bash
# 在另一个终端窗口中启动Laravel开发服务器
php artisan serve
```

### 3. 启动前端构建

```bash
# 在第三个终端窗口中启动Vite开发服务器
npm run dev
# 或者
pnpm dev
```

### 4. 启动游戏数据监听器（可选）

如果你想要从外部游戏WebSocket获取实时数据并自动广播到内部WebSocket：

```bash
# 在第四个终端窗口中
php artisan game:listen
```

## 访问测试页面

启动所有服务后，你可以访问以下页面：

1. **主仪表板**（包含实时数据监控）: http://localhost:8000
2. **WebSocket测试页面**: http://localhost:8000/websocket-test

## 功能特性

### 1. 实时游戏数据广播

- **频道**: `game-updates`
- **事件**: `game.data.updated`
- **数据类型**: 游戏轮次信息、代币数据、结算结果

### 2. 预测分析广播

- **频道**: `predictions`
- **事件**: `prediction.updated`
- **数据类型**: AI预测结果、排名分析、置信度

### 3. 用户私人通知

- **频道**: `user.{userId}`
- **事件**: `user.notification`
- **数据类型**: 个人通知消息

## API 接口

### 广播游戏数据

```http
POST /websocket/broadcast/game-data
Content-Type: application/json

{
  "game_data": {
    "rdId": "round-123",
    "status": "settled",
    "token": {
      "BTC": {"s": 1, "p": 45000},
      "ETH": {"s": 2, "p": 3000}
    }
  },
  "type": "settlement"
}
```

### 广播预测数据

```http
POST /websocket/broadcast/prediction
Content-Type: application/json

{
  "prediction_data": [
    {
      "symbol": "BTC",
      "predicted_rank": 1,
      "risk_adjusted_score": 95.2,
      "rank_confidence": 85.5
    }
  ],
  "round_id": "round-123",
  "type": "prediction"
}
```

### 发送用户通知

```http
POST /websocket/notification
Content-Type: application/json

{
  "user_id": 1,
  "message": "预测分析已完成",
  "type": "success",
  "data": {}
}
```

### 获取最新数据

```http
GET /websocket/latest-data
```

### 检查连接状态

```http
GET /websocket/status
```

## 前端使用示例

### 监听游戏数据更新

```javascript
// 在Vue组件中
window.Echo.channel('game-updates').listen('game.data.updated', (data) => {
  console.log('游戏数据更新:', data);
  // 处理游戏数据
  this.latestGameData = data.data;
});
```

### 监听预测数据更新

```javascript
window.Echo.channel('predictions').listen('prediction.updated', (data) => {
  console.log('预测数据更新:', data);
  // 处理预测数据
  this.predictionData = data.data;
});
```

### 监听用户通知

```javascript
window.Echo.private(`user.${userId}`).listen('user.notification', (data) => {
  console.log('收到通知:', data);
  // 显示通知给用户
  this.showNotification(data.message, data.type);
});
```

## 集成到现有代码

WebSocket广播已自动集成到现有的数据处理流程中：

### 1. 游戏数据处理

在 `GameDataProcessorService::processSettlementData()` 中，处理完结算数据后会自动广播到WebSocket客户端。

### 2. 预测分析

在 `GamePredictionService::generateAndCachePrediction()` 中，生成预测结果后会自动广播到WebSocket客户端。

## 故障排除

### 1. WebSocket连接失败

**问题**: 前端显示"WebSocket连接失败"

**解决方案**:

- 确保Reverb服务器正在运行 (`php artisan reverb:start`)
- 检查端口8080是否被占用
- 确认环境变量配置正确

### 2. 事件未接收

**问题**: 前端没有收到广播事件

**解决方案**:

- 检查Laravel日志中是否有广播错误
- 确认频道名称和事件名称匹配
- 验证Echo配置是否正确

### 3. 权限问题

**问题**: 私人频道连接被拒绝

**解决方案**:

- 检查 `routes/channels.php` 中的权限验证逻辑
- 确保用户已认证
- 验证用户ID匹配

## 环境变量配置

确保 `.env` 文件中包含以下配置：

```env
# 广播配置
BROADCAST_CONNECTION=reverb

# Reverb WebSocket 配置
REVERB_APP_ID=local-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# WebSocket服务器配置
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Vite配置（用于前端）
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

## 监控和调试

### 1. Laravel日志

WebSocket相关日志会记录在Laravel日志中：

```bash
# 实时查看日志
php artisan pail
```

### 2. 浏览器开发者工具

在浏览器中按F12，查看：

- **Console**: WebSocket连接状态和接收的数据
- **Network**: WebSocket连接详情
- **Application > WebSocket**: 实时WebSocket通信

### 3. Reverb服务器日志

Reverb服务器会在终端中显示连接和断开日志。

## 生产环境部署

### 1. 使用进程管理器

```bash
# 使用Supervisor管理Reverb进程
# 创建配置文件 /etc/supervisor/conf.d/reverb.conf

[program:reverb]
command=php /path/to/your/project/artisan reverb:start
directory=/path/to/your/project
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/reverb.log
```

### 2. 使用HTTPS

在生产环境中，将 `REVERB_SCHEME` 设置为 `https`，并配置SSL证书。

### 3. 防火墙配置

确保WebSocket端口（默认8080）在防火墙中开放。

## 扩展功能

### 1. 添加新的事件类型

1. 在 `app/Events/` 中创建新的事件类
2. 在相应的服务中触发事件
3. 在前端组件中监听新事件

### 2. 添加频道认证

修改 `routes/channels.php` 添加更复杂的权限验证逻辑。

### 3. 集成Redis扩展

对于高并发场景，可以启用Redis扩展：

```env
REVERB_SCALING_ENABLED=true
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## 性能优化

1. **连接池管理**: Reverb自动管理连接池
2. **消息缓存**: 重要消息会缓存到Redis
3. **负载均衡**: 可配置多个Reverb实例
4. **压缩传输**: 自动启用WebSocket压缩

---

有任何问题，请查看Laravel Reverb官方文档或联系开发团队。
