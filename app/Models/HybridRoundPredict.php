<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    // 如果需要，定義與 GameRound 的關聯
    // public function gameRound()
    // {
    //     return $this->belongsTo(GameRound::class, 'game_round_id');
    // }
}
