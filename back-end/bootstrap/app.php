<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API 分组前置 Session：登录态由 Cookie 承载（对应需求文档第 7 章）
        $middleware->api(prepend: [
            StartSession::class,
        ]);

        $middleware->alias([
            'auth.session' => \App\Http\Middleware\EnsureLogin::class,
            'auth.role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 统一响应体 {code, message, data}：所有异常收口
        $exceptions->render(function (ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => 422,
                    'message' => $e->validator->errors()->first(),
                    'data' => null,
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => 401,
                    'message' => '未登录或会话已过期，请重新登录',
                    'data' => null,
                ], 401);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => 404,
                    'message' => '资源不存在',
                    'data' => null,
                ], 404);
            }
        });

        $exceptions->render(function (HttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => $e->getStatusCode(),
                    'message' => $e->getMessage() ?: '请求被拒绝',
                    'data' => null,
                ], $e->getStatusCode());
            }
        });

        $exceptions->dontReport(\App\Support\ApiException::class);
    })->create();
