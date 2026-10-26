<script setup>
import { computed } from 'vue'

// 柱状图（单序列）：纯 SVG 实现（对应需求文档 E2 看板的对比类图表）
const props = defineProps({
  labels: { type: Array, default: () => [] },
  values: { type: Array, default: () => [] },
  suffix: { type: String, default: '' }, // 数值后缀（如 元）
})

const W = 640
const H = 260
const PAD = { left: 48, right: 14, top: 22, bottom: 30 }

const maxVal = computed(() => Math.max(0, ...props.values))
const yMax = computed(() => {
  const m = maxVal.value
  if (m <= 0) return 10
  const pow = 10 ** Math.floor(Math.log10(m))
  return Math.ceil((m / pow) * 4) / 4 * pow
})

function fmt(v) {
  if (v === 0) return '0'
  if (v >= 10000) return (v / 10000).toFixed(1) + 'w'
  if (v >= 1000) return (v / 1000).toFixed(1) + 'k'
  return v % 1 ? v.toFixed(1) : String(Math.round(v))
}

const slots = computed(() => {
  const n = props.values.length
  if (!n) return []
  const usable = W - PAD.left - PAD.right
  const slotW = usable / n
  const barW = Math.min(64, slotW * 0.55)
  return props.values.map((v, i) => {
    const h = (v / (yMax.value || 1)) * (H - PAD.top - PAD.bottom)
    return {
      label: props.labels[i],
      v,
      barW,
      x: PAD.left + slotW * i + (slotW - barW) / 2,
      y: H - PAD.bottom - h,
      h: Math.max(h, v > 0 ? 2 : 0),
      cx: PAD.left + slotW * i + slotW / 2,
      showValue: n <= 10 || v > 0,
    }
  })
})

const empty = computed(() => !props.values.length || maxVal.value === 0)
</script>

<template>
  <div class="bc">
    <svg v-if="!empty" :viewBox="`0 0 ${W} ${H}`" role="img" :aria-label="'柱状图' + suffix">
      <line
        :x1="PAD.left"
        :x2="W - PAD.right"
        :y1="H - PAD.bottom"
        :y2="H - PAD.bottom"
        class="axis-line"
      />
      <g v-for="(s, i) in slots" :key="i">
        <rect :x="s.x" :y="s.y" :width="s.barW" :height="s.h" rx="4" class="bar">
          <title>{{ s.label }}：{{ fmt(s.v) }}{{ suffix }}</title>
        </rect>
        <text
          v-if="s.v > 2"
          :x="s.cx"
          :y="s.y - 6"
          text-anchor="middle"
          class="axis num"
        >
          {{ fmt(s.v) }}
        </text>
        <text :x="s.cx" :y="H - 8" text-anchor="middle" class="axis">{{ s.label }}</text>
      </g>
    </svg>
    <div v-else class="bc-empty">该时间段暂无数据</div>
  </div>
</template>

<style scoped>
.bc svg {
  display: block;
  width: 100%;
  height: 240px;
}

.bar {
  fill: var(--accent);
  opacity: 0.85;
}

.axis-line {
  stroke: var(--border);
}

.axis {
  fill: var(--ink-3);
  font-size: 10.5px;
}

.bc-empty {
  height: 240px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ink-3);
  font-size: 14px;
}
</style>
