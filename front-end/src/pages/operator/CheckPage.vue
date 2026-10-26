<script setup>
import { ref, onMounted } from 'vue'
import StatusChip from '../../components/StatusChip.vue'
import { api } from '../../api'

// F07 检票核销：输入 / 扫描核销码 → 校验 → 状态置已检票
const ticketNo = ref('')
const error = ref('')
const success = ref(null)
const busy = ref(false)
const inputEl = ref(null)

// 工作台概览（今日待核销数）
const pendingCheck = ref(null)

onMounted(async () => {
  inputEl.value?.focus()
  try {
    const wb = await api('/operator/workbench')
    pendingCheck.value = wb.todayPendingCheck
  } catch {
    pendingCheck.value = null
  }
})

async function doCheck() {
  const code = ticketNo.value.trim()
  error.value = ''
  success.value = null
  if (!code) {
    error.value = '请输入或扫描门票核销码'
    return
  }
  busy.value = true
  try {
    const data = await api('/tickets/check', { method: 'POST', body: { ticketNo: code } })
    success.value = data.ticket
    ticketNo.value = ''
    try {
      const wb = await api('/operator/workbench')
      pendingCheck.value = wb.todayPendingCheck
    } catch {
      /* 工作台指标失败不影响核销结果 */
    }
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
    inputEl.value?.focus()
  }
}
</script>

<template>
  <div class="check container">
    <h1>检票核销</h1>

    <div class="check-main">
      <section class="card scan">
        <h2>输入核销码</h2>
        <form class="field" @submit.prevent="doCheck">
          <input
            ref="inputEl"
            v-model="ticketNo"
            placeholder="游客出示门票二维码或报出核销码（TK…）"
            autocomplete="off"
            spellcheck="false"
          />
          <button class="btn btn-primary btn-block" :disabled="busy" type="submit">
            {{ busy ? '核销中…' : '核销入园' }}
          </button>
        </form>
        <p class="field-text">支持扫码枪直接扫码（自动键入并回车）；一码一次入园，当日有效。</p>

        <div v-if="success" class="result ok">
          <div class="r-icon">✓</div>
          <div>
            <strong>核销成功，欢迎入园</strong>
            <p class="num code">{{ success.ticketNo }}</p>
            <span class="field-text">已登记检票时间，游客请按景区路线入园。</span>
          </div>
        </div>

        <div v-if="error" class="result fail">
          <div class="r-icon r-fail">!</div>
          <div>
            <strong>核销失败</strong>
            <p class="err">{{ error }}</p>
          </div>
        </div>
      </section>

      <aside class="card side">
        <div class="side-metric">
          <span class="m-label">今日待核销</span>
          <span class="m-value num">{{ pendingCheck ?? '—' }}</span>
          <span class="m-unit">张 · 游园当日未检票</span>
        </div>
        <div class="rules">
          <h2>核销规则</h2>
          <ul>
            <li>核销码不存在 → 提示「核销码不存在」，请检查输入；</li>
            <li>已检票 → 拒绝重复核销；</li>
            <li>已退款 / 已过期 → 状态不可核销；</li>
            <li>非当日 → 该门票非当日有效；</li>
            <li>待审核退票 → 等管理员审核完成。</li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.check {
  padding: 32px 24px 0;
}

.check h1 {
  margin: 0 0 20px;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.check-main {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 18px;
  align-items: start;
}

.scan {
  padding: 20px 24px;
}

.scan h2 {
  margin: 0 0 12px;
  font-size: 15.5px;
}

.scan input {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: #fff;
  padding: 12px 14px;
  font: inherit;
  font-size: 16px;
  letter-spacing: 0.04em;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.scan input:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
}

.scan form {
  margin-bottom: 8px;
}

.result {
  margin-top: 14px;
  display: flex;
  gap: 14px;
  padding: 14px 16px;
  border-radius: var(--radius-sm);
}

.result.ok {
  background: var(--accent-soft);
}

.result.fail {
  background: var(--rose-soft);
}

.r-icon {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  background: var(--accent);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  flex-shrink: 0;
}

.r-icon.r-fail {
  background: var(--rose);
}

.result .code {
  margin: 2px 0;
  font-weight: 600;
  letter-spacing: 0.05em;
}

.side {
  padding: 18px 22px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.side-metric {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--border);
}

.m-label {
  font-size: 13.5px;
  color: var(--ink-2);
}

.m-value {
  font-size: 28px;
  font-weight: 650;
}

.m-unit {
  font-size: 12.5px;
  color: var(--ink-3);
}

.rules h2 {
  margin: 0 0 8px;
  font-size: 14.5px;
}

.rules ul {
  margin: 0;
  padding-left: 20px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  color: var(--ink-2);
  font-size: 13.5px;
}

@media (max-width: 900px) {
  .check-main {
    grid-template-columns: 1fr;
  }
}
</style>
