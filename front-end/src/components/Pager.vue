<script setup>
import { computed } from 'vue'

// 分页器：适配 Laravel paginate 序列化（current_page / last_page / total）
const props = defineProps({
  page: { type: Object, default: null },
})
const emit = defineEmits(['change'])

const current = computed(() => props.page?.current_page ?? 1)
const last = computed(() => props.page?.last_page ?? 1)
const total = computed(() => props.page?.total ?? 0)
const size = computed(() => props.page?.per_page ?? 10)

const has = computed(() => last.value > 1 || total.value > 0)

// 中间三页 + 首末页的简单页码
const pages = computed(() => {
  const out = []
  for (let p = Math.max(1, current.value - 1); p <= Math.min(last.value, current.value + 1); p++) {
    out.push(p)
  }
  return out
})
</script>

<template>
  <div v-if="has" class="pager">
    <span class="muted num">共 {{ total }} 条 · 第 {{ current }} / {{ last }} 页</span>
    <div class="pager-btns">
      <button class="btn btn-ghost btn-sm" :disabled="current <= 1" @click="emit('change', current - 1)">
        上一页
      </button>
      <button
        v-for="p in pages"
        :key="p"
        class="pager-num"
        :class="{ active: p === current }"
        :disabled="p === current"
        @click="p !== current && emit('change', p)"
      >
        {{ p }}
      </button>
      <button class="btn btn-ghost btn-sm" :disabled="current >= last" @click="emit('change', current + 1)">
        下一页
      </button>
    </div>
    <span v-if="size" class="muted num">每页 {{ size }} 条</span>
  </div>
</template>

<style scoped>
.pager {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  margin: 16px 0;
}

.pager-btns {
  display: flex;
  align-items: center;
  gap: 6px;
}

.pager-num {
  min-width: 34px;
  height: 32px;
  border: 1px solid var(--border-strong);
  border-radius: 999px;
  background: #fff;
  color: var(--ink-2);
  font: inherit;
  font-variant-numeric: tabular-nums;
  font-size: 13.5px;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.pager-num:hover:not(:disabled) {
  border-color: var(--ink-2);
  color: var(--ink);
}

.pager-num.active {
  border-color: transparent;
  background: var(--accent);
  color: #fff;
  cursor: default;
}
</style>
