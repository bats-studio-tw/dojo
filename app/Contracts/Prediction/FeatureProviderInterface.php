<?php

namespace App\Contracts\Prediction;

interface FeatureProviderInterface
{
    /** 返回该Provider产出的唯一特征键 */
    public function getKey(): string;

    /**
     * 从行情快照和历史数据中提取特征分数
     * 返回统一结构: ['SYMBOL' => ['raw' => float|null, 'norm' => float|null, 'meta' => array]]
     */
    public function extractFeatures(array $snapshots, array $history = []): array;
}
