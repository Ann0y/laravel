<script setup>
import { computed } from 'vue'

// 门票核销码的示意二维码（演示用途，非可识读的真码）
const props = defineProps({
  code: { type: String, required: true },
  size: { type: Number, default: 132 },
})

const N = 21

function pseudo(x, y) {
  let h = 2166136261
  const s = props.code + ':' + x + ':' + y
  for (let i = 0; i < s.length; i++) {
    h ^= s.charCodeAt(i)
    h = Math.imul(h, 16777619) >>> 0
  }
  return (h >>> 0) & 1
}

const cells = computed(() => {
  const arr = []
  for (let y = 0; y < N; y++) {
    for (let x = 0; x < N; x++) {
      let v
      const inTopLeft = x < 7 && y < 7
      const inTopRight = x >= N - 7 && y < 7
      const inBottomLeft = x < 7 && y >= N - 7
      if (inTopLeft || inTopRight || inBottomLeft) {
        const bx = inTopRight ? N - 7 : 0
        const by = inBottomLeft ? N - 7 : 0
        const d = Math.max(Math.abs(x - bx - 3), Math.abs(y - by - 3))
        v = d === 3 || d <= 1
      } else {
        v = pseudo(x, y) === 1
      }
      arr.push(v)
    }
  }
  return arr
})
</script>

<template>
  <div class="qr" :style="{ width: size + 'px', height: size + 'px' }">
    <svg viewBox="0 0 21 21" role="img" :aria-label="'核销码示意 ' + code">
      <rect
        v-for="(v, i) in cells"
        v-show="v"
        :x="i % N"
        :y="Math.floor(i / N)"
        width="1"
        height="1"
        fill="currentColor"
      />
    </svg>
  </div>
</template>

<style scoped>
.qr {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 6px;
  flex-shrink: 0;
}

.qr svg {
  display: block;
  width: 100%;
  height: 100%;
  color: var(--ink);
}
</style>
