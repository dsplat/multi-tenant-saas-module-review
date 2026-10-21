<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Review\Services;

use MultiTenantSaas\Modules\Review\Contracts\ReviewContentModerationContract;

/**
 * 默认放行的评价正文机审实现（未接入内容安全能力时使用）。
 */
class NullReviewContentModeration implements ReviewContentModerationContract
{
    public function checkUserText(int $tenantId, ?int $userId, ?string $content): void {}
}
