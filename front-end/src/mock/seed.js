// 演示种子数据：与需求文档中的票种 / 景区信息口径一致

export const AREA = {
  name: '青岚谷风景区',
  openTime: '08:30-17:00',
  address: '南岭市青岚区云栖路 88 号',
  notice:
    '景区执行每日限流入园，请按订单上的游园日期当天入园，门票当日一次有效。长者、儿童优惠票请携带有效证件，检票时由工作人员核对。',
}

export const TICKET_TYPES = [
  { id: 'ADULT', name: '成人票', price: 110, applicable: '18 周岁（含）至 59 周岁', refundable: true },
  { id: 'CHILD', name: '儿童票', price: 60, applicable: '6 周岁（含）至 17 周岁或身高 1.2 米（含）以上', refundable: true },
  { id: 'STUDENT', name: '学生票', price: 80, applicable: '全日制在校学生，凭学生证入园', refundable: true },
  { id: 'SENIOR', name: '长者票', price: 70, applicable: '60 周岁（含）以上，凭有效证件入园', refundable: true },
]

const QUOTA = { ADULT: 500, CHILD: 200, STUDENT: 200, SENIOR: 150 }
const SOLD_START = { ADULT: 210, CHILD: 60, STUDENT: 45, SENIOR: 30 }

export function dateStr(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function todayStr() {
  return dateStr(new Date())
}

// 「今天 / 明天 / 周三」短标签
export function weekdayLabel(ds) {
  if (ds === todayStr()) return '今天'
  const d = new Date(ds)
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  if (ds === dateStr(tomorrow)) return '明天'
  return '周' + '日一二三四五六'[d.getDay()]
}

export function shortDate(ds) {
  return ds.slice(5) // MM-DD
}

function isoAt(daysOffset, hour, minute) {
  const d = new Date()
  d.setDate(d.getDate() + daysOffset)
  d.setHours(hour, minute, 0, 0)
  return d.toISOString()
}

function makeTicketNo() {
  let s = 'TK'
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
  for (let i = 0; i < 12; i++) s += chars[Math.floor(Math.random() * chars.length)]
  return s
}

export function nextOrderNo(seq) {
  return 'SO' + dateStr(new Date()).replaceAll('-', '') + String(seq).padStart(4, '0')
}

// 每日额度（含当日已售量的演示初值）
export function buildStock() {
  const dates = []
  const stock = { dates }
  for (let i = 0; i < 7; i++) {
    const d = new Date()
    d.setDate(d.getDate() + i)
    const key = dateStr(d)
    dates.push(key)
    // 未来 3 天的票提前售出更多，模拟"临近周末更满"的真实体感
    const boost = i < 3 ? 0 : 0.18
    stock[key] = {
      closed: false,
      per: Object.fromEntries(
        TICKET_TYPES.map((t) => [
          t.id,
          {
            quota: QUOTA[t.id],
            sold: Math.min(QUOTA[t.id], Math.round(QUOTA[t.id] * (SOLD_START[t.id] / 500 + boost))),
          },
        ]),
      ),
    }
  }
  return stock
}

// 演示订单：覆盖 待支付 / 已支付 / 已使用 三种状态
export function seedOrders(dates) {
  const today = dates[0]
  const future = dates[3]
  const adult = TICKET_TYPES.find((t) => t.id === 'ADULT')
  const student = TICKET_TYPES.find((t) => t.id === 'STUDENT')

  return [
    {
      id: 1,
      orderNo: 'SO' + today.replaceAll('-', '') + '0001',
      visitorName: '张三',
      visitDate: today,
      typeId: 'ADULT',
      typeName: adult.name,
      unitPrice: adult.price,
      quantity: 1,
      totalAmount: adult.price,
      channel: '线上',
      status: '待支付',
      createTime: isoAt(0, 9, 5),
      tickets: [],
    },
    {
      id: 2,
      orderNo: 'SO' + future.replaceAll('-', '') + '0002',
      visitorName: '张三',
      visitDate: future,
      typeId: 'STUDENT',
      typeName: student.name,
      unitPrice: student.price,
      quantity: 2,
      totalAmount: student.price * 2,
      channel: '线上',
      status: '已支付',
      createTime: isoAt(-1, 20, 12),
      payTime: isoAt(-1, 20, 13),
      tickets: [
        { ticketNo: makeTicketNo(), status: '未检票', unitPrice: student.price, refund: null },
        { ticketNo: makeTicketNo(), status: '未检票', unitPrice: student.price, refund: null },
      ],
    },
    {
      id: 3,
      orderNo: 'SO' + dateStr(new Date()).replaceAll('-', '') + '0003',
      visitorName: '张三',
      visitDate: dates[1],
      typeId: 'ADULT',
      typeName: adult.name,
      unitPrice: adult.price,
      quantity: 2,
      totalAmount: adult.price * 2,
      channel: '线上',
      status: '已使用',
      createTime: isoAt(-2, 15, 40),
      payTime: isoAt(-2, 15, 41),
      tickets: [
        { ticketNo: makeTicketNo(), status: '已检票', unitPrice: adult.price, refund: null },
        { ticketNo: makeTicketNo(), status: '已检票', unitPrice: adult.price, refund: null },
      ],
    },
  ]
}
