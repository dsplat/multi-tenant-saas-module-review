<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Review\Contracts;

/**
 * 评价正文 UGC 机审扩展点。
 *
 * 框架默认实现放行（NullReviewContentModeration）；下游可绑定自己的内容安全服务
 * （如微信内容安全）来接管评价正文送检，而不让 Review 模块硬依赖项目模块。
 */
interface ReviewContentModerationContract
{
    /**
     * 送检评价正文；命中违规时抛异常阻断提交，放行则正常返回。
     */
    public function checkUserText(int $tenantId, ?int $userId, ?string $content): void;
}
