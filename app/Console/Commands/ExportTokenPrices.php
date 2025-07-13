<?php

namespace App\Console\Commands;

use App\Models\TokenPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportTokenPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:token-prices {--hours=24 : Number of hours to export (default: 24)} {--all : Export all available data regardless of time range}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export token price data for the specified number of hours into a CSV file.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');
        $exportAll = $this->option('all');

        if ($exportAll) {
            $this->info("Starting token price data export for all available data...");

            // 获取数据总数
            $totalRecords = TokenPrice::count();

            // 获取时间范围信息
            $firstRecord = TokenPrice::oldest('minute_timestamp')->first();
            $lastRecord = TokenPrice::latest('minute_timestamp')->first();

            if ($firstRecord && $lastRecord) {
                $startTime = Carbon::createFromTimestamp($firstRecord->minute_timestamp);
                $endTime = Carbon::createFromTimestamp($lastRecord->minute_timestamp);
            } else {
                $startTime = $endTime = Carbon::now();
            }
        } else {
            $this->info("Starting token price data export for the last {$hours} hours...");

            // 计算时间范围
            $endTime = Carbon::now();
            $startTime = $endTime->copy()->subHours($hours);

            // 转换为分钟时间戳
            $startTimestamp = (int) ($startTime->timestamp / 60) * 60;
            $endTimestamp = (int) ($endTime->timestamp / 60) * 60;

            // 获取数据总数
            $totalRecords = TokenPrice::whereBetween('minute_timestamp', [$startTimestamp, $endTimestamp])
                ->count();
        }

        if ($totalRecords === 0) {
            $this->warn("No token price data found for the specified time range.");

            return 1;
        }

        $this->info("Found {$totalRecords} price records to export.");

        // 生成文件名
        $datetime = now()->format('Ymd_His');
        $filePath = $exportAll ? "token_prices_all_{$datetime}.csv" : "token_prices_{$hours}h_{$datetime}.csv";

        // 使用 Storage Facade 来处理文件
        Storage::disk('local')->put($filePath, '');
        $fullPath = Storage::disk('local')->path($filePath);
        $fileHandle = fopen($fullPath, 'w');

        // 定义 CSV 头部
        $headers = [
            'symbol',
            'price_usd',
            'currency',
            'minute_timestamp',
            'datetime',
            'hour_timestamp',
        ];
        fputcsv($fileHandle, $headers);

        $processedRecords = 0;

        // 使用 chunk 分批处理数据
        $query = TokenPrice::orderBy('minute_timestamp', 'asc')
            ->orderBy('symbol', 'asc');

        if (! $exportAll) {
            $query->whereBetween('minute_timestamp', [$startTimestamp, $endTimestamp]);
        }

        $query->chunk(1000, function ($prices) use ($fileHandle, &$processedRecords) {
            foreach ($prices as $price) {
                $datetime = Carbon::createFromTimestamp($price->minute_timestamp);
                $hourTimestamp = (int) ($price->minute_timestamp / 3600) * 3600;

                $rowData = [
                    $price->symbol,
                    $price->price_usd,
                    $price->currency,
                    $price->minute_timestamp,
                    $datetime->toDateTimeString(),
                    $hourTimestamp,
                ];

                fputcsv($fileHandle, $rowData);
                $processedRecords++;
            }
        });

        fclose($fileHandle);

        $this->info("Export complete! {$processedRecords} price records have been saved.");
        $this->comment("File location: " . $fullPath);
        $this->comment("Time range: " . $startTime->toDateTimeString() . " to " . $endTime->toDateTimeString());

        return 0;
    }
}
