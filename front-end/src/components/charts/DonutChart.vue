<script setup>
import { computed } from 'vue'

// 环形图（占比结构）：SVG stroke-dasharray 实现（对应需求文档 E2 票种结构图）
const props = defineProps({
  items: { type: Array, default: () => [] }, // [{ name, value }]
  centerLabel: { type: String, default: '合计' },
})

const COLORS = ['#0f7a5c', '#96660f', '#3b6ea5', '#ab4a41', '#5b655f', '#7a3f8f']

const total = computed(() => props.items.reduce((s, it) => s + it.value, 0))

function pct(v) {
  if (!total.value) return '0%'
  return ((v / total.value) * 100).toFixed(1) + '%'
}

function fmt(v) {
  return v >= 1000 ? ((v / 1000) * (v % 1000 ? 10 : 1) / 10).toFixed(1) + 'k' : String(v)
}

const R = 64
const C = 2 * Math.PI * R

// 逐段圆环：每段一个 circle 的 stroke-dasharray 偏移实现
const arcs = computed(() => {
  let offset = 0
  return props.items.map((it, i) => {
    const frac = total.value ? it.value / total.value : 0
    const seg = {
      name: it.name,
      value: it.value,
      color: COLORS[i % COLORS.length],
      dash: `${C * frac} ${C}`,
      offset: -offset,
      pct: pct(it.value),
    }
    offset += C * frac
    return seg
  })
})

const empty = computed(() => !props.items.length || total.value === 0)
</script>

<template>
  <div class="donut">
    <div v-if="!empty" class="d-charts">
      <svg viewBox="0 0 160 160" role="img" aria-label="占比环形图">
        <g transform="translate(80,80) rotate(-90)">
          <circle :r="R" fill="none" stroke="var(--zinc-soft)" stroke-width="22" />
          <circle
            v-for="(a, i) in arcs"
            :key="i"
            :r="R"
            fill="none"
            :stroke="a.color"
            stroke-width="22"
            :stroke-dasharray="a.dash"
            :stroke-dashoffset="a.offset"
            class="seg"
          >
            <title>{{ a.name }}：{{ a.value }}（{{ a.pct }}）</title>
          </circle>
        </g>
        <text x="80" y="74" text-anchor="middle" class="d-c-label">{{ centerLabel }}</text>
        <text x="80" y="96" text-anchor="middle" class="d-c-value num">{{ fmt(total) }}</text>
      </svg>
      <ul class="d-legend">
        <li v-for="(a, i) in arcs" :key="i">
          <i class="dot" :style="{ background: a.color }" />
          <span class="lg-name">{{ a.name }}</span>
          <span class="num lg-val">{{ a.value }}</span>
          <span class="num lg-pct">{{ a.pct }}</span>
        </li>
      </ul>
    </div>
    <div v-else class="d-empty">该时间段暂无数据</div>
  </div>
</template>

<style scoped>
.d-charts {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-wrap: wrap;
}

.d-charts svg {
  width: 160px;
  height: 160px;
  flex-shrink: 0;
}

.seg {
  transition: stroke-dasharray 0.4s ease;
}

.d-c-label {
  fill: var(--ink-3);
  font-size: 11px;
}

.d-c-value {
  fill: var(--ink);
  font-size: 17px;
  font-weight: 650;
}

.d-legend {
  list-style: none;
  margin: 0;
  padding: 0;
  flex: 1;
  min-width: 180px;
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.d-legend li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.dot {
  width: 9px;
  height: 9px;
  border-radius: 2.5px;
  display: inline-block;
  flex-shrink: 0;
}

.lg-name {
  color: var(--ink-2);
  flex: 1;
  min-width: 3em;
}

.lg-val {
  color: var(--ink);
  font-weight: 600;
}

.lg-pct {
  color: var(--ink-3);
  width: 4.6em;
  text-align: right;
}

.d-empty {
  height: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ink-3);
  font-size: 14px;
}
</style>
