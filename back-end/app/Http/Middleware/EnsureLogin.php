<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 登录拦截（对应需求文档 7 章：LoginInterceptor / /api/** 校验是否已登录）
 * 登录态由 Session 承载，session('uid') 为空即视为未登录（code=401）
 */
class EnsureLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $uid = session('uid');
        if (!$uid) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或会话已过期，请重新登录',
                'data' => null,
            ], 401);
        }

        $user = \App\Models\SysUser::find($uid);
        if (!$user || (int) $user->status === 0) {
            session()->forget('uid');
            return response()->json([
                'code' => 401,
                'message' => '账号不存在或已停用',
                'data' => null,
            ], 401);
        }

        $request->merge(['authUser' => $user]);
        app()->instance('authUser', $user);

        return $next($request);
    }
}
