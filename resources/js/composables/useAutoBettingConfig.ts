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
}

/**
 * 简化后的默认配置 - 只保留必要字段
 */
export const optimizedDefaultConfig: Omit<AutoBettingConfig, 'jwt_token' | 'uid'> = {
  bet_amount: 200,
  dynamic_conditions: [
    {
      id: `condition_${Date.now()}_ranking`,
      type: 'h2h_rank',
      operator: 'lte',
      value: 3,
      logic: 'and'
    }
  ],
  is_active: false
};

export const useAutoBettingConfig = () => {
  // 配置状态 - 使用简化后的默认配置
  const config = reactive<AutoBettingConfig>({
    jwt_token: '',
    ...optimizedDefaultConfig
  });

  // 配置状态
  const configSaving = ref(false);
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
        config.jwt_token = cloudConfig.jwt_token || '';
        config.bet_amount = cloudConfig.bet_amount || 200;
        config.dynamic_conditions = cloudConfig.dynamic_conditions || optimizedDefaultConfig.dynamic_conditions;
        config.is_active = cloudConfig.is_active || false;
        config.uid = uid;

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

      // 只发送必要的字段
      const configData = {
        uid,
        jwt_token: config.jwt_token,
        bet_amount: config.bet_amount,
        dynamic_conditions: config.dynamic_conditions,
        is_active: config.is_active
      };

      const response = await autoBettingApi.saveConfig(uid, configData);

      if (response.data.success) {
        configSyncStatus.value = { type: 'success', message: '配置已成功保存到云端' };
        return true;
      } else {
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

  // 从本地存储加载配置
  const loadConfigFromLocalStorage = () => {
    try {
      const savedConfig = localStorage.getItem('autoBettingConfig');
      if (savedConfig) {
        const parsedConfig = JSON.parse(savedConfig);
        // 只保留必要的字段
        config.jwt_token = parsedConfig.jwt_token || '';
        config.bet_amount = parsedConfig.bet_amount || 200;
        config.dynamic_conditions = parsedConfig.dynamic_conditions || optimizedDefaultConfig.dynamic_conditions;
        config.is_active = parsedConfig.is_active || false;
        config.uid = parsedConfig.uid || '';
      }
    } catch (error) {
      console.error('加载本地配置失败:', error);
    }
  };

  // 保存配置到本地存储
  const saveConfigToLocalStorage = () => {
    try {
      // 只保存必要的字段
      const configToSave = {
        jwt_token: config.jwt_token,
        bet_amount: config.bet_amount,
        dynamic_conditions: config.dynamic_conditions,
        is_active: config.is_active,
        uid: config.uid
      };
      localStorage.setItem('autoBettingConfig', JSON.stringify(configToSave));
    } catch (error) {
      console.error('保存本地配置失败:', error);
    }
  };

  // 自动保存配置（防抖）
  const autoSaveConfig = (uid?: string) => {
    if (saveConfigTimeout) {
      clearTimeout(saveConfigTimeout);
    }

    saveConfigTimeout = setTimeout(async () => {
      saveConfigToLocalStorage();
      if (uid) {
        await saveConfigToCloud(uid);
      }
    }, 1000);
  };

  // 手动保存配置
  const manualSaveConfig = async (uid?: string) => {
    saveConfigToLocalStorage();
    if (uid) {
      return await saveConfigToCloud(uid);
    }
    return true;
  };

  // 验证配置数据完整性
  const validateConfig = (): { isValid: boolean; errors: string[] } => {
    const errors: string[] = [];

    console.log('🔍 [validateConfig] 开始验证配置...');

    // 检查必要字段
    if (typeof config.bet_amount !== 'number' || config.bet_amount < 200 || config.bet_amount > 2000) {
      errors.push('下注金额必须在200-2000之间');
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
      ...optimizedDefaultConfig
    });
    saveConfigToLocalStorage();
  };

  // 重置所有配置
  const resetAllConfig = () => {
    Object.assign(config, {
      jwt_token: '',
      uid: '',
      ...optimizedDefaultConfig
    });
    saveConfigToLocalStorage();
  };

  // 初始化配置
  const initializeConfig = async (uid?: string) => {
    loadConfigFromLocalStorage();
    if (uid) {
      await loadConfigFromCloud(uid);
    }
  };

  return {
    // 状态
    config,
    configSaving,
    configSyncStatus,
    optimizedDefaultConfig,

    // 方法
    loadConfigFromCloud,
    saveConfigToCloud,
    loadConfigFromLocalStorage,
    saveConfigToLocalStorage,
    autoSaveConfig,
    manualSaveConfig,
    initializeConfig,
    validateConfig,

    // 🔄 重置方法
    resetToDefaultConfig,
    resetAllConfig
  };
};
