import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

import HomePage from '../pages/HomePage.vue'
import BuyPage from '../pages/BuyPage.vue'
import OrdersPage from '../pages/OrdersPage.vue'
import LoginPage from '../pages/LoginPage.vue'
import AccountPage from '../pages/AccountPage.vue'

// 票务员端（F05–F08）
import OperatorWorkbenchPage from '../pages/operator/WorkbenchPage.vue'
import OperatorSellPage from '../pages/operator/SellPage.vue'
import OperatorCheckPage from '../pages/operator/CheckPage.vue'
import OperatorOrdersPage from '../pages/operator/OperatorOrdersPage.vue'

// 后台管理端（F09–F15 + E2）
import AdminHomePage from '../pages/admin/AdminHomePage.vue'
import AdminManagePage from '../pages/admin/ManagePage.vue'
import AdminOrdersPage from '../pages/admin/AdminOrdersPage.vue'
import AdminRefundsPage from '../pages/admin/RefundsPage.vue'
import AdminUsersPage from '../pages/admin/UsersPage.vue'

const routes = [
  // 游客端
  { path: '/', name: 'home', component: HomePage },
  { path: '/buy', name: 'buy', component: BuyPage, meta: { auth: true, roles: ['visitor'] } },
  { path: '/extras', name: 'extras', component: () => import('../pages/ExtrasPage.vue'), meta: { auth: true, roles: ['visitor'] } },
  { path: '/orders', name: 'orders', component: OrdersPage, meta: { auth: true, roles: ['visitor'] } },
  { path: '/login', name: 'login', component: LoginPage },
  { path: '/account', name: 'account', component: AccountPage, meta: { auth: true } },

  // 票务员端
  { path: '/operator', name: 'operator-workbench', component: OperatorWorkbenchPage, meta: { auth: true, roles: ['operator'] } },
  { path: '/operator/sell', name: 'operator-sell', component: OperatorSellPage, meta: { auth: true, roles: ['operator'] } },
  { path: '/operator/check', name: 'operator-check', component: OperatorCheckPage, meta: { auth: true, roles: ['operator'] } },
  { path: '/operator/orders', name: 'operator-orders', component: OperatorOrdersPage, meta: { auth: true, roles: ['operator'] } },

  // 后台管理端
  { path: '/admin', name: 'admin-home', component: AdminHomePage, meta: { auth: true, roles: ['admin'] } },
  { path: '/admin/manage', name: 'admin-manage', component: AdminManagePage, meta: { auth: true, roles: ['admin'] } },
  { path: '/admin/orders', name: 'admin-orders', component: AdminOrdersPage, meta: { auth: true, roles: ['admin'] } },
  { path: '/admin/refunds', name: 'admin-refunds', component: AdminRefundsPage, meta: { auth: true, roles: ['admin'] } },
  { path: '/admin/users', name: 'admin-users', component: AdminUsersPage, meta: { auth: true, roles: ['admin'] } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

// 登录 + 角色守卫：登录态以后端 Session 为准（启动时已探测 /auth/me 并镜像到本地）
// meta.roles 角色不符时回首页（对应需求文档：前端按登录角色渲染入口与菜单）
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.init()

  if (to.meta.auth && !auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.roles && auth.user && !to.meta.roles.includes(auth.user.role)) {
    // 角色不符时回本端工作台（管理员 / 票务员没有购票职责，不落公共首页）
    const r = auth.user.role
    return { path: r === 'admin' ? '/admin' : r === 'operator' ? '/operator' : '/' }
  }
})

export default router
