<script setup>
import { ref, computed } from 'vue'
import QrMock from '../../components/QrMock.vue'
import StatusChip from '../../components/StatusChip.vue'
import { useTicketingStore, MAX_QTY } from '../../stores/ticketing'
import { api } from '../../api'
import { weekdayLabel, shortDate, todayStr } from '../../mock/seed'

// F06 现场售票：票务员为游客代客下单，登记线下支付方式，提交即出票（channel=窗口）
const store = useTicketingStore()
store.ensure().catch(() => {})

const typeId = ref('ADULT')
const visitDate = ref('')
const quantity = ref(1)
const visitorName = ref('')
const payMethod = ref('现金')

const error = ref('')
const submitting = ref(false)

const type = computed(() => store.types.find((t) => t.id === typeId.value))
const remaining = computed(() =>
  visitDate.value ? store.remaining(visitDate.value, typeId.value) : 0,
)
const maxQty = computed(() => Math.min(MAX_QTY, remaining.value))
const total = computed(() => (type.value ? type.value.price * quantity.value : 0))
const canSubmit = computed(
  () =>
    visitDate.value &&
    remaining.value >= quantity.value &&
    visitorName.value.trim().length > 0 &&
    !submitting.value,
)

const dateOptions = computed(() =>
  store.openDates.map((d) => ({
    date: d,
    label: weekdayLabel(d),
    sub: shortDate(d),
    closed: !!store.stock[d]?.closed,
    remaining: store.remaining(d, typeId.value),
  })),
)

// 默认选中第一个可售日期
if (!visitDate.value) {
  const first = dateOptions.value.find((o) => !o.closed && o.remaining > 0)
  if (first) visitDate.value = first.date
}

function pickDate(o) {
  if (o.closed || o.remaining <= 0) return
  visitDate.value = o.date
  error.value = ''
}

function dec() {
  quantity.value = Math.max(1, quantity.value - 1)
}

function inc() {
  quantity.value = Math.min(maxQty.value || 1, quantity.value + 1)
}

// 出票成功结果 & 最近窗口订单
const lastOrder = ref(null)
const recent = ref([])

async function loadRecent() {
  try {
    const data = await api('/orders/mine')
    recent.value = data.orders.slice(0, 5)
  } catch {
    recent.value = []
  }
}

loadRecent()

async function submit() {
  if (!visitorName.value.trim()) {
    error.value = '请填写游客姓名'
    return
  }
  error.value = ''
  submitting.value = true
  try {
    const data = await api('/orders', {
      method: 'POST',
      body: {
        typeId: typeId.value,
        visitDate: visitDate.value,
        quantity: quantity.value,
        visitorName: visitorName.value.trim(),
        payMethod: payMethod.value,
      },
    })
    lastOrder.value = data.order
    // 占额后本地下拉余量同步，保持面板准确
    const per = store.stock[visitDate.value]?.per[typeId.value]
    if (per) per.sold = Math.min(per.quota, per.sold + quantity.value)
    loadRecent()
  } catch (e) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}

function again() {
  lastOrder.value = null
  quantity.value = 1
  visitorName.value = ''
  error.value = ''
}
</script>

