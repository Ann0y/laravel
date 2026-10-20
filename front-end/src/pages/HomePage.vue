<script setup>
import { computed } from 'vue'
import { useTicketingStore } from '../stores/ticketing'
import { todayStr } from '../mock/seed'

const store = useTicketingStore()
const today = computed(() => todayStr())

function remainingOf(t) {
  return store.remaining(today.value, t.id)
}
</script>

<template>
  <div>
    <!-- hero： 左文右图非对称分屏 -->
    <section class="hero container">
      <div class="hero-copy">
        <p class="hero-kicker">青岚谷风景区 线上售票</p>
        <h1>把一个周末，还给青岚谷。</h1>
        <p class="hero-sub">
          峡谷栈道与森林步道两条经典线路，线上选票、到园出示二维码检票，少排队、不跑空。
        </p>
        <div class="hero-cta">
          <RouterLink to="/buy" class="btn btn-primary">立即购票</RouterLink>
          <a href="#tickets" class="btn btn-ghost">查看票种</a>
        </div>
        <dl class="hero-meta">
          <div>
            <dt>开放时间</dt>
            <dd class="num">08:30-17:00</dd>
          </div>
          <div>
            <dt>景区地址</dt>
            <dd>南岭市青岚区云栖路 88 号</dd>
          </div>
        </dl>
      </div>
      <figure class="hero-figure">
        <img
          src="https://picsum.photos/seed/qinglan-valley/1200/900"
          alt="青岚谷峡谷与栈道（示意图片）"
        />
      </figure>
    </section>

    <!-- 票种与价格 -->
    <section id="tickets" class="section container">
      <h2 class="section-title">票种与价格</h2>
      <p class="section-sub">按游园日期限量发售，售完即止。</p>
      <div class="type-grid">
        <article
          v-for="t in store.types"
          :key="t.id"
          class="type-card card"
          :class="{ featured: t.id === 'ADULT' }"
        >
          <header class="type-head">
            <h3>{{ t.name }}</h3>
            <span v-if="t.id === 'ADULT'" class="tag">常规推荐</span>
          </header>
          <p class="price">
            <span class="cur">¥</span><span class="amount num">{{ t.price }}</span
            ><span class="unit">/ 张</span>
          </p>
          <p class="applicable">{{ t.applicable }}</p>
          <footer class="type-foot">
            <span class="rest num" :class="{ soldout: remainingOf(t) <= 0 }">
              {{ remainingOf(t) > 0 ? `今日剩余 ${remainingOf(t)} 张` : '今日已售罄' }}
            </span>
            <button v-if="remainingOf(t) <= 0" class="btn btn-ghost btn-sm" disabled>
              已售罄
            </button>
            <RouterLink v-else :to="`/buy?type=${t.id}`" class="btn btn-ghost btn-sm">
              选购
            </RouterLink>
          </footer>
        </article>
      </div>
    </section>

    <!-- 购票流程 -->
    <section class="section alt">
      <div class="container">
        <h2 class="section-title">购票流程</h2>
        <div class="steps">
          <div class="step">
            <h3>在线选票</h3>
            <p>选择游园日期、票种与购买数量，系统按每日额度实时校验余票。</p>
          </div>
          <div class="step">
            <h3>确认支付</h3>
            <p>提交订单后完成模拟支付，即刻出票，门票与核销码在订单中生成。</p>
          </div>
          <div class="step">
            <h3>扫码入园</h3>
            <p>游园当日出示门票二维码，检票员核销后入园，一码一人仅一次有效。</p>
          </div>
        </div>
      </div>
    </section>

    <!-- 游园公告 -->
    <section class="section container">
      <h2 class="section-title">游园公告</h2>
      <div class="notice card">
        <p>{{ store.area.notice }}</p>
        <p class="muted">票价含园区基础游览项目；缆车、讲解器等二次消费项目以园区现场公示为准。</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.hero {
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  gap: 48px;
  align-items: center;
  padding-top: 56px;
  padding-bottom: 24px;
}

.hero-kicker {
  margin: 0 0 14px;
  font-size: 13.5px;
  letter-spacing: 0.14em;
  color: var(--ink-3);
}

.hero-copy h1 {
  margin: 0 0 16px;
  font-size: clamp(34px, 4.6vw, 52px);
  line-height: 1.15;
  letter-spacing: -0.01em;
  font-weight: 650;
}

.hero-sub {
  margin: 0 0 28px;
  max-width: 34em;
  color: var(--ink-2);
}

.hero-cta {
  display: flex;
  gap: 14px;
  margin-bottom: 36px;
}

.hero-meta {
  display: flex;
  gap: 40px;
  margin: 0;
  padding-top: 22px;
  border-top: 1px solid var(--border);
}

.hero-meta dt {
  font-size: 12.5px;
  color: var(--ink-3);
  margin-bottom: 2px;
}

.hero-meta dd {
  margin: 0;
  font-size: 14.5px;
  color: var(--ink);
}

.hero-figure {
  margin: 0;
}

.hero-figure img {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: 16px;
  border: 1px solid var(--border);
}

.section {
  padding: 44px 0;
}

.section.alt {
  padding: 44px 24px;
  background: var(--surface);
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  margin: 24px 0;
}

.section-title {
  margin: 0 0 6px;
  font-size: 24px;
  font-weight: 620;
  letter-spacing: -0.01em;
}

.section-sub {
  margin: 0 0 28px;
  color: var(--ink-2);
  font-size: 15px;
}

.type-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.type-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.type-head h3 {
  margin: 0;
  font-size: 16.5px;
  font-weight: 620;
}

.tag {
  font-size: 12px;
  color: var(--accent-hover);
  background: var(--accent-soft);
  border-radius: 999px;
  padding: 2px 10px;
}

.price {
  margin: 0 0 8px;
  display: flex;
  align-items: baseline;
  gap: 2px;
}

.cur {
  color: var(--accent);
  font-size: 15px;
  font-weight: 600;
}

.amount {
  font-size: 32px;
  font-weight: 650;
  letter-spacing: -0.02em;
}

.unit {
  color: var(--ink-3);
  font-size: 13px;
}

.applicable {
  margin: 0 0 18px;
  font-size: 13.5px;
  color: var(--ink-2);
  min-height: 3.2em;
}

.type-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.rest {
  font-size: 13px;
  color: var(--ink-2);
}

.rest.soldout {
  color: var(--rose);
}

.featured {
  border-color: var(--accent);
}

.steps {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 32px;
}

.step h3 {
  margin: 0 0 8px;
  font-size: 16.5px;
  font-weight: 620;
}

.step p {
  margin: 0;
  color: var(--ink-2);
  font-size: 14.5px;
}

.notice {
  padding: 24px 28px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.notice p {
  margin: 0;
}

@media (max-width: 960px) {
  .hero {
    grid-template-columns: 1fr;
    gap: 28px;
    padding-top: 36px;
  }

  .type-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .steps {
    grid-template-columns: 1fr;
    gap: 20px;
  }
}

@media (max-width: 640px) {
  .type-grid {
    grid-template-columns: 1fr;
  }

  .applicable {
    min-height: 0;
  }

  .hero-cta {
    flex-wrap: wrap;
  }
}
</style>
