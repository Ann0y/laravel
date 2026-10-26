<script setup>
import { ref, computed, onMounted } from 'vue'
import StatusChip from '../../components/StatusChip.vue'
import { api } from '../../api'

// F08 我的售票记录：本人经手的窗口订单与核销记录（按游园日期 / 状态查询）
const orders = ref([])
const loading = ref(true)
const error = ref('')

const STATUS_FILTERS = ['全部', '已支付', '已使用', '退款中', '已退款']
const status = ref('全部')
const dateFilter = ref('')

onMounted(async () => {
  try {
    const data = await api('/orders/mine')
    orders.value = data.orders
  } catch (e) {
    error.value = e.status === 401 ? '登录已过期，请重新进入票务端' : e.message
  } finally {
    loading.value = false
  }
})

const filtered = computed(() =>
  orders.value.filter(
    (o) =>
      (status.value === '全部' || o.status === status.value) &&
      (!dateFilter.value || o.visitDate === dateFilter.value),
  ),
)

const summary = computed(() => {
  let revenue = 0
  let tickets = 0
  let checked = 0
  for (const o of orders.value) {
    if (['已支付', '已使用', '退款中', '已退款'].includes(o.status)) {
      revenue += o.totalAmount
      tickets += o.quantity
      checked += o.tickets.filter((t) => t.status === '已检票').length
    }
  }
  return { revenue, tickets, checked }
})

const expanded = ref({})
function toggle(no) {
  expanded.value = { ...expanded.value, [no]: !expanded.value[no] }
}

function fmtTime(iso) {
  if (!iso) return '—'
  return iso.replace('T', ' ').slice(0, 16)
}
</script>

<template>
  <div class="rec container">
    <h1>我的售票记录</h1>

    <div class="stats">
      <div class="stat card">
        <span class="stat-label">经手订单</span>
        <span class="stat-value num">{{ orders.length }}</span>
        <span class="stat-unit">笔</span>
      </div>
      <div class="stat card">
        <span class="stat-label">累计交易额</span>
        <span class="stat-value num">¥{{ summary.revenue }}</span>
        <span class="stat-unit">有效订单</span>
      </div>
      <div class="stat card">
        <span class="stat-label">出票 / 已核销</span>
        <span class="stat-value num">{{ summary.tickets }} / {{ summary.checked }}</span>
        <span class="stat-unit">张</span>
      </div>
    </div>

    <div class="filters">
      <button
        v-for="f in STATUS_FILTERS"
        :key="f"
        class="filter-chip"
        :class="{ active: status === f }"
        @click="status = f"
      >
        {{ f }}
      </button>
      <input
        v-model="dateFilter"
        type="date"
        class="date-input"
        aria-label="按游园日期筛选"
        @change="dateFilter = $event.target.value"
      />
      <button v-if="dateFilter" class="link" @click="dateFilter = ''">清除日期</button>
    </div>

    <p v-if="error" class="err">{{ error }}</p>

    <div v-if="loading" class="loading muted">载入中…</div>

    <template v-else>
      <div v-if="!filtered.length" class="empty card">
        <h2>暂无记录</h2>
        <p class="muted">没有符合筛选条件的窗口订单，换个条件试试。</p>
        <router-link to="/operator/sell" class="btn btn-primary">去现场售票</router-link>
      </div>

      <article v-for="o in filtered" :key="o.id" class="order card">
        <header class="o-head">
          <div class="o-id">
            <span class="o-no num">{{ o.orderNo }}</span>
            <span class="o-date num">游园日期 {{ o.visitDate }}</span>
            <span class="o-date">游客 {{ o.visitorName || '散客' }}</span>
          </div>
          <div class="o-side">
            <StatusChip :status="o.status" />
            <span class="o-amount num">¥{{ o.totalAmount }}</span>
          </div>
        </header>

        <p class="o-meta field-text num">
          {{ o.typeName }} × {{ o.quantity }} · 支付：{{ o.payMethod || '线下' }} ·
          下单时间 {{ fmtTime(o.createTime) }}
        </p>

        <ul class="t-list">
          <li v-for="t in o.tickets" :key="t.ticketNo" class="t-item">
            <div class="t-row">
              <!-- 售票记录页不展示二维码：核销码仅展示给购票人本人与管理端 -->
              <span class="t-no num">{{ t.ticketNo }}</span>
              <StatusChip :status="t.refund && t.refund.status === '待审核' ? '待审核' : t.status" />
              <span class="field-text num">
                {{ t.status === '已检票' ? '核销于 ' + fmtTime(t.checkTime) : '未核销' }}
              </span>
              <button class="link" @click="toggle(t.ticketNo)">
                {{ expanded[t.ticketNo] ? '收起' : '详情' }}
              </button>
            </div>
            <div v-if="expanded[t.ticketNo]" class="t-detail field-text num">
              <span>票价 ¥{{ t.unitPrice }} · 游园日期 {{ t.visitDate }}</span>
              <span v-if="t.refund">退票申请（{{ t.refund.status }}）：{{ t.refund.reason }}</span>
              <span v-if="t.refund?.auditOpinion">审核意见：{{ t.refund.auditOpinion }}</span>
            </div>
          </li>
        </ul>
      </article>

      <p v-if="orders.length && !filtered.length" class="muted no-match">该筛选条件下暂无订单。</p>
    </template>
  </div>
</template>

<style scoped>
.rec {
  padding: 32px 24px 0;
}

.rec h1 {
  margin: 0 0 20px;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}

.stat {
  padding: 18px 22px;
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
}

.stat-label {
  font-size: 13.5px;
  color: var(--ink-2);
}

.stat-value {
  font-size: 26px;
  font-weight: 650;
}

.stat-unit {
  font-size: 12.5px;
  color: var(--ink-3);
}

.filters {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 18px;
}

.filter-chip {
  border: 1px solid var(--border-strong);
  background: #fff;
  border-radius: 999px;
  padding: 5px 16px;
  font-size: 13.5px;
  color: var(--ink-2);
  cursor: pointer;
  transition: all 0.15s ease;
  font: inherit;
  font-size: 13.5px;
}

.filter-chip:hover {
  border-color: var(--ink-2);
}

.filter-chip.active {
  border-color: var(--accent);
  background: var(--accent-soft);
  color: var(--accent-hover);
}

.date-input {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: #fff;
  padding: 5px 10px;
  font: inherit;
  font-size: 13.5px;
  color: var(--ink);
}

.order {
  padding: 18px 22px;
  margin-bottom: 14px;
}

.o-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}

.o-id {
  display: flex;
  align-items: baseline;
  gap: 14px;
  flex-wrap: wrap;
}

.o-no {
  font-weight: 600;
}

.o-date {
  color: var(--ink-2);
  font-size: 13.5px;
}

.o-side {
  display: flex;
  align-items: center;
  gap: 14px;
}

.o-amount {
  font-weight: 650;
  font-size: 16px;
}

.o-meta {
  margin: 10px 0 6px;
}

.t-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.t-item {
  padding: 8px 0;
}

.t-row {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.t-no {
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 0.04em;
  min-width: 10em;
}

.t-row .link {
  margin-left: auto;
}

.t-detail {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 8px;
  padding: 12px 14px;
  background: #fafbfa;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
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

.no-match {
  padding: 26px 0;
  text-align: center;
}

@media (max-width: 767px) {
  .stats {
    grid-template-columns: 1fr;
  }
}
</style>
