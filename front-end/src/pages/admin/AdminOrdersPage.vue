<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../../api'
import StatusChip from '../../components/StatusChip.vue'
import Pager from '../../components/Pager.vue'

// F12 订单管理 + 门票检索：多条件组合查询 + 分页（Laravel paginate）
const tab = ref('orders') // orders | tickets

const ORDER_STATUS = ['待支付', '已支付', '已使用', '退款中', '已退款', '已取消']
const TICKET_STATUS = ['未检票', '已检票', '已退款', '已过期']

const f = ref({ orderNo: '', ticketNo: '', status: '', channel: '', visitDate: '' })
const page = ref({})
const list = ref([])
const loading = ref(true)
const error = ref('')

const PAGE_SIZE = 10

function cleanParams(pageNo = 1) {
  const p = { page: pageNo, pageSize: PAGE_SIZE }
  if (f.value.orderNo) p.orderNo = f.value.orderNo
  if (f.value.ticketNo) p.ticketNo = f.value.ticketNo
  if (f.value.status) p.status = f.value.status
  if (f.value.channel) p.channel = f.value.channel
  if (f.value.visitDate) p.visitDate = f.value.visitDate
  return p
}

async function fetchPage(pageNo = 1) {
  loading.value = true
  error.value = ''
  const params = new URLSearchParams(cleanParams(pageNo))
  try {
    const data = await api(`/${tab.value}?${params}`)
    list.value = data.list
    page.value = data.page
  } catch (e) {
    error.value = e.status === 401 ? '登录已过期，请重新进入管理端' : e.message
  } finally {
    loading.value = false
  }
}

function switchTab(t) {
  tab.value = t
  list.value = []
  page.value = {}
  f.value = { orderNo: '', ticketNo: '', status: '', channel: '', visitDate: '' }
  fetchPage()
}

function search() {
  fetchPage(1)
}

onMounted(() => fetchPage())

const expanded = ref({})
function toggle(orderNo) {
  expanded.value = { ...expanded.value, [orderNo]: !expanded.value[orderNo] }
}

function fmtTime(iso) {
  return iso ? iso.replace('T', ' ').slice(0, 16) : '—'
}
</script>

