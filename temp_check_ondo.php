<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 查询所有ONDO记录
$ondoResults = App\Models\RoundResult::where('token_symbol', 'ONDO')->get();

echo "ONDO记录总数: " . $ondoResults->count() . "\n\n";

if ($ondoResults->count() > 0) {
    echo "最新的ONDO记录:\n";
    foreach ($ondoResults as $result) {
        echo "ID: {$result->id}, 游戏轮次ID: {$result->game_round_id}, 排名: {$result->rank}, 价值: {$result->value}\n";
    }
} else {
    echo "没有找到任何ONDO记录!\n";
}

// 查询最新的游戏轮次
echo "\n最新的游戏轮次:\n";
$latestRound = App\Models\GameRound::orderBy('id', 'desc')->first();
if ($latestRound) {
    echo "最新轮次ID: {$latestRound->id}, 轮次标识: {$latestRound->round_id}\n";

    // 查询这个轮次的所有结果
    $resultsInLatestRound = App\Models\RoundResult::where('game_round_id', $latestRound->id)->get();
    echo "该轮次的所有代币结果:\n";
    foreach ($resultsInLatestRound as $result) {
        echo "  {$result->token_symbol}: 排名{$result->rank}, 价值{$result->value}\n";
    }
}

// 特别查询日志中提到的result_id 90
echo "\n查询result_id 90:\n";
$result90 = App\Models\RoundResult::find(90);
if ($result90) {
    echo "找到结果: {$result90->token_symbol}, 排名{$result90->rank}, 价值{$result90->value}\n";
} else {
    echo "没有找到ID为90的记录\n";
}
