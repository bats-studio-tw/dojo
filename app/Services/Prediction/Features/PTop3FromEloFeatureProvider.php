<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Services\EloRatingEngine;
use App\Models\TokenRating;

class PTop3FromEloFeatureProvider implements FeatureProviderInterface
{
    public function __construct(
        private EloRatingEngine $elo
    ) {}

    public function getKey(): string
    {
        return 'p_top3_from_elo';
    }

    /**
     * 计算当前 5 币进入 Top3 的概率（小集合可枚举；默认用简化蒙特卡洛/近似）
     * 返回格式: [symbol => ['raw' => float, 'meta' => array]]
     */
    public function extractFeatures(array $snapshots, array $history = []): array
    {
        // 兼容两种输入：[{symbol: 'xxx'}, ...] 或 ['XXX' => any]
        $symbols = [];
        if (!empty($snapshots)) {
            $firstKey = array_key_first($snapshots);
            if (is_string($firstKey)) {
                // 形如 ['UNI' => ...]
                $symbols = array_map('strtoupper', array_keys($snapshots));
            } else {
                // 形如 [['symbol' => 'UNI'], ...]
                foreach ($snapshots as $s) {
                    $sym = is_array($s) ? ($s['symbol'] ?? null) : ($s->symbol ?? null);
                    if ($sym) {
                        $symbols[] = strtoupper($sym);
                    }
                }
            }
        }
        $symbols = array_values(array_unique(array_filter($symbols)));
        $n = count($symbols);
        if ($n < 2) {
            return [];
        }

        // 读取 Elo 并构造 Plackett–Luce 强度 s_i = 10^(elo/400)
        $elos = TokenRating::whereIn('symbol', $symbols)->pluck('elo', 'symbol')->toArray();
        $strengths = [];
        foreach ($symbols as $s) {
            $elo = (float)($elos[$s] ?? 1500.0);
            // 数值稳定性：将强度缩放到较小数量级，避免极端溢出
            $strengths[] = pow(10, $elo / 400.0);
        }

        // 计算每个代币进入 Top3 的概率（基于 PL，截断到前三层枚举）
        $indexToSymbol = $symbols; // 0..n-1 -> symbol
        $top3Prob = array_fill(0, $n, 0.0);

        $remainingIdx = range(0, $n - 1);

        // 递归枚举前三名的选择序列；当某 index 在第 k(<=3) 层被选中时，
        // 该路径对该 index 的 Top3 概率贡献即为当前路径概率前缀。
        $accumulate = function ($remaining, $prefixProb, $depth) use (&$accumulate, &$top3Prob, $strengths) {
            if ($depth >= 3 || empty($remaining)) {
                return;
            }
            $sum = 0.0;
            foreach ($remaining as $idx) {
                $sum += $strengths[$idx];
            }
            foreach ($remaining as $i => $idx) {
                $pChoose = $strengths[$idx] / $sum;
                $newPrefix = $prefixProb * $pChoose;
                // 被选中者在 Top3（当前位置即进入 Top3）
                $top3Prob[$idx] += $newPrefix;
                // 继续选择下一名次
                $nextRemaining = $remaining;
                unset($nextRemaining[$i]);
                $nextRemaining = array_values($nextRemaining);
                $accumulate($nextRemaining, $newPrefix, $depth + 1);
            }
        };

        $accumulate($remainingIdx, 1.0, 0);

        // 组装输出（raw/norm 同为概率 0~1）
        $out = [];
        foreach ($top3Prob as $i => $p) {
            $symbol = $indexToSymbol[$i];
            $out[$symbol] = [
                'raw' => (float) $p,
                'norm' => (float) $p,
                'meta' => [
                    'source' => 'plackett_luce_from_elo',
                    'note' => 'exact top3 probability via truncated PL (top-3) enumeration',
                ],
            ];
        }

        return $out;
    }
}


