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
        $limit = max(1, min($limit, 1000));

        // 最近已结算轮次
        $rounds = DB::table('game_rounds')
            ->select('id', 'round_id', 'settled_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($rounds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'ok',
                'code' => 0,
            ]);
        }

        // features 过滤（可选）
        $featuresFilter = $request->query('features');
        $featureAllowSet = null;
        if (is_array($featuresFilter) && count($featuresFilter) > 0) {
            $featureAllowSet = array_fill_keys(array_map('strval', $featuresFilter), true);
        }

        // 收集本次涉及的 round 主键
        $roundIdMap = [];
        $roundDbIds = [];
        foreach ($rounds as $r) {
            $roundIdMap[$r->id] = [
                'round_id' => (string) $r->round_id,
                'settled_at' => $r->settled_at,
            ];
            $roundDbIds[] = $r->id;
        }

        // 批量读取快照与结果
        $snapshotRows = DB::table('feature_snapshots')
            ->select('game_round_id', 'token_symbol', 'feature_key', 'normalized_value')
            ->whereIn('game_round_id', $roundDbIds)
            ->get();

        $resultRows = DB::table('round_results')
            ->select('game_round_id', DB::raw('token_symbol as symbol'), 'rank as actual_rank')
            ->whereIn('game_round_id', $roundDbIds)
            ->get();

        // 结果分组：round_id -> [ {symbol, actual_rank} ]
        $resultsByRound = [];
        foreach ($resultRows as $row) {
            $resultsByRound[$row->game_round_id][] = [
                'symbol' => $row->symbol,
                'actual_rank' => (int) $row->actual_rank,
            ];
        }

        // 快照分组：round_id -> feature -> list({symbol, norm})
        $featuresByRound = [];
        foreach ($snapshotRows as $row) {
            $f = (string) $row->feature_key;
            if ($featureAllowSet !== null && !isset($featureAllowSet[$f])) {
                continue; // 按需过滤特征，减少计算/传输
            }
            $featuresByRound[$row->game_round_id][$f][] = [
                'symbol' => $row->token_symbol,
                'norm' => (float) $row->normalized_value,
            ];
        }

        // 组装输出（按轮次降序）
        $history = [];
        foreach ($rounds as $round) {
            $rid = $round->id;
            $byFeature = $featuresByRound[$rid] ?? [];
            if (empty($byFeature)) {
                continue; // 该轮无特征快照，跳过
            }

            $features = [];
            foreach ($byFeature as $feature => $list) {
                usort($list, function ($a, $b) { return ($b['norm'] <=> $a['norm']); });
                $pred = [];
                $rank = 1;
                foreach ($list as $item) {
                    if ($rank > 3) break;
                    $pred[] = [ 'symbol' => $item['symbol'], 'predicted_rank' => $rank ];
                    $rank++;
                }
                $features[(string) $feature] = $pred;
            }

            $history[] = [
                'round_id' => $roundIdMap[$rid]['round_id'],
                'settled_at' => $roundIdMap[$rid]['settled_at'],
                'results' => array_values($resultsByRound[$rid] ?? []),
                'features' => $features,
            ];
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


