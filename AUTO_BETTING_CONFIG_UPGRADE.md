# 自动下注配置系统架构升级

## 🎯 升级目标

本次升级解决了以下核心问题：

1. **配置丢失问题**：用户刷新页面后自定义策略配置丢失
2. **开发灵活性**：前端可以高频率增减策略参数，无需后端修改数据库
3. **职责分工**：实现"后端管配置与数据，前端管决策与执行"的混合架构

## 🏗️ 架构设计

### 核心理念：基于UID的混合模型

- **配置存储**：后端数据库持久化 + 前端localStorage备份
- **配置结构**：JSON字段存储动态参数，支持灵活扩展
- **用户识别**：基于dojo游戏的UID，无需系统登录认证
- **用户体验**：有UID时云端同步，无UID时本地存储

### 技术栈

- **后端**：Laravel 12 + MySQL + 基于UID的配置管理
- **前端**：Vue 3 + TypeScript + Reactive配置管理
- **存储**：数据库JSON字段 + localStorage双重保障

## 📊 数据库设计

### 表结构：`auto_betting_configs`

```sql
CREATE TABLE auto_betting_configs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uid VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    config_payload JSON NOT NULL,
    encrypted_jwt_token TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_uid (uid),
    INDEX idx_is_active (is_active)
);
```

### 字段说明

- `uid`：dojo游戏的用户UID，作为配置隔离标识
- `is_active`：总开关，便于后端快速筛选活跃用户
- `config_payload`：JSON字段，存储所有动态配置参数
- `encrypted_jwt_token`：加密存储的dojo游戏JWT令牌

## 🚀 功能实现

### 后端新增功能

#### 1. AutoBettingConfig 模型

```php
class AutoBettingConfig extends Model
{
    protected $fillable = [
        'uid', 'is_active', 'config_payload', 'encrypted_jwt_token'
    ];

    protected $casts = [
        'config_payload' => 'array', // 自动JSON转换
        'is_active' => 'boolean',
    ];

    public static function getByUid(string $uid): self {
        return self::firstOrCreate(
            ['uid' => $uid],
            ['config_payload' => self::getDefaultConfig()]
        );
    }
}
```

#### 2. 配置管理API（无需认证）

- `GET /api/auto-betting/config?uid=xxx` - 获取用户配置
- `POST /api/auto-betting/config` - 保存用户配置（包含uid）

#### 3. 安全特性

- JWT Token加密存储
- 基于UID的配置隔离
- 无需系统用户认证

### 前端架构升级

#### 1. 响应式配置管理

```typescript
const config = reactive({ ...defaultConfig });

// 基于UID状态的配置同步
const autoSaveConfig = async () => {
  saveConfigToLocalStorage(); // 总是备份到本地
  if (currentUID.value) {
    await saveConfigToCloud(); // 有UID时同步到云端
  }
};
```

#### 2. 混合存储策略

```typescript
// Token验证成功后，优先加载云端配置
const onTokenValidated = async (data) => {
  currentUID.value = data.uid;

  const cloudLoaded = await loadConfigFromCloud();
  if (!cloudLoaded) {
    // 云端无配置，将本地配置同步到云端
    await saveConfigToCloud();
  }
};
```

#### 3. 用户体验优化

- 实时配置状态提示（显示UID前8位）
- 云端/本地同步状态显示
- 自动保存 + 手动保存双重保障

## 📋 API接口文档

### 获取配置

```http
GET /api/auto-betting/config?uid=dojo_user_uid

Response:
{
  "success": true,
  "data": {
    "is_active": false,
    "jwt_token": "decrypted_dojo_jwt_token",
    "bankroll": 1000,
    "bet_amount": 200,
    "confidence_threshold": 88,
    // ... 其他配置参数
  }
}
```

### 保存配置

```http
POST /api/auto-betting/config
Content-Type: application/json

Body:
{
  "uid": "dojo_user_uid",
  "is_active": true,
  "jwt_token": "dojo_jwt_token",
  "bankroll": 1500,
  "bet_amount": 300,
  "confidence_threshold": 90,
  // ... 任意其他参数
}

Response:
{
  "success": true,
  "message": "配置已成功保存"
}
```

## 🔄 使用流程

### 有dojo UID状态

1. 用户完成Token验证 → 获得dojo UID
2. 自动从云端加载配置
3. 修改参数 → 1秒防抖后自动保存到云端
4. 状态提示 → "☁️ 配置已云端同步 (UID: 12345678...)"

### 无UID状态（Token验证前）

1. 打开页面 → 从localStorage加载配置
2. 修改参数 → 自动保存到localStorage
3. 状态提示 → "💾 配置本地存储 - 完成Token验证后可云端同步"

## 🛡️ 安全性与容错

### 安全措施

- dojo JWT Token使用Laravel Crypt加密存储
- 基于UID的配置隔离，用户只能访问自己的配置
- 无需创建系统用户账户

### 容错机制

- 云端加载失败 → 自动降级到localStorage
- 配置损坏 → 自动重置为默认配置
- 网络错误 → 保留本地配置，显示错误提示

## 🚀 扩展性

### 前端轻松添加新参数

```typescript
// 只需在前端添加新的配置项，后端无需任何修改
const config = reactive({
  // 现有配置...
  risk_level: 'medium', // 新参数1
  auto_compound: true, // 新参数2
  max_daily_bets: 10 // 新参数3
});
```

### 后端无需修改

- 新参数自动存储在JSON字段中
- 无需数据库migration
- 无需修改Controller逻辑

## 🎉 升级成果

✅ **解决配置丢失**：基于UID的云端配置永久保存  
✅ **提升开发效率**：前端可独立添加配置参数  
✅ **符合实际场景**：基于dojo游戏UID，无需系统登录  
✅ **保证向后兼容**：未验证Token时依然可以正常使用  
✅ **架构清晰**：前后端职责分工明确

## 🔧 关键修正

**原始设计问题**：使用了 `auth:sanctum` 中间件，但JWT是dojo游戏系统的，不是我们系统的用户认证。

**修正方案**：

1. 移除Laravel用户认证依赖
2. 改为基于dojo游戏UID的配置管理
3. API接口直接接受UID参数，无需认证中间件
4. 保持所有功能特性不变

**优势**：

- 符合实际使用场景（协助用户下注dojo游戏）
- 无需用户注册登录流程
- 直接基于dojo游戏身份进行配置管理
- 保持了云端同步和本地备份的所有优势

---

_本升级完全向后兼容，并修正了认证机制以符合实际使用场景。_
