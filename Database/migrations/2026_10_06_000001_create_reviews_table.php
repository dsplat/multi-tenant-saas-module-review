<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 多态评价表（BL-010 切片一）
 *
 * 决策见 scrm-platform 仓 ADR `2026-10-06-polymorphic-reviews`（跨仓，不复制正文）。
 * - 多态键：entity_type（course/product…）+ entity_id（16 位全局 ID）
 * - 评价人 = User（user_id），非 customer
 * - 唯一：每 (tenant_id, entity_type, entity_id, user_id) 一条
 * - 审核：status = pending/approved/rejected + reviewed_by/reviewed_at/reject_reason
 * - 软删除：下架/删除保留审计（重评口径见 ADR §2.3）
 * - 无金额列（金额单位铁律无涉）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reviews')) {
            return;
        }

        Schema::create('reviews', function (Blueprint $table) {
            $table->bigInteger('review_id')->unsigned()->primary()->comment('IdGenerator 全局ID');
            $table->bigInteger('tenant_id')->unsigned();
            $table->string('entity_type', 50)->comment('评价对象类型：course/product…（白名单）');
            $table->bigInteger('entity_id')->unsigned()->comment('评价对象全局ID（16 位随机数字）');
            $table->bigInteger('user_id')->unsigned()->comment('评价人 User（非 customer）');
            $table->tinyInteger('rating')->unsigned()->comment('评分 1-5');
            $table->text('content')->nullable()->comment('评价正文');
            $table->json('images')->nullable()->comment('图片 URL 列表');
            $table->string('status', 20)->default('pending')->comment('pending|approved|rejected');
            $table->bigInteger('reviewed_by')->unsigned()->nullable()->comment('审核人 Operator ID');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->string('reject_reason', 255)->nullable()->comment('驳回原因');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'entity_type', 'entity_id', 'user_id'], 'reviews_user_entity_unique');
            $table->index(['tenant_id', 'entity_type', 'entity_id', 'status'], 'reviews_entity_status_index');
            $table->index(['tenant_id', 'status', 'created_at'], 'reviews_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
