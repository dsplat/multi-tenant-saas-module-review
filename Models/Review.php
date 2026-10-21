<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Review\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;
use MultiTenantSaas\Concerns\SerializesFriendlyDates;
use MultiTenantSaas\Modules\Auth\Models\User;

/**
 * 多态评价（C 端商品/课程评价，带审核流）
 *
 * - 多态键：entity_type（course/product）+ entity_id（16 位全局 ID）
 * - 评价人 = User（user_id）；审核人 = Operator
 * - 每 (tenant_id, entity_type, entity_id, user_id) 唯一
 * - 仅 status=approved 对外/SEO 直出
 */
class Review extends Model
{
    use BelongsToTenant, HasGlobalId, SoftDeletes;
    use SerializesFriendlyDates;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    // 取值与 Order\Support\EntityTypes 白名单一致；此处不引 Order 包，保持评价底座零业务依赖
    public const ENTITY_COURSE = 'course';

    public const ENTITY_PRODUCT = 'product';

    /**
     * 本模块支持的被评价实体（均为 bigint 全局 ID 实体；可扩）。
     */
    public const ENTITY_TYPES = [
        self::ENTITY_COURSE,
        self::ENTITY_PRODUCT,
    ];

    protected $table = 'reviews';

    protected $primaryKey = 'review_id';

    protected $fillable = [
        'tenant_id', 'entity_type', 'entity_id', 'user_id', 'rating',
        'content', 'images', 'status', 'reviewed_by', 'reviewed_at', 'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'entity_id' => 'integer',
            'rating' => 'integer',
            'reviewed_by' => 'integer',
            'images' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function isValidEntityType(string $entityType): bool
    {
        return in_array($entityType, self::ENTITY_TYPES, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /** 仅已通过审核（对外/直出唯一可见状态） */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForEntity(Builder $query, string $entityType, int $entityId): Builder
    {
        return $query->where('entity_type', $entityType)->where('entity_id', $entityId);
    }
}
