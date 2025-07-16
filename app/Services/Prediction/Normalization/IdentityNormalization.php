<?php

namespace App\Services\Prediction\Normalization;

use App\Contracts\Prediction\NormalizationStrategyInterface;

class IdentityNormalization implements NormalizationStrategyInterface
{
    public function normalize(array $values): array
    {
        // 直接返回原始值，不进行任何标准化
        return $values;
    }
}
