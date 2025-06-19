# 🎯 游戏 WebSocket 监听器使用指南

## 📊 系统概述

这是一个针对 `dojo3.io` 小游戏的自动化数据分析与决策辅助系统的核心 WebSocket 监听器模块。该系统能够：

- 🔄 **持续监听**: 7x24小时连接到游戏WebSocket服务器
- 🎯 **智能识别**: 自动识别新游戏轮次的下注阶段
- 📈 **市场数据**: 实时获取代币市场价格（通过Dex Screener API）
- 💾 **数据存储**: 将游戏数据和市场数据持久化存储
- 🔄 **自动重连**: 连接断开时自动重连机制

## 🚀 快速开始

### 1. 启动监听器（交互模式）

```bash
php artisan game:listen
```

这将启动监听器并在控制台显示实时日志。按 `Ctrl+C` 停止。

### 2. 启动监听器（守护进程模式）

```bash
php artisan game:listen --daemon
```

以后台模式运行，适合生产环境。

### 3. 设置运行时间限制

```bash
# 运行1小时（3600秒）
php artisan game:listen --max-runtime=3600

# 运行12小时
php artisan game:listen --daemon --max-runtime=43200
```

## 📋 命令选项

| 选项            | 说明                               | 默认值         |
| --------------- | ---------------------------------- | -------------- |
| `--daemon`      | 以守护进程模式运行，不显示交互输出 | false          |
| `--max-runtime` | 最大运行时间（秒）                 | 86400 (24小时) |

## 📊 监控和日志

### 日志文件位置

- **WebSocket日志**: `storage/logs/websocket-YYYY-MM-DD.log`
- **应用日志**: `storage/logs/laravel-YYYY-MM-DD.log`

### 实时监控日志

```bash
# 监控WebSocket日志
php artisan pail --filter=websocket

# 监控所有日志
php artisan pail
```

## 🏗️ 系统架构

### 核心组件

1. **GameWebSocketService** - WebSocket连接管理
2. **GameDataProcessorService** - 数据处理和存储
3. **ListenGameWebSocket Command** - 命令行接口

### 数据流程

```
WebSocket连接 → 消息过滤 → 数据处理 → API调用 → 数据库存储
    ↓              ↓           ↓         ↓          ↓
游戏服务器      识别下注阶段   提取代币   获取价格    保存结果
```

## 📦 数据库结构

### game_rounds 表

```sql
- id: 主键
- round_id: 游戏轮次ID (对应WebSocket的rdId)
- settled_at: 结算时间
- created_at, updated_at: 时间戳
```

### round_results 表

```sql
- id: 主键
- game_round_id: 关联到game_rounds表
- token_symbol: 代币符号 (BTC, ETH等)
- rank: 代币排名
- value: 市场价格
```

## 🔧 进程管理

### 使用 Supervisor (推荐)

创建配置文件 `/etc/supervisor/conf.d/game-websocket.conf`:

```ini
[program:game-websocket]
process_name=%(program_name)s_%(process_num)02d
command=php artisan game:listen --daemon
directory=/path/to/your/project
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/supervisor.log
```

启动 Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start game-websocket:*
```

### 使用 systemd

创建服务文件 `/etc/systemd/system/game-websocket.service`:

```ini
[Unit]
Description=Game WebSocket Listener
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/your/project
ExecStart=/usr/bin/php artisan game:listen --daemon
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

启动服务:

```bash
sudo systemctl daemon-reload
sudo systemctl enable game-websocket
sudo systemctl start game-websocket
```

## 🐛 故障排除

### Windows 环境特殊说明

在Windows环境下运行时，你可能会看到以下信息：

```
ℹ️ Windows环境下，使用 Ctrl+C 来停止监听器。
```

这是正常现象，因为：

- Windows不支持Unix/Linux的进程信号机制（pcntl扩展）
- 监听器会正常运行，只是无法通过系统信号优雅关闭
- 使用 `Ctrl+C` 可以正常停止进程

**Windows下推荐的进程管理方式：**

1. 使用 `Ctrl+C` 手动停止
2. 在任务管理器中结束php进程
3. 使用Windows服务管理工具（如NSSM）

### 常见问题

1. **连接失败**

   - 检查网络连接
   - 确认WebSocket服务器地址正确
   - 检查防火墙设置

2. **内存泄漏**

   - 监控内存使用情况
   - 设置合理的 `max-runtime` 参数
   - 定期重启进程

3. **API限制**
   - Dex Screener API有速率限制
   - 系统已内置延迟机制 (0.2秒/请求)

### 调试模式

```bash
# 显示详细日志
php artisan game:listen -vvv

# 检查服务状态
php artisan about
```

## 📈 数据查询示例

### 查询游戏轮次

```php
use App\Models\GameRound;

// 获取最近10个轮次
$recentRounds = GameRound::with('roundResults')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// 获取未结算的轮次
$unsettledRounds = GameRound::unsettled()->get();
```

### 查询代币表现

```php
use App\Services\GameDataProcessorService;

$processor = app(GameDataProcessorService::class);

// 获取BTC最近30天表现
$btcStats = $processor->getTokenPerformanceStats('BTC', 30);
```

## ⚙️ 配置

### 环境变量

```env
# 日志级别
LOG_LEVEL=info

# WebSocket连接超时（如需要）
WEBSOCKET_TIMEOUT=30

# API请求超时
API_TIMEOUT=10
```

### 自定义配置

你可以在 `GameWebSocketService` 中修改：

- WebSocket服务器地址
- 重连间隔和次数
- 心跳包频率

## 🔄 更新和维护

### 更新代码

```bash
# 停止服务
sudo supervisorctl stop game-websocket:*

# 更新代码
git pull

# 清理缓存
php artisan optimize:clear

# 重启服务
sudo supervisorctl start game-websocket:*
```

### 数据库维护

```bash
# 清理旧数据（可选）
php artisan db:prune --model=GameRound

# 数据库优化
php artisan optimize:clear
```

## 📞 支持和联系

如遇到问题，请检查：

1. 日志文件中的错误信息
2. 网络连接状态
3. 数据库连接状态
4. API服务状态

---

**🎯 现在你的游戏情报中心已经准备就绪！启动监听器，开始收集宝贵的市场数据吧！**
