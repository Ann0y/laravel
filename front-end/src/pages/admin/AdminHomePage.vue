<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../../api'
import LineChart from '../../components/charts/LineChart.vue'
import BarChart from '../../components/charts/BarChart.vue'
import DonutChart from '../../components/charts/DonutChart.vue'

// F09 工作台（指标卡 + 待办） + F14/E2 统计报表看板（售票量 / 票种 / 渠道收入 / 入园核销率 / 退票）
const data = ref(null)
const error = ref('')
const loading = ref(true)

const RANGES = [
  { key: '7', label: '近 7 天', days: 7 },
  { key: '30', label: '近 30 天', days: 30 },
  { key: '90', label: '近 90 天', days: 90 },
]
const range = ref('30')

function fmtDate(offset = 0) {
  const x = new Date()
  x.setDate(x.getDate() - offset)
  const m = String(x.getMonth() + 1).padStart(2, '0')
  const d = String(x.getDate()).padStart(2, '0')
  return `${x.getFullYear()}-${m}-${d}`
}

async function load() {
  error.value = ''
  loading.value = true
  const days = RANGES.find((r) => r.key === range.value)?.days ?? 30
  try {
    data.value = await api(`/statistics/overview?start=${fmtDate(days - 1)}&end=${fmtDate()}`)
  } catch (e) {
    error.value = e.status === 401 ? '登录已过期，请重新进入管理端' : e.message
  } finally {
    loading.value = false
  }
}

onMounted(load)

function dLabel(dateStr) {
  return dateStr ? dateStr.slice(5) : dateStr
}

// 趋势序列（售量 / 订单） x 轴使用 byDay 原有日期（稀疏连续日期均补 0）
function fillDates(rows) {
  const map = Object.fromEntries(rows.map((r) => [r.date, r]))
  const out = []
  const days = RANGES.find((r) => r.key === range.value).days
  for (let i = days - 1; i >= 0; i--) {
    const d = fmtDate(i)
    out.push(map[d] ?? { date: d, orders: 0, tickets: 0, revenue: 0 })
  }
  return out
}

const trend = computed(() => (data.value ? fillDates(data.value.byDay) : []))
const trendLabels = computed(() => trend.value.map((r) => dLabel(r.date)))
const maxDay = computed(() => {
  const rows = trend.value
  if (!rows.length) return null
  return rows.reduce((a, b) => (b.tickets > a.tickets ? b : a))
})

const typeItems = computed(() => data.value?.byType.map((t) => ({ name: t.type, value: t.count })) ?? [])

const entryChecked = computed(() => {
  if (!data.value) return []
  return trend.value.map((r) => data.value.checkedByDay[r.date] ?? 0)
})

function sum(arr) {
  return arr.reduce((a, b) => a + b, 0)
}

const ticketSum = computed(() => sum(trend.value.map((r) => r.tickets)))
const checkedSum = computed(() => sum(entryChecked.value))

const refundRate = computed(() => {
  if (!data?.value?.ticketCount) return 0
  return ((data.value.refundCount / data.value.ticketCount) * 100).toFixed(1)
})

const money = (v) =>
  typeof v === 'number' ? v.toLocaleString('zh-CN', { maximumFractionDigits: 0 }) : v
</script>

