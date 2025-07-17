/**
 * 动态条件构建器 Composable
 * 提供条件构建、评估和管理的功能
 */

// 条件类型配置
export const conditionTypes = {
  h2h_rank: {
    label: '智能对战排名',
    unit: '',
    min: 1,
    max: 5,
    step: 1,
    precision: 0,
    placeholder: '1',
    description: '基于H2H对战分析的智能预测排名，数值越小表示排名越靠前（1=第一名）'
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
  },
  absolute_score: {
    label: '绝对分数',
    unit: '',
    min: 0,
    max: 1,
    step: 0.01,
    precision: 2,
    placeholder: '0.7',
    description: '智能对战算法给出的绝对评分，反映Token的综合表现潜力'
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
  score: {
    label: '综合分数',
    unit: '',
    min: 0,
    max: 100,
    step: 1,
    precision: 1,
    placeholder: '60',
    description: '智能对战预测分数的最低要求，分数越高表示该Token在预测中表现越突出'
  },
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
  // H2H策略条件
  confidence: {
    label: '智能对战置信度',
    unit: '%',
    min: 0,
    max: 100,
    step: 1,
    precision: 0,
    placeholder: '85',
    description: '智能对战预测结果的可信程度，数值越高表示算法对预测结果越有把握'
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
    label: '第一名率',
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
  avg_rank: {
    label: '平均排名',
    unit: '',
    min: 1,
    max: 5,
    step: 0.01,
    precision: 2,
    placeholder: '3.0',
    description: 'Token在历史预测中的平均排名，数值越小表示平均表现越好'
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
      if (['avg_rank', 'h2h_rank', 'momentum_rank'].includes(condition.type)) {
        condition.operator = 'lte'; // 排名使用小于等于
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

    // 对于排名，优先显示小于等于
    if (['avg_rank', 'h2h_rank', 'momentum_rank'].includes(type)) {
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

  // 获取逻辑分组预览
  const getLogicGroupPreview = (conditions: DynamicCondition[]) => {
    if (conditions.length === 0) return '';
    if (conditions.length === 1) return getConditionPreview(conditions[0]);

    const groups: string[] = [];
    let currentGroup: DynamicCondition[] = [conditions[0]];

    for (let i = 1; i < conditions.length; i++) {
      const condition = conditions[i];

      if (condition.logic === 'and') {
        // 继续当前 AND 组
        currentGroup.push(condition);
      } else {
        // 遇到 OR，结束当前组并开始新组
        if (currentGroup.length > 0) {
          const groupText = currentGroup.map(c => getConditionPreview(c)).join(' AND ');
          groups.push(currentGroup.length > 1 ? `(${groupText})` : groupText);
        }
        currentGroup = [condition];
      }
    }

    // 处理最后一个组
    if (currentGroup.length > 0) {
      const groupText = currentGroup.map(c => getConditionPreview(c)).join(' AND ');
      groups.push(currentGroup.length > 1 ? `(${groupText})` : groupText);
    }

    return groups.join(' OR ');
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
        return token.win_rate || 0; // win_rate已经是百分比格式
      case 'top3_rate':
        return token.top3_rate || 0; // top3_rate已经是百分比格式
      case 'avg_rank':
        return token.avg_rank || 3;

      case 'absolute_score':
        return token.absolute_score || 0;
      case 'relative_score':
        return token.relative_score || 0;
      case 'h2h_score':
        return token.h2h_score || 0;

      case 'momentum_score':
        return token.momentum_score || token.mom_score || 0;
      case 'elo_win_rate':
        return token.elo_win_rate || token.elo_prob || 0;
      case 'momentum_confidence':
        return token.confidence || 0;

      case 'h2h_rank':
        // 🔧 修复：智能对战预测排名字段映射
        // 从currentAnalysis数据中获取predicted_rank
        return token.predicted_rank || 999;
      case 'momentum_rank':
        // 🔧 修复：动能预测排名字段映射
        // 从合并后的数据中获取momentum_rank，如果没有则使用predicted_rank
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

  // 动态条件评估函数 - 支持逻辑优先级
  const evaluateDynamicConditions = (token: any, conditions: DynamicCondition[]): boolean => {
    // 如果没有条件，默认所有 Token 都通过
    if (conditions.length === 0) return true;

    // 如果只有一个条件，直接返回结果
    if (conditions.length === 1) {
      return evaluateSingleCondition(token, conditions[0]);
    }

    // 使用栈来处理逻辑优先级
    // AND 的优先级高于 OR，所以先处理 AND 连接的条件组
    const stack: boolean[] = [];
    let currentGroup: boolean[] = [];
    let currentLogic: 'and' | 'or' | null = null;

    // 处理第一个条件
    currentGroup.push(evaluateSingleCondition(token, conditions[0]));

    // 从第二个条件开始处理
    for (let i = 1; i < conditions.length; i++) {
      const condition = conditions[i];
      const currentResult = evaluateSingleCondition(token, condition);

      // 如果当前逻辑是 AND，继续累积到当前组
      if (condition.logic === 'and') {
        currentGroup.push(currentResult);
      } else {
        // 如果遇到 OR，先处理当前 AND 组，然后开始新的组
        if (currentGroup.length > 0) {
          // 计算当前 AND 组的结果（所有条件都为真）
          const andResult = currentGroup.every(result => result);
          stack.push(andResult);
          currentGroup = [];
        }
        // 开始新的组
        currentGroup.push(currentResult);
      }
    }

    // 处理最后一个组
    if (currentGroup.length > 0) {
      const andResult = currentGroup.every(result => result);
      stack.push(andResult);
    }

    // 最后，所有组之间用 OR 连接
    return stack.some(result => result);
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
    getLogicGroupPreview,
    getTokenValueByType,
    evaluateSingleCondition,
    evaluateDynamicConditions
  };
};
