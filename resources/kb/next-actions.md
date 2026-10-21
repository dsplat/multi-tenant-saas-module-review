---
module: review
audience: internal
locale: zh
title: 评价（Review）模块典型后续动作
---

# 评价（Review）典型后续动作

## 后台审核入口

- Console 页面「评价审核」：`app/Modules/Review/resources/console/ui/element-plus/views/ReviewAudit.vue`
  （路由 `reviews`，由 `resources/console/routes.ts` 注册；待审列表 + 通过/驳回含理由）。
- 依赖服务面：`ReviewService::listPending() / approve() / reject()`；接口
  `GET /biz/reviews/pending`、`POST /biz/reviews/{id}/approve`、`POST /biz/reviews/{id}/reject`。
- 仅 `status=approved` 对外（公开只读 API 与课程 SEO 直出）；`pending/rejected` 仅审核面可见。

## 侧栏入口（未纳入本次切片）

`ReviewAudit` 路由已注册，但侧栏 `navSectionsRaw`（`resources/pages/console/ui/element-plus/layouts/ConsoleLayout.vue`）
与 `resources/js/console/nav-paths.ts` 未声明 `reviews` 项——与同期下游切片（如 Marketing `journeys`）
口径一致：后台审核入口待统一补侧栏 + 权限点（沿用运营面既有 perm，不新造权限点）时一并落位。

## activity_evaluations → reviews 收敛：评估结论「不做」（2026-10-06）

**结论**：`activity_evaluations` 的 `course`/`product` 维度**不做数据迁移、不做读路径切换**；
course/product 评价的语义收敛**已由切片一达成**（`reviews` 是唯一事实源）。

**依据（代码取证）**：

1. **旧表无 course/product 写入路径**：`activity_evaluations` 的生产写入仅两类——
   `ActivityService::storeEvaluation()` → `entity_type='activity'`（`Services/ActivityService.php:569-579`）；
   `ExamAdminController`（`:264`）与 `SubmissionController`（`:111`）→ `entity_type='submission'`。
   `ENTITY_COURSE`/`ENTITY_PRODUCT` 自 `ActivityEvaluation` 预留后**从未被生产代码写入**，
   仅测试 `tests/Unit/Services/ActivityEvaluationAndCertificateTest.php` 使用。
2. **旧表无 course/product 读取路径**：`listEvaluations()`/`evaluationStats()` 固定
   `entity_type='activity'`；`listEntityEvaluations()` 的生产调用仅 Community 的 `submission`。
   全库无任何按 `course`/`product` 读 `activity_evaluations` 的调用点。
3. **读路径已在新表**：课程评价直出 `SeoRenderService::courseDetail()` 经 `ReviewService`
   读 `reviews(course)`（`app/Modules/Seo/Services/SeoRenderService.php:72-78`）——即 course
   评价的对外读路径**早已切换**，无遗留读路径待切。
4. **旧表→新表无无损映射**（强行迁移的破坏面）：
   - 形态：四维评分（`overall/content/venue/service_score`）→ 单维 `rating`，无一一对应；
   - 状态：`status='visible'`（且列表从不过滤）→ `pending/approved/rejected` 审核流；
   - 主键：`entity_id varchar(64)` → `reviews.entity_id bigint unsigned`，非数字值会强转失败；
   - 约束：旧表无 `(tenant,entity,user)` 唯一约束且可能有重复行 → 迁入 `reviews` 唯一键会冲突。

**净收益为零、风险为正**：既然无读路径消费旧表 course/product 行、且唯一有效读路径已在 `reviews`，
迁移只会引入数据风险。故按 ADR §6.2 的收敛计划**明确不做**，保留旧表 course/product 为「未接入枚举」。
（ADR 正文 `2026-10-06-polymorphic-reviews`（scrm-platform 仓）为定稿，按文档治理 T3 不改写；
本结论以代码内显式记录 + `docs/governance/backlog.md` 登记行留痕。）