<template>
  <div class="ao container">
    <h1>订单与门票管理</h1>

    <div class="tabs">
      <button class="tab" :class="{ active: tab === 'orders' }" @click="switchTab('orders')">订单</button>
      <button class="tab" :class="{ active: tab === 'tickets' }" @click="switchTab('tickets')">门票</button>
    </div>

    <!-- 检索条 -->
    <form class="search card" @submit.prevent="search">
      <div v-if="tab === 'orders'" class="field">
        <label>订单号</label>
        <input v-model="f.orderNo" placeholder="支持模糊搜索" />
      </div>
      <div v-if="tab === 'tickets'" class="field">
        <label>核销码</label>
        <input v-model="f.ticketNo" placeholder="TK…" />
      </div>
      <div v-if="tab === 'orders'" class="field">
        <label>状态</label>
        <select v-model="f.status">
          <option value="">全部</option>
          <option v-for="s in ORDER_STATUS" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
      <div v-if="tab === 'tickets'" class="field">
        <label>状态</label>
        <select v-model="f.status">
          <option value="">全部</option>
          <option v-for="s in TICKET_STATUS" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
      <div v-if="tab === 'orders'" class="field">
        <label>渠道</label>
        <select v-model="f.channel">
          <option value="">全部</option>
          <option>线上</option>
          <option>窗口</option>
        </select>
      </div>
      <div class="field">
        <label>游园日期</label>
        <input v-model="f.visitDate" type="date" />
      </div>
      <div class="actions">
        <button class="btn btn-primary btn-sm" type="submit">查询</button>
        <button class="btn btn-ghost btn-sm" type="button" @click="switchTab(tab)">重置</button>
      </div>
    </form>

    <p v-if="error" class="err">{{ error }}</p>
    <div v-if="loading" class="loading muted">载入中…</div>

    <template v-else>
      <!-- 订单表 -->
      <div v-if="tab === 'orders'" class="card table-wrap">
        <table>
          <thead>
            <tr>
              <th>订单号</th>
              <th>游客</th>
              <th>票种</th>
              <th class="num">数量</th>
              <th class="num">金额</th>
              <th>渠道</th>
              <th>状态</th>
              <th class="num">游园日期</th>
              <th class="num">下单时间</th>
              <th>门票</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in list" :key="o.id">
              <td class="num">{{ o.orderNo }}</td>
              <td>{{ o.visitorName || '散客' }}</td>
              <td>{{ o.typeName }}</td>
              <td class="num">{{ o.quantity }}</td>
              <td class="num">¥{{ o.totalAmount }}</td>
              <td>{{ o.channel }}</td>
              <td><StatusChip :status="o.status" /></td>
              <td class="num">{{ o.visitDate }}</td>
              <td class="num muted">{{ fmtTime(o.createTime) }}</td>
              <td>
                <button class="link" @click="toggle(o.orderNo)">
                  {{ expanded[o.orderNo] ? '收起' : `${o.tickets?.length ?? 0} 张` }}
                </button>
              </td>
            </tr>
            <tr v-if="!list.length">
              <td colspan="10" class="muted table-empty">无符合条件的订单。</td>
            </tr>
          </tbody>
        </table>
        <!-- 展开的门票明细行 -->
        <div v-for="o in list" v-show="expanded[o.orderNo]" :key="'d' + o.id" class="ticket-detail">
          <div v-for="t in o.tickets" :key="t.ticketNo" class="td-row">
            <span class="num code">{{ t.ticketNo }}</span>
            <StatusChip :status="t.status" />
            <span v-if="t.status === '已检票'" class="muted num">核销于 {{ fmtTime(t.checkTime) }}</span>
            <span v-if="t.refund" class="muted">退票申请：{{ t.refund.status }}</span>
          </div>
        </div>
      </div>

      <!-- 门票表 -->
      <div v-else class="card table-wrap">
        <table>
          <thead>
            <tr>
              <th>核销码</th>
              <th class="num">游园日期</th>
              <th class="num">票价</th>
              <th>状态</th>
              <th class="num">所属订单</th>
              <th>游客</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in list" :key="t.ticketNo">
              <td class="num">{{ t.ticketNo }}</td>
              <td class="num">{{ t.visitDate }}</td>
              <td class="num">¥{{ t.unitPrice }}</td>
              <td><StatusChip :status="t.status" /></td>
              <td class="num muted">{{ t.orderNo }}</td>
              <td>{{ t.visitorName }}</td>
            </tr>
            <tr v-if="!list.length">
              <td colspan="6" class="muted table-empty">无符合条件的门票。</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pager :page="page" @change="fetchPage" />
    </template>
  </div>
</template>

<style scoped>
.ao {
  padding: 32px 24px 0;
}

.ao h1 {
  margin: 0 0 18px;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 14px;
}

.tab {
  border: 1px solid var(--border-strong);
  background: #fff;
  border-radius: var(--radius-sm);
  padding: 9px 22px;
  font: inherit;
  font-size: 14.5px;
  color: var(--ink-2);
  cursor: pointer;
  transition: all 0.15s ease;
}

.tab:hover {
  border-color: var(--ink-2);
}

.tab.active {
  border-color: var(--accent);
  background: var(--accent-soft);
  color: var(--accent-hover);
  font-weight: 600;
}

.search {
  display: flex;
  gap: 14px;
  padding: 14px 18px;
  margin-bottom: 16px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.search .field {
  min-width: 150px;
}

.search .field input,
.search .field select {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: #fff;
  padding: 8px 10px;
  font: inherit;
  font-size: 14px;
}

.search .actions {
  margin-left: auto;
  display: flex;
  gap: 8px;
}

.table-wrap {
  overflow-x: auto;
  padding: 6px 18px;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}

th,
td {
  text-align: left;
  padding: 10px 10px;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}

th {
  color: var(--ink-3);
  font-weight: 550;
  font-size: 12.5px;
}

.table-empty {
  text-align: center;
  padding: 26px 0;
}

.ticket-detail {
  padding: 8px 18px;
  border-bottom: 1px solid var(--border);
}

.td-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 5px 0;
  flex-wrap: wrap;
}

.td-row .code {
  font-weight: 600;
  letter-spacing: 0.04em;
}

.loading {
  padding: 30px 0;
}
</style>
