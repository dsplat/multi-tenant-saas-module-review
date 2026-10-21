import type { RouteRecordRaw } from 'vue-router'
import { view } from '@multi-tenant-saas/console/module-loader'

const routes: RouteRecordRaw[] = [
  // 评价审核（待审列表 + 通过/驳回）
  { path: 'reviews/pending', name: 'ReviewAudit', component: view('review', 'ReviewAudit'), meta: { title: '评价审核', permission: 'review.view' } },
]

export default routes
