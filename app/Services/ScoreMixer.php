<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ScoreMixer
{
    /**
     * 混合 Elo 機率和動能分數，產生最終得分和預測排名。
     *
     * @param  array  $eloProb  [symbol => 0-1 機率]
     * @param  ?array  $momScore  [symbol => 0-100 動能分數]
     * @return array 包含預測結果的陣列
     */
    public function mix(array $eloProb, ?array $momScore = null): array
    {
        // 使用默認參數調用 mixWithParams
        return $this->mixWithParams($eloProb, $momScore, []);
    }

    /**
     * 使用自定義參數混合 Elo 機率和動能分數
     *
     * @param  array  $eloProb  [symbol => 0-1 機率]
     * @param  ?array  $momScore  [symbol => 0-100 動能分數]
     * @param  array  $params  自定義參數 ['elo_weight' => 0.6, 'momentum_weight' => 0.4, ...]
     * @return array 包含預測結果的陣列
     */
    public function mixWithParams(array $eloProb, ?array $momScore = null, array $params = []): array
    {
        if (empty($eloProb)) {
            Log::warning('Elo 機率數據為空，無法進行分數混合');

            return [];
        }

        try {
            // 檢查動能分數是否有效，用於決定權重
            $momOk = $momScore && count(array_filter($momScore, fn ($v) => $v !== null && is_numeric($v)));

            // 從參數或配置中獲取權重
            $wElo = $params['elo_weight'] ?? ($momOk ? config('prediction.w_elo', 0.65) : 1.0);
            $wMom = $params['momentum_weight'] ?? ($momOk ? (1 - $wElo) : 0.0);

            Log::info('分數混合開始', [
                'elo_symbols' => array_keys($eloProb),
                'momentum_available' => $momOk,
                'weight_elo' => $wElo,
                'weight_momentum' => $wMom,
            ]);

            $scores = [];
            foreach ($eloProb as $s => $p) {
                // 如果動能分數不存在，則預設為中性 50
                $mom = $momScore[$s] ?? 50;

                // 確保數據有效性
                $eloScore = is_numeric($p) ? max(0, min(1, $p)) : 0;
                $momScore_value = is_numeric($mom) ? max(0, min(100, $mom)) : 50;

                // 改进的混合计算：即使动能分数相同，也要保持Elo差异的影响
                $baseScore = $wElo * ($eloScore * 100) + $wMom * $momScore_value;

                // 添加微小的随机因子来打破完全相同的分数
                $randomFactor = (rand(0, 100) - 50) * 0.01; // ±0.5的随机因子
                $scores[$s] = $baseScore + $randomFactor;

                Log::info('分数混合计算', [
                    'symbol' => $s,
                    'elo_prob' => $p,
                    'elo_score' => $eloScore * 100,
                    'mom_score' => $momScore_value,
                    'weight_elo' => $wElo,
                    'weight_mom' => $wMom,
                    'base_score' => $baseScore,
                    'random_factor' => $randomFactor,
                    'final_score' => $scores[$s],
                ]);
            }

            // 根據分數降序排序
            arsort($scores);

            $out = [];
            $rank = 1;
            $sortedScores = array_values($scores); // 獲取排序後的分數值，用於計算信心度

            foreach ($scores as $s => $sc) {
                // 計算信心度，基於最高分與第二高分之間的差距
                // 確保 $sortedScores[1] 存在，避免因只有一個元素而產生錯誤
                $diff = $sc - ($sortedScores[1] ?? $sc);
                $conf = 50 + max(0, $diff) * 0.2;
                // 確保信心度不超過 100
                $confidence = round(min(100, $conf), 1);

                $out[] = [
                    'symbol' => $s,
                    'predicted_rank' => $rank++,
                    'final_score' => round($sc, 1),
                    'elo_prob' => round(($eloProb[$s] ?? 0) * 100, 1),
                    'mom_score' => round($momScore[$s] ?? 50, 1), // 如果 momScore 不存在，顯示 50
                    'confidence' => $confidence,
                ];
            }

            Log::info('分數混合完成', [
                'results_count' => count($out),
                'top_prediction' => $out[0] ?? null,
            ]);

            return $out;

        } catch (\Exception $e) {
            Log::error('分數混合失敗', [
                'elo_prob_count' => count($eloProb),
                'mom_score_count' => $momScore ? count($momScore) : 0,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 驗證和清理 Elo 機率數據
     *
     * @param  array  $eloProb  原始 Elo 機率數據
     * @return array 清理後的數據
     */
    public function validateEloProb(array $eloProb): array
    {
        $cleaned = [];
        foreach ($eloProb as $symbol => $prob) {
            if (is_numeric($prob) && $prob >= 0 && $prob <= 1) {
                $cleaned[strtoupper($symbol)] = (float) $prob;
            } else {
                Log::warning('無效的 Elo 機率數據', [
                    'symbol' => $symbol,
                    'prob' => $prob,
                ]);
            }
        }

        return $cleaned;
    }

    /**
     * 驗證和清理動能分數數據
     *
     * @param  array|null  $momScore  原始動能分數數據
     * @return array|null 清理後的數據
     */
    public function validateMomScore(?array $momScore): ?array
    {
        if (! $momScore) {
            return null;
        }

        $cleaned = [];
        foreach ($momScore as $symbol => $score) {
            if (is_numeric($score) && $score >= 0 && $score <= 100) {
                $cleaned[strtoupper($symbol)] = (float) $score;
            } elseif ($score === null) {
                $cleaned[strtoupper($symbol)] = null; // 保留 null 值
            } else {
                Log::warning('無效的動能分數數據', [
                    'symbol' => $symbol,
                    'score' => $score,
                ]);
                $cleaned[strtoupper($symbol)] = 50; // 使用預設值
            }
        }

        return $cleaned;
    }

    /**
     * 計算增強版信心度，考慮更多因素
     *
     * @param  array  $scores  分數陣列
     * @param  array  $eloProb  Elo 機率
     * @param  array|null  $momScore  動能分數
     * @return array [symbol => confidence]
     */
    public function calculateAdvancedConfidence(array $scores, array $eloProb, ?array $momScore = null): array
    {
        $confidences = [];
        $sortedScores = array_values($scores);
        $avgScore = array_sum($scores) / count($scores);

        foreach ($scores as $symbol => $score) {
            $baseConfidence = 50;

            // 因子1：與第二名的差距
            $diffFromSecond = $score - ($sortedScores[1] ?? $score);
            $baseConfidence += max(0, $diffFromSecond) * 0.3;

            // 因子2：與平均分的差距
            $diffFromAvg = $score - $avgScore;
            $baseConfidence += $diffFromAvg * 0.1;

            // 因子3：Elo 機率的穩定性
            $eloProb_value = $eloProb[$symbol] ?? 0.5;
            if ($eloProb_value > 0.6 || $eloProb_value < 0.4) {
                $baseConfidence += 5; // Elo 機率偏向極端值時增加信心
            }

            // 因子4：動能分數的存在性
            if ($momScore && isset($momScore[$symbol]) && $momScore[$symbol] !== null) {
                $baseConfidence += 3; // 有動能數據時增加少量信心
            }

            $confidences[$symbol] = round(min(100, max(0, $baseConfidence)), 1);
        }

        return $confidences;
    }

    /**
     * 生成預測報告摘要
     *
     * @param  array  $predictions  預測結果
     * @param  bool  $momOk  動能數據是否可用
     * @return array 報告摘要
     */
    public function generateSummary(array $predictions, bool $momOk): array
    {
        if (empty($predictions)) {
            return [
                'total_tokens' => 0,
                'prediction_method' => 'none',
                'avg_confidence' => 0,
                'top_prediction' => null,
            ];
        }

        $confidences = array_column($predictions, 'confidence');
        $avgConfidence = array_sum($confidences) / count($confidences);

        return [
            'total_tokens' => count($predictions),
            'prediction_method' => $momOk ? 'hybrid' : 'elo_only',
            'avg_confidence' => round($avgConfidence, 1),
            'top_prediction' => $predictions[0],
            'confidence_range' => [
                'min' => min($confidences),
                'max' => max($confidences),
            ],
        ];
    }
}
