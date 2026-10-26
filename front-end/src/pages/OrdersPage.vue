<script setup>
import { ref, computed } from 'vue'
import StatusChip from '../components/StatusChip.vue'
import QrMock from '../components/QrMock.vue'
import LineChart from '../components/charts/LineChart.vue'
import DonutChart from '../components/charts/DonutChart.vue'
import { useTicketingStore } from '../stores/ticketing'
import { todayStr } from '../mock/seed'

const store = useTicketingStore()

const FILTERS = ['全部', '待支付', '已支付', '退款中', '已退款', '已取消', '已使用']
const filter = ref('全部')

const actionError = ref('')

// 进入页面刷新一次订单与额度（下单/审核变化后保持同步）
store.refresh().catch(() => {})

const orders = computed(() =>
  filter.value === '全部' ? store.orders : store.orders.filter((o) => o.status === filter.value),
)

const stats = computed(() => {
  const today = todayStr()
  let activeTickets = 0
  let spent = 0
  let refunded = 0
  for (const o of store.orders) {
    if (o.status === '已支付' || o.status === '已使用') spent += o.totalAmount
    for (const t of o.tickets) {
      if (t.status === '未检票' && o.visitDate >= today) activeTickets++
      if (t.status === '已退款') refunded += t.unitPrice
    }
  }
  return {
    count: store.orders.length,
    activeTickets,
    spent: spent - refunded,
  }
})

// 游客端数据可视化（F04 / E2 口径）：从本人订单聚合，不额外加后端接口
const trend = computed(() => {
  const map = {}
  for (const o of store.orders) {
    if (o.status !== '已支付' && o.status !== '已使用') continue
    const day = (o.createTime || '').slice(0, 10)
    if (!day) continue
    map[day] = (map[day] || 0) + o.totalAmount
  }
  const days = Object.keys(map).sort().slice(-10)
  return {
    labels: days.map((d) => d.slice(5).replace('-', '/')),
    series: [{ name: '消费金额', values: days.map((d) => map[d]) }],
  }
})

const byType = computed(() => {
  const map = {}
  for (const o of store.orders) {
    if (o.status !== '已支付' && o.status !== '已使用') continue
    map[o.typeName] = (map[o.typeName] || 0) + o.totalAmount
  }
  return Object.entries(map).map(([name, value]) => ({ name, value }))
})

function stars(n) {
  return '★'.repeat(n) + '☆'.repeat(5 - n)
}

// 门票改期（F03 扩展）：整单未检票门票迁移到新游园日
const reschedFor = ref(null) // 订单 id
const newDate = ref('')
const reschedError = ref('')

function canReschedule(o) {
  return (
    o.status === '已支付' &&
    o.visitDate > todayStr() &&
    o.tickets.some((t) => t.status === '未检票')
  )
}

function startReschedule(o) {
  reschedFor.value = o.id
  newDate.value = ''
  reschedError.value = ''
}

async function submitReschedule(o) {
  if (!newDate.value) {
    reschedError.value = '请选择新的游园日期'
    return
  }
  if (newDate.value <= o.visitDate && newDate.value <= todayStr()) {
    reschedError.value = '新游园日期不能早于或等于原日期，且需为未到日期'
    return
  }
  try {
    await store.reschedule(o.id, newDate.value)
    reschedFor.value = null
  } catch (e) {
    reschedError.value = e.message
  }
}

// 游园评价（F16）：已检票门票一票一评
const reviewFor = ref(null) // 票号
const reviewRating = ref(5)
const reviewText = ref('')
const reviewError = ref('')

function canReview(t) {
  return t.status === '已检票' && !t.review
}

function startReview(t) {
  reviewFor.value = t.ticketNo
  reviewRating.value = 5
  reviewText.value = ''
  reviewError.value = ''
  // 评价表单在门票详情区内，行上点击时同步展开
  expanded.value = { ...expanded.value, [t.ticketNo]: true }
}

async function submitReview(t) {
  try {
    await store.submitReview(t.ticketNo, reviewRating.value, reviewText.value.trim())
    reviewFor.value = null
  } catch (e) {
    reviewError.value = e.message
  }
}

const expanded = ref({})
function isExpanded(no) {
  return !!expanded.value[no]
}
function toggle(no) {
  expanded.value = { ...expanded.value, [no]: !expanded.value[no] }
}

// 改期最早可选日：明天
const tomorrowMin = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  return d.toISOString().slice(0, 10)
})

// 退票申请
const refundFor = ref(null)
const reason = ref('')
const refundError = ref('')

function canRefund(o, t) {
  return (
    t.status === '未检票' &&
    !t.refund &&
    o.visitDate > todayStr() &&
    o.status !== '退款中'
  )
}

