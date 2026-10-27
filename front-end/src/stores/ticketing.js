import { defineStore } from 'pinia'
import { api } from '../api'
import { useAuthStore } from './auth'

const MAX_QTY = 5 // 保留：普通票种的单笔限购口径（团体票为动态 10-30，见 qtyLimit）

// 单笔购买数量区间：普通票 1-5 张，团体票 10-30 张（须 10 人成团，口径同后端 E1 校验）
function qtyLimit(typeId) {
  return typeId === 'GROUP' ? { min: 10, max: 30 } : { min: 1, max: 5 }
}

// 数据层：本地状态缓存来自后端接口（景区/票种/额度/订单）。
// 库存校验、占额回补、订单状态机均由后端完成（对应需求文档 E1 与第 3 章）。
export const useTicketingStore = defineStore('ticketing', {
  state: () => ({
    area: null,
    types: [],
    stock: {}, // date -> { closed, per: { [typeId]: { quota, sold } } }
    orders: [],
    loaded: false,
  }),
  getters: {
    openDates: (s) => Object.keys(s.stock).sort(),
  },
  actions: {
    // 拉取公开数据：景区信息、票种、未来 7 天开放日与各票种额度
    async ensure(force = false) {
      if (this.loaded && !force) return
      const data = await api('/area')
      this.area = data.area
      this.types = data.types
      const stock = {}
      for (const day of data.openDays) {
        stock[day.date] = { closed: day.closed, per: day.per }
      }
      this.stock = stock
      this.loaded = true
    },
    // 我的订单（游客）；会话过期时清空并跳登录
    async loadOrders() {
      const auth = useAuthStore()
      if (!auth.isLogin) {
        this.orders = []
        return
      }
      try {
        this.orders = (await api('/orders/my')).orders
      } catch (e) {
        if (e.status === 401) auth.forceLogout()
        throw e
      }
    },
    async refresh() {
      await Promise.all([this.ensure(true), this.loadOrders().catch(() => {})])
    },
    remaining(date, typeId) {
      const d = this.stock[date]
      if (!d || d.closed) return 0
      const p = d.per[typeId]
      if (!p) return 0
      return Math.max(0, p.quota - p.sold)
    },
    typeOf(id) {
      return this.types.find((t) => t.id === id)
    },
    findTicket(ticketNo) {
      for (const o of this.orders) {
        const t = o.tickets.find((x) => x.ticketNo === ticketNo)
        if (t) return { order: o, ticket: t }
      }
      return null
    },

    // 下单（库存不足/闭园/停售由后端校验，错误经 throw e.message 返回）
    async createOrder(typeId, visitDate, quantity) {
      const data = await api('/orders', {
        method: 'POST',
        body: { typeId, visitDate, quantity },
      })
      this.orders.unshift(data.order)
      // 占额后本地下拉余量同步 -quantity，避免重复下单余量显示不刷新
      const per = this.stock[visitDate]?.per[typeId]
      if (per) per.sold = Math.min(per.quota, per.sold + quantity)
      return data.order
    },
    async pay(orderId) {
      const data = await api(`/orders/${orderId}/pay`, { method: 'POST' })
      this.replaceOrder(data.order)
      return data.order
    },
    async cancel(orderId) {
      const data = await api(`/orders/${orderId}/cancel`, { method: 'POST' })
      const o = this.orders.find((x) => x.id === orderId)
      if (o) {
        // 取消回补当日额度
        const per = this.stock[o.visitDate]?.per[o.typeId]
        if (per) per.sold = Math.max(0, per.sold - o.quantity)
      }
      this.replaceOrder(data.order)
      return data.order
    },
    // 退票申请（票维度），申请后刷新订单以获取审核状态
    async requestRefund(ticketNo, reason) {
      await api('/refunds', {
        method: 'POST',
        body: { ticketNo, reason },
      })
      await this.loadOrders()
    },
    // 门票改期（整单未检票门票迁移）：原日回补、新日占额（F03 扩展）
    async reschedule(orderId, visitDate) {
      const data = await api(`/orders/${orderId}/reschedule`, {
        method: 'POST',
        body: { visitDate },
      })
      const o = this.orders.find((x) => x.id === orderId)
      if (o) {
        // 本地余量口径同步：旧日回补未检票数，新日占同等数量
        const moved = o.tickets.filter((t) => t.status === '未检票').length
        const oldPer = this.stock[o.visitDate]?.per[o.typeId]
        const newPer = this.stock[visitDate]?.per[o.typeId]
        if (oldPer) oldPer.sold = Math.max(0, oldPer.sold - moved)
        if (newPer) newPer.sold = Math.min(newPer.quota, newPer.sold + moved)
      }
      this.replaceOrder(data.order)
      return data.order
    },
    // 游园评价（F16：已检票门票一票一评）
    async submitReview(ticketNo, rating, content) {
      await api('/reviews', {
        method: 'POST',
        body: { ticketNo, rating, content: content || undefined },
      })
      await this.loadOrders()
    },
    // 撤回待审核的退票申请（撤回后订单状态按票态恢复）
    async withdrawRefund(refundId) {
      await api(`/refunds/${refundId}`, { method: 'DELETE' })
      await this.loadOrders()
    },
    // 演示辅助已移除：退票审核由管理员端接口完成（PATCH /api/refunds/{id}/audit）
    replaceOrder(order) {
      const i = this.orders.findIndex((x) => x.id === order.id)
      if (i >= 0) this.orders.splice(i, 1, order)
      else this.orders.unshift(order)
    },
  },
})

export { qtyLimit }
