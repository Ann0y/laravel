<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../../api'
import { useTicketingStore } from '../../stores/ticketing'

// F05 票务工作台：本人今日工作数据概览
const store = useTicketingStore()
const data = ref(null)
const error = ref('')
const loading = ref(true)

onMounted(async () => {
  try {
    data.value = await api('/operator/workbench')
  } catch (e) {
    error.value = e.status === 401 ? '登录已过期，请重新进入票务端' : e.message
  } finally {
    loading.value = false
  }
})

// 工作台数据依赖公开票种展示（未加载时兜底初始化，失败不打断）
store.ensure().catch(() => {})
</script>

<template>
  <div class="wb container">
    <header class="wb-head">
      <div>
        <h1>票务工作台</h1>
        <p v-if="data?.operator" class="muted num">
          工号 {{ data.operator.code }}<template v-if="data.operator.windowNo"> · 窗口 {{ data.operator.windowNo }}</template>
        </p>
      </div>
      <router-link to="/operator/check" class="btn btn-primary">去检票核销</router-link>
    </header>

    <p v-if="error" class="err">{{ error }}</p>

    <div class="metrics" :class="{ dim: loading }">
      <div class="m-card card">
        <span class="m-label">今日窗口订单</span>
        <span class="m-value num">{{ data?.todaySoldOrders ?? '—' }}</span>
        <span class="m-unit">笔 · 经手</span>
      </div>
      <div class="m-card card">
        <span class="m-label">今日交易额</span>
        <span class="m-value num">¥{{ data ? data.todayRevenue : '—' }}</span>
        <span class="m-unit">窗口渠道</span>
      </div>
      <div class="m-card card">
        <span class="m-label">今日出票</span>
        <span class="m-value num">{{ data?.todaySoldTickets ?? '—' }}</span>
        <span class="m-unit">张 · 全渠道</span>
      </div>
      <div class="m-card card hi">
        <span class="m-label">今日待核销</span>
        <span class="m-value num">{{ data?.todayPendingCheck ?? '—' }}</span>
        <span class="m-unit">张 · 游园当日</span>
      </div>
    </div>

    <div class="quick">
      <router-link to="/operator/sell" class="card q-card">
        <span class="q-title">现场售票</span>
        <span class="muted">为游客选票种 / 日期 / 数量，登记现金或扫码支付，提交即出票。</span>
      </router-link>
      <router-link to="/operator/check" class="card q-card">
        <span class="q-title">检票核销</span>
        <span class="muted">凭门票核销码核销入园，一码一次、当日有效，重复使用将被拒绝。</span>
      </router-link>
      <router-link to="/operator/orders" class="card q-card">
        <span class="q-title">我的售票记录</span>
        <span class="muted">查看本人经手的窗口订单与核销记录，按游园日期 / 状态筛选。</span>
      </router-link>
    </div>

    <section class="card guide">
      <h2>核销口径提示</h2>
      <ul>
        <li>门票仅<strong>游园日期当天</strong>有效，每日一次入园；</li>
        <li>核销码不存在 / 已检票 / 已退款 / 非当日，系统都会给出明确的拒绝原因；</li>
        <li>存在<strong>待审核退票申请</strong>的门票暂不可核销，请等管理员完成审核；</li>
        <li>整张订单的全部门票核销完成后，订单自动置为「已使用」。</li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.wb {
  padding: 32px 24px 0;
}

.wb-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 22px;
  flex-wrap: wrap;
}

.wb-head h1 {
  margin: 0;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.wb-head .muted {
  margin: 2px 0 0;
}

.metrics {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  transition: opacity 0.2s ease;
}

.metrics.dim {
  opacity: 0.55;
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
  color: var(--accent-hover);
}

.quick {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin: 22px 0;
}

.q-card {
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  transition: border-color 0.2s ease, transform 0.15s ease;
}

.q-card:hover {
  border-color: var(--accent);
  transform: translateY(-2px);
}

.q-title {
  font-weight: 600;
  font-size: 16px;
}

.guide {
  padding: 20px 24px;
}

.guide h2 {
  margin: 0 0 10px;
  font-size: 16px;
}

.guide ul {
  margin: 0;
  padding-left: 20px;
  color: var(--ink-2);
  font-size: 14.5px;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

@media (max-width: 900px) {
  .metrics {
    grid-template-columns: repeat(2, 1fr);
  }

  .quick {
    grid-template-columns: 1fr;
  }
}
</style>
