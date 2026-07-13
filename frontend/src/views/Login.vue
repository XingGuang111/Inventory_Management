<template>
  <div class="login-wrap">
    <div class="login-card">
      <div class="login-title">商品库存管理系统</div>
      <div class="login-sub">请登录</div>
      <el-form @submit.prevent="submit" :model="form" label-width="0" size="large">
        <el-form-item>
          <el-input v-model="form.username" placeholder="账号" autocomplete="username" clearable />
        </el-form-item>
        <el-form-item>
          <el-input v-model="form.password" type="password" placeholder="密码" autocomplete="current-password" show-password @keyup.enter="submit" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" style="width:100%" :loading="loading" @click="submit">登 录</el-button>
        </el-form-item>
      </el-form>
      <div class="login-tip">首次使用默认账号：admin / admin123（登录后请及时修改）</div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import request from '../utils/request'

const router = useRouter()
const route  = useRoute()
const form   = ref({ username: '', password: '' })
const loading = ref(false)

async function submit() {
  if (!form.value.username || !form.value.password) {
    ElMessage.warning('请输入账号和密码'); return
  }
  loading.value = true
  try {
    const res = await request.post('/login.php?act=login', form.value)
    // 缓存到 localStorage，App.vue 显示用户名 + 路由守卫本地快速判断
    localStorage.setItem('stock_user', JSON.stringify(res.data))
    ElMessage.success('登录成功')
    const redirect = route.query.redirect || '/goods'
    router.replace(redirect)
  } catch (_) { /* 错误提示已由 request 拦截器处理 */ }
  finally { loading.value = false }
}
</script>

<style scoped>
.login-wrap { height: 100vh; display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg,#001529 0%,#003a70 100%); }
.login-card { width: 360px; padding: 40px 32px; background:#fff; border-radius: 8px;
  box-shadow: 0 8px 32px rgba(0,0,0,.25); }
.login-title { font-size: 20px; font-weight: bold; text-align:center; margin-bottom: 6px; color:#001529; }
.login-sub   { font-size: 13px; text-align:center; color:#888; margin-bottom: 24px; }
.login-tip   { font-size: 12px; color:#999; text-align:center; margin-top: 8px; }
</style>
