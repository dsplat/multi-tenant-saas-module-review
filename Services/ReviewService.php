<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Review\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Modules\Review\Contracts\ReviewContentModerationContract;
use MultiTenantSaas\Modules\Review\Models\Review;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 多态评价服务（创建 / 审核 / 聚合 / 按实体查询 / 删除）
 *
 * - 每 (tenant, entity_type, entity_id, user_id) 唯一；软删后重评 = restore（不插新行）
 * - 仅 approved 对外；聚合实时算（口径：COUNT + SUM/COUNT over approved）
 * - 评价人 = User；提交前 UGC 送检由 ReviewContentModerationContract 扩展
 */
class ReviewService
{
    /**
     * 公开字段白名单（API 只读 / SEO 直出共用），剔除 user_id/status/reviewed_by 等隐私与运营字段。
     */
    private const PUBLIC_FIELDS = ['content', 'rating', 'images', 'created_at'];

    public function __construct(
        protected ReviewContentModerationContract $contentModeration,
    ) {}

    /**
     * 提交评价（缺省 status=pending）。
     *
     * 唯一不变式：同实体同 User 仅一条。命中软删行则恢复并覆盖（不插新行）。
     *
     * @param  array<string, mixed>  $data  rating（必填 1..5）/ content / images
     */
    public function submit(int $tenantId, string $entityType, int $entityId, int $userId, array $data): Review
    {
        TenantContext::setTenantId((string) $tenantId);

        if (! Review::isValidEntityType($entityType)) {
            throw new UnprocessableEntityHttpException("Invalid review entity_type: {$entityType}");
        }
        if ($userId <= 0) {
            throw new UnprocessableEntityHttpException('user_id is required');
        }

        $rating = (int) ($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw new UnprocessableEntityHttpException('rating must be between 1 and 5');
        }

        // UGC 机审：默认放行；下游可绑定内容安全实现接管送检
        $this->contentModeration->checkUserText($tenantId, $userId, $data['content'] ?? null);

        $payload = [
            'rating' => $rating,
            'content' => $data['content'] ?? null,
            'images' => $data['images'] ?? null,
            'status' => Review::STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'reject_reason' => null,
        ];

        // 含软删行按唯一键查找（MySQL 唯一索引不排除软删行）
        $existing = Review::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('user_id', $userId)
            ->first();

        if ($existing !== null) {
            if (! $existing->trashed()) {
                throw new UnprocessableEntityHttpException('您已评价过该对象，请勿重复提交');
            }

            // 软删后重评：恢复原行并覆盖内容（保持唯一不变式）
            $existing->restore();
            $existing->update($payload);

            return $existing->fresh();
        }

        return Review::create(array_merge($payload, [
            'tenant_id' => $tenantId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
        ]));
    }

    /**
     * 审核通过（清空驳回原因）。
     */
    public function approve(int $tenantId, int $reviewId, ?int $operatorId = null): Review
    {
        TenantContext::setTenantId((string) $tenantId);

        $review = $this->findOrFail($tenantId, $reviewId);
        $review->update([
            'status' => Review::STATUS_APPROVED,
            'reviewed_by' => $operatorId,
            'reviewed_at' => now(),
            'reject_reason' => null,
        ]);

        return $review->fresh();
    }

    /**
     * 审核驳回（可附原因）。
     */
    public function reject(int $tenantId, int $reviewId, ?int $operatorId = null, ?string $reason = null): Review
    {
        TenantContext::setTenantId((string) $tenantId);

        $review = $this->findOrFail($tenantId, $reviewId);
        $review->update([
            'status' => Review::STATUS_REJECTED,
            'reviewed_by' => $operatorId,
            'reviewed_at' => now(),
            'reject_reason' => $reason,
        ]);

        return $review->fresh();
    }

    /**
     * 删除本人评价（软删）。
     */
    public function delete(int $tenantId, int $reviewId, int $userId): bool
    {
        TenantContext::setTenantId((string) $tenantId);

        $review = $this->findOrFail($tenantId, $reviewId);
        if ((int) $review->user_id !== $userId) {
            throw new UnprocessableEntityHttpException('只能删除自己的评价');
        }

        return $review->delete() !== false;
    }

    /**
     * 公开评价列表（仅 approved；字段白名单 + 昵称脱敏），供只读 API 与 SEO 直出复用。
     *
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function publicList(int $tenantId, string $entityType, int $entityId, int $perPage = 20, int $page = 1): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $paginator = Review::query()
            ->where('tenant_id', $tenantId)
            ->forEntity($entityType, $entityId)
            ->approved()
            // 次级键取主键，保证同秒行次序确定（ordering 铁律）
            ->orderByDesc('created_at')
            ->orderByDesc('review_id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $this->shapePublic($paginator->items()),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 待审列表（运营面，含全部字段）。
     *
     * @return array{data: array<int, Review>, total: int, page: int, per_page: int, last_page: int}
     */
    public function listPending(int $tenantId, int $perPage = 20, int $page = 1): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $paginator = Review::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Review::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->orderByDesc('review_id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 聚合（仅 approved，实时）：条数 + 均分（保留 2 位）。
     *
     * @return array{entity_type: string, entity_id: int, count: int, rating_sum: int, average: float|null}
     */
    public function aggregate(int $tenantId, string $entityType, int $entityId): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $row = Review::query()
            ->where('tenant_id', $tenantId)
            ->forEntity($entityType, $entityId)
            ->approved()
            ->selectRaw('COUNT(*) as review_count, COALESCE(SUM(rating), 0) as rating_sum')
            ->first();

        $count = (int) ($row->review_count ?? 0);
        $ratingSum = (int) ($row->rating_sum ?? 0);

        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'count' => $count,
            'rating_sum' => $ratingSum,
            // 均分是比率，round(…,2) 合法（非金额，金额单位铁律无涉）
            'average' => $count > 0 ? round($ratingSum / $count, 2) : null,
        ];
    }

    private function findOrFail(int $tenantId, int $reviewId): Review
    {
        $review = Review::where('tenant_id', $tenantId)->where('review_id', $reviewId)->first();

        if ($review === null) {
            throw (new ModelNotFoundException)->setModel(Review::class, [$reviewId]);
        }

        return $review;
    }

    /**
     * 公开字段白名单 + 昵称脱敏（服务端裁剪，不可只靠前端过滤）。
     *
     * @param  array<int, Review>  $reviews
     * @return array<int, array<string, mixed>>
     */
    private function shapePublic(array $reviews): array
    {
        $names = $this->userNames($reviews);

        return array_values(array_map(function (Review $review) use ($names) {
            $row = array_intersect_key($review->toArray(), array_flip(self::PUBLIC_FIELDS));
            $row['user_name'] = $this->maskUserName($names[(int) $review->user_id] ?? null);

            return $row;
        }, $reviews));
    }

    /**
     * @param  array<int, Review>  $reviews
     * @return array<int, string>
     */
    private function userNames(array $reviews): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn (Review $r) => (int) $r->user_id, $reviews))));
        if ($ids === []) {
            return [];
        }

        return DB::table('users')
            ->whereIn('user_id', $ids)
            ->pluck('name', 'user_id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
            ->all();
    }

    /**
     * 昵称脱敏：保留首尾，中间以 * 替代（单字全掩，双字掩尾）。
     */
    private function maskUserName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $length = mb_strlen($name);
        if ($length === 1) {
            return '*';
        }
        if ($length === 2) {
            return mb_substr($name, 0, 1) . '*';
        }

        return mb_substr($name, 0, 1) . str_repeat('*', $length - 2) . mb_substr($name, -1);
    }
}
