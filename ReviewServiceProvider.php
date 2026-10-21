<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Review;

use Illuminate\Support\Facades\Route;
use MultiTenantSaas\Modules\Contracts\ModuleServiceProvider;
use MultiTenantSaas\Modules\Review\Contracts\ReviewContentModerationContract;
use MultiTenantSaas\Modules\Review\Services\NullReviewContentModeration;
use MultiTenantSaas\Support\OptionalModule;

/**
 * 多态评价模块（通用评价底座，自 scrm 物理归位）
 *
 * 继承框架模块基类，路由/迁移/配置交给基类；唯一覆写 loadModuleRoutes()
 * 把基类写死的 api/v1 前缀换成 config('review.route_prefix')，供下游沿用既有
 * api/v1/biz 契约。评价正文机审走 ReviewContentModerationContract 扩展点，
 * 不硬依赖项目侧内容安全模块。
 */
class ReviewServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'review';

    private const MW_VERIFY_OPERATOR_TENANT = 'MultiTenantSaas\Modules\Infrastructure\Http\Middleware\VerifyOperatorTenant';

    private const MW_OPTIONAL_SANCTUM_AUTH = 'MultiTenantSaas\Modules\Infrastructure\Http\Middleware\OptionalSanctumAuth';

    protected function registerModuleBindings(): void
    {
        $this->app->bindIf(ReviewContentModerationContract::class, NullReviewContentModeration::class);
    }

    protected function loadModuleRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        OptionalModule::ensure(self::MW_VERIFY_OPERATOR_TENANT, '评价模块路由加载', 'Infrastructure');

        $moduleDir = $this->getModulePath();
        $moduleGate = 'module.enabled:' . $this->moduleGateName();
        $prefix = (string) config('review.route_prefix', 'api/v1');

        $apiRoute = $moduleDir . '/Routes/api.php';
        if (file_exists($apiRoute)) {
            Route::middleware(['api', 'auth:sanctum', 'throttle:api', 'tenant.identify', self::MW_VERIFY_OPERATOR_TENANT, $moduleGate])
                ->prefix($prefix)
                ->group($apiRoute);
        }

        $publicRoute = $moduleDir . '/Routes/public.php';
        if (file_exists($publicRoute)) {
            Route::middleware(['api'])->prefix($prefix)->group($publicRoute);
        }

        $optionalRoute = $moduleDir . '/Routes/optional.php';
        if (file_exists($optionalRoute)) {
            Route::middleware(['api', 'throttle:api', 'tenant.identify', self::MW_OPTIONAL_SANCTUM_AUTH, $moduleGate])
                ->prefix($prefix)
                ->group($optionalRoute);
        }
    }
}
