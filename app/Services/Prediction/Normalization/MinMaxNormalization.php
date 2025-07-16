<?php

namespace App\Services\Prediction\Normalization;

use App\Contracts\Prediction\NormalizationStrategyInterface;

class MinMaxNormalization implements NormalizationStrategyInterface
{
    public function normalize(array $values): array
    {
        if (empty($values)) {
            return [];
        }

        $min = min($values);
        $max = max($values);

        // 如果最大值等于最小值，返回0.5（中间值）
        if ($max === $min) {
            return array_fill_keys(array_keys($values), 0.5);
        }

        // 应用Min-Max标准化到[0,1]范围
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[$key] = ($value - $min) / ($max - $min);
        }

        return $normalized;
    }
}
