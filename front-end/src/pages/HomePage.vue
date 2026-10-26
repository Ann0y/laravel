<script setup>
import { computed, onMounted, ref } from 'vue'
import { useTicketingStore } from '../stores/ticketing'
import { useAuthStore } from '../stores/auth'
import { api } from '../api'
import { todayStr } from '../mock/seed'

const store = useTicketingStore()
const auth = useAuthStore()
const today = computed(() => todayStr())

// 游园评价（F16）：公开接口展示最新评价（昵称脱敏）
const reviews = ref([])
onMounted(() => {
  api('/reviews')
    .then((d) => (reviews.value = d.list))
    .catch(() => {})
})

// 员工（管理员 / 票务员）不在公共首页暴露购票入口：换成本端工作台跳转
const isVisitor = computed(() => !auth.user || auth.user?.role === 'visitor')
const staffEntry = computed(() => {
  if (auth.user?.role === 'admin') return { to: '/admin', label: '进入管理端' }
  if (auth.user?.role === 'operator') return { to: '/operator', label: '进入工作台' }
  return null
})

function remainingOf(t) {
  return store.remaining(today.value, t.id)
}

const stepList = [
  { title: '在线选票', desc: '选择游园日期、票种与购买数量，系统按每日额度实时校验余票。' },
  { title: '确认支付', desc: '提交订单后完成模拟支付，即刻出票，门票与核销码在订单中生成。' },
  { title: '扫码入园', desc: '游园当日出示门票二维码，检票员核销后入园，一码一人仅一次有效。' },
]
</script>

