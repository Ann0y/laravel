<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 登录用户表（需求文档 6.3.1）：管理员 / 票务员 / 游客统一账号
 * 会话表（sessions）保留，Session 登录态存数据库
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('users');

        Schema::create('sys_user', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('username', 50)->unique('uk_username');
            $table->string('password', 100)->comment('BCrypt 密文');
            $table->string('name', 50)->comment('显示姓名');
            $table->string('role', 20)->default('visitor')->comment('admin/operator/visitor');
            $table->tinyInteger('status')->default(1)->comment('1 启用 / 0 停用');
            $table->timestamp('create_time')->useCurrent();
            $table->tinyInteger('del_flag')->default(0)->comment('逻辑删除 0 正常 / 1 删除');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sys_user');
    }
};
