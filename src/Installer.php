<?php

namespace Lwi\Thinklog;

class Installer
{


    public static function postInstall()
    {
        // 创建日志目录
        $logDir = LOG_PATH . 'api_errors';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // 生成默认配置文件（如果不存在）
        $configFile = config_path() . 'log_async.php';
        if (!file_exists($configFile)) {
            copy(__DIR__ . '/../config/log_async.php', $configFile);
        }

        // 创建队列任务目录
        $jobDir = app_path() . 'common/job';
        if (!is_dir($jobDir)) {
            mkdir($jobDir, 0755, true);
            file_put_contents($jobDir . '/.gitkeep', '');
        }
    }
}