<template>
  <div class="sell container">
    <h1>现场售票</h1>

    <!-- 出票成功结果 -->
    <div v-if="lastOrder" class="done-card card">
      <div class="done-icon">✓</div>
      <h2>已出票，请游客出示核销码入园</h2>
      <p class="muted num">
        订单 {{ lastOrder.orderNo }} · {{ lastOrder.typeName }} × {{ lastOrder.quantity }} ·
        游园日期 {{ lastOrder.visitDate }} · 支付方式 {{ lastOrder.payMethod || '线下' }}
      </p>
      <ul class="done-tickets">
        <li v-for="t in lastOrder.tickets" :key="t.ticketNo">
          <QrMock :code="t.ticketNo" :size="112" />
          <div class="dt-meta">
            <span class="code num">{{ t.ticketNo }}</span>
            <StatusChip :status="t.status" />
            <span class="field-text">游园当日出示，当日一次有效</span>
          </div>
        </li>
      </ul>
      <div class="done-actions">
        <button class="btn btn-primary" @click="again">再售一单</button>
        <router-link to="/operator/orders" class="btn btn-ghost">查看售票记录</router-link>
      </div>
    </div>

    <template v-else>
      <div class="form-grid">
        <div class="form-left">
          <section class="card block">
            <h2>选择票种</h2>
            <div class="type-list">
              <button
                v-for="t in store.types"
                :key="t.id"
                class="type-item"
                :class="{ selected: t.id === typeId, disabled: t.status !== '在售' }"
                :disabled="t.status !== '在售'"
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
                :class="{ selected: visitDate === o.date, closed: o.closed, full: !o.closed && o.remaining <= 0 }"
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
          </section>

          <section class="card block">
            <h2>购买数量</h2>
            <div class="qty-row">
              <button class="btn btn-ghost btn-sm" :disabled="quantity <= 1" @click="dec">−</button>
              <span class="qty num">{{ quantity }}</span>
              <button class="btn btn-ghost btn-sm" :disabled="quantity >= maxQty" @click="inc">+</button>
              <span v-if="visitDate" class="field-text">该日期余 {{ remaining }} 张，单笔限购 {{ MAX_QTY }} 张</span>
            </div>
          </section>

          <section class="card block">
            <h2>游客与支付方式（线下）</h2>
            <div class="field">
              <label for="visitor-name">游客姓名</label>
              <input
                id="visitor-name"
                v-model="visitorName"
                placeholder="请填写游客姓名（用于登记建档）"
                maxlength="50"
              />
            </div>
            <div class="field pay-field">
              <label>支付方式</label>
              <div class="pay-options">
                <button
                  v-for="m in ['现金', '扫码']"
                  :key="m"
                  class="pay-pill"
                  :class="{ active: payMethod === m }"
                  @click="payMethod = m"
                >
                  {{ m }}支付
                </button>
              </div>
            </div>
          </section>
        </div>

        <aside class="form-right">
          <div class="summary card">
            <h2>订单明细</h2>
            <dl>
              <div>
                <dt>票种</dt>
                <dd>{{ type?.name || '—' }}</dd>
              </div>
              <div>
                <dt>游园日期</dt>
                <dd class="num">{{ visitDate || '未选择' }}</dd>
              </div>
              <div>
                <dt>游客</dt>
                <dd>{{ visitorName.trim() || '—' }}</dd>
              </div>
              <div>
                <dt>支付方式</dt>
                <dd>{{ payMethod }}（线下登记）</dd>
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
            <button class="btn btn-primary btn-block" :disabled="!canSubmit" @click="submit">
              {{ submitting ? '提交中…' : '登记支付并出票' }}
            </button>
            <p class="field-text">
              窗口渠道提交即视为支付成功并出票，与线上渠道使用同一套每日额度校验。
            </p>
            <p v-if="error" class="err">{{ error }}</p>
          </div>
        </aside>
      </div>

      <!-- 最近窗口订单 -->
      <section v-if="recent.length" class="card recent">
        <h2>最近窗口订单</h2>
        <ul>
          <li v-for="o in recent" :key="o.id" class="r-row">
            <span class="r-no num">{{ o.orderNo }}</span>
            <span>{{ o.visitorName || '散客' }}</span>
            <span class="num">{{ o.typeName }} × {{ o.quantity }}</span>
            <StatusChip :status="o.status" />
            <span class="r-amount num">¥{{ o.totalAmount }}</span>
          </li>
        </ul>
        <router-link to="/operator/orders" class="link">查看全部记录 →</router-link>
      </section>
    </template>
  </div>
</template>

<style scoped>
.sell {
  padding: 32px 24px 0;
}

.sell h1 {
  margin: 0 0 20px;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 18px;
  align-items: start;
}

