<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 主数据四张 + 额度：需求文档 6.3.2–6.3.7
 */
return new class extends Migration
{
    public function up(): void
    {
        // 6.3.2 游客档案
        Schema::create('visitor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique('uk_user')->constrained('sys_user')->cascadeOnDelete();
            $table->string('id_card', 64)->nullable()->unique('uk_id_card')->comment('AES 密文，展示脱敏');
            $table->string('phone', 20)->nullable();
            $table->string('status', 20)->default('正常')->index('idx_status');
            $table->timestamp('create_time')->useCurrent();
            $table->tinyInteger('del_flag')->default(0);
        });

        // 6.3.3 票务员档案
        Schema::create('operator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique('uk_user')->constrained('sys_user')->cascadeOnDelete();
            $table->string('code', 20)->unique('uk_code')->comment('票务工号');
            $table->string('window_no', 20)->nullable()->comment('常用窗口编号');
            $table->string('phone', 20)->nullable();
            $table->string('status', 10)->default('在职');
            $table->timestamp('create_time')->useCurrent();
            $table->tinyInteger('del_flag')->default(0);
        });

        // 6.3.4 景区信息
        Schema::create('scenic_area', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('description', 500)->nullable();
            $table->string('address', 100)->nullable();
            $table->string('open_time', 50)->nullable();
            $table->string('notice', 255)->nullable();
            $table->integer('default_capacity')->default(5000);
            $table->timestamp('create_time')->useCurrent();
            $table->tinyInteger('del_flag')->default(0);
        });

        // 6.3.5 园区开放日期
        Schema::create('open_day', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('scenic_area')->cascadeOnDelete();
            $table->date('open_date');
            $table->integer('capacity')->default(5000);
            $table->string('status', 10)->default('开放');
            $table->timestamp('create_time')->useCurrent();
            $table->tinyInteger('del_flag')->default(0);

            $table->unique(['area_id', 'open_date'], 'uk_area_date');
            $table->index('open_date', 'idx_date');
        });

        // 6.3.6 票种
        Schema::create('ticket_type', function (Blueprint $table) {
            $table->id();
            $table->string('type_code', 20)->unique('uk_code');
            $table->string('type_name', 50);
            $table->decimal('price', 10, 2);
            $table->string('applicable', 100)->nullable();
            $table->tinyInteger('refundable')->default(1)->comment('1 可退 / 0 不可退');
            $table->string('status', 10)->default('在售');
            $table->timestamp('create_time')->useCurrent();
            $table->tinyInteger('del_flag')->default(0);
        });

        // 6.3.7 每日售票额度
        Schema::create('ticket_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_day_id')->constrained('open_day')->cascadeOnDelete();
            $table->foreignId('type_id')->constrained('ticket_type')->cascadeOnDelete();
            $table->integer('quota');
            $table->integer('sold_count')->default(0)->comment('已出票张数（冗余缓存）');
            $table->string('status', 10)->default('正常')->comment('正常/已售罄/停售');
            $table->timestamp('create_time')->useCurrent();
            $table->tinyInteger('del_flag')->default(0);

            $table->unique(['open_day_id', 'type_id'], 'uk_day_type');
            $table->index('type_id', 'idx_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_stock');
        Schema::dropIfExists('ticket_type');
        Schema::dropIfExists('open_day');
        Schema::dropIfExists('scenic_area');
        Schema::dropIfExists('operator');
        Schema::dropIfExists('visitor');
    }
};
