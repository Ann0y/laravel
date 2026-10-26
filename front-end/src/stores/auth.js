import { defineStore } from 'pinia'
import { api } from '../api'

const SESSION_KEY = 'st_session'

// 登录态由后端 Session 承载（Cookie），本地仅镜像一份用于路由守卫
function persist(user) {
  this.user = user
  if (user) localStorage.setItem(SESSION_KEY, JSON.stringify(user))
  else localStorage.removeItem(SESSION_KEY)
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    ready: false, // 是否已完成一次会话探测
    booted: false,
  }),
  getters: {
    isLogin: (s) => !!s.user,
  },
  actions: {
    // 应用启动时探测会话：有会话则恢复用户信息，无则清空镜像
    async init() {
      if (this.booted) return
      this.booted = true
      try {
        const user = await api('/auth/me')
        persist.call(this, user)
      } catch (e) {
        if (e.status === 401) persist.call(this, null)
      } finally {
        this.ready = true
      }
    },
    async login(username, password) {
      const user = await api('/auth/login', {
        method: 'POST',
        body: { username, password },
      })
      persist.call(this, user)
      return user
    },
    async register({ username, password, name }) {
      const user = await api('/auth/register', {
        method: 'POST',
        body: { username, password, name },
      })
      persist.call(this, user)
      return user
    },
    async logout() {
      try {
        await api('/auth/logout', { method: 'POST' })
      } catch {
        // 会话已失效也视为退出成功
      }
      persist.call(this, null)
    },
    // 会话在服务端过期时由其他 store 调用
    forceLogout() {
      persist.call(this, null)
    },
    async changePassword(oldPassword, newPassword) {
      await api('/auth/password', {
        method: 'PUT',
        body: { oldPassword, newPassword },
      })
    },
  },
})