<template>
  <div class="dash container">
    <header class="d-head">
      <h1>工作台</h1>
      <div class="filters">
        <button
          v-for="r in RANGES"
          :key="r.key"
          class="filter-chip"
          :class="{ active: range === r.key }"
          @click="range = r.key"
        >
          {{ r.label }}
        </button>
      </div>
    </header>

    <p v-if="error" class="err">{{ error }}</p>
    <div v-if="loading && !data" class="loading muted">载入中…</div>

    <template v-if="data">
      <!-- F09 指标卡 -->
      <div class="metrics">
        <div class="m-card card">
          <span class="m-label">今日售票量</span>
          <span class="m-value num">{{ data.today.sold }}</span>
          <span class="m-unit">张 · 全渠道</span>
        </div>
        <div class="m-card card">
          <span class="m-label">今日入园量</span>
          <span class="m-value num">{{ data.today.checked }}</span>
          <span class="m-unit">人次 · 已核销</span>
        </div>
        <div class="m-card card">
          <span class="m-label">今日收入</span>
          <span class="m-value num">¥{{ money(data.today.revenue) }}</span>
          <span class="m-unit">有效订单</span>
        </div>
        <div class="m-card card hi">
          <span class="m-label">待审核退票</span>
          <span class="m-value num">{{ data.pendingRefunds }}</span>
          <div class="m-link">
            <router-link to="/admin/refunds" class="link">去处理 →</router-link>
          </div>
        </div>
      </div>

      <!-- E2 售票量趋势 -->
      <section class="card chart-block">
        <div class="cb-head">
          <h2>售票量趋势</h2>
          <span v-if="maxDay" class="field-text num">
            峰值 {{ maxDay.tickets }} 张（{{ maxDay.date }}）
          </span>
        </div>
        <LineChart
          :labels="trendLabels"
          :series="[
            { name: '售票张数', values: trend.map((r) => r.tickets) },
            { name: '订单数', values: trend.map((r) => r.orders) },
          ]"
        />
      </section>

      <div class="chart-row">
        <!-- E2 票种结构 -->
        <section class="card chart-block">
          <h2>票种结构</h2>
          <DonutChart :items="typeItems" center-label="订单数" />
        </section>

        <!-- E2 渠道收入 -->
        <section class="card chart-block">
          <div class="cb-head">
            <h2>渠道收入</h2>
            <span class="field-text num">
              {{ data.byChannel.map((c) => `${c.channel} ¥${money(c.revenue)}（${c.orders} 单）`).join(' · ') }}
            </span>
          </div>
          <BarChart
            :labels="data.byChannel.map((c) => c.channel)"
            :values="data.byChannel.map((c) => c.revenue)"
            suffix=" 元"
          />
        </section>
      </div>

      <!-- E2 入园与核销 -->
      <section class="card chart-block">
        <div class="cb-head">
          <h2>出票与核销</h2>
          <span class="field-text num">
            区间内核销率 {{ checkedSum }} / {{ ticketSum }} =
            {{ ticketSum ? ((checkedSum / ticketSum) * 100).toFixed(1) : '0.0' }}%
          </span>
        </div>
        <LineChart
          :labels="trendLabels"
          :series="[
            { name: '出票张数', values: trend.map((r) => r.tickets) },
            { name: '核销人次', values: entryChecked },
          ]"
          height="220"
        />
      </section>

      <!-- E2 退票情况 -->
      <section class="card chart-block">
        <div class="cb-head">
          <h2>退票情况</h2>
          <span class="field-text">退票张数按审核申请统计，退票率 = 退票申请数 / 订单数</span>
        </div>
        <div class="r-metrics">
          <div class="r-card">
            <span class="m-label">退票申请</span>
            <span class="r-value num">{{ data.refundCount }}</span>
            <span class="m-unit">单</span>
          </div>
          <div class="r-card">
            <span class="m-label">区间订单</span>
            <span class="r-value num">{{ data.ticketCount }}</span>
            <span class="m-unit">单</span>
          </div>
          <div class="r-card">
            <span class="m-label">退票率</span>
            <span class="r-value num">{{ refundRate }}%</span>
            <span class="m-unit">作业目标 ≤ 5%</span>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.dash {
  padding: 32px 24px 0;
}

.d-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 22px;
  flex-wrap: wrap;
}

.d-head h1 {
  margin: 0;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.filters {
  display: flex;
  gap: 8px;
}

.filter-chip {
  border: 1px solid var(--border-strong);
  background: #fff;
  border-radius: 999px;
  padding: 5px 16px;
  font: inherit;
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

.metrics {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 18px;
}

.m-card {
  padding: 18px 22px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.m-label {
  font-size: 13.5px;
  color: var(--ink-2);
}

.m-value {
  font-size: 30px;
  font-weight: 650;
  letter-spacing: -0.02em;
}

.m-unit {
  font-size: 12.5px;
  color: var(--ink-3);
}

.m-card.hi .m-value {
  color: var(--rose);
}

.m-link {
  margin-top: 2px;
}

.chart-block {
  padding: 18px 22px;
  margin-bottom: 18px;
}

.chart-block h2 {
  margin: 0 0 12px;
  font-size: 15.5px;
}

.cb-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}

.cb-head h2 {
  margin: 0;
  font-size: 15.5px;
}

.chart-row {
  display: grid;
  grid-template-columns: 5fr 6fr;
  gap: 18px;
}

.r-metrics {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}

.r-card {
  padding: 12px 16px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: baseline;
  gap: 9px;
  flex-wrap: wrap;
  background: #fafbfa;
}

.r-value {
  font-size: 23px;
  font-weight: 650;
  color: var(--rose);
}

.r-card .m-unit {
  font-size: 12px;
}

.loading {
  padding: 30px 0;
}

@media (max-width: 900px) {
  .metrics {
    grid-template-columns: repeat(2, 1fr);
  }

  .chart-row {
    grid-template-columns: 1fr;
  }

  .r-metrics {
    grid-template-columns: 1fr;
  }
}
</style>
