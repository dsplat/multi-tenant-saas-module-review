<?php

use Illuminate\Support\Facades\Route;
use MultiTenantSaas\Modules\Review\Http\Controllers\ReviewController;

/*
|--------------------------------------------------------------------------
| 评价只读路由（可选认证：游客可读 + 登录后个性化）
|--------------------------------------------------------------------------
|
| 前缀由 ReviewServiceProvider::loadModuleRoutes() 从 config('review.route_prefix') 读取。
| 仅公开已审核（approved）评价；字段白名单 + 昵称脱敏在服务层完成。
|
*/

Route::get('reviews', [ReviewController::class, 'index']);
Route::get('reviews/summary', [ReviewController::class, 'summary']);
