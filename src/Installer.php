<?php

namespace Lwi\Thinklog;

use think\facade\App;
use RuntimeException;

class Installer
{
    public static function postInstall()
    {
        if (!self::isThinkPHPInstalled()) {
            return;
        }

        // 获取运行时路径（兼容TP5和TP6/8）
        $runtimePath = self::getThinkRuntimePath();

        self::ensureLogDirectory($runtimePath);
        self::publishConfig();
        self::ensureJobDirectory();
    }

    protected static function isThinkPHPInstalled(): bool
    {
        // 同时检测 TP5 和 TP6/8 的入口类
        return class_exists('think\App') || class_exists('think\facade\App');
    }

    protected static function getThinkRuntimePath(): string
    {
        // TP5 和 TP6/8 兼容的路径获取方式
        if (class_exists('think\App')) {
            return \think\App::getRuntimePath();
        }

        if (class_exists('think\facade\App')) {
            return \think\facade\App::getRuntimePath();
        }

        throw new \RuntimeException('ThinkPHP runtime path not resolvable');
    }

    protected static function ensureLogDirectory(string $runtimePath)
    {
        $logDir = $runtimePath . 'api_errors';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
            file_put_contents($logDir . '/.gitignore', "*\n!.gitignore");
        }
    }

    /**
     * Publish package configuration if not exists
     *
     * @throws RuntimeException When config file operations fail
     */
    protected static function publishConfig()
    {
        $target = config_path() . 'log_async.php';

        if (!file_exists($target)) {
            $source = __DIR__ . '/../config/log_async.php';

            if (!file_exists($source)) {
                throw new RuntimeException("Missing package config template: {$source}");
            }

            if (!copy($source, $target)) {
                throw new RuntimeException("Failed to copy config file to: {$target}");
            }
        }
    }

    /**
     * Ensure job directory exists for queue workers
     *
     * @throws RuntimeException When directory creation fails
     */
    protected static function ensureJobDirectory()
    {
        $jobDir = app_path() . 'common/job';

        if (!is_dir($jobDir)) {
            if (!mkdir($jobDir, 0755, true) && !is_dir($jobDir)) {
                throw new RuntimeException("Failed to create job directory: {$jobDir}");
            }

            // Add .gitignore to maintain empty directory
            $gitignorePath = $jobDir . '/.gitignore';
            if (file_put_contents($gitignorePath, "*\n!.gitignore") === false) {
                throw new RuntimeException("Failed to create .gitignore in job directory");
            }
        }
    }
}