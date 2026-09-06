<template>
  <router-view v-if="route.meta.public" />

  <el-container v-else class="app">
    <el-aside width="220px" class="aside">
      <div class="logo">商品库存管理系统</div>
      <el-menu :default-active="route.path" router background-color="#001529" text-color="#fff" active-text-color="#409EFF">
        <el-menu-item index="/goods">商品清单</el-menu-item>
        <el-menu-item v-if="hasPerm('stock_in')"  index="/in">入库管理</el-menu-item>
        <el-menu-item v-if="hasPerm('stock_out')" index="/out">出库管理</el-menu-item>
        <el-menu-item index="/stock">库存查询</el-menu-item>
        <el-menu-item v-if="hasPerm('check')"     index="/check">库存盘点</el-menu-item>
        <el-menu-item v-if="user.role === 'admin'" index="/users">用户管理</el-menu-item>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header class="header">
        <span>{{ route.meta.title || '库存管理' }}</span>
        <el-dropdown class="user-box" trigger="click" @command="onCmd">
          <span class="user-name">
            {{ user.username }} <em>({{ user.role }})</em>
            <el-icon style="margin-left:4px"><ArrowDown /></el-icon>
          </span>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="pwd">修改密码</el-dropdown-item>
              <el-dropdown-item divided command="logout">退出登录</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </el-header>
      <el-main><router-view /></el-main>
    </el-container>

    <!-- 修改密码弹窗 -->
    <el-dialog v-model="showPwd" title="修改密码" width="420px" :close-on-click-modal="false">
      <el-form :model="pwdForm" label-width="90px">
        <el-form-item label="原密码"><el-input v-model="pwdForm.old_password" type="password" show-password /></el-form-item>
        <el-form-item label="新密码"><el-input v-model="pwdForm.new_password" type="password" show-password /></el-form-item>
        <el-form-item label="确认新密码"><el-input v-model="pwdForm.confirm" type="password" show-password /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPwd = false">取消</el-button>
        <el-button type="primary" @click="doChangePwd">确定</el-button>
      </template>
    </el-dialog>
  </el-container>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowDown } from '@element-plus/icons-vue'
import request from './utils/request'
import { user, clearUser } from './utils/authState'

const route  = useRoute()
const router = useRouter()

// user 来自 authState，是响应式 ref；登录/退出/改自己权限后会自动重渲染
// 权限判断：admin 一律 true；其它按 permissions 数组
function hasPerm(code) {
  const u = user.value
  if (u.role === 'admin') return true
  return Array.isArray(u.permissions) && u.permissions.includes(code)
}

const showPwd = ref(false)
const pwdForm = ref({ old_password: '', new_password: '', confirm: '' })

function onCmd(cmd) {
  if (cmd === 'pwd')    { pwdForm.value = { old_password: '', new_password: '', confirm: '' }; showPwd.value = true }
  if (cmd === 'logout') logout()
}

async function doChangePwd() {
  const f = pwdForm.value
  if (!f.old_password || !f.new_password) return ElMessage.warning('请填写完整')
  if (f.new_password.length < 6)          return ElMessage.warning('新密码至少 6 位')
  if (f.new_password !== f.confirm)       return ElMessage.warning('两次输入的新密码不一致')

  try {
    await request.post('/login.php?act=change_password',
      { old_password: f.old_password, new_password: f.new_password })
  } catch { return }
  ElMessage.success('密码已修改，请重新登录')
  showPwd.value = false
  // 密码变更 → 强制重新登录（后端并未 destroy session，前端主动登出即可）
  try { await request.post('/login.php?act=logout') } catch (_) {}
  clearUser()
  router.replace('/login')
}

async function logout() {
  try { await ElMessageBox.confirm('确定退出登录吗？', '提示', { type: 'warning' }) } catch { return }
  try { await request.post('/login.php?act=logout') } catch (_) {}
  clearUser()
  ElMessage.success('已退出')
  router.replace('/login')
}
</script>

<style>
html, body, #app { height: 100%; margin: 0; }
.app { height: 100vh; }
.aside { background: #001529; }
.logo { color: #fff; text-align: center; padding: 18px 0; font-size: 16px; font-weight: bold; border-bottom: 1px solid #1a3a5c; }
.header { background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.08); line-height: 60px; font-size: 18px; font-weight: bold;
  display:flex; align-items:center; justify-content: space-between; }
.header .user-box { cursor: pointer; font-size: 14px; font-weight: normal; color:#555; display:flex; align-items:center; }
.header .user-name { display:inline-flex; align-items:center; }
.header .user-name em { color:#999; font-style: normal; font-size: 12px; margin-left: 4px; }
.el-menu { border: none !important; }

/* ===== 禁止用户调整表格列宽 ===== */
.el-table th.el-table__cell { pointer-events: none !important; cursor: default !important; }
.el-table th.el-table__cell .cell,
.el-table th.el-table__cell .caret-wrapper { pointer-events: auto; }
.el-table__column-resize-proxy { display: none !important; }

/* ===== 禁止 textarea 拖拽缩放 ===== */
.el-textarea__inner, textarea { resize: none !important; }

/* ===== 禁止 dialog 手动缩放 ===== */
.el-dialog { resize: none !important; }

/* ===== 打印区域 ===== */
.print-hidden { position: fixed; left: -10000px; top: 0; width: 800px; }
</style>
