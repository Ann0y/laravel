<script setup>
import { ref, computed, onMounted } from 'vue'
import StatusChip from '../components/StatusChip.vue'
import { useTicketingStore } from '../stores/ticketing'
import { api } from '../api'
import { weekdayLabel, shortDate } from '../mock/seed'

// F17 园内项目：入园后单独购买的缆车 / 玻璃桥 / 游船等项目票（需持当日有效门票）
const store = useTicketingStore()
store.ensure().catch(() => {})

const items = ref([])
const selItem = ref(null) // { itemCode, name, price, description }
const visitDate = ref('')
const quantity = ref(1)
const maxQty = 5 // 项目票单笔限购

const error = ref('')
const submitting = ref(false)
const bought = ref(null) // 刚购买成功的订单（显示核销码）

const myOrders = ref([])
const myLoading = ref(true)

const dateOptions = computed(() =>
  store.openDates
    .filter((d) => d !== store.openDates[0]) // 项目票仅可购买未开始的游园日期
    .map((d) => ({
      date: d,
      label: weekdayLabel(d),
      sub: shortDate(d),
      closed: !!store.stock[d]?.closed,
    })),
)

const total = computed(() => (selItem.value && visitDate.value ? selItem.value.price * quantity.value : 0))

const icons = { CABLEWAY: '🚡', GLASS_BRIDGE: '🌉', CRUISE: '🛥️', GUIDE: '🎧', SHUTTLE: '🚌' }

async function pickItem(it) {
  selItem.value = it
  quantity.value = 1
  error.value = ''
  if (!visitDate.value) {
    const first = dateOptions.value.find((d) => !d.closed)
    visitDate.value = first ? first.date : ''
  }
}

function dec() {
  quantity.value = Math.max(1, quantity.value - 1)
}

function inc() {
  quantity.value = Math.min(maxQty, quantity.value + 1)
}

async function loadMyOrders() {
  try {
    const data = await api('/park/orders/my')
    myOrders.value = data.orders
  } catch {
    myOrders.value = []
  } finally {
    myLoading.value = false
  }
}

async function buy() {
  if (!selItem.value || !visitDate.value) return
  error.value = ''
  submitting.value = true
  try {
    const data = await api('/park/orders', {
      method: 'POST',
      body: { itemCode: selItem.value.itemCode, visitDate: visitDate.value, quantity: quantity.value },
    })
    bought.value = data.order
    selItem.value = null
    await loadMyOrders()
  } catch (e) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}

function reset() {
  bought.value = null
  error.value = ''
}

onMounted(() => {
  api('/park/items').then((d) => (items.value = d.items)).catch(() => {})
  loadMyOrders()
})
</script>

