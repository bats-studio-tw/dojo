<?php

namespace App\Jobs;

use App\Contracts\Prediction\FeatureProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExtractFeaturesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $roundId,
        public array $symbols
    ) {
        $this->symbols = array_values(array_unique(array_map('strtoupper', $this->symbols)));
    }

    public $tries = 2;
    public $backoff = [5, 15];

    /** @param FeatureProviderInterface[] $providers */
    public function handle(array $providers = []): void
    {
        $roundId = $this->roundId;
        $symbols = $this->symbols;

        $start = microtime(true);
        try {
            if (empty($providers)) {
                // 从配置解析 Provider 列表
                $providerClasses = (array) (config('prediction_v3.providers') ?? []);
                foreach ($providerClasses as $cls) {
                    try {
                        $instance = app($cls);
                        if ($instance instanceof FeatureProviderInterface) {
                            $providers[] = $instance;
                        }
                    } catch (\Throwable $e) {
                        // 忽略单个provider解析失败
                    }
                }
            }
            // 并行/顺序执行 Provider（此处顺序，后续可改并行 pool）
            $snapshots = [];
            foreach ($providers as $provider) {
                if (!$provider instanceof FeatureProviderInterface) {
                    continue;
                }
                $key = method_exists($provider, 'getKey') ? $provider->getKey() : null;
                $result = $provider->extractFeatures(array_fill_keys($symbols, []), []);
                if (!$result) continue;
                foreach ($result as $symbol => $payload) {
                    $snapshots[] = [
                        'game_round_id' => $roundId,
                        'token_symbol' => strtoupper($symbol),
                        'feature_key' => $key,
                        'raw_value' => $payload['raw'] ?? null,
                        'normalized_value' => $payload['norm'] ?? ($payload['raw'] ?? null),
                        'meta' => json_encode($payload['meta'] ?? []),
                        'computed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($snapshots) {
                DB::table('feature_snapshots')->upsert(
                    $snapshots,
                    ['game_round_id', 'token_symbol', 'feature_key'],
                    ['raw_value', 'normalized_value', 'meta', 'computed_at', 'updated_at']
                );
            }

            // 组装缓存矩阵
            $tokens = [];
            $features = [];
            $matrix = [];
            foreach ($snapshots as $r) {
                $tokens[$r['token_symbol']] = true;
                $features[$r['feature_key']] = true;
                $matrix[$r['token_symbol']][$r['feature_key']] = [
                    'raw' => $r['raw_value'],
                    'norm' => $r['normalized_value'],
                ];
            }

            $payload = [
                'round_id' => (string)$roundId,
                'tokens' => array_keys($tokens),
                'features' => array_keys($features),
                'matrix' => $matrix,
                'computed_at' => now()->toISOString(),
            ];
            Cache::put("feature_matrix:{$roundId}", $payload, (int) (config('prediction_v3.cache_ttl', 60)));

            \Log::info('features extracted', [
                'round_id' => $roundId,
                'tokens' => count($tokens),
                'features' => count($features),
                'ms' => (int)((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            \Log::error('extract features failed', [
                'round_id' => $roundId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}


