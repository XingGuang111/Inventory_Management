import { createRouter, createWebHashHistory } from 'vue-router'

const routes = [
  { path: '/', redirect: '/goods' },
  { path: '/goods', name: 'goods', component: () => import('../views/Goods.vue'), meta: { title: '商品清单' } },
  { path: '/in', name: 'in', component: () => import('../views/StockIn.vue'), meta: { title: '入库管理' } },
  { path: '/out', name: 'out', component: () => import('../views/StockOut.vue'), meta: { title: '出库管理' } },
  { path: '/stock', name: 'stock', component: () => import('../views/Stock.vue'), meta: { title: '库存查询' } },
  { path: '/check', name: 'check', component: () => import('../views/Check.vue'), meta: { title: '库存盘点' } }
]

export default createRouter({
  history: createWebHashHistory(),
  routes
})