function startRefund(no) {
  refundFor.value = no
  reason.value = ''
  refundError.value = ''
}

async function submitRefund() {
  if (!reason.value.trim()) {
    refundError.value = '请填写退票原因'
    return
  }
  try {
    await store.requestRefund(refundFor.value, reason.value.trim())
    refundFor.value = null
  } catch (e) {
    refundError.value = e.message
  }
}

// 支付 / 取消统一异步处理，错误就近展示
const busyOrder = ref(null)

async function doPay(o) {
  actionError.value = ''
  busyOrder.value = o.id
  try {
    await store.pay(o.id)
  } catch (e) {
    actionError.value = e.status === 401 ? '登录已过期，请重新登录后再支付' : e.message
  } finally {
    busyOrder.value = null
  }
}

async function doCancel(o) {
  actionError.value = ''
  busyOrder.value = o.id
  try {
    await store.cancel(o.id)
  } catch (e) {
    actionError.value = e.status === 401 ? '登录已过期，请重新登录后再操作' : e.message
  } finally {
    busyOrder.value = null
  }
}
</script>

<template>
  <div class="orders container">
    <h1>我的订单与门票</h1>

    <div class="stats">
      <div class="stat card">
        <span class="stat-label">累计订单</span>
        <span class="stat-value num">{{ stats.count }}</span>
        <span class="stat-unit">笔</span>
      </div>
      <div class="stat card">
        <span class="stat-label">在用门票</span>
        <span class="stat-value num">{{ stats.activeTickets }}</span>
        <span class="stat-unit">张</span>
      </div>
      <div class="stat card">
        <span class="stat-label">累计消费</span>
        <span class="stat-value num">¥{{ stats.spent }}</span>
        <span class="stat-unit">已扣除退款</span>
      </div>
    </div>

    <!-- 游客端数据可视化（F04）：本人消费趋势与票种占比 -->
    <div v-if="store.orders.length && (trend.labels.length || byType.length)" class="charts">
      <div class="chart card">
        <h3 class="chart-title">消费趋势（按下单日）</h3>
        <LineChart :labels="trend.labels" :series="trend.series" :height="220" unit="元" />
      </div>
      <div class="chart card">
        <h3 class="chart-title">票种消费占比</h3>
        <DonutChart :items="byType" center-label="消费" />
      </div>
    </div>

    <div class="filters" v-if="store.orders.length">
      <button
        v-for="f in FILTERS"
        :key="f"
        class="filter-chip"
        :class="{ active: filter === f }"
        @click="filter = f"
      >
        {{ f }}
      </button>
    </div>

    <div v-if="!store.orders.length" class="empty card">
      <h2>还没有订单</h2>
      <p class="muted">选好日期与票种，两分钟完成第一笔订单。</p>
      <RouterLink to="/buy" class="btn btn-primary">去购票</RouterLink>
    </div>

    <template v-else>
      <article v-for="o in orders" :key="o.id" class="order card">
        <header class="o-head">
          <div class="o-id">
            <span class="o-no num">{{ o.orderNo }}</span>
            <span class="o-date num">游园日期 {{ o.visitDate }}</span>
          </div>
          <div class="o-side">
            <StatusChip :status="o.status" />
            <span class="o-amount num">¥{{ o.totalAmount }}</span>
          </div>
        </header>

        <ul v-if="o.tickets.length || o.status === '已支付'" class="t-list">
          <!-- 已支付未出票兜底（理论上不会出现）：不渲染行 -->
          <li v-if="!o.tickets.length && o.status === '已支付'" class="t-none muted">
            出票生成中，请稍后刷新
          </li>
          <li v-for="t in o.tickets" :key="t.ticketNo" class="t-item">
            <div class="t-row">
              <span class="t-no num">{{ t.ticketNo }}</span>
              <StatusChip :status="t.refund && t.refund.status === '待审核' ? '待审核' : t.status" />
              <div class="t-actions">
                <button class="link" @click="toggle(t.ticketNo)">
                  {{ isExpanded(t.ticketNo) ? '收起' : '出示门票' }}
                </button>
                <button v-if="canRefund(o, t)" class="link danger" @click="startRefund(t.ticketNo)">
                  申请退票
                </button>
                <!-- 游园评价（F16）：入口放在门票行，已检票可见 -->
                <button v-if="canReview(t)" class="link review" @click="startReview(t)">
                  ✍️ 写评价
                </button>
                <span
                  v-else-if="t.status === '已检票' && t.review"
                  class="review done"
                  :title="'我的评价：' + (t.review.content || '（仅星级）')"
                >
                  <span class="stars num">{{ stars(t.review.rating) }}</span> 已评价
                </span>
              </div>
            </div>

            <div v-if="isExpanded(t.ticketNo)" class="t-detail">
              <QrMock :code="t.ticketNo" :size="124" />
              <div class="t-meta">
                <span class="code num">{{ t.ticketNo }}</span>
                <span class="field-text">
                  {{ o.typeName }} · {{ o.visitDate }} 入园，当日一次有效
                </span>
                <span v-if="t.refund" class="field-text">
                  退款申请（{{ t.refund.status }}）：{{ t.refund.reason }}
                </span>
                <span
                  v-if="t.refund && t.refund.auditOpinion"
                  class="field-text"
                >
                  审核意见：{{ t.refund.auditOpinion }}
                </span>
                <span
                  v-if="t.refund && t.refund.status === '待审核'"
                  class="field-text"
                >
                  申请已提交，由景区管理员在后台审核，请耐心等待。
                </span>

                <!-- 游园评价（F16）：已检票门票，一票一评 -->
                <div v-if="t.status === '已检票'" class="review-block">
                  <template v-if="t.review">
                    <span class="stars num" aria-label="我的评分">{{ stars(t.review.rating) }}</span>
                    <span class="field-text">我的评价：{{ t.review.content || '（仅星级）' }}</span>
                  </template>
                  <template v-else>
                    <button class="link" @click="startReview(t)">写评价</button>
                    <div class="review-form">
                      <div class="star-pick">
                        <button
                          v-for="n in 5"
                          :key="n"
                          class="star"
                          :class="{ on: n <= reviewRating }"
                          type="button"
                          @click="reviewRating = n"
                        >
                          ★
                        </button>
                        <span class="num star-val">{{ reviewRating }} 星</span>
                      </div>
                      <textarea
                        v-model="reviewText"
                        rows="2"
                        maxlength="255"
                        placeholder="说说游园感受（选填）"
                      ></textarea>
                      <p class="err">{{ reviewError }}</p>
                      <div class="refund-actions">
                        <button class="btn btn-primary btn-sm" @click="submitReview(t)">提交评价</button>
                        <button class="btn btn-ghost btn-sm" @click="reviewFor = null">取消</button>
                      </div>
                    </div>
                  </template>
                </div>
              </div>

              <div v-if="refundFor === t.ticketNo" class="refund-form">
                <label :for="'reason-' + t.ticketNo">退票原因</label>
                <textarea
                  :id="'reason-' + t.ticketNo"
                  v-model="reason"
                  rows="2"
                  placeholder="请填写退票原因（必填）"
                ></textarea>
                <p class="err">{{ refundError }}</p>
                <div class="refund-actions">
                  <button class="btn btn-primary btn-sm" @click="submitRefund">确认申请</button>
                  <button class="btn btn-ghost btn-sm" @click="refundFor = null">取消</button>
                </div>
              </div>
            </div>
          </li>
        </ul>

        <footer v-if="o.status === '待支付'" class="o-foot">
          <span class="field-text">订单待支付，支付后即刻出票；取消将自动回补当日额度。</span>
          <div class="o-foot-actions">
            <button
              class="btn btn-primary btn-sm"
              :disabled="busyOrder === o.id"
              @click="doPay(o)"
            >
              {{ busyOrder === o.id ? '处理中…' : '模拟支付' }}
            </button>
            <button
              class="btn btn-ghost btn-sm"
              :disabled="busyOrder === o.id"
              @click="doCancel(o)"
            >
              取消订单
            </button>
          </div>
        </footer>

        <!-- 门票改期（F03 扩展）：游园日前，整单未检票门票迁移 -->
        <footer v-else-if="canReschedule(o)" class="o-foot">
          <span class="field-text">游园日之前可改期：仅未检票门票迁移，原日期额度自动回补。</span>
          <div class="o-foot-actions">
            <template v-if="reschedFor === o.id">
              <input v-model="newDate" type="date" :min="tomorrowMin" class="inline-input num" />
              <button class="btn btn-primary btn-sm" @click="submitReschedule(o)">确认改期</button>
              <button class="btn btn-ghost btn-sm" @click="reschedFor = null">取消</button>
            </template>
            <button v-else class="btn btn-ghost btn-sm" @click="startReschedule(o)">改期</button>
          </div>
        </footer>
        <p v-if="reschedError && reschedFor === o.id" class="err">{{ reschedError }}</p>

        <p v-if="actionError && o.status === '待支付'" class="err">{{ actionError }}</p>
      </article>

      <p v-if="!orders.length" class="muted no-match">该状态下暂无订单。</p>
      <p class="refund-rule field-text">
        退票规则：仅游园日之前、未检票门票可申请退票，退款金额为票面价，审核通过后当日额度自动回补。
      </p>
    </template>
  </div>
