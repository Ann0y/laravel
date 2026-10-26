<?php

namespace App\Support;

use RuntimeException;

/**
 * 光标：业务异常 + 统一响应体助手 {code, message, data}（需求文档第 7 章）
 */

/**
 * 业务异常：Service 层用于表达库存不足、状态非法等口径错误，
 * 全局异常处理器会转换为 {code, message, ...}
 */
class ApiException extends RuntimeException
{
    public function __construct(string $message, int $code = 400)
    {
        parent::__construct($message, $code);
    }

    public function render($request)
    {
        return response()->json([
            'code' => $this->code,
            'message' => $this->getMessage(),
            'data' => null,
        ], $this->code);
    }
}

if (!function_exists('App\\Support\\ok')) {
    function ok(mixed $data = null, string $message = 'success'): \Illuminate\Http\JsonResponse
    {
        return response()->json(['code' => 200, 'message' => $message, 'data' => $data]);
    }
}
