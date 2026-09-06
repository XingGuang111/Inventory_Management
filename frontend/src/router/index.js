import { createRouter, createWebHashHistory } from 'vue-router'
import { user } from '../utils/authState'

// 路由 meta.perm：登录用户需拥有此模块权限才可进；admin 天然全通
// meta.role：仅指定角色可进（用户管理）
const routes = [
  { path: '/login', name: 'login', component: () => import('../views/Login.vue'), meta: { title: '登录', public: true } },
  { path: '/', redirect: '/goods' },
  // 商品清单页对所有登录用户可读（写操作在页面内按权限禁用），故不设 perm
  { path: '/goods', name: 'goods', component: () => import('../views/Goods.vue'), meta: { title: '商品清单' } },
  { path: '/in',    name: 'in',    component: () => import('../views/StockIn.vue'),  meta: { title: '入库管理', perm: 'stock_in' } },
  { path: '/out',   name: 'out',   component: () => import('../views/StockOut.vue'), meta: { title: '出库管理', perm: 'stock_out' } },
  { path: '/stock', name: 'stock', component: () => import('../views/Stock.vue'),    meta: { title: '库存查询' } },
  { path: '/check', name: 'check', component: () => import('../views/Check.vue'),    meta: { title: '库存盘点', perm: 'check' } },
  { path: '/users', name: 'users', component: () => import('../views/Users.vue'),    meta: { title: '用户管理', role: 'admin' } }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

router.beforeEach((to) => {
  if (to.meta.public) return true
  const u = user.value
  if (!u.id) return { path: '/login', query: { redirect: to.fullPath } }

  // 角色限定（用户管理）
  if (to.meta.role && (u.role !== to.meta.role)) return { path: '/goods' }
  // 模块权限限定；admin 直通 — 确保处理 undefined/null 情况
  if (to.meta.perm && (u.role !== 'admin')) {
    const perms = u.permissions || []
    if (!Array.isArray(perms) || !perms.includes(to.meta.perm)) return { path: '/goods' }
  }
  return true
})

export default router
