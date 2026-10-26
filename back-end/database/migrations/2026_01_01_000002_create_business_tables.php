<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 业务流水三张：需求文档 6.3.8–6.3.10
 */
return new class extends Migration
{
    public function up(): void
    {
        // 6.3.8 售票订单
        Schema::create('sale_order', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 32)->unique('uk_order_no');
            $table->foreignId('visitor_id')->constrained('visitor')->cascadeOnDelete();
            $table->foreignId('type_id')->constrained('ticket_type')->restrictOnDelete();
            $table->date('visit_date')->comment('游园日期（出票后不可改）');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2)->comment('下单时单价快照');
            $table->decimal('total_amount', 10, 2);
            $table->string('channel', 10)->default('线上')->comment('线上 / 窗口');
            $table->string('pay_method', 20)->nullable();
            $table->dateTime('pay_time')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('operator')->nullOnDelete();
            $table->string('status', 10)->default('待支付')
                ->comment('待支付/已支付/已使用/退款中/已退款/已取消');
            $table->dateTime('cancel_time')->nullable();
            $table->dateTime('refund_time')->nullable();
            $table->timestamp('create_time')->useCurrent();

            $table->index(['visitor_id', 'status'], 'idx_visitor_status');
            $table->index('visit_date', 'idx_visit_date');
        });

        // 6.3.9 门票（含核销码）
        Schema::create('ticket', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no', 32)->unique('uk_ticket_no');
            $table->foreignId('order_id')->constrained('sale_order')->cascadeOnDelete();
            $table->foreignId('type_id')->constrained('ticket_type')->restrictOnDelete();
            $table->date('visit_date');
            $table->decimal('unit_price', 10, 2);
            $table->string('status', 10)->default('未检票')->comment('未检票/已检票/已退款/已过期');
            $table->dateTime('check_time')->nullable();
            $table->foreignId('checker_id')->nullable()->constrained('sys_user')->nullOnDelete();
            $table->timestamp('create_time')->useCurrent();

            $table->index('order_id', 'idx_order');
            $table->index(['visit_date', 'status'], 'idx_visit_date_status');
        });

        // 6.3.10 退票申请与审核
        Schema::create('refund_record', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ticket')->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained('visitor')->cascadeOnDelete();
            $table->string('reason', 255);
            $table->decimal('refund_amount', 10, 2);
            $table->dateTime('apply_time')->useCurrent();
            $table->string('audit_status', 10)->default('待审核')->comment('待审核/已通过/已驳回');
            $table->foreignId('auditor_id')->nullable()->constrained('sys_user')->nullOnDelete();
            $table->string('audit_opinion', 255)->nullable();
            $table->dateTime('audit_time')->nullable();
            $table->timestamp('create_time')->useCurrent();

            $table->index('ticket_id', 'idx_ticket');
            $table->index('audit_status', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_record');
        Schema::dropIfExists('ticket');
        Schema::dropIfExists('sale_order');
    }
};
