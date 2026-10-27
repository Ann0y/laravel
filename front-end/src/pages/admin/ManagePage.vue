<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../../api'
import StatusChip from '../../components/StatusChip.vue'

// F10 景区与票种管理 + F11 每日售票额度管理（开发日历 × 票种额度）+ F17 园内项目管理
const tab = ref('types') // types | days | items

const types = ref([])
const days = ref([])
const items = ref([])
const typesLoading = ref(true)
const daysLoading = ref(true)
const itemsLoading = ref(true)
const error = ref('')

async function loadTypes() {
  try {
    const data = await api('/ticket-types')
    types.value = data.list
  } catch (e) {
    error.value = e.message
  } finally {
    typesLoading.value = false
  }
}

// 额度管理：默认列出未来 14 天（与滚动开园窗口一致）
async function loadDays() {
  daysLoading.value = true
  try {
    const d = new Date()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    const from = `${d.getFullYear()}-${m}-${dd}`
    const data = await api(`/open-days?from=${from}&limit=14`)
    days.value = data.list
    // 行编辑缓存：{ [stockId]: { quota, status }, [dayId]: { status, capacity } }
    stockDraft.value = {}
    dayDraft.value = {}
  } catch (e) {
    error.value = e.message
  } finally {
    daysLoading.value = false
  }
}

onMounted(() => {
  loadTypes()
  loadDays()
  loadItems()
})

/* ---- 票种编辑 ---- */
const typeForm = ref(null) // null 关闭；{ id, typeCode, typeName, price, applicable, refundable, status }
const typeError = ref('')
const typeBusy = ref(false)

function openTypeForm(t = null) {
  typeForm.value = t
    ? { ...t }
    : { id: null, typeCode: '', typeName: '', price: null, applicable: '', refundable: true, status: '在售' }
  typeError.value = ''
}

async function saveType() {
  const f = typeForm.value
  if (!f.typeCode.trim() || !f.typeName.trim() || !f.price) {
    typeError.value = '请填写票种编码、名称与单价'
    return
  }
  typeBusy.value = true
  typeError.value = ''
  try {
    await api(f.id ? `/ticket-types/${f.id}` : '/ticket-types', {
      method: f.id ? 'PUT' : 'POST',
      body: {
        typeCode: f.typeCode.trim(),
        typeName: f.typeName.trim(),
        price: Number(f.price),
        applicable: f.applicable?.trim() || null,
        refundable: !!f.refundable,
        status: f.status,
      },
    })
    typeForm.value = null
    loadTypes()
  } catch (e) {
    typeError.value = e.message
  } finally {
    typeBusy.value = false
  }
}

const confirmDeleteType = ref(null)

async function deleteType() {
  if (!confirmDeleteType.value) return
  try {
    await api(`/ticket-types/${confirmDeleteType.value.id}`, { method: 'DELETE' })
    confirmDeleteType.value = null
    loadTypes()
  } catch (e) {
    error.value = e.message
  }
}

/* ---- 开放日与额度 ---- */
const stockDraft = ref({})
const dayDraft = ref({})
const busyKey = ref(null)
const flash = ref('')

const sel = (s) => stockDraft.value[s.id] ?? { quota: s.quota, status: s.status }
const daySel = (day) => dayDraft.value[day.id] ?? { status: day.status, capacity: day.capacity }

async function saveStock(s) {
  const draft = sel(s)
  busyKey.value = 's' + s.id
  error.value = ''
  try {
    const data = await api(`/stocks/${s.id}`, {
      method: 'PUT',
      body: { quota: Number(draft.quota), status: draft.status },
    })
    flash.value = `${s.date} ${s.typeName} 额度已更新，剩余 ${data.remaining}`
    loadDays()
  } catch (e) {
    error.value = e.message
  } finally {
    busyKey.value = null
  }
}

