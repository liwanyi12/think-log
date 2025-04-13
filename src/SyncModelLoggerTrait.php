<?php

namespace Lwi\Thinklog;

trait SyncModelLoggerTrait
{
    /**
     * 需要忽略记录的字段
     * @var array
     */
    protected $logExceptFields = ['update_time', 'update_at'];

    /**
     * 初始化模型事件监听
     */
    protected static function bootSyncModelLoggerTrait()
    {
        static::afterWrite(function ($model) {
            $model->handleModelEvent($model->isUpdate() ? 'updated' : 'created');
        });

        static::afterDelete(function ($model) {
            $model->handleModelEvent('deleted');
        });

        static::afterRestore(function ($model) {
            $model->handleModelEvent('restored');
        });
    }

    /**
     * 处理模型事件
     * @param string $event 事件名称
     */
    protected function handleModelEvent(string $event)
    {
        try {
            $context = $this->buildLogContext($event);
            $this->writeSyncLog($event, $context);
        } catch (\Exception $e) {
            $this->emergencyLog($event, [], $e);
        }
    }

    /**
     * 同步写入日志
     * @param string $event 事件名称
     * @param array $context 日志上下文
     */
    protected function writeSyncLog(string $event, array $context)
    {
        try {
            // 获取当前日志通道
            $channel = config('log_async.channel') ?? 'model';

            \think\Log::channel($channel)->write(
                $this->formatLogMessage($event),
                'info',
                $this->filterContext($context)
            );
        } catch (\Exception $e) {
            $this->emergencyLog($event, $context, $e);
        }
    }

    /**
     * 过滤敏感字段
     * @param array $context 原始上下文
     * @return array 过滤后的上下文
     */
    protected function filterContext(array $context): array
    {
        if (!empty($this->logExceptFields)) {
            $context['changes'] = array_diff_key(
                $context['changes'] ?? [],
                array_flip($this->logExceptFields)
            );
        }
        return $context;
    }

    /**
     * 紧急日志记录（当主日志记录失败时）
     * @param string $event 事件名称
     * @param array $context 日志上下文
     * @param \Exception $e 异常对象
     */
    protected function emergencyLog(string $event, array $context, \Exception $e)
    {
        try {
            $logContent = sprintf(
                "[%s] Model log failed: %s\nEvent: %s\nData: %s\nTrace:\n%s\n",
                date('Y-m-d H:i:s'),
                $e->getMessage(),
                $event,
                json_encode($context, JSON_UNESCAPED_UNICODE),
                $e->getTraceAsString()
            );

            $logPath = $this->getRuntimePath() . 'model_emergency.log';

            file_put_contents(
                $logPath,
                $logContent,
                FILE_APPEND
            );
        } catch (\Exception $fallbackException) {
            // 终极回退方案：错误日志
            error_log(sprintf(
                "Emergency log failed: %s\nOriginal error: %s",
                $fallbackException->getMessage(),
                $e->getMessage()
            ));
        }
    }

    /**
     * 构建日志上下文
     * @param string $event 事件名称
     * @return array 日志上下文
     */
    protected function buildLogContext(string $event): array
    {
        return [
            'model'  => get_class($this),
            'table'  => $this->getTable(),
            'pk'     => $this->getPk(),
            'id'     => $this->getKey(),
            'event'  => $event,
            'time'   => time(),
            'changes'=> $this->getChangedData(),
            'ip'     => request()->ip()
        ];
    }

    /**
     * 格式化日志消息
     * @param string $event 事件名称
     * @return string 格式化后的日志消息
     */
    protected function formatLogMessage(string $event): string
    {
        return sprintf(
            '[%s] %s %s(id:%s)',
            strtoupper($event),
            class_basename($this),
            $this->getTable(),
            $this->getKey()
        );
    }

    /**
     * 获取运行时路径（兼容多版本）
     * @return string
     */
    protected function getRuntimePath(): string
    {
        if (function_exists('runtime_path')) {
            return runtime_path();
        }

        if (defined('RUNTIME_PATH')) {
            return RUNTIME_PATH;
        }

        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR;
    }
}