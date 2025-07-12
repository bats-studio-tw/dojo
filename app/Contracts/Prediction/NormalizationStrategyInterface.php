<?php

namespace App\Contracts\Prediction;

interface NormalizationStrategyInterface
{
    /**
     * 对数值数组进行标准化处理
     *
     * @param array $values 原始数值数组
     * @return array 标准化后的数值数组
     */
    public function normalize(array $values): array;
}