async function saveDay(day) {
  const draft = daySel(day)
  busyKey.value = 'd' + day.id
  error.value = ''
  try {
    await api(`/open-days/${day.id}`, {
      method: 'PUT',
      body: { status: draft.status, capacity: Number(draft.capacity) },
    })
    flash.value = `${day.date} 开放日设置已更新`
    loadDays()
  } catch (e) {
    error.value = e.message
  } finally {
    busyKey.value = null
  }
}

const quotaPct = (s) => (s.quota ? Math.min(100, Math.round((s.soldCount / s.quota) * 100)) : 0)
const typeSelling = computed(() => types.value.filter((t) => t.status === '在售').length)
const itemSelling = computed(() => items.value.filter((i) => i.status === '在售').length)

/* ---- 园内项目管理（F17） ---- */
async function loadItems() {
  try {
    const data = await api('/park-items')
    items.value = data.list
  } catch (e) {
    error.value = e.message
  } finally {
    itemsLoading.value = false
  }
}

const itemForm = ref(null)
const itemError = ref('')
const itemBusy = ref(false)

function openItemForm(i = null) {
  itemForm.value = i
    ? { ...i }
    : { id: null, itemCode: '', name: '', price: null, description: '', status: '在售' }
  itemError.value = ''
}

async function saveItem() {
  const f = itemForm.value
  if (!f.itemCode.trim() || !f.name.trim() || !f.price) {
    itemError.value = '请填写项目编码、名称与单价'
    return
  }
  itemBusy.value = true
  itemError.value = ''
  try {
    await api(f.id ? `/park-items/${f.id}` : '/park-items', {
      method: f.id ? 'PUT' : 'POST',
      body: {
        itemCode: f.itemCode.trim(),
        name: f.name.trim(),
        price: Number(f.price),
        description: f.description?.trim() || null,
        status: f.status,
      },
    })
    itemForm.value = null
    loadItems()
  } catch (e) {
    itemError.value = e.message
  } finally {
    itemBusy.value = false
  }
}

const confirmDeleteItem = ref(null)

async function deleteItem() {
  if (!confirmDeleteItem.value) return
  try {
    await api(`/park-items/${confirmDeleteItem.value.id}`, { method: 'DELETE' })
    confirmDeleteItem.value = null
    loadItems()
  } catch (e) {
    error.value = e.message
  }
}
</script>

