<script setup>
import { ref, onMounted, computed } from 'vue'
import { api } from '../../api'
import Pager from '../../components/Pager.vue'
import { useAuthStore } from '../../stores/auth'

// F15 用户管理：账号增删改查、启停用、角色分配（管理员）
const auth = useAuthStore()

const ROLES = [
  { value: 'visitor', label: '游客' },
  { value: 'operator', label: '票务员' },
  { value: 'admin', label: '管理员' },
]
const roleLabel = (r) => ROLES.find((x) => x.value === r)?.label ?? r

const f = ref({ keyword: '', role: '' })
const page = ref({})
const list = ref([])
const loading = ref(true)
const error = ref('')
const flash = ref('')

const PAGE_SIZE = 10

const isSelf = computed(() => (u) => auth.user && u.username === auth.user.username)

async function fetchUsers(pageNo = 1) {
  loading.value = true
  error.value = ''
  const params = new URLSearchParams({ page: pageNo, pageSize: PAGE_SIZE })
  if (f.value.keyword) params.set('keyword', f.value.keyword)
  if (f.value.role) params.set('role', f.value.role)
  try {
    const data = await api(`/users?${params}`)
    list.value = data.list
    page.value = data.page
  } catch (e) {
    error.value = e.status === 401 ? '登录已过期，请重新进入管理端' : e.message
  } finally {
    loading.value = false
  }
}

function search() {
  fetchUsers(1)
}

onMounted(fetchUsers)

/* ---- 新建 / 编辑 ---- */
const form = ref(null) // { id, username, name, role, status, password }
const formError = ref('')
const busy = ref(false)

function openForm(u = null) {
  form.value = u
    ? { id: u.id, username: u.username, name: u.name, role: u.role, status: u.status }
    : { id: null, username: '', name: '', role: 'visitor', status: 1, password: '' }
  formError.value = ''
}

async function save() {
  const fm = form.value
  if (!fm.username.trim() || !fm.name.trim()) {
    formError.value = '请填写登录名与姓名'
    return
  }
  if (!fm.id && (!fm.password || fm.password.length < 6)) {
    formError.value = '新账号需设置至少 6 位初始密码'
    return
  }
  busy.value = true
  formError.value = ''
  const body = {
    username: fm.username.trim(),
    name: fm.name.trim(),
    role: fm.role,
    status: fm.status,
  }
  if (!fm.id) body.password = fm.password
  try {
    await api(fm.id ? `/users/${fm.id}` : '/users', {
      method: fm.id ? 'PUT' : 'POST',
      body,
    })
    flash.value = fm.id ? `账号 ${fm.username} 已更新` : `账号 ${fm.username} 创建成功`
    form.value = null
    fetchUsers()
  } catch (e) {
    formError.value = e.message
  } finally {
    busy.value = false
  }
}

