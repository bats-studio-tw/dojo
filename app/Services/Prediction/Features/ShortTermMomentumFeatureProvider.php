<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Models\TokenPrice;
use App\Services\Prediction\Utils\MathUtils;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 短期动能特征提供者
 * 使用数据库中每分钟价格数据计算短期动能指标
 */
class ShortTermMomentumFeatureProvider implements FeatureProviderInterface
{
    private MathUtils $mathUtils;

    // 配置参数
    private int $shortTermMinutes = 2;    // 短期分析窗口（2分钟）
    private int $mediumTermMinutes = 5;   // 中期分析窗口（5分钟）
    private int $longTermMinutes = 15;    // 长期分析窗口（15分钟）

    public function __construct(MathUtils $mathUtils = null)
    {
        $this->mathUtils = $mathUtils ?? new MathUtils();
    }

    public function extractFeatures(array $snapshots, array $history): array
    {
        $scores = [];

        foreach ($snapshots as $snapshot) {
            // 处理数组和对象两种格式
            if (is_array($snapshot)) {
                $symbol = $snapshot['symbol'] ?? '';
            } else {
                $symbol = $snapshot->symbol ?? '';
            }

            if (empty($symbol)) {
                continue;
            }

            try {
                // 计算短期动能分数
                $momentumScore = $this->calculateShortTermMomentum($symbol);
                $scores[$symbol] = $momentumScore;

                Log::debug("Short-term momentum calculated for {$symbol}: {$momentumScore}");
            } catch (\Exception $e) {
                Log::warning("Failed to calculate short-term momentum for {$symbol}: " . $e->getMessage());
                $scores[$symbol] = 50.0; // 中性默认值
            }
        }

        return $scores;
    }

    /**
     * 计算短期动能分数
     */
    private function calculateShortTermMomentum(string $symbol): float
    {
        // 获取不同时间窗口的价格数据
        $shortTermData = $this->getPriceData($symbol, $this->shortTermMinutes);
        $mediumTermData = $this->getPriceData($symbol, $this->mediumTermMinutes);
        $longTermData = $this->getPriceData($symbol, $this->longTermMinutes);

        // 如果数据不足，返回中性分数
        if (count($shortTermData) < 2) {
            return 50.0;
        }

        // 计算各种动能指标
        $shortTermReturn = $this->calculateReturn($shortTermData);
        $shortTermVolatility = $this->calculateVolatility($shortTermData);
        $shortTermTrend = $this->calculateTrend($shortTermData);

        // 计算中期指标（如果数据充足）
        $mediumTermReturn = count($mediumTermData) >= 2 ?
            $this->calculateReturn($mediumTermData) : $shortTermReturn;
        $mediumTermTrend = count($mediumTermData) >= 3 ?
            $this->calculateTrend($mediumTermData) : $shortTermTrend;

        // 计算长期指标（如果数据充足）
        $longTermReturn = count($longTermData) >= 2 ?
            $this->calculateReturn($longTermData) : $mediumTermReturn;

        // 权重配置
        $weights = [
            'short_return' => 0.4,      // 短期收益率权重
            'medium_return' => 0.2,     // 中期收益率权重
            'long_return' => 0.1,       // 长期收益率权重
            'short_trend' => 0.2,       // 短期趋势权重
            'medium_trend' => 0.1,      // 中期趋势权重
        ];

        // 计算加权动能分数
        $momentumScore =
            $this->returnToScore($shortTermReturn) * $weights['short_return'] +
            $this->returnToScore($mediumTermReturn) * $weights['medium_return'] +
            $this->returnToScore($longTermReturn) * $weights['long_return'] +
            $this->trendToScore($shortTermTrend) * $weights['short_trend'] +
            $this->trendToScore($mediumTermTrend) * $weights['medium_trend'];

        // 波动率调整（高波动率降低置信度）
        $volatilityAdjustment = $this->calculateVolatilityAdjustment($shortTermVolatility);
        $adjustedScore = $momentumScore + $volatilityAdjustment;

        // 确保分数在0-100范围内
        return max(0, min(100, $adjustedScore));
    }

    /**
     * 获取价格数据
     */
    private function getPriceData(string $symbol, int $minutes): array
    {
        $endTime = Carbon::now()->startOfMinute()->timestamp;
        $startTime = $endTime - ($minutes * 60);

        return TokenPrice::where('symbol', $symbol)
            ->where('minute_timestamp', '>=', $startTime)
            ->where('minute_timestamp', '<=', $endTime)
            ->orderBy('minute_timestamp', 'desc')
            ->pluck('price_usd')
            ->map(fn($price) => (float) $price)
            ->values()
            ->toArray();
    }

    /**
     * 计算收益率
     */
    private function calculateReturn(array $prices): float
    {
        if (count($prices) < 2) {
            return 0.0;
        }

        $latestPrice = $prices[0];
        $earliestPrice = $prices[count($prices) - 1];

        if ($earliestPrice == 0) {
            return 0.0;
        }

        return ($latestPrice - $earliestPrice) / $earliestPrice;
    }

    /**
     * 计算波动率（标准差）
     */
    private function calculateVolatility(array $prices): float
    {
        return $this->mathUtils->standardDeviation($prices);
    }

    /**
     * 计算趋势（线性回归斜率）
     */
    private function calculateTrend(array $prices): float
    {
        if (count($prices) < 3) {
            return 0.0;
        }

        // 时间序列，最新的价格对应最大的时间值
        $times = range(0, count($prices) - 1);
        $prices = array_reverse($prices); // 确保时间序列正确

        return $this->mathUtils->linearRegressionSlope($times, $prices);
    }

    /**
     * 将收益率转换为分数 (0-100)
     */
    private function returnToScore(float $return): float
    {
        // 将收益率映射为分数
        // +5% = 75分，-5% = 25分，0% = 50分
        $score = 50 + ($return * 500);
        return max(0, min(100, $score));
    }

    /**
     * 将趋势转换为分数 (0-100)
     */
    private function trendToScore(float $slope): float
    {
        // 将斜率映射为分数
        // 正斜率 = 高分，负斜率 = 低分
        $normalizedSlope = $slope * 1000; // 放大斜率值
        $score = 50 + $normalizedSlope;
        return max(0, min(100, $score));
    }

    /**
     * 计算波动率调整
     */
    private function calculateVolatilityAdjustment(float $volatility): float
    {
        // 高波动率时，向中性分数(50)拉动
        $volatilityThreshold = 0.05; // 5%波动率阈值

        if ($volatility > $volatilityThreshold) {
            // 波动率越高，调整幅度越大，但限制最大调整幅度
            $adjustmentFactor = min(0.2, ($volatility - $volatilityThreshold) * 2);
            return -$adjustmentFactor * 10; // 向下调整，降低极端分数
        }

        return 0.0;
    }

    /**
     * 获取配置参数
     */
    public function getConfig(): array
    {
        return [
            'short_term_minutes' => $this->shortTermMinutes,
            'medium_term_minutes' => $this->mediumTermMinutes,
            'long_term_minutes' => $this->longTermMinutes,
        ];
    }

    /**
     * 设置配置参数
     */
    public function setConfig(array $config): void
    {
        if (isset($config['short_term_minutes'])) {
            $this->shortTermMinutes = $config['short_term_minutes'];
        }
        if (isset($config['medium_term_minutes'])) {
            $this->mediumTermMinutes = $config['medium_term_minutes'];
        }
        if (isset($config['long_term_minutes'])) {
            $this->longTermMinutes = $config['long_term_minutes'];
        }
    }
}
