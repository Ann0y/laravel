<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QrMock from '../components/QrMock.vue'
import { useTicketingStore, qtyLimit } from '../stores/ticketing'
import { weekdayLabel, shortDate, todayStr } from '../mock/seed'

const store = useTicketingStore()
const route = useRoute()
const router = useRouter()

const stage = ref('form') // form | pay | done
const order = ref(null)
const error = ref('')
const submitting = ref(false)

function defaultType() {
  const q = route.query.type
  return store.types.some((t) => t.id === q) ? q : 'ADULT'
}

const typeId = ref(defaultType())
const visitDate = ref('')
const quantity = ref(1)

const type = computed(() => store.types.find((t) => t.id === typeId.value))
const remaining = computed(() =>
  visitDate.value ? store.remaining(visitDate.value, typeId.value) : 0,
)
const qtyRange = computed(() => qtyLimit(typeId.value))
const maxQty = computed(() => Math.min(qtyRange.value.max, remaining.value))
const total = computed(() => (type.value ? type.value.price * quantity.value : 0))

const dateOptions = computed(() =>
  store.openDates.map((d) => ({
    date: d,
    label: weekdayLabel(d),
    sub: shortDate(d),
    closed: !!store.stock[d]?.closed,
    remaining: store.remaining(d, typeId.value),
  })),
)

function ensureValidDate() {
  const cur = dateOptions.value.find((o) => o.date === visitDate.value)
  if (!cur || cur.closed || cur.remaining <= 0) {
    const first = dateOptions.value.find((o) => !o.closed && o.remaining > 0)
    visitDate.value = first ? first.date : ''
  }
}

ensureValidDate()

watch(
  () => route.query.type,
  (v) => {
    if (store.types.some((t) => t.id === v)) typeId.value = v
  },
)

watch(typeId, () => {
  ensureValidDate()
  quantity.value = qtyRange.value.min
  error.value = ''
})

function pickDate(o) {
  if (o.closed || o.remaining <= 0) return
  visitDate.value = o.date
  error.value = ''
}

function dec() {
  quantity.value = Math.max(qtyRange.value.min, quantity.value - 1)
}

function inc() {
  quantity.value = Math.min(maxQty.value || 1, quantity.value + 1)
}

async function submitOrder() {
  error.value = ''
  submitting.value = true
  try {
    order.value = await store.createOrder(typeId.value, visitDate.value, quantity.value)
    stage.value = 'pay'
  } catch (e) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}

async function payNow() {
  error.value = ''
  try {
    order.value = await store.pay(order.value.id)
    stage.value = 'done'
  } catch (e) {
    error.value = e.message
    stage.value = 'form'
  }
}

async function cancelOrder() {
  error.value = ''
  try {
    await store.cancel(order.value.id)
    order.value = null
    stage.value = 'form'
  } catch (e) {
    error.value = e.message
  }
}
</script>

