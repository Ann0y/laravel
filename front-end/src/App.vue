<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useTicketingStore } from './stores/ticketing'

const auth = useAuthStore()
const ticketing = useTicketingStore()
const router = useRouter()

onMounted(() => {
  ticketing.ensure()
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
          青岚谷<span class="brand-sub">售票中心</span>
        </RouterLink>
        <nav class="nav-links">
          <RouterLink to="/">首页</RouterLink>
          <RouterLink to="/buy">在线购票</RouterLink>
          <RouterLink to="/orders">我的订单</RouterLink>
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
        <span>青岚谷风景区</span>
        <span class="num">08:30-17:00</span>
        <span>南岭市青岚区云栖路 88 号</span>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.topnav {
  background: rgba(245, 247, 246, 0.88);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 20;
}

.nav-inner {
  height: 64px;
  display: flex;
  align-items: center;
  gap: 32px;
}

.brand {
  font-size: 19px;
  font-weight: 650;
  letter-spacing: 0.02em;
}

.brand-sub {
  margin-left: 8px;
  font-size: 13px;
  font-weight: 400;
  color: var(--ink-3);
}

.nav-links {
  display: flex;
  gap: 24px;
  flex: 1;
}

.nav-links a {
  color: var(--ink-2);
  font-size: 15px;
  transition: color 0.2s ease;
}

.nav-links a:hover,
.nav-links a.router-link-active {
  color: var(--ink);
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
}

.nav-user:hover {
  color: var(--accent);
}

.app-main {
  min-height: calc(100vh - 64px - 60px);
}

.app-footer {
  border-top: 1px solid var(--border);
  padding: 20px 0;
  margin-top: 64px;
}

.footer-inner {
  display: flex;
  gap: 24px;
  color: var(--ink-3);
  font-size: 13px;
  flex-wrap: wrap;
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

  .brand-sub {
    display: none;
  }
}
</style>
