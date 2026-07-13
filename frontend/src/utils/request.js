import axios from 'axios'
import { ElMessage } from 'element-plus'

const service = axios.create({
  baseURL: '/api',
  timeout: 10000,
  withCredentials: true      // 携带 PHPSESSID cookie
})

// ===== 请求拦截：给所有 POST 自动带上 CSRF token =====
// token 由登录接口返回并存 localStorage；后端在 requireLogin 中比对 X-CSRF-Token
service.interceptors.request.use(cfg => {
  if ((cfg.method || 'get').toLowerCase() === 'post') {
    try {
      const u = JSON.parse(localStorage.getItem('stock_user') || '{}')
      if (u.csrf) cfg.headers['X-CSRF-Token'] = u.csrf
    } catch (_) { /* ignore */ }
  }
  return cfg
})

service.interceptors.response.use(
  res => {
    const d = res.data
    if (d.code === 401) {
      localStorage.removeItem('stock_user')
      ElMessage.warning(d.msg || '登录已过期，请重新登录')
      const cur = location.hash.replace(/^#/, '') || '/'
      if (!cur.startsWith('/login')) {
        location.hash = '#/login?redirect=' + encodeURIComponent(cur)
      }
      return Promise.reject(d)
    }
    if (d.code === 403) {
      ElMessage.error(d.msg || '权限不足')
      return Promise.reject(d)
    }
    if (d.code === 429) {
      ElMessage.error(d.msg || '操作过于频繁，请稍后再试')
      return Promise.reject(d)
    }
    if (d.code !== 200) {
      ElMessage.error(d.msg || '操作失败')
      return Promise.reject(d)
    }
    return d
  },
  err => {
    ElMessage.error('网络异常')
    return Promise.reject(err)
  }
)

export default service
