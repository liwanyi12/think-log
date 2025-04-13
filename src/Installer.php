<?php

namespace Lwi\Thinklog;

use think\facade\App;
use RuntimeException;

class Installer
{
    /**
     * Post-installation setup for ThinkPHP environment
     *
     * @throws RuntimeException When critical operations fail
     */
    public static function postInstall()
    {
        if (!class_exists(App::class)) {
            return; // Skip if not in ThinkPHP environment
        }

        try {
            self::ensureLogDirectory();
            self::publishConfig();
            self::ensureJobDirectory();
        } catch (RuntimeException $e) {
            // Log error or handle as needed
            throw new RuntimeException("ThinkLog installation failed: " . $e->getMessage());
        }
    }

    /**
     * Ensure log directory exists with proper permissions
     *
     * @throws RuntimeException When directory creation fails
     */
    protected static function ensureLogDirectory()
    {
        $logDir = App::getRuntimePath() . 'api_errors';

        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                throw new RuntimeException("Failed to create log directory: {$logDir}");
            }

            // Add .gitignore to prevent accidental log commits
            $gitignorePath = $logDir.'/.gitignore';
            if (file_put_contents($gitignorePath, "*\n!.gitignore") === false) {
                throw new RuntimeException("Failed to create .gitignore in log directory");
            }
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
            $source = __DIR__.'/../config/log_async.php';

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
            $gitignorePath = $jobDir.'/.gitignore';
            if (file_put_contents($gitignorePath, "*\n!.gitignore") === false) {
                throw new RuntimeException("Failed to create .gitignore in job directory");
            }
        }
    }
}