async function toggleStatus(u) {
  busy.value = true
  error.value = ''
  try {
    await api(`/users/${u.id}`, {
      method: 'PUT',
      body: { username: u.username, name: u.name, role: u.role, status: u.status === 1 ? 0 : 1 },
    })
    flash.value = `账号 ${u.username} 已${u.status === 1 ? '停用' : '启用'}`
    fetchUsers()
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

const confirmDelete = ref(null)

async function doDelete() {
  if (!confirmDelete.value) return
  try {
    await api(`/users/${confirmDelete.value.id}`, { method: 'DELETE' })
    flash.value = `账号 ${confirmDelete.value.username} 已删除（逻辑删除）`
    confirmDelete.value = null
    fetchUsers()
  } catch (e) {
    error.value = e.message
  }
}
</script>

<template>
  <div class="us container">
    <header class="us-head">
      <h1>用户管理</h1>
      <button class="btn btn-primary btn-sm" @click="openForm()">新建账号</button>
    </header>

    <form class="search card" @submit.prevent="search">
      <div class="field">
        <label>关键字</label>
        <input v-model="f.keyword" placeholder="登录名 / 姓名" />
      </div>
      <div class="field">
        <label>角色</label>
        <select v-model="f.role">
          <option value="">全部</option>
          <option v-for="r in ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
        </select>
      </div>
      <div class="actions">
        <button class="btn btn-primary btn-sm" type="submit">查询</button>
        <button class="btn btn-ghost btn-sm" type="button" @click="((f.keyword = ''), (f.role = ''), search())">
          重置
        </button>
      </div>
    </form>

    <p v-if="flash" class="flash">{{ flash }}</p>
    <p v-if="error" class="err">{{ error }}</p>
    <div v-if="loading" class="loading muted">载入中…</div>

    <template v-else>
      <div class="card table-wrap">
        <table>
          <thead>
            <tr>
              <th>登录名</th>
              <th>姓名</th>
              <th>角色</th>
              <th>状态</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in list" :key="u.id">
              <td class="num">{{ u.username }}</td>
              <td>{{ u.name }}</td>
              <td
                :class="{
                  'role-admin': u.role === 'admin',
                  'role-operator': u.role === 'operator',
                }"
              >
                {{ roleLabel(u.role) }}
              </td>
              <td>
                <span class="chip" :class="u.status === 1 ? 'chip-on' : 'chip-off'">
                  {{ u.status === 1 ? '启用' : '停用' }}
                </span>
              </td>
              <td class="ops">
                <button class="link" @click="openForm(u)">编辑</button>
                <button
                  class="link"
                  :disabled="isSelf(u)"
                  :title="isSelf(u) ? '不能停用自己的账号' : ''"
                  @click="toggleStatus(u)"
                >
                  {{ u.status === 1 ? '停用' : '启用' }}
                </button>
                <button
                  class="link danger"
                  :disabled="isSelf(u)"
                  :title="isSelf(u) ? '不能删除自己的账号' : ''"
                  @click="confirmDelete = u"
                >
                  删除
                </button>
              </td>
            </tr>
            <tr v-if="!list.length">
              <td colspan="5" class="muted table-empty">无符合条件的账号。</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :page="page" @change="fetchUsers" />
    </template>

    <!-- 新建 / 编辑弹窗 -->
    <div v-if="form" class="modal-mask" @click.self="form = null">
      <div class="modal card">
        <h3>{{ form.id ? '编辑账号' : '新建账号' }}</h3>
        <div class="m-grid">
          <div class="field">
            <label>登录名</label>
            <input v-model="form.username" maxlength="50" :disabled="!!form.id" placeholder="如 walkin_user1" />
          </div>
          <div class="field">
            <label>姓名</label>
            <input v-model="form.name" maxlength="50" placeholder="显示姓名" />
          </div>
          <div v-if="!form.id" class="field">
            <label>初始密码</label>
            <input v-model="form.password" type="password" maxlength="100" placeholder="至少 6 位" />
          </div>
          <div class="field">
            <label>角色</label>
            <select v-model="form.role">
              <option v-for="r in ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
          </div>
          <div class="field">
            <label>状态</label>
            <select v-model="form.status" class="snum">
              <option :value="1">启用</option>
              <option :value="0">停用</option>
            </select>
          </div>
        </div>
        <p class="field-text">创建游客 / 票务员时将自动建立对应档案（含票务工号）。</p>
        <p v-if="formError" class="err">{{ formError }}</p>
        <div class="form-actions">
          <button class="btn btn-primary btn-sm" :disabled="busy" @click="save">保存</button>
          <button class="btn btn-ghost btn-sm" @click="form = null">取消</button>
        </div>
      </div>
    </div>

    <!-- 删除确认 -->
    <div v-if="confirmDelete" class="modal-mask" @click.self="confirmDelete = null">
      <div class="modal card">
        <h3>删除账号「{{ confirmDelete.username }}」？</h3>
        <p class="muted">删除为逻辑删除并停用该账号；其历史订单与门票数据保留可查。</p>
        <div class="form-actions">
          <button class="btn btn-danger btn-sm" @click="doDelete">确认删除</button>
          <button class="btn btn-ghost btn-sm" @click="confirmDelete = null">取消</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.us {
  padding: 32px 24px 0;
}

.us-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 18px;
}

.us-head h1 {
  margin: 0;
  font-size: 28px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.search {
  display: flex;
  gap: 14px;
  padding: 14px 18px;
  margin-bottom: 16px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.search .field {
  min-width: 180px;
}

.search .field input,
.search .field select {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: #fff;
  padding: 8px 10px;
  font: inherit;
  font-size: 14px;
}

.search .actions {
  margin-left: auto;
  display: flex;
  gap: 8px;
}

.table-wrap {
  overflow-x: auto;
  padding: 6px 18px;
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

.role-admin {
  color: var(--amber);
  font-weight: 600;
}

.role-operator {
  color: var(--accent-hover);
  font-weight: 550;
}

.chip {
  display: inline-flex;
  padding: 1px 10px;
  border-radius: 999px;
  font-size: 12.5px;
}

.chip-on {
  background: var(--accent-soft);
  color: var(--accent-hover);
}

.chip-off {
  background: var(--zinc-soft);
  color: var(--zinc);
}

.ops {
  display: flex;
  gap: 12px;
}

.ops .link:disabled {
  color: var(--ink-3);
  cursor: not-allowed;
  text-decoration: none;
}

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
  max-width: 460px;
  width: 100%;
  padding: 22px;
}

.modal h3 {
  margin: 0 0 14px;
}

.m-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.m-grid select {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: #fff;
  padding: 9px 10px;
  font: inherit;
  font-size: 15px;
}

.form-actions {
  display: flex;
  gap: 10px;
  margin-top: 16px;
}

.flash {
  color: var(--accent-hover);
  font-size: 13.5px;
  margin: 0 0 12px;
}

.loading {
  padding: 30px 0;
}
</style>
