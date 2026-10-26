<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 游园评价（F16）：已检票门票一票一评，公开接口展示脱敏评价
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_review', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ticket')->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained('visitor')->cascadeOnDelete();
            $table->tinyInteger('rating')->comment('星级 1-5');
            $table->string('content', 255)->nullable()->comment('评价内容（可仅星级）');
            $table->timestamp('create_time')->useCurrent();

            // 一票一评
            $table->unique('ticket_id', 'uk_review_ticket');
            $table->index('create_time', 'idx_review_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_review');
    }
};
