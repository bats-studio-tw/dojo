<?php

namespace Database\Seeders;

use App\Models\GameRound;
use App\Models\RoundResult;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class GameDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 清空现有数据（考虑外键约束）
        RoundResult::query()->delete();
        GameRound::query()->delete();

        // 生成测试代币列表
        $tokens = ['BTC', 'ETH', 'BNB', 'SOL', 'ADA'];

        // 生成50局历史数据
        for ($i = 1; $i <= 50; $i++) {
            $round = GameRound::create([
                'round_id' => 'RD' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'settled_at' => Carbon::now()->subMinutes(rand(10, 7200)), // 随机过去时间
                'created_at' => Carbon::now()->subMinutes(rand(10, 7200)),
                'updated_at' => Carbon::now()->subMinutes(rand(10, 7200)),
            ]);

            // 为这一局生成随机排名
            $shuffledTokens = collect($tokens)->shuffle();

            foreach ($shuffledTokens as $index => $token) {
                RoundResult::create([
                    'game_round_id' => $round->id,
                    'token_symbol' => $token,
                    'rank' => $index + 1,
                    'value' => number_format(rand(100, 100000) / 100, 4, '.', ''), // 随机价格
                ]);
            }
        }

        $this->command->info('已生成 50 局游戏数据，每局包含 5 个代币');
    }
}