<template>
  <div class="buy container">
    <div class="buy-head">
      <button class="link" @click="router.push('/')">返回首页</button>
      <h1>在线购票</h1>
    </div>

    <ol class="steps-bar" aria-label="购票步骤">
      <li :class="{ active: stage === 'form', done: stage !== 'form' }">选票</li>
      <li :class="{ active: stage === 'pay', done: stage === 'done' }">支付</li>
      <li :class="{ active: stage === 'done', done: stage === 'done' }">出票</li>
    </ol>

    <!-- 阶段一：选票 -->
    <div v-if="stage === 'form'" class="form-grid">
      <div class="form-left">
        <section class="card block">
          <h2>选择票种</h2>
          <div class="type-list">
            <button
              v-for="t in store.types"
              :key="t.id"
              class="type-item"
              :class="{ selected: t.id === typeId }"
              @click="typeId = t.id"
            >
              <span class="t-name">{{ t.name }}</span>
              <span class="t-app">{{ t.applicable }}</span>
              <span class="t-price num">¥{{ t.price }} / 张</span>
            </button>
          </div>
        </section>

        <section class="card block">
          <h2>选择游园日期</h2>
          <div class="date-grid">
            <button
              v-for="o in dateOptions"
              :key="o.date"
              class="date-pill"
              :class="{
                selected: visitDate === o.date,
                full: !o.closed && o.remaining <= 0,
                closed: o.closed,
              }"
              :disabled="o.closed || o.remaining <= 0"
              @click="pickDate(o)"
            >
              <span class="d-label">{{ o.label }}</span>
              <span class="d-sub num">{{ o.sub }}</span>
              <span v-if="o.closed" class="d-rest">闭园</span>
              <span v-else-if="o.remaining <= 0" class="d-rest soldout">售罄</span>
              <span v-else class="d-rest num">余 {{ o.remaining }}</span>
            </button>
          </div>
          <p class="field-text">每个日期按票种每日额度限量发售，售完即止。</p>
        </section>

        <section class="card block">
          <h2>购买数量</h2>
          <div class="qty-row">
            <button class="btn btn-ghost btn-sm" :disabled="quantity <= qtyRange.min" @click="dec">−</button>
            <span class="qty num">{{ quantity }}</span>
            <button class="btn btn-ghost btn-sm" :disabled="quantity >= maxQty" @click="inc">
              +
            </button>
            <span v-if="visitDate" class="field-text">该日期余 {{ remaining }} 张，{{ typeId === 'GROUP' ? '团体票单笔 10-30 张（10 人成团）' : `单笔限购 ${qtyRange.max} 张` }}</span>
          </div>
        </section>
      </div>

      <aside class="form-right">
        <div class="summary card">
          <h2>订单明细</h2>
          <dl>
            <div>
              <dt>票种</dt>
              <dd>{{ type?.name }}</dd>
            </div>
            <div>
              <dt>游园日期</dt>
              <dd>{{ visitDate ? `${visitDate}（${weekdayLabel(visitDate)}）` : '未选择' }}</dd>
            </div>
            <div>
              <dt>单价</dt>
              <dd class="num">¥{{ type?.price }}</dd>
            </div>
            <div>
              <dt>数量</dt>
              <dd class="num">{{ quantity }} 张</dd>
            </div>
          </dl>
          <div class="total-row">
            <span>合计</span>
            <span class="total num">¥{{ total }}</span>
          </div>
          <p v-if="visitDate && remaining < quantity" class="err">
            该日期{{ type?.name }}剩余 {{ remaining }} 张，请减少数量或另选日期。
          </p>
          <button
            class="btn btn-primary btn-block"
            :disabled="!visitDate || remaining < quantity || quantity < qtyRange.min || submitting"
            @click="submitOrder"
          >
            {{ submitting ? '提交中…' : '提交订单' }}
          </button>
          <p class="field-text">
            提交后可在「我的订单」中继续支付或取消；取消订单将自动回补当日额度。
          </p>
          <p v-if="error" class="err">{{ error }}</p>
        </div>
      </aside>
    </div>

    <!-- 阶段二：模拟支付 -->
    <div v-else-if="stage === 'pay'" class="pay-wrap">
      <div class="card pay-card">
        <h2>确认支付</h2>
        <dl class="pay-meta">
          <div>
            <dt>订单号</dt>
            <dd class="num">{{ order.orderNo }}</dd>
          </div>
          <div>
            <dt>票种</dt>
            <dd>{{ order.typeName }} × {{ order.quantity }}</dd>
          </div>
          <div>
            <dt>游园日期</dt>
            <dd class="num">{{ order.visitDate }}</dd>
          </div>
          <div>
            <dt>应付金额</dt>
            <dd class="pay-amount num">¥{{ order.totalAmount }}</dd>
          </div>
        </dl>
        <div class="pay-actions">
          <button class="btn btn-primary" @click="payNow">模拟支付</button>
          <button class="btn btn-ghost" @click="cancelOrder">取消订单</button>
        </div>
        <p class="field-text">本期为毕业设计原型，支付环节以模拟支付代替真实支付网关。</p>
      </div>
    </div>

    <!-- 阶段三：出票成功 -->
    <div v-else class="done-wrap">
      <div class="card done-card">
        <div class="done-icon">✓</div>
        <h2>出票成功</h2>
        <p class="muted">
          已出 {{ order.quantity }} 张{{ order.typeName }}，游园日期
          {{ order.visitDate }}。门票可在「我的订单」中随时出示。
        </p>
        <ul class="done-tickets">
          <li v-for="t in order.tickets" :key="t.ticketNo">
            <QrMock :code="t.ticketNo" :size="112" />
            <div class="dt-meta">
              <span class="code num">{{ t.ticketNo }}</span>
              <span class="field-text">游园当日出示，当日一次有效</span>
            </div>
          </li>
        </ul>
        <RouterLink to="/orders" class="btn btn-primary">查看我的订单</RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.buy {
  padding: 32px 24px 0;
}

.buy-head .link {
  margin-bottom: 10px;
}