<template>
  <div class="extras container">
    <h1>园内项目</h1>
    <p class="muted page-sub">入园后可单独购买的游玩项目。购买需已完成当日入园门票（未退款）的预订；项目票售出后不可退改，游玩当日凭核销码在项目口核销。</p>

    <div v-if="bought" class="bought card">
      <h3>购买成功 🎉 {{ bought.itemName }} × {{ bought.quantity }}</h3>
      <p class="muted">{{ bought.visitDate }} 游玩，当日有效。</p>
      <div class="codes">
        <div v-for="t in bought.tickets" :key="t.ticketCode" class="code-row">
          <span class="code num">{{ t.ticketCode }}</span>
          <span class="muted">{{ t.status }}</span>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-ghost btn-sm" @click="reset">继续购买</button>
        <router-link class="btn btn-ghost btn-sm" to="/orders">去查看门票</router-link>
      </div>
    </div>

    <!-- 项目卡片 -->
    <div class="item-grid" v-if="!bought">
      <button
        v-for="it in items"
        :key="it.itemCode"
        class="item card"
        :class="{ selected: selItem?.itemCode === it.itemCode }"
        @click="pickItem(it)"
      >
        <span class="icon">{{ icons[it.itemCode] || '🎫' }}</span>
        <span class="name">{{ it.name }}</span>
        <span class="price num">¥{{ it.price }}</span>
        <span class="desc muted">{{ it.description }}</span>
      </button>
    </div>

    <section v-if="selItem && !bought" class="buy card">
      <h2>购买：{{ selItem.name }}</h2>
      <div class="row">
        <span class="label">游玩日期</span>
        <div class="date-strip">
          <button
            v-for="d in dateOptions"
            :key="d.date"
            class="date-opt"
            :class="{ active: visitDate === d.date, closed: d.closed }"
            :disabled="d.closed"
            @click="visitDate = d.date"
          >
            <span class="num">{{ d.label }}</span>
            <span class="muted num">{{ d.sub }}</span>
          </button>
        </div>
      </div>
      <div class="row">
        <span class="label">数量</span>
        <span class="qty-group">
          <button class="btn btn-ghost btn-sm" :disabled="quantity <= 1" @click="dec">−</button>
          <span class="qty num">{{ quantity }}</span>
          <button class="btn btn-ghost btn-sm" :disabled="quantity >= maxQty" @click="inc">+</button>
          <span class="muted">单笔限购 {{ maxQty }} 份</span>
        </span>
      </div>
      <div class="row total-row">
        <span class="label">合计</span>
        <span class="num total">¥{{ total }}</span>
        <button
          class="btn btn-primary"
          :disabled="!visitDate || submitting"
          @click="buy"
        >
          {{ submitting ? '处理中…' : '确认购买（模拟支付）' }}
        </button>
      </div>
      <p v-if="error" class="err">{{ error }}</p>
    </section>

    <!-- 我的项目票 -->
    <section class="mine">
      <h2>我的项目票</h2>
      <div v-if="myLoading" class="muted">载入中…</div>
      <p v-else-if="!myOrders.length" class="muted">还没有项目票，选一个项目开始吧。</p>
      <article v-for="o in myOrders" :key="o.id" class="card io">
        <header class="io-head">
          <div>
            <span class="io-no num">{{ o.orderNo }}</span>
            <span class="io-item">{{ o.itemName }} × {{ o.quantity }}</span>
          </div>
          <div class="io-side">
            <span class="chip-soft">{{ o.visitDate }}</span>
            <StatusChip :status="o.status === '已支付' ? '已支付' : '已使用'" />
            <span class="num">¥{{ o.totalAmount }}</span>
          </div>
        </header>
        <ul class="io-tickets">
          <li v-for="t in o.tickets" :key="t.ticketCode">
            <span class="code num">{{ t.ticketCode }}</span>
            <span class="muted">{{ t.status === '未核销' ? '未核销，游玩当日项目口出示' : '已核销 ' + (t.checkTime || '').slice(0, 10) }}</span>
          </li>
        </ul>
      </article>
    </section>
  </div>
</template>

<style scoped>
.extras {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.page-sub {
  font-size: 14px;
  margin-top: -10px;
}

.item-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
}

.item {
  text-align: left;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 16px;
  border: 1px solid var(--border);
}

.item.selected {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
}

.icon {
  font-size: 26px;
}

.name {
  font-weight: 700;
  font-size: 15.5px;
}

.price {
  color: var(--accent);
  font-weight: 700;
}

.desc {
  font-size: 12.5px;
  line-height: 1.55;
}

.buy {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.row {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.label {
  width: 70px;
  color: var(--ink-3);
  font-size: 14px;
}

.date-strip {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.date-opt {
  border: 1px solid var(--border);
  background: none;
  border-radius: var(--radius-sm);
  padding: 6px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  cursor: pointer;
  font: inherit;
}

.date-opt.active {
  border-color: var(--accent);
  color: var(--accent);
  background: var(--accent-ring);
}

.date-opt.closed {
  opacity: 0.4;
  cursor: not-allowed;
}

.qty-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.total {
  font-size: 22px;
  font-weight: 800;
  color: var(--accent);
}

.total-row {
  border-top: 1px dashed var(--border);
  padding-top: 14px;
}

.bought {
  border-color: var(--accent);
  padding: 18px 20px;
}

.codes {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 10px 0;
}

.code-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px dashed var(--border);
  padding-bottom: 6px;
}

.code,
.io .code {
  font-family: var(--mono, monospace);
  font-weight: 700;
  letter-spacing: 1px;
}

.mine {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.io {
  padding: 14px 18px;
}

.io-head {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.io-no {
  color: var(--ink-3);
  margin-right: 10px;
  font-size: 13px;
}

.io-item {
  font-weight: 700;
}

.io-side {
  display: flex;
  align-items: center;
  gap: 12px;
}

.chip-soft {
  font-size: 13px;
  color: var(--ink-2);
}

.io-tickets {
  list-style: none;
  margin: 10px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13.5px;
}

@media (max-width: 767px) {
  .item-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
