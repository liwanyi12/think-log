<?php

namespace Lwi\Thinklog;

use think\facade\App;
use RuntimeException;

class Installer
{

    public static function postInstall()
    {
        // 在 postInstall() 开头添加调试代码
        echo "[ThinkLog] Starting installation...\n";
        if (!self::isThinkPHPInstalled()) {
            return;
        }
        try {
            $runtimePath = self::getThinkRuntimePath();
            self::ensureLogDirectory($runtimePath);
            self::publishConfig();
            self::ensureJobDirectory();
        } catch (\Exception $e) {
            // 记录错误日志或抛出
            error_log('ThinkLog install failed: ' . $e->getMessage());
        }
    }

    protected static function isThinkPHPInstalled(): bool
    {
        return class_exists('think\App') || class_exists('think\facade\App');
    }

    protected static function getThinkRuntimePath(): string
    {
        // TP6/8 优先检测
        if (class_exists('think\facade\App')) {
            return \think\facade\App::getRuntimePath();
        }

        // TP5 处理
        if (class_exists('think\App')) {
            if (method_exists('think\App', 'getRuntimePath')) {
                // 某些TP5版本可能有静态方法
                return \think\App::getRuntimePath();
            }
            return (new \think\App())->getRuntimePath();
        }

        throw new \RuntimeException('ThinkPHP runtime path resolver not available');
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
        var_dump($jobDir); // 检查路径是否正确

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