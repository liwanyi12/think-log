<?php

namespace Lwi\Thinklog;

use think\App;
use think\Log;

class Service
{
    /**
     * 注册服务
     */
    public static function register(App $app)
    {
        // 注册日志驱动
        Log::extend('async', function (array $config) {
            return new Driver($config);
        });

        // 合并配置文件
        $app->config->set(load_config(__DIR__ . '/../config/log_async.php'), 'log_async');
    }
}