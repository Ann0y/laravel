import { defineStore } from 'pinia'
import { load, save } from '../utils/storage'

const USERS_KEY = 'st_users'
const SESSION_KEY = 'st_session'

// 仅演示用途：口令明文存于 localStorage，真实系统按需求文档使用 BCrypt（第 8 章）
const seedUsers = [{ username: 'demo', password: '123456', name: '张临风' }]

export const useAuthStore = defineStore('auth', {
  state: () => ({
    users: load(USERS_KEY, seedUsers),
    user: load(SESSION_KEY, null),
  }),
  getters: {
    isLogin: (s) => !!s.user,
  },
  actions: {
    persistUsers() {
      save(USERS_KEY, this.users)
    },
    login(username, password) {
      const u = this.users.find((x) => x.username === username)
      if (!u || u.password !== password) throw new Error('用户名或密码不正确')
      this.user = { username: u.username, name: u.name, role: 'visitor' }
      save(SESSION_KEY, this.user)
      return this.user
    },
    register({ username, password, name }) {
      const un = username.trim()
      if (this.users.some((x) => x.username === un)) throw new Error('该用户名已被注册')
      const u = { username: un, password, name: name.trim() }
      this.users.push(u)
      this.persistUsers()
      this.user = { username: u.username, name: u.name, role: 'visitor' }
      save(SESSION_KEY, this.user)
      return this.user
    },
    logout() {
      this.user = null
      localStorage.removeItem(SESSION_KEY)
    },
    changePassword(oldPassword, newPassword) {
      const u = this.users.find((x) => x.username === this.user?.username)
      if (!u || u.password !== oldPassword) throw new Error('原密码不正确')
      if (newPassword.length < 6) throw new Error('新密码至少 6 位')
      u.password = newPassword
      this.persistUsers()
    },
  },
})
