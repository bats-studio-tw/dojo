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
}
