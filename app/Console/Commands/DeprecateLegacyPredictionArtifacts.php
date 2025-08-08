<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeprecateLegacyPredictionArtifacts extends Command
{
    protected $signature = 'prediction:deprecate-legacy {--dry-run}';
    protected $description = '标记/清理旧版预测相关表与数据（保留只读对照所需最小集）';

    public function handle(): int
    {
        $dry = (bool)$this->option('dry-run');

        $tables = [
            'prediction_results',
            'hybrid_round_predicts',
            'strategy_predictions',
        ];

        foreach ($tables as $t) {
            $exists = DB::select("SHOW TABLES LIKE ?", [$t]);
            if (!$exists) {
                $this->line("[skip] table not found: {$t}");
                continue;
            }

            if ($dry) {
                $this->line("[dry-run] would archive/clean: {$t}");
                continue;
            }

            try {
                // 简单做法：添加只读标记表（若需要），此处先清理无用索引或过期数据
                // 也可根据需求将数据转存到 *_archive 表
                $this->line("[clean] no-op for {$t} (manual archive recommended)");
            } catch (\Throwable $e) {
                Log::error('deprecate legacy table error', ['table' => $t, 'error' => $e->getMessage()]);
                $this->error("error cleaning {$t}: {$e->getMessage()}");
            }
        }

        $this->info('Legacy prediction artifacts deprecation done.');
        return self::SUCCESS;
    }
}


