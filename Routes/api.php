<?php

use Illuminate\Support\Facades\Route;
use MultiTenantSaas\Modules\Review\Http\Controllers\ReviewController;

/*
|--------------------------------------------------------------------------
| 评价写入 + 审核路由（需登录）
|--------------------------------------------------------------------------
|
| 前缀由 ReviewServiceProvider::loadModuleRoutes() 从 config('review.route_prefix') 读取
| （框架默认 api/v1；下游设 REVIEW_ROUTE_PREFIX=api/v1/biz 沿用既有契约）。
|
| C 端写入（提交/删除本人评价）不挂 RBAC —— RBAC 只作用于 Operator；
| 运营审核面按读写分离：待审列表 review.view、通过/驳回 review.manage。
|
| 只读接口（游客可读 approved）见 Routes/optional.php。
|
*/

Route::post('reviews', [ReviewController::class, 'store']);
Route::delete('reviews/{id}', [ReviewController::class, 'destroy']);

Route::middleware('rbac.permission:review.view')->group(function () {
    Route::get('reviews/pending', [ReviewController::class, 'pending']);
});

Route::middleware('rbac.permission:review.manage')->group(function () {
    Route::post('reviews/{id}/approve', [ReviewController::class, 'approve']);
    Route::post('reviews/{id}/reject', [ReviewController::class, 'reject']);
});
