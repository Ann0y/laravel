<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = ref({ oldPassword: '', newPassword: '', confirm: '' })
const error = ref('')
const success = ref(false)

async function submit() {
  error.value = ''
  success.value = false
  try {
    if (form.value.newPassword !== form.value.confirm) {
      throw new Error('两次输入的新密码不一致')
    }
    if (form.value.newPassword.length < 6) {
      throw new Error('新密码至少 6 位')
    }
    await auth.changePassword(form.value.oldPassword, form.value.newPassword)
    form.value = { oldPassword: '', newPassword: '', confirm: '' }
    success.value = true
  } catch (e) {
    error.value = e.status === 401 ? '登录已过期，请重新登录' : e.message
    if (e.status === 401) {
      await auth.logout()
      router.push({ name: 'login', query: { redirect: '/account' } })
    }
  }
}

function onLogout() {
  auth.logout()
  router.push('/')
}
</script>

<template>
  <div class="account container">
    <h1>个人中心</h1>

    <div class="account-grid">
      <section class="card block">
        <header class="block-head">
          <h2>我的信息</h2>
          <button class="btn btn-ghost btn-sm" @click="onLogout">退出登录</button>
        </header>
        <dl class="info-list">
          <div>
            <dt>姓名</dt>
            <dd>{{ auth.user?.name }}</dd>
          </div>
          <div>
            <dt>用户名</dt>
            <dd class="num">{{ auth.user?.username }}</dd>
          </div>
          <div>
            <dt>角色</dt>
            <dd>游客</dd>
          </div>
        </dl>
      </section>

      <section class="card block">
        <h2>修改密码</h2>
        <form @submit.prevent="submit" novalidate>
          <div class="field">
            <label for="old">原密码</label>
            <input id="old" v-model="form.oldPassword" type="password" autocomplete="current-password" />
          </div>
          <div class="field">
            <label for="new">新密码（至少 6 位）</label>
            <input id="new" v-model="form.newPassword" type="password" autocomplete="new-password" />
          </div>
          <div class="field">
            <label for="confirm">确认新密码</label>
            <input id="confirm" v-model="form.confirm" type="password" autocomplete="new-password" />
          </div>
          <p v-if="error" class="err">{{ error }}</p>
          <p v-if="success" class="ok">密码已更新。</p>
          <button type="submit" class="btn btn-primary">保存修改</button>
        </form>
      </section>
    </div>
  </div>
</template>

<style scoped>
.account {
  padding: 32px 24px 0;
  max-width: 880px;
}

.account h1 {
  margin: 0 0 20px;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.account-grid {
  display: grid;
  grid-template-columns: 0.85fr 1.15fr;
  gap: 16px;
  align-items: start;
}

.block {
  padding: 22px 24px;
}

.block-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.block h2 {
  margin: 0;
  font-size: 16.5px;
  font-weight: 620;
}

.info-list {
  margin: 0;
  display: flex;
  flex-direction: column;
}

.info-list div {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
}

.info-list div:last-child {
  border-bottom: none;
}

.info-list dt {
  color: var(--ink-2);
  font-size: 14px;
}

.info-list dd {
  margin: 0;
  font-size: 14.5px;
}

form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  max-width: 360px;
}

.ok {
  color: var(--accent-hover);
  font-size: 13.5px;
  margin: 0;
}

@media (max-width: 767px) {
  .account-grid {
    grid-template-columns: 1fr;
  }
}
</style>
