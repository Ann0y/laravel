import { defineStore } from 'pinia'
import { load, save } from '../utils/storage'
import { AREA, TICKET_TYPES, buildStock, seedOrders, todayStr, nextOrderNo } from '../mock/seed'

const STOCK_KEY = 'st_stock'
const ORDERS_KEY = 'st_orders'
const MAX_QTY = 5

// 原型数据层：内存 + localStorage。逻辑对应需求文档 E1（库存校验与防超卖）与订单状态机。
export const useTicketingStore = defineStore('ticketing', {
  state: () => ({
    area: AREA,
    types: TICKET_TYPES,
    stock: { dates: [] },
    orders: [],
  }),
  getters: {
    openDates: (s) => s.stock.dates || [],
  },
  actions: {
    ensure() {
      const today = todayStr()
      let stock = load(STOCK_KEY, null)
      if (!stock || !(stock.dates || []).includes(today)) {
        stock = buildStock()
        save(STOCK_KEY, stock)
      }
      this.stock = stock
      let orders = load(ORDERS_KEY, null)
      if (!orders) {
        orders = seedOrders(stock.dates)
        save(ORDERS_KEY, orders)
      }
      this.orders = orders
    },
    persistOrders() {
      save(ORDERS_KEY, this.orders)
    },
    persistStock() {
      save(STOCK_KEY, this.stock)
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

    // 提交订单：库存校验（剩余额度不足 / 闭园 / 停售直接拒绝）
    createOrder(typeId, visitDate, quantity, visitorName) {
      const t = this.typeOf(typeId)
      if (!t || t.status !== '在售') throw new Error('该票种已停售，请重新选择')
      const day = this.stock[visitDate]
      if (!day || day.closed) throw new Error('该日期闭园，请另选日期')
      const rest = this.remaining(visitDate, typeId)
      if (rest < quantity) throw new Error(`该日期“${t.name}”仅剩 ${rest} 张，请减少数量或另选日期`)
      day.per[typeId].sold += quantity // 占额，取消或退款时回补（防超卖口径）
      this.persistStock()

      const order = {
        id: Date.now(),
        orderNo: nextOrderNo(this.orders.length + 1),
        visitorName,
        visitDate,
        typeId,
        typeName: t.name,
        unitPrice: t.price,
        quantity,
        totalAmount: t.price * quantity,
        channel: '线上',
        status: '待支付',
        createTime: new Date().toISOString(),
        tickets: [],
      }
      this.orders.unshift(order)
      this.persistOrders()
      return order
    },

    // 模拟支付：出票（每张一张票，含唯一核销码）
    pay(orderId) {
      const o = this.orders.find((x) => x.id === orderId)
      if (!o || o.status !== '待支付') throw new Error('订单状态已变化，请刷新后重试')
      o.status = '已支付'
      o.payTime = new Date().toISOString()
      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
      o.tickets = Array.from({ length: o.quantity }, () => {
        let code = 'TK'
        for (let i = 0; i < 12; i++) code += chars[Math.floor(Math.random() * chars.length)]
        return { ticketNo: code, status: '未检票', unitPrice: o.unitPrice, refund: null }
      })
      this.persistOrders()
      return o
    },

    cancel(orderId) {
      const o = this.orders.find((x) => x.id === orderId)
      if (!o || o.status !== '待支付') throw new Error('仅待支付订单可以取消')
      o.status = '已取消'
      o.cancelTime = new Date().toISOString()
      const day = this.stock[o.visitDate]
      if (day?.per[o.typeId]) day.per[o.typeId].sold = Math.max(0, day.per[o.typeId].sold - o.quantity)
      this.persistStock()
      this.persistOrders()
      return o
    },

    // 退票申请：仅未检票且游园日之前（对应需求文档 3.3）
    requestRefund(ticketNo, reason) {
      const found = this.findTicket(ticketNo)
      if (!found) throw new Error('未找到该门票')
      const { order, ticket } = found
      if (order.visitDate <= todayStr()) throw new Error('游园日当天及之后不支持退票')
      if (ticket.status !== '未检票') throw new Error('该门票当前状态不可退票')
      ticket.refund = { status: '待审核', reason, amount: ticket.unitPrice, applyTime: new Date().toISOString() }
      order.status = '退款中'
      this.persistOrders()
      return ticket
    },

    // 演示辅助：模拟管理员审核通过（真实系统由后台 F13 完成）
    approveRefund(ticketNo) {
      const found = this.findTicket(ticketNo)
      if (!found) return
      const { order, ticket } = found
      if (ticket.refund?.status !== '待审核') return
      ticket.refund.status = '已通过'
      ticket.refund.auditTime = new Date().toISOString()
      ticket.status = '已退款'
      const day = this.stock[order.visitDate]
      if (day?.per[order.typeId]) day.per[order.typeId].sold = Math.max(0, day.per[order.typeId].sold - 1)
      this.persistStock()
      if (order.tickets.every((t) => t.status === '已退款')) order.status = '已退款'
      else order.status = '已支付'
      this.persistOrders()
    },

    rejectRefund(ticketNo, opinion) {
      const found = this.findTicket(ticketNo)
      if (!found) return
      const { order, ticket } = found
      if (ticket.refund?.status !== '待审核') return
      ticket.refund.status = '已驳回'
      ticket.refund.auditOpinion = opinion
      ticket.refund.auditTime = new Date().toISOString()
      order.status = '已支付'
      this.persistOrders()
    },

    findTicket(ticketNo) {
      for (const o of this.orders) {
        const t = o.tickets.find((x) => x.ticketNo === ticketNo)
        if (t) return { order: o, ticket: t }
      }
      return null
    },
  },
})

export { MAX_QTY }
