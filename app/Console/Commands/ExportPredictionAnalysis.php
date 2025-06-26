<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

        // 獲取所有已結算且包含預測和結果的輪次
        $rounds = GameRound::whereNotNull('settled_at')
            ->whereHas('roundPredicts')
            ->whereHas('roundResults')
            ->with(['roundPredicts', 'roundResults'])
            ->orderBy('id', 'asc')
            ->get();

        if ($rounds->isEmpty()) {
            $this->warn('No completed rounds with predictions and results found.');
            return 1;
        }

        $this->info("Found {$rounds->count()} rounds to process.");

        $filePath = 'prediction_analysis.csv';
        // 使用 Storage Facade 來處理檔案，更安全
        Storage::disk('local')->put($filePath, ''); // 建立或清空檔案
        $fileHandle = fopen(Storage::disk('local')->path($filePath), 'w');

        // 定義 CSV 標頭
        $headers = [
            'round_id', 'settled_at', 'token_symbol',
            // 預測數據
            'predicted_rank', 'actual_rank', 'rank_difference', 'is_breakeven',
            // 演算法分數
            'predicted_final_value', 'risk_adjusted_score', 'market_momentum_score', 'rank_confidence',
            // 歷史數據
            'hist_total_games', 'hist_avg_rank', 'hist_win_rate', 'hist_top3_rate', 'hist_avg_value', 'hist_value_stddev',
            // 市場數據
            'market_price', 'market_change_5m', 'market_change_1h', 'market_change_4h', 'market_change_24h', 'market_volume_24h',
        ];
        fputcsv($fileHandle, $headers);

        $processedPredictions = 0;
        // 使用 chunk 避免記憶體溢出
        $rounds->chunk(100, function ($chunkedRounds) use ($fileHandle, &$processedPredictions) {
            foreach ($chunkedRounds as $round) {
                // 建立一個快速查找賽果的 map
                $resultsMap = $round->roundResults->keyBy('token_symbol');

                foreach ($round->roundPredicts as $prediction) {
                    $symbol = $prediction->token_symbol;
                    $actualResult = $resultsMap->get($symbol);

                    if (!$actualResult) {
                        continue; // 如果賽果中沒有該代幣，則跳過
                    }

                    // 從 details JSON 中提取詳細數據
                    $details = $prediction->details ?? [];
                    $historicalStats = $details['historical_stats'] ?? [];
                    $marketData = $details['market_data'] ?? [];

                    $rowData = [
                        $round->round_id,
                        $round->settled_at->toDateTimeString(),
                        $symbol,
                        // 預測與實際結果
                        $prediction->predict_rank,
                        $actualResult->rank,
                        abs($prediction->predict_rank - $actualResult->rank),
                        $actualResult->rank <= 3 ? '1' : '0', // 1 代表保本, 0 代表虧本
                        // 演算法核心分數
                        $prediction->predicted_final_value,
                        $prediction->risk_adjusted_score,
                        $details['market_momentum_score'] ?? null,
                        $details['rank_confidence'] ?? null,
                        // 歷史數據
                        $historicalStats['total_games'] ?? null,
                        $historicalStats['avg_rank'] ?? null,
                        $historicalStats['win_rate'] ?? null,
                        $historicalStats['top3_rate'] ?? null,
                        $historicalStats['avg_value'] ?? null,
                        $historicalStats['value_stddev'] ?? null,
                        // 市場數據
                        $marketData['price'] ?? null,
                        $marketData['change_5m'] ?? null,
                        $marketData['change_1h'] ?? null,
                        $marketData['change_4h'] ?? null,
                        $marketData['change_24h'] ?? null,
                        $marketData['volume_24h'] ?? null,
                    ];
                    fputcsv($fileHandle, $rowData);
                    $processedPredictions++;
                }
            }
        });

        fclose($fileHandle);

        $this->info("Export complete! {$processedPredictions} predictions have been saved.");
        $this->comment("File location: " . Storage::disk('local')->path($filePath));

        return 0;
    }
}
