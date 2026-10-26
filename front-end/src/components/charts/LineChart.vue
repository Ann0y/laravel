<script setup>
import { computed } from 'vue'

// 折线图（支持多序列）：纯 SVG 实现，无第三方依赖（对应需求文档 E2 看板的趋势类图表）
const props = defineProps({
  labels: { type: Array, default: () => [] }, // x 轴标签
  series: { type: Array, default: () => [] }, // [{ name, values: number[] }]
  height: { type: Number, default: 240 },
  unit: { type: String, default: '' },
})

const W = 640
const H = 260
const PAD = { left: 48, right: 14, top: 18, bottom: 30 }

const COLORS = ['#0f7a5c', '#96660f', '#ab4a41', '#3b6ea5']

const maxVal = computed(() => {
  let max = 0
  for (const s of props.series) for (const v of s.values) if (v > max) max = v
  return max
})

// y 轴上取整到好看的刻度
const yMax = computed(() => {
  const m = maxVal.value
  if (m <= 0) return 10
  const pow = 10 ** Math.floor(Math.log10(m))
  return Math.ceil(m / pow * 4) / 4 * pow
})

const gridLines = computed(() =>
  [0, 0.25, 0.5, 0.75, 1].map((t) => ({
    value: yMax.value * t,
    y: H - PAD.bottom - (H - PAD.top - PAD.bottom) * t,
  })),
)

function fmt(v) {
  if (v === 0) return '0'
  if (v >= 1000) return (v / 1000).toFixed(1) + 'k'
  return v % 1 ? v.toFixed(1) : String(Math.round(v))
}

function xAt(i) {
  const n = props.labels.length
  if (n <= 1) return PAD.left + (W - PAD.left - PAD.right) / 2
  return PAD.left + (i / (n - 1)) * (W - PAD.left - PAD.right)
}

function yAt(v) {
  return H - PAD.bottom - (v / (yMax.value || 1)) * (H - PAD.top - PAD.bottom)
}

const paths = computed(() =>
  props.series.map((s, si) => ({
    name: s.name,
    color: s.color || COLORS[si % COLORS.length],
    line: s.values.map((v, i) => `${xAt(i)},${yAt(v)}`).join(' '),
    area:
      `M ${xAt(0)},${H - PAD.bottom} ` +
      s.values.map((v, i) => `L ${xAt(i)},${yAt(v)}`).join(' ') +
      ` L ${xAt(s.values.length - 1)},${H - PAD.bottom} Z`,
    dots: s.values.map((v, i) => ({ x: xAt(i), y: yAt(v), v })),
  })),
)

// x 轴标签防重叠：按宽度均匀抽样
const xTicks = computed(() => {
  const n = props.labels.length
  if (!n) return []
  const maxShow = Math.max(2, Math.floor((W - PAD.left - PAD.right) / 72))
  const step = Math.ceil(n / maxShow)
  return props.labels
    .map((label, i) => ({ label, i }))
    .filter(({ i }) => i % step === 0 || i === n - 1)
})

const empty = computed(() => !props.labels.length || maxVal.value === 0)
</script>

<template>
  <div class="lc" :style="{ '--h': height + 'px' }">
    <svg v-if="!empty" :viewBox="`0 0 ${W} ${H}`" role="img" :aria-label="'折线图' + unit">
      <g>
        <line
          v-for="(g, gi) in gridLines"
          :key="gi"
          :x1="PAD.left"
          :x2="W - PAD.right"
          :y1="g.y"
          :y2="g.y"
          class="grid"
        />
        <text v-for="(g, gi) in gridLines" :key="'t' + gi" :x="PAD.left - 8" :y="g.y + 4" class="axis num">
          {{ fmt(g.value) }}
        </text>
        <text
          v-for="({ label, i }, ti) in xTicks"
          :key="'x' + ti"
          :x="xAt(i)"
          :y="H - 8"
          text-anchor="middle"
          class="axis"
        >
          {{ label }}
        </text>
      </g>
      <g v-for="(p, pi) in paths" :key="pi">
        <path :d="p.area" :fill="p.color" class="area" />
        <polyline :points="p.line" fill="none" :stroke="p.color" class="line" />
        <circle v-for="(d, di) in p.dots" :key="di" :cx="d.x" :cy="d.y" r="2.6" :fill="p.color">
          <title>{{ labels[di] }}：{{ d.v }}</title>
        </circle>
      </g>
    </svg>
    <div v-else class="lc-empty">该时间段暂无数据</div>

    <div v-if="series.length && !empty" class="lc-legend">
      <span v-for="(p, pi) in paths" :key="pi" class="lg-item">
        <i class="dot" :style="{ background: p.color }" />
        {{ p.name }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.lc svg {
  display: block;
  width: 100%;
  height: var(--h);
}

.grid {
  stroke: var(--border);
  stroke-width: 1;
}

.area {
  opacity: 0.07;
}

.line {
  stroke-width: 2;
  vector-effect: non-scaling-stroke;
  stroke-linejoin: round;
  stroke-linecap: round;
}

.axis {
  fill: var(--ink-3);
  font-size: 10.5px;
}

.lc-empty {
  height: var(--h);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ink-3);
  font-size: 14px;
}

.lc-legend {
  display: flex;
  gap: 18px;
  padding: 0 4px 6px;
  flex-wrap: wrap;
}

.lg-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--ink-2);
}

.dot {
  width: 9px;
  height: 9px;
  border-radius: 999px;
  display: inline-block;
}
</style>
