<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useTicketingStore } from './stores/ticketing'

const auth = useAuthStore()
const ticketing = useTicketingStore()
const router = useRouter()

// 按登录角色渲染导航（需求文档第 2 章：游客端 / 票务员端 / 管理端入口）
const links = computed(() => {
  const role = auth.user?.role
  if (role === 'operator') {
    return [
      { to: '/operator', label: '工作台' },
      { to: '/operator/sell', label: '现场售票' },
      { to: '/operator/check', label: '检票核销' },
      { to: '/operator/orders', label: '售票记录' },
    ]
  }
  if (role === 'admin') {
    return [
      { to: '/admin', label: '工作台' },
      { to: '/admin/manage', label: '票务管理' },
      { to: '/admin/orders', label: '订单门票' },
      { to: '/admin/refunds', label: '退票审核' },
      { to: '/admin/users', label: '用户管理' },
    ]
  }
  return [
    { to: '/', label: '首页' },
    { to: '/buy', label: '在线购票' },
    { to: '/extras', label: '园内项目' },
    { to: '/orders', label: '我的订单' },
  ]
})

onMounted(() => {
  // 公开数据（景区/票种/额度）加载失败时页面显示兜底，不打断渲染
  ticketing.ensure().catch(() => {})
})

function onLogout() {
  auth.logout()
  router.push('/')
}
</script>

<template>
  <div class="app-shell">
    <header class="topnav">
      <div class="nav-inner container">
        <RouterLink to="/" class="brand">
          <span class="brand-mark" aria-hidden="true">青</span>
          <span>青岚谷<span class="brand-sub">售票中心</span></span>
        </RouterLink>
        <nav class="nav-links">
          <!-- 以当前路径精确比对高亮，避免父路径（/operator 与 /operator/sell）同时高亮 -->
          <RouterLink
            v-for="l in links"
            :key="l.to"
            :to="l.to"
            :class="{ active: $route.path === l.to }"
            >{{ l.label }}</RouterLink
          >
        </nav>
        <div class="nav-auth">
          <template v-if="auth.user">
            <RouterLink to="/account" class="nav-user">{{ auth.user.name }}</RouterLink>
            <button class="btn btn-ghost btn-sm" @click="onLogout">退出</button>
          </template>
          <RouterLink v-else to="/login" class="btn btn-primary btn-sm">登录 / 注册</RouterLink>
        </div>
      </div>
    </header>

    <main class="app-main">
      <RouterView v-slot="{ Component }">
        <Transition name="page-fade" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>

    <footer class="app-footer">
      <div class="footer-inner container">
        <span>南岭市青岚区云栖路 88 号</span>
        <span class="sep" aria-hidden="true">/</span>
        <span class="num">08:30-17:00</span>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.topnav {
  background: rgba(246, 247, 246, 0.85);
  backdrop-filter: blur(12px) saturate(140%);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 20;
}

.nav-inner {
  height: 68px;
  display: flex;
  align-items: center;
  gap: 32px;
}

.brand {
  font-size: 19px;
  font-weight: 650;
  letter-spacing: 0.02em;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  white-space: nowrap;
}

.brand-mark {
  display: inline-flex;
  width: 30px;
  height: 30px;
  align-items: center;
  justify-content: center;
  border-radius: 9px;
  background: var(--accent);
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  letter-spacing: 0;
  box-shadow: 0 2px 8px rgba(14, 122, 90, 0.25);
}

.brand-sub {
  font-size: 13px;
  font-weight: 400;
  color: var(--ink-3);
}

.nav-links {
  display: flex;
  gap: 6px;
  flex: 1;
  overflow-x: auto;
}

.nav-links a {
  color: var(--ink-2);
  font-size: 14.5px;
  padding: 7px 14px;
  border-radius: 999px;
  transition: color 0.2s ease, background-color 0.2s ease;
  white-space: nowrap;
}

.nav-links a:hover {
  color: var(--ink);
  background: rgba(32, 38, 31, 0.05);
}

.nav-links a.active {
  color: var(--accent-hover);
  background: var(--accent-soft);
  font-weight: 560;
}

.nav-auth {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 14px;
}

.nav-user {
  font-size: 15px;
  color: var(--ink);
  padding: 6px 12px;
  border-radius: 999px;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.nav-user:hover {
  color: var(--accent);
  background: var(--accent-soft);
}

.app-main {
  min-height: calc(100vh - 68px - 60px);
}

.app-footer {
  border-top: 1px solid var(--border);
  padding: 24px 0;
  margin-top: 80px;
}

.footer-inner {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--ink-3);
  font-size: 13px;
  flex-wrap: wrap;
}

.footer-inner .sep {
  color: var(--border-strong);
}

@media (max-width: 767px) {
  .nav-inner {
    gap: 16px;
  }

  .nav-links {
    gap: 14px;
  }

  .nav-links a {
    font-size: 14px;
  }

  .brand {
    gap: 8px;
  }

  .brand-mark {
    width: 26px;
    height: 26px;
    font-size: 13px;
  }

  .brand-sub {
    display: none;
  }

  .footer-inner {
    gap: 6px;
  }
}
</style>
