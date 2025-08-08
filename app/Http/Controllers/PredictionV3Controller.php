<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\GameRound;

class PredictionV3Controller extends Controller
{
    /**
     * GET /api/v3/features/round/{roundId?}
     */
    public function getRoundFeatures(Request $request, $roundId = null)
    {
        $roundId = $roundId ?: Cache::get('game:current_round')['round_id'] ?? null;
        if (!$roundId) {
            return response()->json(['success' => false, 'message' => '当前轮次不可用'], 400);
        }

        // 优先读缓存
        $cacheKey = "feature_matrix:{$roundId}";
        if (Cache::has($cacheKey)) {
            return response()->json(['success' => true, 'data' => Cache::get($cacheKey)]);
        }

        // 将字符串 round_id 映射为内部整型 game_round_id
        $gameRoundId = GameRound::where('round_id', $roundId)->value('id');
        if (!$gameRoundId) {
            return response()->json(['success' => true, 'data' => [
                'round_id' => (string) $roundId,
                'tokens' => [],
                'features' => [],
                'matrix' => [],
                'computed_at' => now()->toISOString(),
            ]]);
        }

        // 读取快照并拼装矩阵
        $rows = DB::table('feature_snapshots')
            ->select('token_symbol', 'feature_key', 'raw_value', 'normalized_value')
            ->where('game_round_id', $gameRoundId)
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['success' => true, 'data' => [
                'round_id' => (string) $roundId,
                'tokens' => [],
                'features' => [],
                'matrix' => [],
                'computed_at' => now()->toISOString(),
            ]]);
        }

        $tokens = [];
        $features = [];
        $matrix = [];
        foreach ($rows as $r) {
            $tokens[$r->token_symbol] = true;
            $features[$r->feature_key] = true;
            $matrix[$r->token_symbol][$r->feature_key] = [
                'raw' => $r->raw_value,
                'norm' => $r->normalized_value,
            ];
        }

        $payload = [
            'round_id' => (string) $roundId,
            'tokens' => array_keys($tokens),
            'features' => array_keys($features),
            'matrix' => $matrix,
            'computed_at' => now()->toISOString(),
        ];

        // 缓存短期
        Cache::put($cacheKey, $payload, 60);

        return response()->json(['success' => true, 'data' => $payload]);
    }

    /**
     * GET /api/v3/features/history
     * 返回特征排名的历史记录（占位实现，便于前端联调）。
     * 结构：[{ round_id, feature, predictions:[{symbol,predicted_rank}], results:[{symbol,actual_rank}], settled_at? }]
     */
    public function getFeatureHistory(Request $request)
    {
        $limit = (int)($request->query('limit', 100));
        $limit = max(1, min($limit, 500));

        // 占位：尝试从最近的已结算轮次生成伪造结构，若没有数据则返回空数组
        $rounds = DB::table('game_rounds')
            ->select('id', 'round_id', 'status', 'settled_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $history = [];
        foreach ($rounds as $round) {
            // 读取当轮的特征快照，如果不存在则跳过
            $rows = DB::table('feature_snapshots')
                ->select('token_symbol', 'feature_key', 'raw_value', 'normalized_value')
                ->where('game_round_id', $round->id)
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            // 简单规则：对每个特征按norm降序生成预测排名（前3）
            $byFeature = [];
            foreach ($rows as $r) {
                $byFeature[$r->feature_key][] = [
                    'symbol' => $r->token_symbol,
                    'norm' => $r->normalized_value,
                ];
            }

            foreach ($byFeature as $feature => $list) {
                usort($list, function ($a, $b) {
                    return ($b['norm'] <=> $a['norm']);
                });
                $pred = [];
                $rank = 1;
                foreach ($list as $item) {
                    if ($rank > 3) break; // 仅前三
                    $pred[] = [
                        'symbol' => $item['symbol'],
                        'predicted_rank' => $rank,
                    ];
                    $rank++;
                }

                // 读取当轮结果（RoundResult表）
                $results = DB::table('round_results')
                    ->select('symbol', 'rank as actual_rank')
                    ->where('game_round_id', $round->id)
                    ->get()
                    ->map(function ($r) {
                        return ['symbol' => $r->symbol, 'actual_rank' => (int)$r->actual_rank];
                    })
                    ->values()
                    ->all();

                $history[] = [
                    'round_id' => (string)$round->round_id,
                    'feature' => (string)$feature,
                    'predictions' => $pred,
                    'results' => $results,
                    'settled_at' => $round->settled_at,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $history,
            'message' => 'ok',
            'code' => 0,
        ]);
    }
    /**
     * POST /api/v3/predict/aggregate （可选，默认前端做聚合）
     */
    public function aggregate(Request $request)
    {
        $validated = $request->validate([
            'round_id' => ['nullable'],
            'weights' => ['required', 'array'],
            'rules' => ['nullable', 'array'],
        ]);

        $roundId = $validated['round_id'] ?? (Cache::get('game:current_round')['round_id'] ?? null);
        if (!$roundId) {
            return response()->json(['success' => false, 'message' => '当前轮次不可用'], 400);
        }

        // 读取矩阵（复用 getRoundFeatures 逻辑）
        $matrixResp = $this->getRoundFeatures($request, $roundId);
        $json = $matrixResp->getData(true);
        if (!($json['success'] ?? false)) {
            return $matrixResp;
        }
        $matrix = $json['data'];
        $weights = $validated['weights'];

        $scores = [];
        foreach ($matrix['tokens'] as $token) {
            $s = 0.0;
            foreach ($weights as $f => $w) {
                $v = $matrix['matrix'][$token][$f]['norm'] ?? null;
                if ($v !== null && $w != 0) {
                    $s += (float)$v * (float)$w;
                }
            }
            $scores[$token] = $s;
        }

        arsort($scores);
        $ranking = [];
        $rank = 1;
        foreach ($scores as $token => $score) {
            $ranking[] = ['token' => $token, 'score' => round($score, 6), 'rank' => $rank++];
        }

        // 贡献解释（按特征汇总）
        $contrib = [];
        foreach ($weights as $f => $w) {
            $contrib[$f] = (float)$w;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'round_id' => (string)$roundId,
                'ranking' => $ranking,
                'contrib_by_feature' => $contrib,
            ],
        ]);
    }
}


