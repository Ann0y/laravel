<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

// 个人信息（姓名 / 用户名，角色只读展示）
const infoForm = ref({ name: '', username: '' })
// 密码（选填：三项都不填则只保存资料）
const pwdForm = ref({ oldPassword: '', newPassword: '', confirm: '' })
const error = ref('')
const success = ref(false)

const roleLabel = { visitor: '游客', operator: '票务员', admin: '管理员' }

watch(
  () => auth.user,
  (u) => {
    if (u) {
      infoForm.value = { name: u.name || '', username: u.username || '' }
    }
  },
  { immediate: true },
)

// 一个保存按钮统一提交：资料必存，密码填了才校验并更新
async function saveAll() {
  error.value = ''
  success.value = false

  if (!infoForm.value.name.trim()) {
    error.value = '姓名不能为空'
    return
  }
  if (!infoForm.value.username.trim()) {
    error.value = '用户名不能为空'
    return
  }

  const touchingPwd =
    pwdForm.value.oldPassword || pwdForm.value.newPassword || pwdForm.value.confirm
  if (touchingPwd) {
    if (!pwdForm.value.oldPassword) {
      error.value = '请输入原密码'
      return
    }
    if (pwdForm.value.newPassword.length < 6) {
      error.value = '新密码至少 6 位'
      return
    }
    if (pwdForm.value.newPassword !== pwdForm.value.confirm) {
      error.value = '两次输入的新密码不一致'
      return
    }
  }

  try {
    await auth.updateProfile({
      name: infoForm.value.name.trim(),
      username: infoForm.value.username.trim(),
    })
    if (touchingPwd) {
      await auth.changePassword(pwdForm.value.oldPassword, pwdForm.value.newPassword)
      // 密码已修改：强制退出登录，跳转登录页用新密码重新登录（登录后回首页）
      await auth.logout()
      router.push({ name: 'login', query: { reason: 'password' } })
      return
    }
    success.value = true
  } catch (e) {
    if (e.status === 401) {
      error.value = '登录已过期，请重新登录'
      await auth.logout()
      router.push({ name: 'login', query: { redirect: '/account' } })
    } else {
      error.value = e.message
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

    <form @submit.prevent="saveAll" novalidate>
      <div class="account-grid">
        <section class="card block">
          <header class="block-head">
            <h2>我的信息</h2>
            <button type="button" class="btn btn-ghost btn-sm" @click="onLogout">退出登录</button>
          </header>
          <div class="fields">
            <div class="field">
              <label for="profile-name">姓名</label>
              <input id="profile-name" v-model="infoForm.name" maxlength="50" autocomplete="name" />
            </div>
            <div class="field">
              <label for="profile-username">用户名</label>
              <input
                id="profile-username"
                v-model="infoForm.username"
                maxlength="50"
                autocomplete="username"
              />
            </div>
            <div class="info-row">
              <span class="info-label">角色</span>
              <span>{{ roleLabel[auth.user?.role] || auth.user?.role }}</span>
            </div>
          </div>
        </section>

        <section class="card block">
          <header class="block-head">
            <h2>修改密码</h2>
            <span class="hint">不修改可留空</span>
          </header>
          <div class="fields">
            <div class="field">
              <label for="old">原密码</label>
              <input
                id="old"
                v-model="pwdForm.oldPassword"
                type="password"
                autocomplete="current-password"
              />
            </div>
            <div class="field">
              <label for="new">新密码（至少 6 位）</label>
              <input
                id="new"
                v-model="pwdForm.newPassword"
                type="password"
                autocomplete="new-password"
              />
            </div>
            <div class="field">
              <label for="confirm">确认新密码</label>
              <input
                id="confirm"
                v-model="pwdForm.confirm"
                type="password"
                autocomplete="new-password"
              />
            </div>
          </div>
        </section>
      </div>

      <div class="save-row">
        <button type="submit" class="btn btn-primary">保存</button>
        <p v-if="error" class="err">{{ error }}</p>
        <p v-else-if="success" class="ok">已保存。</p>
      </div>
    </form>
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

.hint {
  font-size: 12.5px;
  color: var(--ink-3);
}

.fields {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
  font-size: 14.5px;
}

.info-row .info-label {
  color: var(--ink-2);
  font-size: 14px;
}

.save-row {
  margin-top: 16px;
  display: flex;
  align-items: center;
  gap: 14px;
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
