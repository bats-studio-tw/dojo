/**
 * 动态条件构建器 Composable
 * 提供条件构建、评估和管理的功能
 */

// 条件类型配置
export const conditionTypes = {
  // H2H策略条件
  confidence: {
    label: '置信度',
    unit: '%',
    min: 0,
    max: 100,
    step: 1,
    precision: 0,
    placeholder: '85',
    description: 'AI预测结果的可信程度，数值越高表示算法对预测结果越有把握'
  },
  score: {
    label: '分数',
    unit: '',
    min: 0,
    max: 100,
    step: 1,
    precision: 1,
    placeholder: '60',
    description: '预测分数的最低要求，分数越高表示该Token在预测中表现越突出'
  },
  sample_count: {
    label: '最少样本数',
    unit: '',
    min: 1,
    max: 200,
    step: 1,
    precision: 0,
    placeholder: '10',
    description: '预测所需的最少历史数据量，样本数越多预测结果越可靠'
  },
  win_rate: {
    label: '胜率',
    unit: '%',
    min: 0,
    max: 100,
    step: 1,
    precision: 1,
    placeholder: '65',
    description: '该Token在历史预测中的成功率，数值越高表示过往表现越好'
  },
  top3_rate: {
    label: '保本率',
    unit: '%',
    min: 0,
    max: 100,
    step: 1,
    precision: 1,
    placeholder: '50',
    description: 'Token排名进入前三的比例，通常前三有奖励可以保本或盈利'
  },
  avg_rank: {
    label: '平均排名',
    unit: '',
    min: 1,
    max: 5,
    step: 0.01,
    precision: 2,
    placeholder: '3.0',
    description: 'Token在历史预测中的平均排名，数值越小表示平均表现越好'
  },
  stability: {
    label: '波动性',
    unit: '',
    min: 0,
    max: 2,
    step: 0.01,
    precision: 2,
    placeholder: '0.8',
    description: 'Token价格波动的标准差，数值越小表示价格越稳定风险越低'
  },
  absolute_score: {
    label: '绝对分数',
    unit: '',
    min: 0,
    max: 1,
    step: 0.01,
    precision: 2,
    placeholder: '0.7',
    description: 'AI算法给出的绝对评分，反映Token的综合表现潜力'
  },
  relative_score: {
    label: '相对分数',
    unit: '',
    min: 0,
    max: 1,
    step: 0.01,
    precision: 2,
    placeholder: '0.5',
    description: '该Token相对于其他Token的评分优势'
  },
  h2h_score: {
    label: 'H2H分数',
    unit: '',
    min: 0,
    max: 1,
    step: 0.01,
    precision: 2,
    placeholder: '0.7',
    description: 'Head-to-Head对战分数，反映该Token与其他Token直接竞争时的胜率'
  },
  change_5m: {
    label: '5分钟涨跌',
    unit: '%',
    min: -10,
    max: 10,
    step: 0.01,
    precision: 2,
    placeholder: '2.0',
    description: 'Token在过去5分钟的价格变动百分比'
  },
  change_1h: {
    label: '1小时涨跌',
    unit: '%',
    min: -20,
    max: 20,
    step: 0.01,
    precision: 2,
    placeholder: '5.0',
    description: 'Token在过去1小时的价格变动百分比'
  },
  change_4h: {
    label: '4小时涨跌',
    unit: '%',
    min: -30,
    max: 30,
    step: 0.01,
    precision: 2,
    placeholder: '10.0',
    description: 'Token在过去4小时的价格变动百分比'
  },
  change_24h: {
    label: '24小时涨跌',
    unit: '%',
    min: -50,
    max: 50,
    step: 0.01,
    precision: 2,
    placeholder: '20.0',
    description: 'Token在过去24小时的价格变动百分比'
  },
  // 动能策略条件
  momentum_score: {
    label: '动能分数',
    unit: '',
    min: -5,
    max: 5,
    step: 0.1,
    precision: 1,
    placeholder: '1.5',
    description: '基于价格动能的综合评分，数值越高表示上涨动能越强'
  },
  elo_win_rate: {
    label: 'Elo胜率',
    unit: '',
    min: 0,
    max: 1,
    step: 0.01,
    precision: 2,
    placeholder: '0.55',
    description: '基于Elo评分系统的胜率预测，数值越高表示获胜概率越大'
  },
  momentum_confidence: {
    label: '动能置信度',
    unit: '',
    min: 0,
    max: 1,
    step: 0.01,
    precision: 2,
    placeholder: '0.65',
    description: '动能预测模型对结果的置信程度，数值越高表示预测越可靠'
  },
  // 排名条件
  h2h_rank: {
    label: 'AI预测排名',
    unit: '',
    min: 1,
    max: 5,
    step: 1,
    precision: 0,
    placeholder: '1',
    description: 'AI预测的Token排名，数值越小表示排名越靠前（1=第一名）'
  },
  momentum_rank: {
    label: '动能预测排名',
    unit: '',
    min: 1,
    max: 5,
    step: 1,
    precision: 0,
    placeholder: '1',
    description: '动能预测的Token排名，数值越小表示排名越靠前（1=第一名）'
  }
};

// 条件类型定义
export interface DynamicCondition {
  id: string;
  type: string;
  operator: string;
  value: number;
  logic: 'and' | 'or';
}

