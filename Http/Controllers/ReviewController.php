<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Review\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Http\Controllers\BaseController;
use MultiTenantSaas\Modules\Auth\Models\User;
use MultiTenantSaas\Modules\Operator\Models\Operator;
use MultiTenantSaas\Modules\Review\Http\Requests\StoreReviewRequest;
use MultiTenantSaas\Modules\Review\Models\Review;
use MultiTenantSaas\Modules\Review\Services\ReviewService;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 多态评价（只读 + 写入 + 审核）
 *
 * 写入身份只认登录 User 本人；审核动作由 Operator 执行。
 */
class ReviewController extends BaseController
{
    public function __construct(
        protected ReviewService $reviews,
    ) {}

    /**
     * 只读列表（仅 approved；游客可读，字段白名单 + 昵称脱敏）。
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $validated = $request->validate([
            'entity_type' => ['required', 'string', Rule::in(Review::ENTITY_TYPES)],
            'entity_id' => 'required|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->reviews->publicList(
            $tenantId,
            (string) $validated['entity_type'],
            (int) $validated['entity_id'],
            (int) ($validated['per_page'] ?? 20),
            (int) ($validated['page'] ?? 1),
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 聚合（仅 approved）。
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $validated = $request->validate([
            'entity_type' => ['required', 'string', Rule::in(Review::ENTITY_TYPES)],
            'entity_id' => 'required|integer|min:1',
        ]);

        $result = $this->reviews->aggregate(
            $tenantId,
            (string) $validated['entity_type'],
            (int) $validated['entity_id'],
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 提交评价（登录 User 本人；status=pending）。
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $user = $request->user();
        if (! $user instanceof User) {
            throw new UnprocessableEntityHttpException('仅前台登录用户可提交评价');
        }

        $review = $this->reviews->submit(
            $tenantId,
            (string) $request->validated('entity_type'),
            (int) $request->validated('entity_id'),
            (int) $user->user_id,
            $request->validated(),
        );

        return response()->json(['success' => true, 'data' => $review], 201);
    }

    /**
     * 删除本人评价（软删）。
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $user = $request->user();
        if (! $user instanceof User) {
            throw new UnprocessableEntityHttpException('仅前台登录用户可删除自己的评价');
        }

        $this->reviews->delete($tenantId, (int) $id, (int) $user->user_id);

        return response()->json(['success' => true, 'message' => '评价已删除']);
    }

    /**
     * 待审列表（运营面）。
     */
    public function pending(Request $request): JsonResponse
    {
        // 审核为运营场景：登录 User 亦拒绝（VerifyOperatorTenant 对非 Operator 放行，须在此显式守卫）
        if (! $request->user() instanceof Operator) {
            return response()->json(['success' => false, 'message' => '审核仅限运营人员'], 403);
        }

        $tenantId = (int) TenantContext::getId();
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->reviews->listPending(
            $tenantId,
            (int) ($validated['per_page'] ?? 20),
            (int) ($validated['page'] ?? 1),
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 审核通过。
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        // 审核为运营场景：登录 User 亦拒绝（VerifyOperatorTenant 对非 Operator 放行，须在此显式守卫）
        if (! $request->user() instanceof Operator) {
            return response()->json(['success' => false, 'message' => '审核仅限运营人员'], 403);
        }

        $tenantId = (int) TenantContext::getId();
        $review = $this->reviews->approve($tenantId, (int) $id, $this->operatorId($request));

        return response()->json(['success' => true, 'data' => $review]);
    }

    /**
     * 审核驳回（可附原因）。
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        // 审核为运营场景：登录 User 亦拒绝（VerifyOperatorTenant 对非 Operator 放行，须在此显式守卫）
        if (! $request->user() instanceof Operator) {
            return response()->json(['success' => false, 'message' => '审核仅限运营人员'], 403);
        }

        $tenantId = (int) TenantContext::getId();
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $review = $this->reviews->reject($tenantId, (int) $id, $this->operatorId($request), $validated['reason'] ?? null);

        return response()->json(['success' => true, 'data' => $review]);
    }

    private function operatorId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof Operator ? (int) $user->operator_id : null;
    }
}
