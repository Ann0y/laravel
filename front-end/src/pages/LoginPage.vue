<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import sideImg from '../assets/images/login-side.jpg'

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
    // 登录成功后统一进入首页（员工同样先看公共首页，可从导航进入各自工作台）
    router.push('/')
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

// 演示快捷登录：管理员一键直达管理端（演示账号 admin / admin123）
const quickBusy = ref(false)
async function quickLoginAdmin() {
  error.value = ''
  quickBusy.value = true
  try {
    await auth.login('admin', 'admin123')
    router.push('/admin')
  } catch (e) {
    error.value = e.message
  } finally {
    quickBusy.value = false
  }
}
</script>

<template>
  <div class="login">
    <div class="login-grid container">
      <aside class="auth-aside">
        <img
          :src="sideImg"
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
            <p v-if="route.query.reason === 'password'" class="notice">
              密码已修改，请使用新密码重新登录。
            </p>
            <button type="submit" class="btn btn-primary btn-block" :disabled="submitting">
              {{ submitting ? '提交中…' : mode === 'login' ? '登录' : '注册并登录' }}
            </button>
          </form>

          <p class="demo-hint">
            <span class="demo-row">
              游客演示 <b>demo</b> / <b>demo123456</b>
              <button class="link" @click="fillDemo">一键填入</button>
            </span>
            <span class="demo-row">
              管理员演示 <b>admin</b> / <b>admin123</b>
              <button class="link" :disabled="quickBusy" @click="quickLoginAdmin">
                {{ quickBusy ? '登录中…' : '一键登录' }}
              </button>
            </span>
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
  border-radius: var(--radius);
  overflow: hidden;
  min-height: 380px;
  box-shadow: var(--shadow-card-hover);
}

.auth-aside::before {
  /* 底部压暗渐变：保证引文在任意照片上都可读 */
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(16, 22, 19, 0) 55%, rgba(16, 22, 19, 0.55) 100%);
  pointer-events: none;
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
  box-shadow: 0 1px 3px rgba(28, 38, 31, 0.1);
}

form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.notice {
  margin: 0;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  background: var(--accent-soft, #eef6f0);
  color: var(--accent-hover);
  font-size: 13.5px;
}

.demo-hint {  margin: 18px 0 0;
  padding-top: 16px;
  text-align: center;
  font-size: 13.5px;
  color: var(--ink-2);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  border-top: 1px solid var(--border);
}

.demo-row {
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
