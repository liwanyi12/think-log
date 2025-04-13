<?php

namespace Lwi\Thinklog;
/**
 * 异步模型日志追踪 (ThinkPHP 5.x 专用版)
 * @documentation
 * - 支持 TP5 的队列系统
 * - 自动兼容 TP5 的模型事件
 * - PHP 7.4+ 语法兼容
 */
trait AsyncModelLoggerTrait
{
    /**
     * 日志记录模式
     * @var string sync|async
     */
    protected $logMode = 'async';

    /**
     * 需要忽略记录的字段
     * @var array
     */
    protected $logExceptFields = ['update_time', 'update_at'];

    /**
     * 初始化配置
     */
    protected static function bootAsyncModelLoggerTrait()
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
     */
    protected function handleModelEvent(string $event)
    {
        try {
            $context = $this->buildLogContext($event);

            if ($this->shouldAsync()) {
                $this->dispatchToTp5Queue($event, $context);
            } else {
                $this->writeSyncLog($event, $context);
            }
        } catch (\Exception $e) {
            $this->emergencyLog($event, [], $e);
        }
    }


    /**
     * 判断是否异步
     */
    protected function shouldAsync()
    {
        if ($this->logMode === 'sync') {
            return false;
        }

        // 检查队列是否可用
        if (!class_exists('\\think\\Queue')) {
            return false;
        }

        return (bool)\liwanyi\thinklog\config('model_log.async', true);
    }

    /**
     * 投递到TP5队列
     * @param string $event
     * @param array $context
     * @return void
     */
    protected function dispatchToTp5Queue($event, array $context)
    {
        try {
            \think\Queue::push('app\\common\\job\\ModelLogJob', [
                'event' => $event,
                'context' => $context
            ], \liwanyi\thinklog\config('model_log.queue_name', 'default'));
        } catch (\Exception $e) {
            $this->writeSyncLog($event, $context);
            $this->logQueueError($e);
        }
    }

    /**
     * 同步写入日志
     */
    protected function writeSyncLog($event, $context)
    {
        try {
            \think\Log::write(
                $this->formatLogMessage($event),
                'info',
                '',
                $this->filterContext($context)
            );
        } catch (\Exception $e) {
            $this->emergencyLog($event, $context, $e);
        }
    }

    /**
     * 过滤敏感字段
     */
    protected function filterContext(array $context)
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
     * 紧急日志记录（当所有方式都失败时）
     */
    protected function emergencyLog($event, $context, \Exception $e)
    {
        $logContent = sprintf(
            "[%s] Model log failed: %s\nEvent: %s\nData: %s\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $event,
            json_encode($context, JSON_UNESCAPED_UNICODE)
        );

        file_put_contents(
            RUNTIME_PATH . 'log' . DIRECTORY_SEPARATOR . 'model_emergency.log',
            $logContent,
            FILE_APPEND
        );
    }

    /**
     * 队列错误记录
     */
    protected function logQueueError(\Exception $e)
    {
        \think\Log::write(
            "Async log dispatch failed: " . $e->getMessage(),
            'error'
        );
    }

    /**
     * 构建日志上下文
     */
    protected function buildLogContext($event)
    {
        return [
            'model' => get_class($this),
            'table' => $this->getTable(),
            'pk' => $this->getPk(),
            'id' => $this->getKey(),
            'event' => $event,
            'time' => time(),
            'changes' => $this->getChangedData(),
            'ip' => \Liwanyi\Utils2\models\trait\request()->ip()
        ];
    }

    /**
     * 格式化日志消息
     */
    protected function formatLogMessage($event)
    {
        return sprintf(
            '[%s] %s %s(id:%s)',
            strtoupper($event),
            basename(str_replace('\\', '/', get_class($this))),
            $this->getTable(),
            $this->getKey()
        );
    }
}