<template>
  <div>
    <!-- hero： 左文右图非对称分屏 -->
    <section class="hero container">
      <div class="hero-copy">
        <p class="hero-kicker">青岚谷风景区 线上售票</p>
        <h1>把一个周末，<br />还给青岚谷。</h1>
        <p class="hero-sub">
          峡谷栈道与森林步道两条经典线路，线上选票、到园出示二维码检票，少排队、不跑空。
        </p>
        <div class="hero-cta">
          <RouterLink v-if="staffEntry" :to="staffEntry.to" class="btn btn-primary">
            {{ staffEntry.label }}
          </RouterLink>
          <RouterLink v-else to="/buy" class="btn btn-primary">立即购票</RouterLink>
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
        <figcaption class="visually-hidden">青岚谷峡谷与栈道</figcaption>
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
            <template v-if="isVisitor">
              <button v-if="remainingOf(t) <= 0" class="btn btn-ghost btn-sm" disabled>
                已售罄
              </button>
              <RouterLink v-else :to="`/buy?type=${t.id}`" class="btn btn-ghost btn-sm">
                选购
              </RouterLink>
            </template>
          </footer>
        </article>
      </div>
    </section>

    /* 购票流程（纯游客宣传内容，员工角色不展示） */
    <section v-if="isVisitor" class="section alt">
      <div class="container">
        <h2 class="section-title">购票流程</h2>
        <div class="steps">
          <div class="step" v-for="(s, i) in stepList" :key="s.title">
            <span class="step-idx num">{{ String(i + 1).padStart(2, '0') }}</span>
            <div>
              <h3>{{ s.title }}</h3>
              <p>{{ s.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 游园人说（F16）：最新游园评价，昵称脱敏 -->
    <section v-if="reviews.length" class="section container">
      <h2 class="section-title">游园人说</h2>
      <p class="section-sub">来自已游园游客的真实反馈（昵称脱敏展示）。</p>
      <div class="review-grid">
        <article v-for="r in reviews" :key="r.id" class="review card">
          <p class="stars num" :aria-label="r.rating + ' 星评价'">
            {{ '★'.repeat(r.rating) }}<span class="dim">{{ '☆'.repeat(5 - r.rating) }}</span>
            <span class="stars-val num">{{ r.rating }}.0</span>
          </p>
          <p class="r-content">{{ r.content || '（该游客未填写文字评价）' }}</p>
          <footer class="r-foot">
            <span class="r-name">{{ r.visitorName }}</span>
            <span class="r-meta num">{{ r.typeName }} · {{ r.visitDate }}</span>
          </footer>
        </article>
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
.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

.hero {
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  gap: 56px;
  align-items: center;
  padding-top: 64px;
  padding-bottom: 32px;
}

.hero-kicker {
  margin: 0 0 18px;
  font-size: 13px;
  letter-spacing: 0.16em;
  color: var(--accent);
  font-weight: 560;
}

/* 游园人说（F16） */
.review-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.review {
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.stars {
  margin: 0;
  color: #d99a1f;
  letter-spacing: 2px;
}

.stars .dim {
  color: var(--border-strong);
}

.stars-val {
  margin-left: 6px;
  font-size: 13px;
  color: var(--ink-2);
  letter-spacing: 0;
}

.r-content {
  margin: 0;
  flex: 1;
  font-size: 14.5px;
  line-height: 1.7;
}

.r-foot {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  border-top: 1px dashed var(--border);
  padding-top: 10px;
  font-size: 13px;
  color: var(--ink-2);
}

.r-name {
  font-weight: 600;
}

@media (max-width: 767px) {
  .review-grid {
    grid-template-columns: 1fr;
  }
}

.hero-copy h1 {
  margin: 0 0 18px;
  font-size: clamp(36px, 4.8vw, 56px);
  line-height: 1.12;
  letter-spacing: -0.015em;
  font-weight: 680;
}

.hero-sub {
  margin: 0 0 32px;
  max-width: 32em;
  color: var(--ink-2);
  font-size: 16.5px;
}

.hero-cta {
  display: flex;
  gap: 14px;
  margin-bottom: 40px;
}

.hero-meta {
  display: flex;
  gap: 48px;
  margin: 0;
  padding-top: 24px;
  border-top: 1px solid var(--border);
}

.hero-meta dt {
  font-size: 12.5px;
  color: var(--ink-3);
  margin-bottom: 3px;
}

.hero-meta dd {
  margin: 0;
  font-size: 14.5px;
  color: var(--ink);
}

.hero-figure {
  margin: 0;
  position: relative;
}

.hero-figure img {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: var(--radius);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-card-hover);
}

.hero-figure::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: var(--radius);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
  pointer-events: none;
}

.section {
  padding: 56px 0;
}

.section.alt {
  padding: 56px 24px;
  background: var(--surface);
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  margin: 24px 0;
}

.section-title {
  margin: 0 0 8px;
  font-size: 25px;
  font-weight: 660;
  letter-spacing: -0.01em;
}

.section-sub {
  margin: 0 0 32px;
  color: var(--ink-2);
  font-size: 15px;
}

.type-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
}

.type-card {
  padding: 24px 24px 20px;
  display: flex;
  flex-direction: column;
}

.type-card:hover {
  box-shadow: var(--shadow-card-hover);
  transform: translateY(-2px);
}

.type-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.type-head h3 {
  margin: 0;
  font-size: 16.5px;
  font-weight: 640;
}

.tag {
  font-size: 12px;
  color: var(--accent-hover);
  background: var(--accent-soft);
  border-radius: 999px;
  padding: 2px 10px;
}

.price {
  margin: 0 0 10px;
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
  font-size: 36px;
  font-weight: 680;
  letter-spacing: -0.02em;
}

.unit {
  color: var(--ink-3);
  font-size: 13px;
}

.applicable {
  margin: 0 0 20px;
  font-size: 13.5px;
  color: var(--ink-2);
  min-height: 3.2em;
}

.type-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: auto;
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
  box-shadow: 0 0 0 1px var(--accent), var(--shadow-card);
}

.steps {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 36px;
}

.step {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.step-idx {
  flex: none;
  font-size: 13px;
  font-weight: 640;
  color: var(--accent);
  background: var(--accent-soft);
  border-radius: 999px;
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.step h3 {
  margin: 4px 0 8px;
  font-size: 16.5px;
  font-weight: 640;
}

.step p {
  margin: 0;
  color: var(--ink-2);
  font-size: 14.5px;
}

.notice {
  padding: 26px 30px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  border-left: 3px solid var(--accent);
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
