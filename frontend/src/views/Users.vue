<template>
  <div class="page">
    <div class="tools">
      <el-button type="primary" @click="openAdd">新增用户</el-button>
      <el-button @click="load">刷新</el-button>
    </div>

    <el-table :data="list" border stripe style="width: 100%">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="username" label="账号" width="140" />
      <el-table-column prop="role" label="角色" width="100">
        <template #default="{ row }">
          <el-tag :type="row.role === 'admin' ? 'danger' : 'success'">{{ row.role }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="权限" min-width="260">
        <template #default="{ row }">
          <span v-if="row.role === 'admin'" style="color:#909399">— 管理员（全部权限）—</span>
          <template v-else>
            <el-tag v-for="p in row.permissions" :key="p" size="small" style="margin-right:4px">
              {{ permLabel(p) }}
            </el-tag>
            <span v-if="!row.permissions?.length" style="color:#c0c4cc">未分配</span>
          </template>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? '启用' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="last_login_at" label="最近登录" width="170" />
      <el-table-column label="操作" width="360" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="openEdit(row)">编辑权限</el-button>
          <el-button size="small" @click="toReset(row)">重置密码</el-button>
          <el-button size="small" :type="row.is_active ? 'warning' : 'success'" @click="toggle(row)">
            {{ row.is_active ? '禁用' : '启用' }}
          </el-button>
          <el-button size="small" type="danger" @click="del(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 新增用户 -->
    <el-dialog v-model="showAdd" title="新增用户" width="480px">
      <el-form :model="addForm" label-width="80px">
        <el-form-item label="账号"><el-input v-model="addForm.username" /></el-form-item>
        <el-form-item label="密码"><el-input v-model="addForm.password" type="password" show-password /></el-form-item>
        <el-form-item label="角色">
          <el-radio-group v-model="addForm.role">
            <el-radio value="admin">管理员</el-radio>
            <el-radio value="keeper">普通用户</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="权限" v-if="addForm.role !== 'admin'">
          <el-checkbox-group v-model="addForm.permissions">
            <el-checkbox v-for="p in allPerms" :key="p.code" :value="p.code">{{ p.name }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item v-else label="权限">
          <span style="color:#909399">管理员默认拥有全部权限</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAdd = false">取消</el-button>
        <el-button type="primary" @click="doAdd">确定</el-button>
      </template>
    </el-dialog>

    <!-- 编辑权限 -->
    <el-dialog v-model="showEdit" :title="`编辑：${editForm.username}`" width="480px">
      <el-form :model="editForm" label-width="80px">
        <el-form-item label="角色">
          <el-radio-group v-model="editForm.role">
            <el-radio value="admin">管理员</el-radio>
            <el-radio value="keeper">普通用户</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="权限" v-if="editForm.role !== 'admin'">
          <el-checkbox-group v-model="editForm.permissions">
            <el-checkbox v-for="p in allPerms" :key="p.code" :value="p.code">{{ p.name }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item v-else label="权限">
          <span style="color:#909399">管理员默认拥有全部权限</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEdit = false">取消</el-button>
        <el-button type="primary" @click="doEdit">保存</el-button>
      </template>
    </el-dialog>

    <!-- 重置密码 -->
    <el-dialog v-model="showReset" :title="`重置密码：${resetForm.username}`" width="420px">
      <el-form :model="resetForm" label-width="90px">
        <el-form-item label="新密码"><el-input v-model="resetForm.new_password" type="password" show-password /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showReset = false">取消</el-button>
        <el-button type="primary" @click="doReset">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import request from '../utils/request'

const list      = ref([])
const allPerms  = ref([])
const showAdd   = ref(false)
const showEdit  = ref(false)
const showReset = ref(false)

const addForm   = ref({ username: '', password: '', role: 'keeper', permissions: [] })
const editForm  = ref({ id: 0, username: '', role: 'keeper', permissions: [] })
const resetForm = ref({ id: 0, username: '', new_password: '' })

function permLabel(code) {
  return allPerms.value.find(x => x.code === code)?.name || code
}

async function load() {
  const [users, perms] = await Promise.all([
    request.get('/users.php?act=list'),
    request.get('/users.php?act=perms'),
  ])
  list.value     = users.data
  allPerms.value = perms.data
}

function openAdd() {
  addForm.value = { username: '', password: '', role: 'keeper', permissions: [] }
  showAdd.value = true
}
async function doAdd() {
  const r = await request.post('/users.php?act=add', addForm.value)
  ElMessage.success(r.msg)
  showAdd.value = false
  load()
}

function openEdit(row) {
  editForm.value = {
    id: row.id, username: row.username,
    role: row.role,
    permissions: [...(row.permissions || [])],
  }
  showEdit.value = true
}
async function doEdit() {
  const r = await request.post('/users.php?act=update', {
    id: editForm.value.id,
    role: editForm.value.role,
    permissions: editForm.value.permissions,
  })
  ElMessage.success(r.msg)
  showEdit.value = false
  load()
}

function toReset(row) {
  resetForm.value = { id: row.id, username: row.username, new_password: '' }
  showReset.value = true
}
async function doReset() {
  const r = await request.post('/users.php?act=reset_password', {
    id: resetForm.value.id, new_password: resetForm.value.new_password
  })
  ElMessage.success(r.msg)
  showReset.value = false
}

async function toggle(row) {
  const r = await request.post('/users.php?act=set_active',
    { id: row.id, is_active: row.is_active ? 0 : 1 })
  ElMessage.success(r.msg)
  load()
}

async function del(row) {
  try {
    await ElMessageBox.confirm(`确定删除账号 ${row.username}？`, '提示', { type: 'warning' })
  } catch { return }
  const r = await request.post('/users.php?act=del', { id: row.id })
  ElMessage.success(r.msg)
  load()
}

onMounted(load)
</script>

<style scoped>
.tools { margin-bottom: 12px; }
</style>
