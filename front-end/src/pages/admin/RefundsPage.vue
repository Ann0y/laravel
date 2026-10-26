<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../../api'
import StatusChip from '../../components/StatusChip.vue'

// F13 退票审核：待审核受理单逐条通过 / 驳回（通过后自动回补额度并更新门票与订单状态）
const list = ref([])
const loading = ref(true)
const error = ref('')
const flash = ref('')

const rejectingId = ref(null)
const opinion = ref('')
const auditError = ref('')
const busyId = ref(null)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const data = await api('/refunds/pending')
    list.value = data.list
  } catch (e) {
    error.value = e.status === 401 ? '登录已过期，请重新进入管理端' : e.message
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function audit(id, approve) {
  auditError.value = ''
  if (!approve && !opinion.value.trim()) {
    auditError.value = '驳回必须填写审核意见'
    return
  }
  busyId.value = id
  try {
    const data = await api(`/refunds/${id}/audit`, {
      method: 'PATCH',
      body: { approve, opinion: opinion.value.trim() || null },
    })
    flash.value = `退款单 #${id} 已${data.auditStatus}`
    rejectingId.value = null
    opinion.value = ''
    await load()
  } catch (e) {
    auditError.value = e.message
  } finally {
    busyId.value = null
  }
}

function fmtTime(iso) {
  return iso ? iso.replace('T', ' ').slice(0, 16) : '—'
}
</script>

<template>
  <div class="rf container">
    <header class="rf-head">
      <h1>退票审核</h1>
      <span class="chip-plain" v-if="loading === false && list.length">
        待审核 <strong class="num">{{ list.length }}</strong> 条
      </span>
    </header>

    <p v-if="flash" class="flash">{{ flash }}</p>
    <p v-if="error" class="err">{{ error }}</p>
    <div v-if="loading" class="loading muted">载入中…</div>

    <template v-else>
      <div v-if="!list.length" class="empty card">
        <h2>没有待审核的退票申请</h2>
        <p class="muted">游客提交的退票申请会实时出现在这里，逐条审核后自动回补当日额度。</p>
        <router-link to="/admin" class="btn btn-ghost btn-sm">返回工作台</router-link>
      </div>

      <article v-for="r in list" :key="r.id" class="rv card">
        <header class="rv-head">
          <div class="rv-id">
            <span class="num no">#{{ r.id }}</span>
            <span class="num ticket">{{ r.ticketNo }}</span>
            <StatusChip status="待审核" />
          </div>
          <span class="amount num">¥{{ r.refundAmount }}</span>
        </header>

        <dl class="rv-meta">
          <div>
            <dt>游客</dt>
            <dd>{{ r.visitorName }}</dd>
          </div>
          <div>
            <dt>票种 / 游园日期</dt>
            <dd class="num">{{ r.typeName }} · {{ r.visitDate }}</dd>
          </div>
          <div>
            <dt>所属订单</dt>
            <dd class="num">{{ r.orderNo }}</dd>
          </div>
          <div>
            <dt>申请时间</dt>
            <dd class="num">{{ fmtTime(r.applyTime) }}</dd>
          </div>
        </dl>

        <div class="rv-reason">
          <span class="field-text">退票原因</span>
          <p class="reason">{{ r.reason }}</p>
        </div>

        <footer class="rv-actions">
          <button class="btn btn-primary btn-sm" :disabled="busyId === r.id" @click="audit(r.id, true)">
            通过（回补额度）
          </button>
          <button
            class="btn btn-danger btn-sm"
            :disabled="busyId === r.id"
            @click="rejectingId = rejectingId === r.id ? null : r.id"
          >
            驳回
          </button>

          <div v-if="rejectingId === r.id" class="reject-form">
            <textarea
              v-model="opinion"
              rows="2"
              placeholder="请填写驳回原因（将反馈给游客）"
              maxlength="255"
            ></textarea>
            <p v-if="auditError" class="err">{{ auditError }}</p>
            <div class="reject-actions">
              <button class="btn btn-danger btn-sm" :disabled="busyId === r.id" @click="audit(r.id, false)">
                确认驳回
              </button>
              <button class="btn btn-ghost btn-sm" @click="rejectingId = null">取消</button>
            </div>
          </div>
        </footer>
      </article>

      <p class="field-text tip">
        规则：通过后门票置「已退款」、当日对应票种额度自动回补，整单全部退完时订单置「已退款」；
        驳回需填写意见，门票状态不变。
      </p>
    </template>
  </div>
</template>

<style scoped>
.rf {
  padding: 32px 24px 0;
}

.rf-head {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 20px;
}

.rf-head h1 {
  margin: 0;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.chip-plain {
  font-size: 13.5px;
  color: var(--amber);
  background: var(--amber-soft);
  border-radius: 999px;
  padding: 4px 14px;
}

.rv {
  padding: 18px 22px;
  margin-bottom: 14px;
}

.rv-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}

.rv-id {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.no {
  color: var(--ink-3);
  font-size: 13px;
}

.ticket {
  font-weight: 600;
  letter-spacing: 0.04em;
}

.amount {
  font-weight: 650;
  font-size: 16px;
}

.rv-meta {
  margin: 12px 0;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}

.rv-meta div {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.rv-meta dt {
  color: var(--ink-3);
  font-size: 12.5px;
}

.rv-meta dd {
  margin: 0;
  font-size: 14px;
  overflow-wrap: anywhere;
}

.rv-reason {
  padding: 10px 14px;
  background: #fafbfa;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.reason {
  margin: 0;
  font-size: 14px;
}

.rv-actions {
  display: flex;
  gap: 10px;
  margin-top: 14px;
  align-items: flex-start;
  flex-wrap: wrap;
}

.reject-form {
  flex-basis: 100%;
  display: flex;
  flex-direction: column;
  gap: 8px;
  border-top: 1px dashed var(--border-strong);
  padding-top: 12px;
}

.reject-form textarea {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  font: inherit;
  font-size: 14px;
  resize: vertical;
}

.reject-form textarea:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
}

.reject-actions {
  display: flex;
  gap: 10px;
}

.empty {
  padding: 44px 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: center;
}

.empty h2 {
  margin: 0;
  font-size: 19px;
}

.flash {
  color: var(--accent-hover);
  font-size: 13.5px;
  margin: 0 0 12px;
}

.tip {
  margin: 2px 0 30px;
}

.loading {
  padding: 30px 0;
}

@media (max-width: 767px) {
  .rv-meta {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
