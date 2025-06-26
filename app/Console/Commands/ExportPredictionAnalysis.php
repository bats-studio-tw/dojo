<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\GameRound;

class ExportPredictionAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analysis:export-predictions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all prediction and result data into a CSV file for analysis.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting prediction data export...');

        // 先获取总数，避免一次性加载过多数据
        $totalRounds = GameRound::whereNotNull('settled_at')
            ->whereHas('roundPredicts')
            ->whereHas('roundResults')
            ->count();

        if ($totalRounds === 0) {
            $this->warn('No completed rounds with predictions and results found.');
            return 1;
        }

        $this->info("Found {$totalRounds} rounds to process.");
        $datetime = now()->format('Ymd_His');
        $filePath = 'prediction_analysis_v7_' . $datetime . '.csv';
        // 使用 Storage Facade 來處理檔案，更安全
        Storage::disk('local')->put($filePath, ''); // 建立或清空檔案
        $fullPath = Storage::disk('local')->path($filePath);
        $fileHandle = fopen($fullPath, 'w');

        // 定義 CSV 標頭
        $headers = [
            'round_id', 'settled_at', 'token_symbol',
            // 預測數據
            'predicted_rank', 'actual_rank', 'rank_difference', 'is_breakeven',
            // 預測分數
            'prediction_score',
            // 歷史數據 (來自prediction_data JSON)
            'hist_total_games', 'hist_avg_rank', 'hist_win_rate', 'hist_top3_rate', 'hist_avg_value', 'hist_value_stddev',
            // 市場數據 (來自prediction_data JSON)
            'market_price', 'market_change_5m', 'market_change_1h', 'market_change_4h', 'market_change_24h', 'market_volume_24h',
            // 其他分析數據
            'predicted_final_value', 'risk_adjusted_score', 'market_momentum_score', 'rank_confidence',
        ];
        fputcsv($fileHandle, $headers);

        $processedPredictions = 0;

        // 使用 Query Builder 的 chunk，每次處理100筆
        GameRound::whereNotNull('settled_at')
            ->whereHas('roundPredicts')
            ->whereHas('roundResults')
            ->with(['roundPredicts', 'roundResults'])
            ->orderBy('id', 'asc')
            ->chunk(100, function ($rounds) use ($fileHandle, &$processedPredictions) {
                foreach ($rounds as $round) {
                    // 建立一個快速查找賽果的 map
                    $resultsMap = $round->roundResults->keyBy('token_symbol');

                    foreach ($round->roundPredicts as $prediction) {
                        $symbol = $prediction->token_symbol;
                        $actualResult = $resultsMap->get($symbol);

                        if (!$actualResult) {
                            continue; // 如果賽果中沒有該代幣，則跳過
                        }

                        // 從 prediction_data JSON 中提取詳細數據
                        $predictionData = $prediction->prediction_data ?? [];

                        $rowData = [
                            $round->round_id,
                            $round->settled_at->toDateTimeString(),
                            $symbol,
                            // 預測與實際結果 - 修正字段名稱
                            $prediction->predicted_rank,
                            $actualResult->rank,
                            abs($prediction->predicted_rank - $actualResult->rank),
                            $actualResult->rank <= 3 ? '1' : '0', // 1 代表保本, 0 代表虧本
                            // 預測分數
                            $prediction->prediction_score,
                            // 歷史數據 - 直接從prediction_data根級別提取
                            $predictionData['total_games'] ?? null,
                            $predictionData['avg_rank'] ?? null,
                            $predictionData['win_rate'] ?? null,
                            $predictionData['top3_rate'] ?? null,
                            $predictionData['avg_value'] ?? null,
                            $predictionData['value_stddev'] ?? null,
                            // 市場數據 - 直接從prediction_data根級別提取
                            $predictionData['price'] ?? null,
                            $predictionData['change_5m'] ?? null,
                            $predictionData['change_1h'] ?? null,
                            $predictionData['change_4h'] ?? null,
                            $predictionData['change_24h'] ?? null,
                            $predictionData['volume_24h'] ?? null,
                            // 其他分析數據 - 直接從prediction_data根級別提取
                            $predictionData['predicted_final_value'] ?? null,
                            $predictionData['risk_adjusted_score'] ?? null,
                            $predictionData['market_momentum_score'] ?? null,
                            $predictionData['rank_confidence'] ?? null,
                        ];
                        fputcsv($fileHandle, $rowData);
                        $processedPredictions++;
                    }
                }
            });

        fclose($fileHandle);

        $this->info("Export complete! {$processedPredictions} predictions have been saved.");
        $this->comment("File location: " . $fullPath);

        return 0;
    }
}
