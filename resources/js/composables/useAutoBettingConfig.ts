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
  dynamic_conditions: [], // 🔧 修复：改为空数组，避免预设条件
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

        // 🔧 修复：确保正确加载云端数据，避免被默认值覆盖
        if (cloudConfig.jwt_token !== undefined) {
          config.jwt_token = cloudConfig.jwt_token;
        }
        if (cloudConfig.bet_amount !== undefined) {
          config.bet_amount = cloudConfig.bet_amount;
        }
        if (cloudConfig.dynamic_conditions !== undefined) {
          config.dynamic_conditions = cloudConfig.dynamic_conditions;
        }
        if (cloudConfig.is_active !== undefined) {
          config.is_active = cloudConfig.is_active;
        }
        config.uid = uid;

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

      // 只发送必要的字段
      const configData = {
        uid,
        jwt_token: config.jwt_token,
        bet_amount: config.bet_amount,
        dynamic_conditions: config.dynamic_conditions,
        is_active: config.is_active
      };

      console.log('💾 [saveConfigToCloud] 准备保存配置到云端:', {
        uid,
        bet_amount: configData.bet_amount,
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
  };

  // 重置所有配置
  const resetAllConfig = () => {
    Object.assign(config, {
      jwt_token: '',
      uid: '',
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
