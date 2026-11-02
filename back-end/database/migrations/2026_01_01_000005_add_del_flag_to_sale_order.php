<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用户端删除订单记录（F04 扩展）：沿用系统层 del_flag 口径做逻辑删除
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_order', function (Blueprint $table) {
            $table->tinyInteger('del_flag')->default(0)->after('status')
                ->comment('逻辑删除：0正常 1已删除（仅用户端视图隐藏，管理端保留可查）');
        });
    }

    public function down(): void
    {
        Schema::table('sale_order', function (Blueprint $table) {
            $table->dropColumn('del_flag');
        });
    }
};
