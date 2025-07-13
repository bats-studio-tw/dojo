<?php

namespace App\Console\Commands;

use App\Models\GameRound;
use App\Models\RoundResult;
use App\Models\TokenRating;
use App\Services\EloRatingEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BatchUpdateEloCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elo:batch-update
                            {--limit=5000 : 处理的轮次数量限制}
                            {--reset : 重置所有代币的Elo评分到1500}
                            {--dry-run : 只显示将要进行的操作，不实际执行}
                            {--from-date= : 从指定日期开始处理 (格式: Y-m-d)}
                            {--to-date= : 处理到指定日期结束 (格式: Y-m-d)}
                            {--mode=all : 胜负关系模式 (first-vs-all, all-vs-all, top3-vs-all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '批量更新代币的Elo评分，基于历史游戏数据';

    protected EloRatingEngine $eloEngine;

    public function __construct(EloRatingEngine $eloEngine)
    {
        parent::__construct();
        $this->eloEngine = $eloEngine;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $reset = $this->option('reset');
        $dryRun = $this->option('dry-run');
        $fromDate = $this->option('from-date');
        $toDate = $this->option('to-date');
        $mode = $this->option('mode');

        $this->info("🚀 开始批量更新Elo评分");
        $this->info("处理轮次限制: {$limit}");
        $this->info("重置模式: " . ($reset ? '是' : '否'));
        $this->info("试运行模式: " . ($dryRun ? '是' : '否'));
        $this->info("胜负关系模式: {$mode}");

        if ($fromDate) {
            $this->info("开始日期: {$fromDate}");
        }
        if ($toDate) {
            $this->info("结束日期: {$toDate}");
        }

        try {
            // 步骤1: 如果需要重置，先重置所有代币评分
            if ($reset) {
                $this->resetAllTokenRatings($dryRun);
            }

            // 步骤2: 获取历史游戏数据
            $rounds = $this->getHistoricalRounds($limit, $fromDate, $toDate);

            if ($rounds->isEmpty()) {
                $this->warn("⚠️ 没有找到符合条件的游戏轮次数据");

                return;
            }

            $this->info("📊 找到 {$rounds->count()} 个游戏轮次");

            // 步骤3: 按时间顺序处理每个轮次
            $this->processRounds($rounds, $dryRun, $mode);

            // 步骤4: 显示最终结果
            $this->displayFinalResults($dryRun);

        } catch (\Exception $e) {
            $this->error("❌ 批量更新失败: " . $e->getMessage());
            Log::error('批量更新Elo评分失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        $this->info("✅ 批量更新Elo评分完成");

        return 0;
    }

    /**
     * 重置所有代币的Elo评分
     */
    private function resetAllTokenRatings(bool $dryRun): void
    {
        $this->info("🔄 重置所有代币的Elo评分...");

        if ($dryRun) {
            $this->line("  [试运行] 将重置所有代币评分到1500");

            return;
        }

        // 获取所有代币
        $tokens = RoundResult::distinct()->pluck('token_symbol')->unique();

        foreach ($tokens as $symbol) {
            $this->eloEngine->resetElo($symbol);
        }

        $this->info("✅ 已重置 {$tokens->count()} 个代币的评分");
    }

    /**
     * 获取历史游戏轮次数据
     */
    private function getHistoricalRounds(int $limit, ?string $fromDate, ?string $toDate)
    {
        $query = GameRound::with(['roundResults' => function ($query) {
            $query->orderBy('rank');
        }])
        ->whereHas('roundResults')
        ->settled()
        ->orderBy('settled_at', 'asc'); // 按时间顺序处理

        if ($fromDate) {
            $query->where('settled_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('settled_at', '<=', $toDate . ' 23:59:59');
        }

        return $query->limit($limit)->get();
    }

    /**
     * 处理游戏轮次数据
     */
    private function processRounds($rounds, bool $dryRun, string $mode): void
    {
        $progressBar = $this->output->createProgressBar($rounds->count());
        $progressBar->start();

        $processedCount = 0;
        $errorCount = 0;
        $totalMatches = 0;

        foreach ($rounds as $round) {
            try {
                $matches = $this->processSingleRound($round, $dryRun, $mode);
                $totalMatches += $matches;
                $processedCount++;
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('处理轮次失败', [
                    'round_id' => $round->round_id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("📈 处理完成: {$processedCount} 个轮次成功, {$errorCount} 个轮次失败");
        $this->info("🏆 总共处理了 {$totalMatches} 场对决");
    }

    /**
     * 处理单个游戏轮次
     */
    private function processSingleRound(GameRound $round, bool $dryRun, string $mode): int
    {
        $results = $round->roundResults->sortBy('rank');

        if ($results->count() < 2) {
            return 0; // 至少需要2个代币才能进行Elo计算
        }

        $matches = 0;

        switch ($mode) {
            case 'first-vs-all':
                // 第一名击败所有其他代币
                $winner = $results->first()->token_symbol;
                $losers = $results->slice(1)->pluck('token_symbol')->toArray();

                if ($dryRun) {
                    $this->line("  [试运行] 轮次 {$round->round_id}: {$winner} 击败 " . implode(', ', $losers));
                } else {
                    foreach ($losers as $loser) {
                        $this->eloEngine->updateElo($winner, $loser);
                        $matches++;
                    }
                }

                break;

            case 'top3-vs-all':
                // 前三名分别击败所有排名更低的代币
                $top3 = $results->take(3);
                $others = $results->slice(3);

                if ($dryRun) {
                    $top3Symbols = $top3->pluck('token_symbol')->toArray();
                    $otherSymbols = $others->pluck('token_symbol')->toArray();
                    $this->line("  [试运行] 轮次 {$round->round_id}: 前三名 " . implode(', ', $top3Symbols) . " 击败 " . implode(', ', $otherSymbols));
                } else {
                    foreach ($top3 as $winner) {
                        foreach ($others as $loser) {
                            $this->eloEngine->updateElo($winner->token_symbol, $loser->token_symbol);
                            $matches++;
                        }
                    }
                }

                break;

            case 'all-vs-all':
            default:
                // 所有排名之间的对决：排名高的击败排名低的
                $resultsArray = $results->toArray();

                if ($dryRun) {
                    $this->line("  [试运行] 轮次 {$round->round_id}: 所有排名对决");
                } else {
                    for ($i = 0; $i < count($resultsArray); $i++) {
                        for ($j = $i + 1; $j < count($resultsArray); $j++) {
                            $winner = $resultsArray[$i]['token_symbol'];
                            $loser = $resultsArray[$j]['token_symbol'];
                            $this->eloEngine->updateElo($winner, $loser);
                            $matches++;
                        }
                    }
                }

                break;
        }

        return $matches;
    }

    /**
     * 显示最终结果
     */
    private function displayFinalResults(bool $dryRun): void
    {
        if ($dryRun) {
            $this->info("📋 试运行完成，未实际更新数据库");

            return;
        }

        $this->info("📊 最终Elo评分结果:");

        // 获取所有代币的当前评分
        $ratings = TokenRating::orderBy('elo', 'desc')->get();

        if ($ratings->isEmpty()) {
            $this->warn("⚠️ 没有找到代币评分数据");

            return;
        }

        $headers = ['排名', '代币', 'Elo评分', '游戏次数'];
        $rows = [];

        foreach ($ratings as $index => $rating) {
            $rows[] = [
                $index + 1,
                $rating->symbol,
                round($rating->elo, 2),
                $rating->games,
            ];
        }

        $this->table($headers, $rows);

        // 显示统计信息
        $this->info("📈 统计信息:");
        $this->line("  总代币数量: " . $ratings->count());
        $this->line("  最高评分: " . round($ratings->max('elo'), 2));
        $this->line("  最低评分: " . round($ratings->min('elo'), 2));
        $this->line("  平均评分: " . round($ratings->avg('elo'), 2));
        $this->line("  总游戏次数: " . $ratings->sum('games'));
    }
}