</template>

<style scoped>
.orders {
  padding: 32px 24px 0;
}

.orders h1 {
  margin: 0 0 20px;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 22px;
}

.stat {
  padding: 18px 22px;
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.stat-label {
  font-size: 13.5px;
  color: var(--ink-2);
}

.stat-value {
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.02em;
}

.stat-unit {
  font-size: 12.5px;
  color: var(--ink-3);
}

/* 游客端可视化面板 */
.charts {
  display: grid;
  grid-template-columns: 1.6fr 1fr;
  gap: 16px;
  margin-bottom: 22px;
}

.chart {
  padding: 16px 18px;
}

.chart-title {
  margin: 0 0 8px;
  font-size: 14.5px;
  font-weight: 600;
  color: var(--ink-2);
}

/* 评价（F16） */
.review-block {
  flex-basis: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  border-top: 1px dashed var(--border);
  padding-top: 10px;
  margin-top: 4px;
}

.stars {
  color: #d99a1f;
  letter-spacing: 2px;
  font-size: 15px;
}

.review-form {
  flex-basis: 100%;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.star-pick {
  display: flex;
  align-items: center;
  gap: 4px;
}

.star {
  border: none;
  background: none;
  cursor: pointer;
  font-size: 20px;
  line-height: 1;
  color: var(--border-strong);
  padding: 2px;
}

.star.on {
  color: #d99a1f;
}

.star-val {
  margin-left: 6px;
  font-size: 13.5px;
  color: var(--ink-2);
}

.review-form textarea {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  font: inherit;
  font-size: 14.5px;
  resize: vertical;
}

.review-form textarea:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
}

.inline-input {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  padding: 5px 8px;
  font: inherit;
  font-size: 14px;
}

.filters {
  display: flex;
  gap: 8px;
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
}

.filter-chip:hover {
  border-color: var(--ink-2);
}

.filter-chip.active {
  border-color: var(--accent);
  background: var(--accent-soft);
  color: var(--accent-hover);
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
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border);
}

