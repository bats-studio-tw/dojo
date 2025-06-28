<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutoBettingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_address',
        'round_id',
        'token_symbol',
        'bet_amount',
        'bet_id',
        'success',
        'prediction_data',
        'result_data',
        'profit_loss',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'bet_amount' => 'decimal:2',
            'profit_loss' => 'decimal:2',
            'success' => 'boolean',
            'prediction_data' => 'array',
            'result_data' => 'array',
        ];
    }

    /**
     * 获取该记录关联的游戏轮次
     */
    public function gameRound()
    {
        return $this->belongsTo(GameRound::class, 'round_id', 'round_id');
    }

    /**
     * 根据钱包地址获取用户的下注统计
     */
    public static function getUserStats(string $walletAddress): array
    {
        $records = static::where('wallet_address', $walletAddress)->get();

        return [
            'total_bets' => $records->count(),
            'successful_bets' => $records->where('success', true)->count(),
            'total_amount' => $records->sum('bet_amount'),
            'total_profit_loss' => $records->sum('profit_loss'),
            'success_rate' => $records->count() > 0 ?
                ($records->where('success', true)->count() / $records->count() * 100) : 0,
        ];
    }

    /**
     * 获取用户今日的下注统计
     */
    public static function getTodayStats(string $walletAddress): array
    {
        $today = now()->startOfDay();
        $records = static::where('wallet_address', $walletAddress)
            ->where('created_at', '>=', $today)
            ->get();

        return [
            'today_bets' => $records->count(),
            'today_profit_loss' => $records->sum('profit_loss'),
            'today_amount' => $records->sum('bet_amount'),
        ];
    }
}
