<?php

return [
    // 是否启用异步日志
    'async' => env('model_log.async', true),

    // 使用的队列名称
    'queue_name' => env('model_log.queue_name', 'default'),

    // 日志通道
    'channel' => 'model',

    // 存储驱动 file|database
    'driver' => env('model_log.driver', 'file'),

    // 数据库配置
    'database' => [
        'connection' => env('model_log.db_connection'),
        'table' => 'model_logs',
    ],

    // 全局忽略字段
    'except_fields' => [
        'update_time',
        'update_at',
        'delete_time'
    ],
];