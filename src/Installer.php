<?php

namespace Lwi\Thinklog;

use think\App;
use RuntimeException;

class Installer
{
    public static function postInstall()
    {
        try {
            if (!self::isThinkPHPInstalled()) {
                throw new \RuntimeException("ThinkPHP 5.x not detected");
            }

            $runtimePath = self::getThinkRuntimePath();
            self::ensureLogDirectory($runtimePath);
            self::publishConfig();
            self::ensureJobDirectory();

        } catch (\Exception $e) {
            file_put_contents(
                getcwd().'/runtime/thinklog_install_error.log',
                date('Y-m-d H:i:s')." - ".$e->getMessage()."\n",
                FILE_APPEND
            );
            throw $e;
        }
    }

    /**
     * 检测TP5环境
     */
    protected static function isThinkPHPInstalled(): bool
    {
        return class_exists('think\App') && defined('THINK_VERSION');
    }

    /**
     * 获取TP5运行时路径
     */
    protected static function getThinkRuntimePath(): string
    {
        if (!self::isThinkPHPInstalled()) {
            throw new RuntimeException('ThinkPHP 5.x not initialized');
        }

        // TP5专用路径获取方式
        $app = new App();
        return $app->getRuntimePath();
    }

    /**
     * 确保日志目录存在
     */
    protected static function ensureLogDirectory(string $runtimePath)
    {
        $logDir = $runtimePath . 'api_errors';
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                throw new RuntimeException("Failed to create log directory: {$logDir}");
            }
            file_put_contents($logDir.'/.gitignore', "*\n!.gitignore");
        }
    }

    /**
     * 发布配置文件
     */
    protected static function publishConfig()
    {
        // TP5专用配置路径
        $target = self::getThinkConfigPath() . 'log_async.php';
        $source = __DIR__ . '/../config/log_async.php';

        if (!file_exists($target) && file_exists($source)) {
            if (!copy($source, $target)) {
                throw new RuntimeException("Failed to publish config to: {$target}");
            }
        }
    }

    /**
     * 确保任务目录存在
     */
    protected static function ensureJobDirectory()
    {
        // TP5专用应用路径
        $jobDir = self::getThinkAppPath() . 'common/job';

        if (!is_dir($jobDir)) {
            if (!mkdir($jobDir, 0755, true)) {
                throw new RuntimeException("Failed to create job directory: {$jobDir}");
            }
            file_put_contents($jobDir.'/.gitignore', "*\n!.gitignore");
        }
    }

    /**
     * 获取TP5配置目录
     */
    protected static function getThinkConfigPath(): string
    {
        return self::getThinkAppPath() . 'config/';
    }

    /**
     * 获取TP5应用目录
     */
    protected static function getThinkAppPath(): string
    {
        // 默认TP5应用目录结构
        $possiblePaths = [
            getcwd().'/application/',
            getcwd().'/app/',
            dirname(__DIR__, 3).'/application/' // vendor内使用时
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        throw new RuntimeException('Cannot locate ThinkPHP 5 application directory');
    }
}