.o-id {
  display: flex;
  align-items: baseline;
  gap: 14px;
  flex-wrap: wrap;
}

.o-no {
  font-weight: 600;
  font-size: 15px;
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

.t-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.t-none {
  padding: 12px 0;
}

.t-item {
  padding: 12px 0;
}

.t-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.t-no {
  font-size: 14.5px;
  font-weight: 600;
  letter-spacing: 0.04em;
  flex: 1;
  min-width: 10em;
}

.t-actions {
  margin-left: auto;
  display: flex;
  gap: 18px;
  align-items: center;
}

/* 评价入口（F16）：与「申请退票」同级，已检票门票行内直出 */
.link.review {
  color: #b47405;
  font-weight: 600;
}

.link.review:hover {
  color: #d99a1f;
}

.review.done {
  color: var(--ink-3);
  font-size: 13px;
}

.t-detail {
  margin-top: 12px;
  padding: 16px;
  background: #fafbfa;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  display: flex;
  gap: 18px;
  align-items: flex-start;
  flex-wrap: wrap;
}

.t-meta {
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-width: 220px;
}

.code {
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.05em;
}

.refund-form {
  flex-basis: 100%;
  display: flex;
  flex-direction: column;
  gap: 8px;
  border-top: 1px dashed var(--border-strong);
  padding-top: 14px;
}

.refund-form label {
  font-size: 13px;
  color: var(--ink-2);
}

.refund-form textarea {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  font: inherit;
  font-size: 14.5px;
  resize: vertical;
}

.refund-form textarea:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
}

.refund-form .err:empty {
  display: none;
}

.refund-actions {
  display: flex;
  gap: 10px;
}

.o-foot {
  margin-top: 12px;
  padding-top: 14px;
  border-top: 1px dashed var(--border-strong);
  display: flex;
  align-items: center;
  gap: 14px;
  justify-content: space-between;
  flex-wrap: wrap;
}

.o-foot-actions {
  display: flex;
  gap: 10px;
}

.empty {
  padding: 48px 24px;
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

.empty .muted {
  margin: 0 0 8px;
}

.no-match {
  padding: 32px 0;
  text-align: center;
}

.refund-rule {
  margin: 4px 0 0;
}

@media (max-width: 767px) {
  .stats {
    grid-template-columns: 1fr;
  }

  .charts {
    grid-template-columns: 1fr;
  }

  .t-row {
    flex-wrap: wrap;
    gap: 10px;
  }

  .t-actions {
    margin-left: 0;
  }
}
</style>
