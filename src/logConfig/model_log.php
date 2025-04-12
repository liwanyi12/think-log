<?php

namespace Liwanyi\Utils2\models\logConfig;

return [
    /*
     * 是否启用异步日志
     */
    'async' => true,

    /*
     * 使用的队列名称
     */
    'queue_name' => 'default',

    /*
     * 日志通道（如果使用TP5.1+的日志通道）
     */
    'log_channel' => 'model',

    /*
     * 日志存储驱动 file|database
     */
    'driver' => 'file',

    /*
     * 数据库驱动配置
     */
    'database' => [
        'table' => 'model_logs',
    ],
];