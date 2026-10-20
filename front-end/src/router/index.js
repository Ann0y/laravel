import { createRouter, createWebHistory } from 'vue-router'
import HomePage from '../pages/HomePage.vue'
import BuyPage from '../pages/BuyPage.vue'
import OrdersPage from '../pages/OrdersPage.vue'
import LoginPage from '../pages/LoginPage.vue'
import AccountPage from '../pages/AccountPage.vue'

const routes = [
  { path: '/', name: 'home', component: HomePage },
  { path: '/buy', name: 'buy', component: BuyPage, meta: { auth: true } },
  { path: '/orders', name: 'orders', component: OrdersPage, meta: { auth: true } },
  { path: '/account', name: 'account', component: AccountPage, meta: { auth: true } },
  { path: '/login', name: 'login', component: LoginPage },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

// 登录守卫：与需求文档口径一致（Session 登录态；原型以 localStorage 会话代替）
router.beforeEach((to) => {
  if (to.meta.auth && !localStorage.getItem('st_session')) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
})

export default router
