<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;

class VolumeFeatureProvider implements FeatureProviderInterface
{
    public function extractFeatures(array $snapshots, array $history): array
    {
        $scores = [];

        // 收集所有代币的24小时交易量
        $volumes = [];
        foreach ($snapshots as $snapshot) {
            $symbol = $snapshot['symbol'] ?? $snapshot->symbol ?? '';
            $volume = $snapshot['volume_24h'] ?? $snapshot->volume_24h ?? 0;

            if (! empty($symbol) && $volume > 0) {
                $volumes[$symbol] = $volume;
            }
        }

        // 如果没有交易量数据，返回空数组
        if (empty($volumes)) {
            return $scores;
        }

        // 计算交易量的统计信息用于标准化
        $maxVolume = max($volumes);
        $minVolume = min($volumes);
        $avgVolume = array_sum($volumes) / count($volumes);

        foreach ($volumes as $symbol => $volume) {
            try {
                // 计算相对交易量分数 (0-100)
                if ($maxVolume > $minVolume) {
                    // 使用min-max标准化
                    $volumeScore = (($volume - $minVolume) / ($maxVolume - $minVolume)) * 100;
                } else {
                    // 如果所有交易量相同，给平均分
                    $volumeScore = 50.0;
                }

                $scores[$symbol] = $volumeScore;
            } catch (\Exception $e) {
                \Log::warning("Failed to extract volume feature for {$symbol}: " . $e->getMessage());
                $scores[$symbol] = 50.0; // 默认值
            }
        }

        return $scores;
    }
}
