<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'price_usd',
        'currency',
        'minute_timestamp'
    ];

    protected $casts = [
        'price_usd' => 'decimal:8',
        'minute_timestamp' => 'integer'
    ];

    /**
     * 获取当前分钟时间戳
     */
    public static function getCurrentMinuteTimestamp(): int
    {
        return (int) (time() / 60) * 60;
    }

    /**
     * 获取唯一键约束
     */
    public static function getUniqueKeys(): array
    {
        return ['symbol', 'minute_timestamp'];
    }
}
