<?php

namespace lwi\thinklog;

use think\Exception;
use think\Response;
use think\facade\Log;
use think\facade\Request;
use Throwable;

trait ApiErrorLoggerTrait
{
    // region 配置参数
    protected $logSensitiveFields = [
        'password', 'token', 'credit_card'
    ];

    protected $httpStatusMap = [
        'think\exception\ValidateException' => 422,
        'think\exception\AuthException' => 401,
        'think\exception\HttpException' => 404
    ];
    // endregion

    // region 核心方法
    public function handleApiError(Throwable $e): Response
    {
        $requestId = $this->generateAndStoreRequestId();
        $statusCode = $this->determineHttpStatusCode($e);
        $response = $this->buildStandardResponse($e, $statusCode, $requestId);
        $this->logApiError($e, $response);
        return $response;
    }

    public function logApiError(Throwable $e, Response $response): void
    {
        try {
            $context = $this->buildLogContext($e, $response);
            $this->writeLog($context);
        } catch (Throwable $logException) {
            $this->emergencyLog($context ?? [], $logException);
        }
    }

    protected function generateAndStoreRequestId(): string
    {
        $requestId = md5(uniqid(microtime(true), true));
        Request::instance()->requestId = $requestId; // 关键：存储到请求对象
        return $requestId;
    }

    // region 上下文构建
    protected function buildLogContext(Throwable $e, Response $response): array
    {
        return [
            'request_id' => Request::instance()->requestId ?? 'null',
            'request' => $this->getRequestInfo(),
            'error' => $this->getErrorDetails($e),
            'response' => $this->getResponseInfo($response),
            'system' => $this->getSystemInfo(),
            'timestamp' => microtime(true)
        ];
    }

    protected function getRequestInfo(): array
    {
        return [
            'method' => Request::method(),
            'url' => Request::url(),
            'ip' => Request::ip(),
            'headers' => $this->filterHeaders(Request::header()),
            'params' => $this->filterParams(Request::param())
        ];
    }

    protected function getErrorDetails(Throwable $e): array
    {
        return [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $this->formatTrace($e->getTrace())
        ];
    }

    protected function getResponseInfo(Response $response): array
    {
        return [
            'status' => $response->getCode(),
            'data' => $this->formatResponseData($response),
            'headers' => $response->getHeader()
        ];
    }

    protected function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'framework' => 'ThinkPHP ' . $this->getFrameworkVersion(),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
        ];
    }
    // endregion

    // region 数据处理
    protected function filterParams(array $params): array
    {
        foreach ($this->logSensitiveFields as $field) {
            if (isset($params[$field])) {
                $params[$field] = '******';
            }
        }
        return $params;
    }

    protected function filterHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie'];
        return array_map(function ($value, $key) use ($sensitiveHeaders) {
            return in_array(strtolower($key), $sensitiveHeaders) ? '******' : $value;
        }, $headers, array_keys($headers));
    }

    protected function formatTrace(array $trace): array
    {
        return array_map(function ($item) {
            return [
                'file' => $item['file'] ?? '',
                'line' => $item['line'] ?? 0,
                'function' => $item['function'] ?? '',
                'class' => $item['class'] ?? ''
            ];
        }, $trace);
    }

    protected function formatResponseData(Response $response)
    {
        $content = $response->getContent();

        if ($this->isValidJson($content)) {
            $data = json_decode($content, true);
            return $this->filterResponseData($data);
        }

        return is_string($content)
            ? mb_substr($content, 0, 500)
            : $content;
    }

    protected function filterResponseData($data)
    {
        if (!is_array($data)) return $data;

        foreach ($this->logSensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '​**​​**​​**​';
            }
        }
        return $data;
    }
    // endregion

    // region 日志处理
    protected function writeLog(array $context): void
    {
        Log::error($this->formatLogMessage($context), $context);
    }

    protected function formatLogMessage(array $context): string
    {
        return sprintf(
            '[API ERROR][%s] %s %s - %s (Status:%d)',
            $context['request_id'],
            $context['request']['method'],
            $context['request']['url'],
            $context['error']['message'],
            $context['response']['status']
        );
    }

    protected function emergencyLog(array $context, Throwable $e): void
    {
        try {
            $logContent = json_encode([
                'time' => date('Y-m-d H:i:s'),
                'context' => $context,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            file_put_contents(
                $this->getRuntimePath() . 'api_emergency.log',
                $logContent . PHP_EOL,
                FILE_APPEND
            );
        } catch (Throwable $finalError) {
            error_log('Critical logging failure: ' . $finalError->getMessage());
        }
    }
    // endregion

    // region 响应构建
    protected function buildStandardResponse(Throwable $e, int $statusCode, string $requestId): Response
    {
        return $this->createJsonResponse(
            $this->buildResponseBody($e, $statusCode, $requestId),
            $statusCode
        );
    }

    protected function buildResponseBody(Throwable $e, int $statusCode, string $requestId): array
    {
        return [
            'code' => $e->getCode(),
            'message' => $this->getClientMessage($e, $statusCode),
            'request_id' => $requestId,
            'timestamp' => time(),
            'data' => null
        ];
    }


    protected function createJsonResponse(array $data, int $status): Response
    {
        // TP6.0.0+ 参数结构
        if ($this->isThinkPHP6()) {
            return new \think\response\Json(
                $data,
                $status,
                [],
                $this->getJsonOptions()
            );
        }
        // TP5.1-5.2 参数结构
        return Response::create($data, 'json', $status);
    }

    protected function getJsonOptions(): int
    {
        try {
            return Config::get('app.json_encode_options', JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return JSON_UNESCAPED_UNICODE;
        }
    }

    protected function isThinkPHP6(): bool
    {
        return class_exists('\think\App') &&
            method_exists('\think\App', 'getThinkPath') &&
            !method_exists('\think\Response', 'create');
    }


    protected function isValidJson(string $str): bool
    {
        if (trim($str) === '') return false;

        json_decode($str);
        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function getRuntimePath(): string
    {
        return app()->getRuntimePath();
    }

    protected function determineHttpStatusCode(Throwable $e): int
    {
        foreach ($this->httpStatusMap as $class => $code) {
            if ($e instanceof $class) {
                return $code;
            }
        }

        $code = $e->getCode();
        return ($code >= 400 && $code < 600) ? $code : 500;
    }

    protected function getClientMessage(Throwable $e, int $statusCode): string
    {
        if (app()->isDebug()) {
            return $e->getMessage();
        }

        $defaultMessages = [
            400 => '请求参数错误',
            401 => '未授权访问',
            403 => '禁止访问',
            404 => '资源不存在',
            500 => '服务器内部错误'
        ];

        return $defaultMessages[$statusCode] ?? '服务不可用';
    }

    protected function getFrameworkVersion(): string
    {
        return defined('\think\App::VERSION')
            ? \think\App::VERSION
            : (method_exists(app(), 'version') ? app()->version() : '5.x');
    }
}