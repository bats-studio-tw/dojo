<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HybridRoundPredict extends Model
{
    use HasFactory;

    protected $table = 'hybrid_round_predicts'; // 明確指定資料表名稱

    protected $fillable = [
        'game_round_id',
        'token_symbol',
        'predicted_rank',
        'final_score',
        'elo_prob',
        'mom_score',
        'confidence',
    ];

    // 定义与 GameRound 的关联
    public function gameRound(): BelongsTo
    {
        return $this->belongsTo(GameRound::class, 'game_round_id');
    }
}