<template>
  <div class="mg container">
    <h1>票务管理</h1>

    <div class="tabs">
      <button class="tab" :class="{ active: tab === 'types' }" @click="tab = 'types'">
        票种管理<span class="tab-sub num">{{ typeSelling }} 个在售</span>
      </button>
      <button class="tab" :class="{ active: tab === 'days' }" @click="tab = 'days'">
        开放日与额度<span class="tab-sub num">未来 14 天</span>
      </button>
    </div>

    <p v-if="error" class="err">{{ error }}</p>
    <p v-if="flash" class="flash">{{ flash }}</p>

    <!-- 票种管理 -->
    <section v-if="tab === 'types'" class="card block">
      <div class="blk-head">
        <h2>票种</h2>
        <button class="btn btn-primary btn-sm" @click="openTypeForm()">新增票种</button>
      </div>

      <div v-if="typeForm" class="type-form">
        <h3>{{ typeForm.id ? '编辑票种' : '新增票种' }}</h3>
        <div class="tf-grid">
          <div class="field">
            <label>票种编码</label>
            <input
              v-model="typeForm.typeCode"
              placeholder="如 SENIOR"
              maxlength="20"
              :disabled="!!typeForm.id"
            />
          </div>
          <div class="field">
            <label>票种名称</label>
            <input v-model="typeForm.typeName" placeholder="如 长者票" maxlength="50" />
          </div>
          <div class="field">
            <label>单价（元）</label>
            <input v-model="typeForm.price" type="number" min="0.01" step="0.01" placeholder="如 70" />
          </div>
          <div class="field">
            <label>在售状态</label>
            <select v-model="typeForm.status">
              <option>在售</option>
              <option>停售</option>
            </select>
          </div>
          <div class="field tf-wide">
            <label>适用人群说明</label>
            <input v-model="typeForm.applicable" placeholder="如 60 周岁（含）以上，凭有效证件入园" maxlength="100" />
          </div>
        </div>
        <label class="chk">
          <input v-model="typeForm.refundable" type="checkbox" />
          支持退票（不可退票种下单后允许核销、不允许退）
        </label>
        <p v-if="typeError" class="err">{{ typeError }}</p>
        <div class="form-actions">
          <button class="btn btn-primary btn-sm" :disabled="typeBusy" @click="saveType">保存</button>
          <button class="btn btn-ghost btn-sm" @click="typeForm = null">取消</button>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>编码</th>
              <th>名称</th>
              <th class="num">单价</th>
              <th>适用人群</th>
              <th>可退</th>
              <th>状态</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in types" :key="t.id">
              <td class="num">{{ t.typeCode }}</td>
              <td>{{ t.typeName }}</td>
              <td class="num">¥{{ t.price }}</td>
              <td class="muted">{{ t.applicable || '—' }}</td>
              <td>{{ t.refundable ? '可退' : '不可退' }}</td>
              <td><StatusChip :status="t.status" /></td>
              <td class="ops">
                <button class="link" @click="openTypeForm(t)">编辑</button>
                <button class="link danger" @click="confirmDeleteType = t">删除</button>
              </td>
            </tr>
            <tr v-if="!types.length && !typesLoading">
              <td colspan="7" class="muted table-empty">暂无票种，点击右上角新增。</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 删除确认 -->
      <div v-if="confirmDeleteType" class="modal-mask" @click.self="confirmDeleteType = null">
        <div class="modal card">
          <h3>删除票种「{{ confirmDeleteType.typeName }}」？</h3>
          <p class="muted">删除为逻辑下架（置停售），已有订单与门票数据不受影响。</p>
          <div class="form-actions">
            <button class="btn btn-danger btn-sm" @click="deleteType">确认删除</button>
            <button class="btn btn-ghost btn-sm" @click="confirmDeleteType = null">取消</button>
          </div>
        </div>
      </div>
    </section>

    <!-- 开放日与额度 -->
    <template v-else>
      <div v-if="daysLoading" class="loading muted">载入中…</div>
      <section v-for="day in days" :key="day.id" class="card day-block">
        <header class="day-head">
          <span class="day-date num">{{ day.date }}</span>
          <div class="day-edit">
            <select v-model="daySel(day).status" class="inline-input">
              <option>开放</option>
              <option>闭园</option>
            </select>
            <span class="field-text">入园总容量</span>
            <input v-model="daySel(day).capacity" type="number" min="0" class="inline-input cap num" />
            <button
              class="btn btn-ghost btn-sm"
              :disabled="busyKey === 'd' + day.id"
              @click="saveDay(day)"
            >
              保存
            </button>
          </div>
        </header>

        <div class="stock-table">
          <div v-for="s in day.stocks" :key="s.id" class="stock-row">
            <span class="st-name">{{ s.typeName }}</span>
            <div class="st-bar">
              <i :style="{ width: quotaPct(s) + '%' }" :class="{ done: s.remaining <= 0 }" />
            </div>
            <span class="num st-count">已售 {{ s.soldCount }} / 额 {{ s.quota }}（余 {{ s.remaining }}）</span>
            <label class="field-text st-label">额度</label>
            <input v-model="sel(s).quota" type="number" :min="s.soldCount" class="inline-input num" />
            <select v-model="sel(s).status" class="inline-input">
              <option>正常</option>
              <option>停售</option>
            </select>
            <button
              class="btn btn-ghost btn-sm"
              :disabled="busyKey === 's' + s.id"
              @click="saveStock(s)"
            >
              保存
            </button>
          </div>
        </div>
      </section>
      <p class="field-text tip">
        额度下调不能低于已售数量；停售后该日期票种不可再售；周一闭园可直接将当日状态置为「闭园」。
      </p>
    </template>
  </div>
