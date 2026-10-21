import { http, extractListResult, moduleApiPrefix } from '@multi-tenant-saas/console/shared/http'

/** 评价对象类型（白名单，与 Review::ENTITY_TYPES 对齐） */
export type ReviewEntityType = 'course' | 'product'

/** 审核状态 */
export type ReviewStatus = 'pending' | 'approved' | 'rejected'

export interface Review {
  review_id: number
  tenant_id: number
  entity_type: ReviewEntityType
  entity_id: number
  user_id: number
  rating: number
  content: string | null
  images: string[] | null
  status: ReviewStatus
  reviewed_by: number | null
  reviewed_at: string | null
  reject_reason: string | null
  created_at: string
  updated_at: string
}

export interface PendingReviewParams {
  page: number
  perPage: number
}

export interface PendingReviewResult {
  data: Review[]
  total: number
}

/** 待审列表（仅 pending；运营面返回全字段） */
export async function getPendingReviews(params: PendingReviewParams): Promise<PendingReviewResult> {
  const res = await http.get<Review[]>(`${moduleApiPrefix('review')}/reviews/pending`, {
    params: { page: params.page, per_page: params.perPage },
  })
  return extractListResult(res)
}

/** 审核通过 */
export async function approveReview(id: number): Promise<Review> {
  const res = await http.post<Review>(`${moduleApiPrefix('review')}/reviews/${id}/approve`)
  return res.data
}

/** 审核驳回（附理由） */
export async function rejectReview(id: number, reason: string): Promise<Review> {
  const res = await http.post<Review>(`${moduleApiPrefix('review')}/reviews/${id}/reject`, { reason })
  return res.data
}
