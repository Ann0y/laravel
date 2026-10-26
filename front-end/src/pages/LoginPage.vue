<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const mode = ref('login')
const username = ref('')
const password = ref('')
const name = ref('')
const confirm = ref('')
const error = ref('')
const submitting = ref(false)

async function submit() {
  error.value = ''
  submitting.value = true
  try {
    if (mode.value === 'login') {
      await auth.login(username.value.trim(), password.value)
    } else {
      if (!name.value.trim()) throw new Error('请填写姓名')
      if (password.value.length < 6) throw new Error('密码至少 6 位')
      if (password.value !== confirm.value) throw new Error('两次输入的密码不一致')
      await auth.register({
        username: username.value.trim(),
        password: password.value,
        name: name.value.trim(),
      })
    }
    // 员工登录直达本端工作台，不停留公共首页（管理员 / 票务员无购票职责）
    const role = auth.user?.role
    if (route.query.redirect) {
      router.push(route.query.redirect)
    } else if (role === 'admin') {
      router.push('/admin')
    } else if (role === 'operator') {
      router.push('/operator')
    } else {
      router.push('/')
    }
  } catch (e) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}

function fillDemo() {
  mode.value = 'login'
  username.value = 'demo'
  password.value = 'demo123456'
}
</script>

<template>
  <div class="login">
    <div class="login-grid container">
      <aside class="auth-aside">
        <img
          src="https://picsum.photos/seed/qinglan-forest/900/1200"
          alt="青岚谷森林步道（示意图片）"
        />
        <p class="aside-quote">峡谷、栈道与森林，都在四十分钟车程内。</p>
      </aside>

      <div class="auth-panel">
        <div class="auth-card card">
          <div class="tabs" role="tablist">
            <button role="tab" :class="{ active: mode === 'login' }" @click="mode = 'login'">
              登录
            </button>
            <button
              role="tab"
              :class="{ active: mode === 'register' }"
              @click="mode = 'register'; error = ''"
            >
              注册
            </button>
          </div>

          <form @submit.prevent="submit" novalidate>
            <div v-if="mode === 'register'" class="field">
              <label for="name">姓名</label>
              <input id="name" v-model="name" placeholder="用于订单与检票展示" />
            </div>
            <div class="field">
              <label for="username">用户名</label>
              <input id="username" v-model="username" autocomplete="username" />
            </div>
            <div class="field">
              <label for="password">密码</label>
              <input
                id="password"
                v-model="password"
                type="password"
                autocomplete="current-password"
              />
            </div>
            <div v-if="mode === 'register'" class="field">
              <label for="confirm">确认密码</label>
              <input id="confirm" v-model="confirm" type="password" autocomplete="new-password" />
            </div>

            <p v-if="error" class="err">{{ error }}</p>
            <button type="submit" class="btn btn-primary btn-block" :disabled="submitting">
              {{ submitting ? '提交中…' : mode === 'login' ? '登录' : '注册并登录' }}
            </button>
          </form>

          <p class="demo-hint">
            演示账号 <b>demo</b> 密码 <b>123456</b>
            <button class="link" @click="fillDemo">一键填入</button>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login {
  padding: 40px 0;
}

.login-grid {
  display: grid;
  grid-template-columns: 0.9fr 1.1fr;
  gap: 40px;
  align-items: stretch;
  min-height: 520px;
}

.auth-aside {
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  min-height: 380px;
}

.auth-aside img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.aside-quote {
  position: absolute;
  left: 24px;
  right: 24px;
  bottom: 20px;
  margin: 0;
  color: #fff;
  font-size: 16.5px;
  font-weight: 500;
  text-shadow: 0 1px 12px rgba(0, 0, 0, 0.45);
}

.auth-panel {
  display: flex;
  align-items: center;
}

.auth-card {
  width: 100%;
  max-width: 420px;
  margin: 0 auto;
  padding: 32px 34px;
}

.tabs {
  display: flex;
  gap: 4px;
  background: var(--zinc-soft);
  border-radius: 999px;
  padding: 4px;
  margin-bottom: 24px;
}

.tabs button {
  flex: 1;
  border: none;
  background: transparent;
  border-radius: 999px;
  padding: 8px 0;
  font-size: 14.5px;
  color: var(--ink-2);
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.tabs button.active {
  background: #fff;
  color: var(--ink);
  font-weight: 600;
}

form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.demo-hint {
  margin: 18px 0 0;
  text-align: center;
  font-size: 13.5px;
  color: var(--ink-2);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  flex-wrap: wrap;
}

@media (max-width: 960px) {
  .auth-aside {
    display: none;
  }

  .login-grid {
    grid-template-columns: 1fr;
    min-height: 0;
  }
}
</style>