.form-left {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.block {
  padding: 18px 22px;
}

.block h2 {
  margin: 0 0 12px;
  font-size: 15.5px;
}

.type-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.type-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
  text-align: left;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: #fff;
  padding: 12px 14px;
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.type-item:hover:not(:disabled) {
  border-color: var(--ink-2);
}

.type-item.selected {
  border-color: var(--accent);
  background: var(--accent-soft);
}

.type-item.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.t-name {
  font-weight: 600;
}

.t-app {
  font-size: 12.5px;
  color: var(--ink-3);
}

.t-price {
  font-size: 14px;
  color: var(--accent-hover);
  font-weight: 600;
}

.date-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 8px;
}

.date-pill {
  border: 1px solid var(--border-strong);
  background: #fff;
  border-radius: var(--radius-sm);
  padding: 8px 4px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
  cursor: pointer;
  transition: border-color 0.15s ease;
}

.date-pill:hover:not(:disabled) {
  border-color: var(--ink-2);
}

.date-pill.selected {
  border-color: var(--accent);
  background: var(--accent-soft);
}

.date-pill:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.d-label {
  font-size: 13px;
  color: var(--ink-2);
}

.d-sub {
  font-size: 11.5px;
  color: var(--ink-3);
}

.d-rest {
  font-size: 11.5px;
  color: var(--accent-hover);
}

.d-rest.soldout {
  color: var(--rose);
}

.qty-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.qty {
  font-size: 22px;
  font-weight: 650;
  min-width: 2em;
  text-align: center;
}

.pay-field {
  margin-top: 14px;
}

.pay-options {
  display: flex;
  gap: 10px;
}

.pay-pill {
  border: 1px solid var(--border-strong);
  background: #fff;
  border-radius: 999px;
  padding: 6px 18px;
  font: inherit;
  font-size: 14px;
  color: var(--ink-2);
  cursor: pointer;
  transition: all 0.15s ease;
}

.pay-pill:hover {
  border-color: var(--ink-2);
}

.pay-pill.active {
  border-color: var(--accent);
  background: var(--accent-soft);
  color: var(--accent-hover);
}

.summary {
  padding: 18px 22px;
}

.summary h2 {
  margin: 0 0 10px;
  font-size: 15.5px;
}

.summary dl {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.summary dl div {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.summary dt {
  color: var(--ink-2);
  font-size: 13.5px;
}

.summary dd {
  margin: 0;
  text-align: right;
  font-size: 14px;
  overflow-wrap: anywhere;
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 12px 0;
  padding-top: 12px;
  border-top: 1px dashed var(--border-strong);
}

.total {
  font-size: 24px;
  font-weight: 650;
  color: var(--accent-hover);
}

/* 出票结果 */
.done-card {
  padding: 26px 28px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: flex-start;
}

.done-icon {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  background: var(--accent-soft);
  color: var(--accent-hover);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  font-weight: 700;
}

.done-card h2 {
  margin: 0;
}

.done-tickets {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}

.done-tickets li {
  display: flex;
  gap: 12px;
}

.dt-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.dt-meta .chip {
  align-self: flex-start;
}

.code {
  font-weight: 600;
  letter-spacing: 0.05em;
}

.done-actions {
  display: flex;
  gap: 10px;
  margin-top: 6px;
}

/* 最近订单 */
.recent {
  margin-top: 18px;
  padding: 18px 22px;
}

.recent h2 {
  margin: 0 0 10px;
  font-size: 15.5px;
}

.recent ul {
  list-style: none;
  margin: 0 0 8px;
  padding: 0;
}

.r-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 9px 0;
  border-bottom: 1px solid var(--border);
  font-size: 14px;
  flex-wrap: wrap;
}

.r-no {
  font-weight: 600;
  min-width: 15em;
}

.r-amount {
  margin-left: auto;
  font-weight: 600;
}

@media (max-width: 900px) {
  .form-grid {
    grid-template-columns: 1fr;
  }

  .date-grid {
    grid-template-columns: repeat(4, 1fr);
  }

  .type-list {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 767px) {
  .date-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
