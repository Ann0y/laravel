<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 园内另付费项目（F17）：入园后单独购买的项目票（缆车 / 玻璃桥 / 游船 / 讲解 / 观光车）
 * 口径：不限量直接买（无额度表）、需持当日有效门票、项目票售出后不可退
 */
return new class extends Migration
{
    public function up(): void
    {
        // 主数据：园内项目
        Schema::create('park_item', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 20)->unique('uk_item_code')->comment('项目编码（CABLEWAY / GLASS_BRIDGE / CRUISE / GUIDE / SHUTTLE）');
            $table->string('item_name', 50)->comment('项目名称');
            $table->decimal('price', 10, 2)->comment('现售价（每人次）');
            $table->string('description', 255)->nullable()->comment('项目说明');
            $table->string('status', 10)->default('在售')->comment('在售 / 停售');
            $table->timestamp('create_time')->useCurrent();
            $table->tinyInteger('del_flag')->default(0)->comment('逻辑删除 0 正常 / 1 删除');
        });

        // 流水：项目订单（提交即支付出票，售出不可退）
        Schema::create('park_item_order', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 32)->unique('uk_item_order_no');
            $table->foreignId('visitor_id')->constrained('visitor')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('park_item')->restrictOnDelete();
            $table->date('visit_date')->comment('游玩日期（须与入园门票同日）');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2)->comment('下单时单价快照');
            $table->decimal('total_amount', 10, 2);
            $table->string('pay_method', 20)->default('模拟支付');
            $table->dateTime('pay_time')->nullable();
            $table->string('status', 10)->default('已支付')->comment('已支付 / 已使用');
            $table->timestamp('create_time')->useCurrent();

            $table->index(['visitor_id', 'status'], 'idx_item_visitor_status');
            $table->index('visit_date', 'idx_item_visit_date');
        });

        // 流水：项目票（每张一码，项目口核销）
        Schema::create('park_item_ticket', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code', 32)->unique('uk_item_ticket_code')->comment('核销码（PI 前缀）');
            $table->foreignId('order_id')->constrained('park_item_order')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('park_item')->restrictOnDelete();
            $table->date('visit_date');
            $table->string('status', 10)->default('未核销')->comment('未核销 / 已核销');
            $table->dateTime('check_time')->nullable();
            $table->foreignId('checker_id')->nullable()->constrained('sys_user')->nullOnDelete();
            $table->timestamp('create_time')->useCurrent();

            $table->index('order_id', 'idx_item_ticket_order');
            $table->index(['visit_date', 'status'], 'idx_item_visit_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('park_item_ticket');
        Schema::dropIfExists('park_item_order');
        Schema::dropIfExists('park_item');
    }
};
