<template>
  <el-card>
    <div style="margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <el-input v-model="keyword" placeholder="按名称/分类/规格搜索" clearable style="width:260px" />
      <el-select v-model="sortKey" style="width:170px">
        <el-option label="默认（ID 升序）" value="id_asc" />
        <el-option label="库存 ↑" value="stock_asc" />
        <el-option label="库存 ↓" value="stock_desc" />
        <el-option label="进价 ↑" value="buy_asc" />
        <el-option label="进价 ↓" value="buy_desc" />
        <el-option label="售价 ↑" value="sell_asc" />
        <el-option label="售价 ↓" value="sell_desc" />
      </el-select>
      <el-checkbox v-model="onlyWarn">仅显示预警商品</el-checkbox>
      <el-button type="success" @click="exportAll" :disabled="!filteredList.length">导出 Excel</el-button>
      <span style="color:#909399">共 {{ filteredList.length }} 条</span>
    </div>
    <el-table :data="filteredList" border stripe>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="name" label="商品名称" />
      <el-table-column prop="category" label="分类" />
      <el-table-column prop="spec" label="规格" />
      <el-table-column prop="buy_price" label="进价" />
      <el-table-column prop="sell_price" label="售价" />
      <el-table-column label="当前库存">
        <template #default="{ row }">
          <span :style="{color: row.stock_num<=row.warn_num?'red':'inherit', fontWeight:row.stock_num<=row.warn_num?'bold':'normal'}">
            {{ row.stock_num }}
            <el-tag v-if="row.stock_num<=row.warn_num" type="danger" size="small" style="margin-left:6px">预警</el-tag>
          </span>
        </template>
      </el-table-column>
      <el-table-column prop="warn_num" label="预警阈值" />
    </el-table>
  </el-card>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import req from '../utils/request'
import { exportExcel } from '../utils/export'
const list = ref([])
const keyword = ref('')
const sortKey = ref('id_asc')
const onlyWarn = ref(false)

const filteredList = computed(() => {
  const kw = keyword.value.trim().toLowerCase()
  let arr = list.value.filter(r => {
    if (onlyWarn.value && Number(r.stock_num) > Number(r.warn_num)) return false
    if (!kw) return true
    return (r.name || '').toLowerCase().includes(kw) ||
           (r.category || '').toLowerCase().includes(kw) ||
           (r.spec || '').toLowerCase().includes(kw)
  })
  const cmp = {
    id_asc:    (a,b) => a.id - b.id,
    stock_asc: (a,b) => Number(a.stock_num) - Number(b.stock_num),
    stock_desc:(a,b) => Number(b.stock_num) - Number(a.stock_num),
    buy_asc:   (a,b) => Number(a.buy_price) - Number(b.buy_price),
    buy_desc:  (a,b) => Number(b.buy_price) - Number(a.buy_price),
    sell_asc:  (a,b) => Number(a.sell_price) - Number(b.sell_price),
    sell_desc: (a,b) => Number(b.sell_price) - Number(a.sell_price)
  }[sortKey.value]
  return [...arr].sort(cmp)
})

onMounted(async () => {
  const r = await req.get('/stock.php')
  list.value = r.data
})

const exportAll = () => {
  const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14)
  const rows = filteredList.value.map(r => ({
    ...r, is_warn: Number(r.stock_num) <= Number(r.warn_num) ? '预警' : ''
  }))
  exportExcel('库存表_' + stamp, rows, [
    { key: 'id',         label: 'ID',       width: 8 },
    { key: 'name',       label: '商品名称', width: 24 },
    { key: 'category',   label: '分类',     width: 12 },
    { key: 'spec',       label: '规格',     width: 14 },
    { key: 'buy_price',  label: '进价',     width: 10 },
    { key: 'sell_price', label: '售价',     width: 10 },
    { key: 'stock_num',  label: '当前库存', width: 10 },
    { key: 'warn_num',   label: '预警阈值', width: 10 },
    { key: 'is_warn',    label: '预警',     width: 8 }
  ], { '导出时间': new Date().toLocaleString(), '条数': rows.length })
  ElMessage.success('已导出库存表')
}
</script>
