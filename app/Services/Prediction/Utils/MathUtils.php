<?php

namespace App\Services\Prediction\Utils;

/**
 * 数学工具类
 * 提供统计和数学计算功能
 */
class MathUtils
{
    /**
     * 计算标准差
     */
    public function standardDeviation(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / count($values);
        $sumSquaredDifferences = 0;

        foreach ($values as $value) {
            $sumSquaredDifferences += pow($value - $mean, 2);
        }

        $variance = $sumSquaredDifferences / (count($values) - 1);
        return sqrt($variance);
    }

    /**
     * 计算平均值
     */
    public function mean(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * 计算线性回归斜率
     *
     * @param array $x X轴数据（时间）
     * @param array $y Y轴数据（价格）
     * @return float 斜率
     */
    public function linearRegressionSlope(array $x, array $y): float
    {
        $n = count($x);

        if ($n < 2 || count($y) !== $n) {
            return 0.0;
        }

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumXX += $x[$i] * $x[$i];
        }

        // 斜率计算公式: (n*∑xy - ∑x*∑y) / (n*∑x² - (∑x)²)
        $denominator = $n * $sumXX - $sumX * $sumX;

        if ($denominator == 0) {
            return 0.0;
        }

        return ($n * $sumXY - $sumX * $sumY) / $denominator;
    }

    /**
     * 计算线性回归截距
     */
    public function linearRegressionIntercept(array $x, array $y): float
    {
        $n = count($x);

        if ($n < 2 || count($y) !== $n) {
            return 0.0;
        }

        $meanX = $this->mean($x);
        $meanY = $this->mean($y);
        $slope = $this->linearRegressionSlope($x, $y);

        // 截距 = 平均Y - 斜率 * 平均X
        return $meanY - $slope * $meanX;
    }

    /**
     * 计算相关系数
     */
    public function correlation(array $x, array $y): float
    {
        $n = count($x);

        if ($n < 2 || count($y) !== $n) {
            return 0.0;
        }

        $meanX = $this->mean($x);
        $meanY = $this->mean($y);

        $numerator = 0;
        $sumXSquared = 0;
        $sumYSquared = 0;

        for ($i = 0; $i < $n; $i++) {
            $xDiff = $x[$i] - $meanX;
            $yDiff = $y[$i] - $meanY;

            $numerator += $xDiff * $yDiff;
            $sumXSquared += $xDiff * $xDiff;
            $sumYSquared += $yDiff * $yDiff;
        }

        $denominator = sqrt($sumXSquared * $sumYSquared);

        if ($denominator == 0) {
            return 0.0;
        }

        return $numerator / $denominator;
    }

    /**
     * 计算百分位数
     */
    public function percentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);

        if ($index == floor($index)) {
            return $values[(int)$index];
        }

        $lower = $values[(int)floor($index)];
        $upper = $values[(int)ceil($index)];
        $fraction = $index - floor($index);

        return $lower + ($upper - $lower) * $fraction;
    }

    /**
     * 计算移动平均
     */
    public function movingAverage(array $values, int $window): array
    {
        if ($window <= 0 || $window > count($values)) {
            return [];
        }

        $result = [];

        for ($i = $window - 1; $i < count($values); $i++) {
            $sum = 0;
            for ($j = $i - $window + 1; $j <= $i; $j++) {
                $sum += $values[$j];
            }
            $result[] = $sum / $window;
        }

        return $result;
    }

    /**
     * 计算指数移动平均
     */
    public function exponentialMovingAverage(array $values, float $alpha): array
    {
        if (empty($values) || $alpha <= 0 || $alpha > 1) {
            return [];
        }

        $result = [$values[0]]; // 第一个值作为初始值

        for ($i = 1; $i < count($values); $i++) {
            $ema = $alpha * $values[$i] + (1 - $alpha) * $result[$i - 1];
            $result[] = $ema;
        }

        return $result;
    }

    /**
     * 计算 RSI（相对强弱指数）
     */
    public function rsi(array $prices, int $period = 14): array
    {
        if (count($prices) < $period + 1) {
            return [];
        }

        $gains = [];
        $losses = [];

        // 计算每日收益和损失
        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i - 1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }

        $result = [];

        // 计算初始平均收益和损失
        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        for ($i = $period; $i < count($gains); $i++) {
            if ($avgLoss == 0) {
                $result[] = 100;
            } else {
                $rs = $avgGain / $avgLoss;
                $rsi = 100 - (100 / (1 + $rs));
                $result[] = $rsi;
            }

            // 更新平均收益和损失（指数移动平均）
            $avgGain = (($avgGain * ($period - 1)) + $gains[$i]) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $losses[$i]) / $period;
        }

        return $result;
    }

    /**
     * 正规化数组到指定范围
     */
    public function normalize(array $values, float $min = 0, float $max = 1): array
    {
        if (empty($values)) {
            return [];
        }

        $valueMin = min($values);
        $valueMax = max($values);

        if ($valueMax == $valueMin) {
            return array_fill(0, count($values), ($min + $max) / 2);
        }

        $range = $valueMax - $valueMin;
        $targetRange = $max - $min;

        return array_map(function ($value) use ($valueMin, $range, $min, $targetRange) {
            return $min + (($value - $valueMin) / $range) * $targetRange;
        }, $values);
    }
}
