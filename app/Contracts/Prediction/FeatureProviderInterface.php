<?php

namespace App\Contracts\Prediction;

interface FeatureProviderInterface
{
    /**
     * 从行情快照和历史数据中提取特征分数
     *
     * @param array $snapshots 当前行情快照数组
     * @param array $history 历史数据数组
     * @return array 返回格式: ['symbol' => score, ...]
     */
    public function extractFeatures(array $snapshots, array $history): array;
}
