<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 角色校验：auth.role:admin 或 auth.role:admin,operator
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = app('authUser');
        if (!in_array($user->role, $roles, true)) {
            return response()->json([
                'code' => 403,
                'message' => '没有权限执行该操作',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
