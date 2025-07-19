<?php

namespace App\Services\Prediction;

use App\Contracts\Prediction\MarketDataProviderInterface;
use App\Contracts\Prediction\NormalizationStrategyInterface;
use App\Services\AlchemyPriceService;
use App\Services\DexPriceClient;
use App\Services\EloRatingEngine;
use Illuminate\Support\Facades\App;

class PredictionServiceFactory
{
    /**
     * 创建预测服务实例
     *
     * @param  string  $strategy  策略名称
     */
    public static function create(string $strategy = 'default'): PredictionService
    {
        $config = config('prediction');

        // 如果策略不存在，使用默认策略
        if (! isset($config['strategies'][$strategy])) {
            $strategy = $config['default_strategy'] ?? 'conservative';
        }

        $strategyConfig = $config['strategies'][$strategy];

        // 创建数据提供者
        $dataProvider = self::createDataProvider($config['default_data_provider']);

        // 创建特征提供者
        $featureProviders = self::createFeatureProviders($config['features']);

        // 创建标准化策略
        $normalizationConfig = $strategyConfig['feature_normalization'] ?? $strategyConfig['normalization'] ?? [];
        $normalizationStrategies = self::createNormalizationStrategies($normalizationConfig);

        // 创建分数聚合器
        $aggregator = new ScoreAggregator(
            $strategyConfig['weights'],
            $normalizationStrategies
        );

        return new PredictionService(
            $dataProvider,
            $featureProviders,
            $aggregator,
            $strategy
        );
    }

    /**
     * 创建数据提供者
     */
    private static function createDataProvider(string $providerClass): MarketDataProviderInterface
    {
        return match ($providerClass) {
            \App\Services\Prediction\Providers\DexScreenerProvider::class => new \App\Services\Prediction\Providers\DexScreenerProvider(
                App::make(DexPriceClient::class)
            ),
            \App\Services\Prediction\Providers\AlchemyProvider::class => new \App\Services\Prediction\Providers\AlchemyProvider(
                App::make(AlchemyPriceService::class)
            ),
            default => throw new \InvalidArgumentException("Unknown data provider: {$providerClass}")
        };
    }

    /**
     * 创建特征提供者
     */
    private static function createFeatureProviders(array $features): array
    {
        $providers = [];

        if ($features['elo'] ?? false) {
            $providers['elo'] = new \App\Services\Prediction\Features\EloFeatureProvider(
                App::make(EloRatingEngine::class)
            );
        }

        if ($features['momentum'] ?? false) {
            $providers['momentum'] = new \App\Services\Prediction\Features\MomentumFeatureProvider;
        }

        if ($features['volume'] ?? false) {
            $providers['volume'] = new \App\Services\Prediction\Features\VolumeFeatureProvider;
        }

        return $providers;
    }

    /**
     * 创建标准化策略
     */
    private static function createNormalizationStrategies(array $normalizationConfig): array
    {
        $strategies = [];
        $config = config('prediction');

        foreach ($normalizationConfig as $feature => $strategyName) {
            $strategyClass = $config['normalization_strategies'][$strategyName] ?? null;
            if ($strategyClass) {
                $strategies[$feature] = self::createNormalizationStrategy($strategyClass);
            }
        }

        return $strategies;
    }

    /**
     * 创建单个标准化策略
     */
    private static function createNormalizationStrategy(string $strategyClass): NormalizationStrategyInterface
    {
        return match ($strategyClass) {
            \App\Services\Prediction\Normalization\ZScoreNormalization::class => new \App\Services\Prediction\Normalization\ZScoreNormalization,
            \App\Services\Prediction\Normalization\MinMaxNormalization::class => new \App\Services\Prediction\Normalization\MinMaxNormalization,
            \App\Services\Prediction\Normalization\IdentityNormalization::class => new \App\Services\Prediction\Normalization\IdentityNormalization,
            default => throw new \InvalidArgumentException("Unknown normalization strategy: {$strategyClass}")
        };
    }

    /**
     * 创建自定义配置的预测服务
     *
     * @param  array  $customConfig  自定义配置
     */
    public static function createWithCustomConfig(array $customConfig): PredictionService
    {
        $config = config('prediction');

        // 合并自定义配置
        $mergedConfig = array_merge_recursive($config, $customConfig);

        // 创建数据提供者
        $dataProviderClass = $customConfig['data_provider'] ?? $config['default_data_provider'];
        $dataProvider = self::createDataProvider($dataProviderClass);

        // 创建特征提供者
        $features = $customConfig['features'] ?? $config['features'];
        $featureProviders = self::createFeatureProviders($features);

        // 创建标准化策略
        $normalizationConfig = $customConfig['normalization'] ?? [];
        $normalizationStrategies = self::createNormalizationStrategies($normalizationConfig);

        // 创建分数聚合器
        $weights = $customConfig['weights'] ?? [];
        $aggregator = new ScoreAggregator($weights, $normalizationStrategies);

        $strategyTag = $customConfig['strategy_tag'] ?? 'custom';

        return new PredictionService(
            $dataProvider,
            $featureProviders,
            $aggregator,
            $strategyTag
        );
    }

    /**
     * 获取可用策略列表
     */
    public static function getAvailableStrategies(): array
    {
        $config = config('prediction');

        return array_keys($config['strategies'] ?? []);
    }

    /**
     * 验证策略配置
     */
    public static function validateStrategy(string $strategy): bool
    {
        $config = config('prediction');

        return isset($config['strategies'][$strategy]);
    }
}
