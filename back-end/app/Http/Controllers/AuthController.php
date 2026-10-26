<?php

namespace App\Http\Controllers;

use App\Models\ScenicArea;
use App\Models\TicketStock;
use App\Models\OpenDay;
use App\Models\TicketType;
use App\Support\ApiException;
use App\Support\Formatter;
use App\Support\TicketingService;
use Illuminate\Http\Request;

use function App\Support\ok;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|max:50|unique:sys_user,username',
            'password' => 'required|string|min:6|max:100',
            'name' => 'required|string|max:50',
        ], self::messages());

        $user = \App\Models\SysUser::create([
            'username' => $data['username'],
            'password' => bcrypt($data['password']),
            'name' => $data['name'],
            'role' => 'visitor',
        ]);

        \App\Models\Visitor::create(['user_id' => $user->id, 'status' => '正常']);

        session(['uid' => $user->id]);
        session()->save();

        return ok(self::userPayload($user), '注册成功');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|max:50',
            'password' => 'required|string|max:100',
        ], self::messages());

        $user = \App\Models\SysUser::where('username', $data['username'])->where('del_flag', 0)->first();
        if (!$user || !password_verify($data['password'], $user->password)) {
            throw new ApiException('用户名或密码不正确', 401);
        }
        if ((int) $user->status === 0) {
            throw new ApiException('账号已停用，请联系管理员', 403);
        }

        session(['uid' => $user->id]);
        session()->save();

        return ok(self::userPayload($user), '登录成功');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('uid');
        $request->session()->save();
        return ok(null, '已退出登录');
    }

    public function me()
    {
        return ok(self::userPayload(app('authUser')));
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'oldPassword' => 'required|string',
            'newPassword' => 'required|string|min:6|max:100',
        ], self::messages());

        $user = app('authUser');
        if (!password_verify($data['oldPassword'], $user->password)) {
            throw new ApiException('原密码不正确');
        }

        $user->password = bcrypt($data['newPassword']);
        $user->save();

        return ok(null, '密码已更新');
    }

    public static function userPayload(\App\Models\SysUser $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role,
        ];
    }

    public static function messages(): array
    {
        return ['required' => ':attribute 为必填项', 'unique' => '该用户名已被注册', 'min' => ':attribute 至少 :min 位'];
    }
}