export const useConditionBuilder = () => {
  // 生成唯一ID
  const generateId = () => `condition_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

  // 条件类型变化处理
  const onConditionTypeChange = (condition: DynamicCondition) => {
    const typeConfig = conditionTypes[condition.type as keyof typeof conditionTypes];
    if (typeConfig) {
      // 重置为默认值
      condition.value = parseFloat(typeConfig.placeholder);
      // 根据条件类型设置合适的操作符
      if (['avg_rank', 'stability', 'h2h_rank', 'momentum_rank'].includes(condition.type)) {
        condition.operator = 'lte'; // 排名和波动性使用小于等于
      } else {
        condition.operator = 'gte'; // 其他条件使用大于等于
      }
    }
  };

  // 获取条件类型选项
  const getConditionTypeOptions = () => {
    return Object.entries(conditionTypes).map(([key, config]) => ({
      label: config.label,
      value: key
    }));
  };

  // 获取操作符选项
  const getOperatorOptions = (type: string) => {
    const baseOperators = [
      { label: '≥', value: 'gte' },
      { label: '≤', value: 'lte' },
      { label: '=', value: 'eq' },
      { label: '≠', value: 'ne' }
    ];

    // 对于排名和波动性，优先显示小于等于
    if (['avg_rank', 'stability', 'h2h_rank', 'momentum_rank'].includes(type)) {
      return [
        { label: '≤', value: 'lte' },
        { label: '≥', value: 'gte' },
        { label: '=', value: 'eq' },
        { label: '≠', value: 'ne' }
      ];
    }

    return baseOperators;
  };

  // 获取数值范围配置
  const getMinValue = (type: string) => conditionTypes[type as keyof typeof conditionTypes]?.min || 0;
  const getMaxValue = (type: string) => conditionTypes[type as keyof typeof conditionTypes]?.max || 100;
  const getStepValue = (type: string) => conditionTypes[type as keyof typeof conditionTypes]?.step || 1;
  const getPrecision = (type: string) => conditionTypes[type as keyof typeof conditionTypes]?.precision || 0;
  const getPlaceholder = (type: string) => conditionTypes[type as keyof typeof conditionTypes]?.placeholder || '0';

  // 获取条件描述
  const getConditionDescription = (condition: DynamicCondition) => {
    const typeConfig = conditionTypes[condition.type as keyof typeof conditionTypes];
    return typeConfig?.description || '请选择条件类型';
  };

  // 获取条件预览文本
  const getConditionPreview = (condition: DynamicCondition) => {
    const typeConfig = conditionTypes[condition.type as keyof typeof conditionTypes];
    if (!typeConfig) return '未知条件';

    const operatorText =
      {
        gte: '≥',
        lte: '≤',
        eq: '=',
        ne: '≠'
      }[condition.operator] || '≥';

    return `${typeConfig.label} ${operatorText} ${condition.value}${typeConfig.unit}`;
  };

  // 根据条件类型获取Token值
  const getTokenValueByType = (token: any, type: string): number => {
    switch (type) {
      case 'confidence':
        return token.rank_confidence || token.confidence || 0;
      case 'score':
        return token.predicted_final_value || token.score || 0;
      case 'sample_count':
        return token.total_games || token.sample_count || 0;
      case 'win_rate':
        return (token.win_rate || 0) * 100; // 转换为百分比
      case 'top3_rate':
        return (token.top3_rate || 0) * 100; // 转换为百分比
      case 'avg_rank':
        return token.avg_rank || 3;
      case 'stability':
        return token.value_stddev || 0;
      case 'absolute_score':
        return token.absolute_score || 0;
      case 'relative_score':
        return token.relative_score || 0;
      case 'h2h_score':
        return token.h2h_score || 0;
      case 'change_5m':
        return (token.change_5m || 0) * 100; // 转换为百分比
      case 'change_1h':
        return (token.change_1h || 0) * 100; // 转换为百分比
      case 'change_4h':
        return (token.change_4h || 0) * 100; // 转换为百分比
      case 'change_24h':
        return (token.change_24h || 0) * 100; // 转换为百分比
      case 'momentum_score':
        return token.momentum_score || 0;
      case 'elo_win_rate':
        return token.elo_win_rate || 0;
      case 'momentum_confidence':
        return token.confidence || 0;
      case 'h2h_rank':
        return token.predicted_rank || 999;
      case 'momentum_rank':
        return token.momentum_rank || token.predicted_rank || 999;
      default:
        return 0;
    }
  };

  // 评估单个条件
  const evaluateSingleCondition = (token: any, condition: DynamicCondition): boolean => {
    const tokenValue = getTokenValueByType(token, condition.type);

    switch (condition.operator) {
      case 'gte':
        return tokenValue >= condition.value;
      case 'lte':
        return tokenValue <= condition.value;
      case 'eq':
        return Math.abs(tokenValue - condition.value) < 0.001; // 浮点数比较
      case 'ne':
        return Math.abs(tokenValue - condition.value) >= 0.001;
      default:
        return true;
    }
  };

  // 动态条件评估函数
  const evaluateDynamicConditions = (token: any, conditions: DynamicCondition[]): boolean => {
    // 如果没有条件，默认所有 Token 都通过
    if (conditions.length === 0) return true;

    // 1. 先计算第一个条件的结果作为初始值
    let result = evaluateSingleCondition(token, conditions[0]);

    // 2. 从第二个条件开始遍历
    for (let i = 1; i < conditions.length; i++) {
      const condition = conditions[i];
      const currentResult = evaluateSingleCondition(token, condition);

      // 3. 使用当前条件自身的 logic (and/or) 来与之前的结果进行组合
      if (condition.logic === 'and') {
        result = result && currentResult;
      } else {
        // or
        result = result || currentResult;
      }
    }

    return result;
  };

  return {
    conditionTypes,
    generateId,
    onConditionTypeChange,
    getConditionTypeOptions,
    getOperatorOptions,
    getMinValue,
    getMaxValue,
    getStepValue,
    getPrecision,
    getPlaceholder,
    getConditionDescription,
    getConditionPreview,
    getTokenValueByType,
    evaluateSingleCondition,
    evaluateDynamicConditions
  };
};
