import { ref } from 'vue'

// 集中维护「当前登录用户」的状态。
// 关键：用户信息既要写到 localStorage（持久化 + 路由守卫/CSRF 取值），
//      也要写到这个响应式 ref（驱动 App.vue 的菜单/顶栏/按钮重渲染）。
// 之前 App.vue 用 computed(() => localStorage.getItem(...))，而 localStorage
// 不是 Vue 响应式源，所以登录/退出/改自己权限后 UI 不会刷新；只能整页刷新
// 让 App.vue 重新挂载后 computed 才会重读 localStorage —— 这就是"刷新一下
// 才正常显示该账号该有的权限"的根因。

function loadFromStorage() {
  try { return JSON.parse(localStorage.getItem('stock_user') || '{}') }
  catch { return {} }
}

// 单例 ref：全应用共享同一份
const _user = ref(loadFromStorage())

// 跨标签页同步：A 标签页里 setUser，B 标签页也能立刻看到
if (typeof window !== 'undefined') {
  window.addEventListener('storage', (e) => {
    if (e.key === 'stock_user') _user.value = loadFromStorage()
  })
}

// 直接导出 ref，模板里用 user.value.xxx
export const user = _user

// 写入：localStorage + 响应式 ref 双写
export function setUser(u) {
  localStorage.setItem('stock_user', JSON.stringify(u))
  _user.value = u
}

// 清除：localStorage + 响应式 ref 双清
export function clearUser() {
  localStorage.removeItem('stock_user')
  _user.value = {}
}
