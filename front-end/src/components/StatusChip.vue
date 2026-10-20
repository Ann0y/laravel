<script setup>
defineProps({ status: { type: String, required: true } })

const map = {
  待支付: 'chip-amber',
  已支付: 'chip-green',
  退款中: 'chip-rose',
  已退款: 'chip-rose',
  已取消: 'chip-zinc',
  已使用: 'chip-zinc',
  未检票: 'chip-green-outline',
  已检票: 'chip-green',
  已过期: 'chip-zinc',
  待审核: 'chip-amber-outline',
  已通过: 'chip-green',
  已驳回: 'chip-rose',
}
</script>

<template>
  <span class="chip" :class="map[status] || 'chip-zinc'">{{ status }}</span>
</template>

<style scoped>
.chip {
  display: inline-flex;
  align-items: center;
  padding: 1px 10px;
  border-radius: 999px;
  font-size: 12.5px;
  line-height: 1.8;
  white-space: nowrap;
}

.chip-green {
  background: var(--accent-soft);
  color: var(--accent-hover);
}

.chip-green-outline {
  background: #fff;
  color: var(--accent-hover);
  border: 1px solid var(--accent);
}

.chip-amber {
  background: var(--amber-soft);
  color: var(--amber);
}

.chip-amber-outline {
  background: #fff;
  color: var(--amber);
  border: 1px solid var(--amber-border);
}

.chip-rose {
  background: var(--rose-soft);
  color: var(--rose);
}

.chip-zinc {
  background: var(--zinc-soft);
  color: var(--zinc);
}
</style>
