<template>
  <div class="page-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>评价审核</span>
          <el-button @click="refresh"> 刷新 </el-button>
        </div>
      </template>

      <ProTable ref="tableRef" :columns="columns" :request="handleRequest" :actions="actions" />
    </el-card>

    <el-dialog
      v-model="rejectVisible"
      title="驳回评价"
      width="480px"
      :close-on-click-modal="false"
      @closed="handleRejectClosed"
    >
      <el-form label-width="80px">
        <el-form-item label="驳回理由" required>
          <el-input
            v-model="rejectReason"
            type="textarea"
            :rows="3"
            maxlength="255"
            show-word-limit
            placeholder="请填写驳回理由（将记录在该评价上）"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rejectVisible = false"> 取消 </el-button>
        <el-button
          type="danger"
          :disabled="!rejectReason.trim()"
          :loading="submitting"
          @click="confirmReject"
        >
          确认驳回
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, h } from 'vue'
import { ElMessage, ElMessageBox, ElRate, ElTag } from 'element-plus'
import ProTable from '@multi-tenant-saas/console/components/common/ProTable/ProTable.vue'
import type {
  ActionConfig,
  ColumnConfig,
  RequestParams,
  RequestResult,
} from '@multi-tenant-saas/console/components/common/ProTable/ProTable.vue'
import {
  approveReview,
  getPendingReviews,
  rejectReview,
  type Review,
} from '@modules/Review/api/review'

defineOptions({ name: 'ReviewAudit' })

const tableRef = ref<InstanceType<typeof ProTable>>()

const rejectVisible = ref(false)
const rejectReason = ref('')
const submitting = ref(false)
let rejectTargetId: number | null = null

const ENTITY_LABELS: Record<string, string> = {
  course: '课程',
  product: '商品',
}

function entityLabel(row: Review): string {
  const label = ENTITY_LABELS[row.entity_type] ?? row.entity_type
  return `${label} #${row.entity_id}`
}

function renderImages(row: Review) {
  const images = row.images ?? []
  if (images.length === 0) return h('span', null, '-')

  const nodes = images
    .slice(0, 3)
    .map((url) =>
      h('img', {
        src: url,
        style: 'width:36px;height:36px;object-fit:cover;border-radius:4px;margin-right:4px',
      }),
    )
  if (images.length > 3) nodes.push(h('span', null, `+${images.length - 3}`))

  return h('div', { style: 'display:flex;align-items:center' }, nodes)
}

const columns: ColumnConfig[] = [
  { prop: 'review_id', label: '评价 ID', width: 180 },
  {
    prop: 'entity_type',
    label: '评价对象',
    minWidth: 160,
    render: (row: Review) => h(ElTag, { type: 'info' }, () => entityLabel(row)),
  },
  {
    prop: 'rating',
    label: '评分',
    width: 150,
    render: (row: Review) => h(ElRate, { modelValue: row.rating, disabled: true, max: 5 }),
  },
  { prop: 'content', label: '评价内容', minWidth: 200 },
  {
    prop: 'images',
    label: '图片',
    width: 150,
    render: (row: Review) => renderImages(row),
  },
  {
    prop: 'user_id',
    label: '提交人',
    width: 150,
    render: (row: Review) => h('span', null, `用户 #${row.user_id}`),
  },
  { prop: 'created_at', label: '提交时间', width: 170, sortable: true },
]

const actions: ActionConfig[] = [
  { label: '通过', type: 'success', onClick: (row) => handleApprove(row as Review) },
  { label: '驳回', type: 'danger', onClick: (row) => openReject(row as Review) },
]

async function handleRequest(params: RequestParams): Promise<RequestResult> {
  try {
    const res = await getPendingReviews({ page: params.page, perPage: params.pageSize })
    return { data: res.data ?? [], total: res.total ?? 0 }
  } catch (e: any) {
    ElMessage.error(e.message || '获取待审评价失败')
    return { data: [], total: 0 }
  }
}

function refresh() {
  tableRef.value?.refresh()
}

async function handleApprove(row: Review) {
  try {
    await ElMessageBox.confirm(`确认通过该评价（${entityLabel(row)}）？`, '审核通过', {
      type: 'warning',
    })
    await approveReview(row.review_id)
    ElMessage.success('已通过')
    refresh()
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '操作失败')
    }
  }
}

function openReject(row: Review) {
  rejectTargetId = row.review_id
  rejectReason.value = ''
  rejectVisible.value = true
}

function handleRejectClosed() {
  rejectTargetId = null
  rejectReason.value = ''
}

async function confirmReject() {
  if (rejectTargetId === null) return

  const reason = rejectReason.value.trim()
  if (!reason) {
    ElMessage.warning('请填写驳回理由')
    return
  }

  submitting.value = true
  try {
    await rejectReview(rejectTargetId, reason)
    ElMessage.success('已驳回')
    rejectVisible.value = false
    refresh()
  } catch (e: any) {
    ElMessage.error(e.message || '驳回失败')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
