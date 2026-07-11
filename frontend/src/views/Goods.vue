<template>
  <el-card>
    <div style="margin-bottom:12px;display:flex;gap:10px;align-items:center">
      <el-button type="primary" @click="openAdd">新增商品</el-button>
      <el-button type="success" @click="exportAll" :disabled="!filteredList.length">导出 Excel</el-button>
      <el-input v-model="keyword" placeholder="按名称/分类/规格搜索" clearable style="width:260px" />
      <span style="color:#909399">共 {{ filteredList.length }} 条</span>
    </div>
    <el-table :data="filteredList" border stripe>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="name" label="商品名称" />
      <el-table-column prop="category" label="分类" />
      <el-table-column prop="spec" label="规格" />
      <el-table-column prop="buy_price" label="进价" />
      <el-table-column prop="sell_price" label="售价" />
      <el-table-column prop="stock_num" label="当前库存" />
      <el-table-column prop="warn_num" label="预警库存" />
      <el-table-column label="操作" width="160">
        <template #default="{ row }">
          <el-button size="small" @click="openEdit(row)">编辑</el-button>
          <el-button size="small" type="danger" @click="del(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dlg" :title="form.id ? '编辑商品' : '新增商品'" width="500px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="商品名称"><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="分类"><el-input v-model="form.category" /></el-form-item>
        <el-form-item label="规格"><el-input v-model="form.spec" /></el-form-item>
        <el-form-item label="进价"><el-input-number v-model="form.buy_price" :min="0" :precision="2" /></el-form-item>
        <el-form-item label="售价"><el-input-number v-model="form.sell_price" :min="0" :precision="2" /></el-form-item>
        <el-form-item label="预警库存"><el-input-number v-model="form.warn_num" :min="0" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dlg=false">取消</el-button>
        <el-button type="primary" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import req from '../utils/request'
import { exportExcel } from '../utils/export'

const list = ref([])
const dlg = ref(false)
const form = ref({ name: '', category: '', spec: '', buy_price: 0, sell_price: 0, warn_num: 10 })
const keyword = ref('')

const filteredList = computed(() => {
  const kw = keyword.value.trim().toLowerCase()
  if (!kw) return list.value
  return list.value.filter(r =>
    (r.name || '').toLowerCase().includes(kw) ||
    (r.category || '').toLowerCase().includes(kw) ||
    (r.spec || '').toLowerCase().includes(kw)
  )
})

const load = async () => {
  const r = await req.get('/goods.php?act=list')
  list.value = r.data
}
const openAdd = () => {
  form.value = { name: '', category: '', spec: '', buy_price: 0, sell_price: 0, warn_num: 10 }
  dlg.value = true
}
const openEdit = (row) => {
  form.value = { ...row }
  dlg.value = true
}
const save = async () => {
  if (!form.value.name) return ElMessage.warning('请输入商品名称')
  const act = form.value.id ? 'edit' : 'add'
  await req.post('/goods.php?act=' + act, form.value)
  ElMessage.success('保存成功')
  dlg.value = false
  load()
}
const del = (row) => {
  ElMessageBox.confirm(`确认删除商品「${row.name}」？`, '提示', { type: 'warning' })
    .then(async () => {
      await req.post('/goods.php?act=del', { id: row.id })
      ElMessage.success('删除成功')
      load()
    }).catch(() => {})
}
const exportAll = () => {
  const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14)
  exportExcel('商品清单_' + stamp, filteredList.value, [
    { key: 'id',          label: 'ID',       width: 8 },
    { key: 'name',        label: '商品名称', width: 24 },
    { key: 'category',    label: '分类',     width: 12 },
    { key: 'spec',        label: '规格',     width: 14 },
    { key: 'buy_price',   label: '进价',     width: 10 },
    { key: 'sell_price',  label: '售价',     width: 10 },
    { key: 'stock_num',   label: '当前库存', width: 10 },
    { key: 'warn_num',    label: '预警阈值', width: 10 },
    { key: 'create_time', label: '创建时间', width: 20 }
  ], { '导出时间': new Date().toLocaleString(), '条数': filteredList.value.length })
  ElMessage.success('已导出商品清单')
}
onMounted(load)
</script>
