export interface ErrorOptions {
  showToast?: boolean;
  logToConsole?: boolean;
  fallbackMessage?: string;
}

export class AutoBettingError extends Error {
  constructor(
    message: string,
    public code?: string,
    public originalError?: any
  ) {
    super(message);
    this.name = 'AutoBettingError';
  }
}

export const handleError = (error: any, options: ErrorOptions = {}): void => {
  const { showToast = true, logToConsole = true, fallbackMessage = '操作失败，请稍后重试' } = options;

  let userMessage = fallbackMessage;

  // 根据错误类型提供用户友好的消息
  if (error instanceof AutoBettingError) {
    userMessage = error.message;
  } else if (error?.response?.data?.message) {
    userMessage = error.response.data.message;
  } else if (error?.message) {
    // 转换技术错误为用户友好的消息
    if (error.message.includes('Network Error')) {
      userMessage = '网络连接失败，请检查网络设置';
    } else if (error.message.includes('timeout')) {
      userMessage = '请求超时，请稍后重试';
    } else if (error.message.includes('401')) {
      userMessage = '身份验证失败，请重新登录';
    } else if (error.message.includes('403')) {
      userMessage = '权限不足，无法执行此操作';
    } else if (error.message.includes('500')) {
      userMessage = '服务器错误，请稍后重试';
    }
  }

  if (logToConsole) {
    console.error('AutoBetting Error:', {
      message: userMessage,
      originalError: error,
      timestamp: new Date().toISOString()
    });
  }

  if (showToast && window.$message) {
    window.$message.error(userMessage);
  }
};

export const handleAsyncOperation = async <T>(
  operation: () => Promise<T>,
  options: ErrorOptions & {
    loadingMessage?: string;
    successMessage?: string;
  } = {}
): Promise<T | null> => {
  const { loadingMessage, successMessage, ...errorOptions } = options;

  try {
    if (loadingMessage && window.$message) {
      window.$message.loading(loadingMessage);
    }

    const result = await operation();

    if (successMessage && window.$message) {
      window.$message.success(successMessage);
    }

    return result;
  } catch (error) {
    handleError(error, errorOptions);
    return null;
  }
};

export const createConfirmDialog = (
  title: string,
  content: string,
  onConfirm: () => void | Promise<void>,
  options: {
    confirmText?: string;
    cancelText?: string;
    type?: 'warning' | 'error' | 'info';
  } = {}
): void => {
  const { confirmText = '确认', cancelText = '取消', type = 'warning' } = options;

  if (window.$dialog) {
    window.$dialog[type]({
      title,
      content,
      positiveText: confirmText,
      negativeText: cancelText,
      onPositiveClick: async () => {
        try {
          await onConfirm();
        } catch (error) {
          handleError(error);
        }
      }
    });
  }
};