</template>

<style scoped>
.mg {
  padding: 32px 24px 0;
}

.mg h1 {
  margin: 0 0 18px;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 18px;
}

.tab {
  border: 1px solid var(--border-strong);
  background: #fff;
  border-radius: var(--radius-sm);
  padding: 9px 18px;
  font: inherit;
  font-size: 14.5px;
  color: var(--ink-2);
  cursor: pointer;
  display: inline-flex;
  align-items: baseline;
  gap: 8px;
  transition: all 0.15s ease;
}

.tab:hover {
  border-color: var(--ink-2);
}

.tab.active {
  border-color: var(--accent);
  background: var(--accent-soft);
  color: var(--accent-hover);
  font-weight: 600;
}

.tab-sub {
  font-size: 12px;
  color: var(--ink-3);
}

.block {
  padding: 20px 24px;
}

.blk-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.blk-head h2 {
  margin: 0;
  font-size: 16px;
}

/* 表单 */
.type-form {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  padding: 16px;
  margin-bottom: 16px;
  background: #fafbfa;
}

.type-form h3 {
  margin: 0 0 12px;
  font-size: 14.5px;
}

.tf-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.tf-wide {
  grid-column: span 4;
}

.tf-grid select {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: #fff;
  padding: 9px 10px;
  font: inherit;
  font-size: 15px;
}

.chk {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  font-size: 13.5px;
  color: var(--ink-2);
}

.form-actions {
  display: flex;
  gap: 10px;
  margin-top: 14px;
}

/* 表格 */
.table-wrap {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

th,
td {
  text-align: left;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}

th {
  color: var(--ink-3);
  font-weight: 550;
  font-size: 13px;
}

.table-empty {
  text-align: center;
  padding: 26px 0;
}

.ops {
  display: flex;
  gap: 14px;
}

/* 弹窗 */
.modal-mask {
  position: fixed;
  inset: 0;
  background: rgba(28, 35, 31, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 40;
  padding: 20px;
}

.modal {
  max-width: 420px;
  width: 100%;
  padding: 22px;
}

.modal h3 {
  margin: 0 0 8px;
}

/* 开放日 */
.day-block {
  padding: 16px 22px;
  margin-bottom: 14px;
}

.day-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 10px;
}

.day-date {
  font-weight: 650;
  font-size: 16px;
}

.day-edit {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.inline-input,
.select-input,
select.inline-input {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: #fff;
  padding: 6px 9px;
  font: inherit;
  font-size: 13.5px;
  color: var(--ink);
}

input.inline-input {
  width: 74px;
}

input.cap {
  width: 90px;
}

.stock-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  flex-wrap: wrap;
  border-bottom: 1px dashed var(--border);
}

.stock-row:last-child {
  border-bottom: none;
}

.st-name {
  min-width: 5em;
  font-weight: 600;
  font-size: 14px;
}

.st-bar {
  flex: 1;
  min-width: 120px;
  height: 7px;
  border-radius: 999px;
  background: var(--zinc-soft);
  overflow: hidden;
}

.st-bar i {
  display: block;
  height: 100%;
  background: var(--accent);
  border-radius: 999px;
  transition: width 0.3s ease;
}

.st-bar i.done {
  background: var(--rose);
}

.st-count {
  color: var(--ink-2);
  font-size: 12.5px;
}

.st-label {
  flex-shrink: 0;
}

.flash {
  color: var(--accent-hover);
  font-size: 13.5px;
  margin: 0 0 12px;
}

.tip {
  margin: 6px 0 30px;
}

.loading {
  padding: 30px 0;
}

@media (max-width: 900px) {
  .tf-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .tf-wide {
    grid-column: span 2;
  }
}
</style>
