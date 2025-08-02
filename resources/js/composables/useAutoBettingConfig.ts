import { ref, reactive } from 'vue';
import { autoBettingApi } from '@/utils/api';

export interface AutoBettingConfig {
  uid?: string;
  jwt_token: string;
  bet_amount: number;
  dynamic_conditions: Array<{
    id: string;
    type: string;
    operator: string;
    value: number;
    logic: 'and' | 'or';
  }>;
  is_active: boolean;
  betting_mode: 'real' | 'dummy'; // 新增：下注模式 - real(真实) 或 dummy(模拟)
}

/**
 * 简化后的默认配置 - 只保留必要字段
 */
export const optimizedDefaultConfig: Omit<AutoBettingConfig, 'jwt_token' | 'uid' | 'bet_amount'> = {
  dynamic_conditions: [],
  is_active: false,
  betting_mode: 'dummy' // 默认使用模拟模式，更安全
};

export const useAutoBettingConfig = () => {
  // 配置状态 - 使用简化后的默认配置
  const config = reactive<AutoBettingConfig>({
    jwt_token: '',
    bet_amount: 5, // 硬编码默认值，会根据betting_mode动态调整
    ...optimizedDefaultConfig
  });

  // 配置状态
  const configSaving = ref(false);
  const configLoading = ref(false); // 新增：配置加载状态
  const configSyncStatus = ref<{ type: 'success' | 'error' | 'info'; message: string } | null>(null);

  // 防抖器
  let saveConfigTimeout: NodeJS.Timeout | null = null;

  // 从云端加载配置
  const loadConfigFromCloud = async (uid: string): Promise<boolean> => {
    if (!uid) return false;

    try {
      const response = await autoBettingApi.getConfig(uid);
      if (response.data.success) {
        // 只保留必要的字段
        const cloudConfig = response.data.data;

        // 🔧 修复：JWT Token应该始终使用当前会话的Token，不应该被云端配置覆盖
        // JWT Token是会话级别的，不应该从云端加载
        // if (cloudConfig.jwt_token !== undefined) {
        //   config.jwt_token = cloudConfig.jwt_token;
        // }
        console.log('🔑 [loadConfigFromCloud] 跳过云端JWT Token，保持当前会话Token');
        if (cloudConfig.dynamic_conditions !== undefined) {
          config.dynamic_conditions = cloudConfig.dynamic_conditions;
        }
        if (cloudConfig.is_active !== undefined) {
          config.is_active = cloudConfig.is_active;
        }
        if (cloudConfig.betting_mode !== undefined) {
          config.betting_mode = cloudConfig.betting_mode;
        }
        config.uid = uid;

        // ⚙️ 根据betting_mode设置正确的下注金额
        if (config.betting_mode === 'real') {
          config.bet_amount = 200;
        } else {
          config.bet_amount = 5;
        }

        console.log('✅ [loadConfigFromCloud] 成功加载云端配置:', {
          bet_amount: config.bet_amount,
          dynamic_conditions: config.dynamic_conditions,
          is_active: config.is_active
        });

        configSyncStatus.value = { type: 'success', message: '已从云端加载配置' };
        return true;
      } else {
        configSyncStatus.value = { type: 'error', message: '加载云端配置失败' };
        return false;
      }
    } catch (error) {
      console.error('加载云端配置失败:', error);
      configSyncStatus.value = { type: 'error', message: '网络错误，无法加载云端配置' };
      return false;
    }
  };

  // 保存配置到云端
  const saveConfigToCloud = async (uid: string): Promise<boolean> => {
    if (!uid) return false;

    try {
      configSaving.value = true;

      // 🔧 修复：JWT Token是会话级别的，不应该保存到云端
      // 只发送必要的配置字段，bet_amount现在是硬编码的，不需要保存
      const configData = {
        uid,
        // jwt_token: config.jwt_token, // 不保存JWT Token到云端
        dynamic_conditions: config.dynamic_conditions,
        is_active: config.is_active,
        betting_mode: config.betting_mode
      };

      console.log('💾 [saveConfigToCloud] 准备保存配置到云端:', {
        uid,
        betting_mode: configData.betting_mode,
        dynamic_conditions: configData.dynamic_conditions,
        is_active: configData.is_active
      });

      const response = await autoBettingApi.saveConfig(uid, configData);

      if (response.data.success) {
        console.log('✅ [saveConfigToCloud] 配置已成功保存到云端');
        configSyncStatus.value = { type: 'success', message: '配置已成功保存到云端' };
        return true;
      } else {
        console.error('❌ [saveConfigToCloud] 保存云端配置失败:', response.data);
        configSyncStatus.value = { type: 'error', message: '保存云端配置失败' };
        return false;
      }
    } catch (error) {
      console.error('保存云端配置失败:', error);
      configSyncStatus.value = { type: 'error', message: '网络错误，无法保存到云端' };
      return false;
    } finally {
      configSaving.value = false;
    }
  };

  // 自动保存配置（防抖）
  const autoSaveConfig = (uid?: string) => {
    if (saveConfigTimeout) {
      clearTimeout(saveConfigTimeout);
    }

    saveConfigTimeout = setTimeout(async () => {
      if (uid) {
        await saveConfigToCloud(uid);
      }
    }, 1000);
  };

  // 手动保存配置
  const manualSaveConfig = async (uid?: string) => {
    if (uid) {
      return await saveConfigToCloud(uid);
    }
    return true;
  };

  // 验证配置数据完整性
  const validateConfig = (): { isValid: boolean; errors: string[] } => {
    const errors: string[] = [];

    console.log('🔍 [validateConfig] 开始验证配置...');

    // 检查下注模式
    if (!config.betting_mode || !['real', 'dummy'].includes(config.betting_mode)) {
      errors.push('下注模式必须是real或dummy');
    }

    // 检查动态条件
    if (config.dynamic_conditions && Array.isArray(config.dynamic_conditions)) {
      config.dynamic_conditions.forEach((condition, index) => {
        if (!condition.id || !condition.type || !condition.operator || typeof condition.value !== 'number') {
          errors.push(`动态条件${index + 1}格式不正确`);
        }
      });
    }

    const isValid = errors.length === 0;

    console.log('🔍 配置验证结果:', {
      isValid,
      errorCount: errors.length,
      errors
    });

    return { isValid, errors };
  };

  // 重置为默认配置
  const resetToDefaultConfig = () => {
    Object.assign(config, {
      jwt_token: config.jwt_token, // 保留JWT token
      uid: config.uid, // 保留UID
      bet_amount: config.betting_mode === 'real' ? 200 : 5, // 根据模式设置硬编码值
      ...optimizedDefaultConfig
    });
  };

  // 重置所有配置
  const resetAllConfig = () => {
    Object.assign(config, {
      jwt_token: '',
      uid: '',
      bet_amount: 5, // 默认模拟模式
      ...optimizedDefaultConfig
    });
  };

  // 初始化配置
  const initializeConfig = async (uid?: string) => {
    console.log('🚀 [initializeConfig] 开始初始化配置, uid:', uid);

    // 🔧 修复：在初始化开始时设置loading状态
    configLoading.value = true;

    try {
      if (uid) {
        console.log('📡 [initializeConfig] 有UID，开始从云端加载配置...');
        await loadConfigFromCloud(uid);
      } else {
        console.log('⚠️ [initializeConfig] 无UID，使用默认配置');
        // 如果没有UID，保持默认配置不变
        // 延迟一下让用户看到loading状态
        await new Promise((resolve) => setTimeout(resolve, 500));
      }
    } finally {
      // 🔧 修复：确保loading状态被正确结束
      configLoading.value = false;
    }
  };

  return {
    // 状态
    config,
    configSaving,
    configLoading, // 新增：配置加载状态
    configSyncStatus,
    optimizedDefaultConfig,

    // 方法
    loadConfigFromCloud,
    saveConfigToCloud,
    autoSaveConfig,
    manualSaveConfig,
    initializeConfig,
    validateConfig,

    // 🔄 重置方法
    resetToDefaultConfig,
    resetAllConfig
  };
};