.buy-head h1 {
  margin: 0 0 18px;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.steps-bar {
  list-style: none;
  display: flex;
  gap: 8px;
  margin: 0 0 28px;
  padding: 0;
}

.steps-bar li {
  font-size: 13.5px;
  color: var(--ink-3);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 5px 16px;
}

.steps-bar li.active {
  color: var(--accent-hover);
  border-color: var(--accent);
  background: var(--accent-soft);
}

.steps-bar li.done {
  color: var(--ink);
}

.form-grid {
  display: grid;
  grid-template-columns: 1.55fr 0.85fr;
  gap: 28px;
  align-items: start;
}

.block {
  padding: 22px 24px;
  margin-bottom: 16px;
}

.block h2,
.summary h2,
.pay-card h2,
.done-card h2 {
  margin: 0 0 16px;
  font-size: 16.5px;
  font-weight: 620;
}

.type-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.type-item {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: baseline;
  gap: 4px 14px;
  text-align: left;
  padding: 13px 16px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: #fff;
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.type-item:hover {
  border-color: var(--ink-2);
}

.type-item.selected {
  border-color: var(--accent);
  background: var(--accent-soft);
  box-shadow: 0 0 0 3px var(--accent-ring);
}

.t-name {
  font-weight: 600;
  font-size: 15px;
}

.t-app {
  color: var(--ink-2);
  font-size: 13px;
}

.t-price {
  color: var(--accent-hover);
  font-weight: 600;
}

.date-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 8px;
  margin-bottom: 12px;
}

.date-pill {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 10px 4px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: #fff;
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.date-pill:hover:not(:disabled) {
  border-color: var(--ink-2);
}

.date-pill.selected {
  border-color: var(--accent);
  background: var(--accent-soft);
  box-shadow: 0 0 0 3px var(--accent-ring);
}

.date-pill.selected .d-label {
  color: var(--accent-hover);
}

.date-pill:disabled {
  cursor: not-allowed;
  color: var(--ink-3);
}

.d-label {
  font-size: 14px;
  font-weight: 600;
}

.d-sub {
  font-size: 12px;
  color: var(--ink-3);
}

.d-rest {
  font-size: 11.5px;
  color: var(--ink-2);
}

.d-rest.soldout {
  color: var(--rose);
}

.qty-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.qty {
  font-size: 18px;
  font-weight: 650;
  min-width: 28px;
  text-align: center;
}

.summary {
  padding: 22px 24px;
  position: sticky;
  top: 88px;
}

.summary dl {
  margin: 0;
  display: flex;
  flex-direction: column;
}

.summary dl div {
  display: flex;
  justify-content: space-between;
  padding: 7px 0;
}

.summary dt {
  color: var(--ink-2);
  font-size: 14px;
}

.summary dd {
  margin: 0;
  font-size: 14.5px;
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  border-top: 1px solid var(--border);
  margin-top: 8px;
  padding-top: 14px;
  margin-bottom: 16px;
  font-size: 15px;
}

.total {
  font-size: 26px;
  font-weight: 650;
  letter-spacing: -0.02em;
  color: var(--accent-hover);
}

.summary .field-text {
  margin: 12px 0 0;
}

.form-right .err {
  margin-bottom: 10px;
}

.pay-wrap,
.done-wrap {
  max-width: 560px;
}

.pay-card,
.done-card {
  padding: 28px 30px;
}

.pay-meta {
  margin: 0 0 8px;
}

.pay-meta div {
  display: flex;
  justify-content: space-between;
  padding: 7px 0;
}

.pay-meta dt {
  color: var(--ink-2);
  font-size: 14px;
}

.pay-meta dd {
  margin: 0;
}

.pay-amount {
  font-size: 24px;
  font-weight: 650;
  color: var(--accent-hover);
}

.pay-actions {
  display: flex;
  gap: 12px;
  margin: 18px 0 14px;
}

.pay-card .field-text {
  margin: 0;
}

.done-card {
  text-align: center;
}

.done-icon {
  width: 48px;
  height: 48px;
  margin: 0 auto 14px;
  border-radius: 999px;
  background: var(--accent);
  color: #fff;
  font-size: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 18px rgba(14, 122, 90, 0.3);
}

.done-card h2 {
  margin-bottom: 6px;
}

.done-card .muted {
  margin: 0 0 22px;
}

.done-tickets {
  list-style: none;
  margin: 0 0 24px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
  text-align: left;
}

.done-tickets li {
  display: flex;
  gap: 16px;
  align-items: center;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
}

.dt-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.code {
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.04em;
}

@media (max-width: 960px) {
  .form-grid {
    grid-template-columns: 1fr;
  }

  .summary {
    position: static;
  }

  .date-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media (max-width: 520px) {
  .date-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
