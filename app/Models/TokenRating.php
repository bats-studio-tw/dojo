<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenRating extends Model
{
    use HasFactory;

    protected $primaryKey = 'symbol'; // 設定 primary key
    public $incrementing = false; // primary key 不是自增長整數
    protected $keyType = 'string'; // primary key 的類型是字串

    protected $fillable = [
        'symbol',
        'elo',
        'games',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'elo' => 'float',
        'games' => 'integer',
    ];

    /**
     * 模型属性默认值
     */
    protected $attributes = [
        'elo' => 1500,
        'games' => 0,
    ];

    /**
     * 获取 games 属性，确保不为 null
     */
    public function getGamesAttribute($value): int
    {
        return $value ?? 0;
    }
}
