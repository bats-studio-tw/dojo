<?php

namespace App\Services\Prediction\Normalization;

use App\Contracts\Prediction\NormalizationStrategyInterface;

class ZScoreNormalization implements NormalizationStrategyInterface
{
    public function normalize(array $values): array
    {
        if (empty($values)) {
            return [];
        }

        $count = count($values);
        if ($count === 1) {
            // 只有一个值时，返回0（标准化后的均值）
            return array_fill_keys(array_keys($values), 0.0);
        }

        // 计算均值和标准差
        $mean = array_sum($values) / $count;
        $variance = 0;

        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= $count;
        $stdDev = sqrt($variance);

        // 避免除零错误
        if ($stdDev === 0.0) {
            return array_fill_keys(array_keys($values), 0.0);
        }

        // 应用Z-Score标准化
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[$key] = ($value - $mean) / $stdDev;
        }

        return $normalized;
    }
}
