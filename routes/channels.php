<?php

use Illuminate\Support\Facades\Broadcast;

// 用户私人频道
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// 游戏数据更新频道 - 公开频道
Broadcast::channel('game-updates', function () {
    return true; // 允许所有用户监听游戏数据更新
});

// 预测数据频道 - 公开频道
Broadcast::channel('predictions', function () {
    return true; // 允许所有用户监听预测数据
});

// Hybrid预测数据频道 - 公开频道
Broadcast::channel('hybrid-predictions', function () {
    return true; // 允许所有用户监听Hybrid预测数据
});

// 用户通知私人频道
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return $user->id === (int) $userId;
});
