日志记录



config/log 下日志记录
  'json' => [
            'type' => 'file',
            'path' => runtime_path('logs/json'),
            'json' => true, // 自动转为JSON格式
            'format' => '[{timestamp}] {message} {context}'
        ]


         public function index()
    {
        try {
            // 业务逻辑
            $user = $this->validateLogin();
            return json(['user' => $user]);
        } catch (\Exception $e) {
            return $this->handleApiError($e);
        }
    }

    private function validateLogin()
    {
        // 模拟验证失败
        throw new \Exception('密码错误');
    }
