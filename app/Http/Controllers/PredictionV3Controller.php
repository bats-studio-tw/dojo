<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // 读取快照并拼装矩阵
        $rows = DB::table('feature_snapshots')
            ->select('token_symbol', 'feature_key', 'raw_value', 'normalized_value')
            ->where('game_round_id', $roundId)
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


