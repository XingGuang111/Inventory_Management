<template>
  <el-card>
    <div style="margin-bottom:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <el-button type="primary" @click="openAdd">新建入库单</el-button>
      <el-input v-model="keyword" placeholder="按商品名 / 供应商搜索" clearable style="width:260px" />
      <el-select v-model="sortKey" style="width:170px">
        <el-option label="时间倒序（默认）" value="time_desc" />
        <el-option label="数量 ↑" value="num_asc" />
        <el-option label="数量 ↓" value="num_desc" />
        <el-option label="金额 ↑" value="money_asc" />
        <el-option label="金额 ↓" value="money_desc" />
      </el-select>
      <span style="color:#909399">共 {{ filteredList.length }} 条</span>
    </div>
    <el-table :data="filteredList" border stripe>
      <el-table-column prop="in_no" label="入库单号" width="220" />
      <el-table-column prop="supplier" label="供应商" />
      <el-table-column label="商品明细">
        <template #default="{ row }">
          <span v-for="(it,i) in row.items" :key="i" style="margin-right:8px">
            {{ it.name }}×{{ it.num }}
          </span>
        </template>
      </el-table-column>
      <el-table-column label="总数量" width="90">
        <template #default="{ row }">{{ sumNum(row) }}</template>
      </el-table-column>
      <el-table-column prop="total_money" label="总金额" width="100" />
      <el-table-column prop="create_time" label="入库时间" width="170" />
      <el-table-column label="操作" width="180">
        <template #default="{ row }">
          <el-button size="small" @click="printOne(row)">打印</el-button>
          <el-button size="small" type="success" @click="exportOne(row)">导出</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dlg" title="新建入库单" width="800px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="供应商"><el-input v-model="form.supplier" /></el-form-item>
      </el-form>
      <el-table :data="form.items" border>
        <el-table-column label="商品">
          <template #default="{ row }">
            <el-select v-model="row.goods_id" filterable placeholder="选择商品">
              <el-option v-for="g in goods" :key="g.id" :label="g.name+' '+(g.spec||'')" :value="g.id" />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column label="数量" width="140">
          <template #default="{ row }">
            <el-input-number v-model="row.num" :min="1" />
          </template>
        </el-table-column>
        <el-table-column label="单价" width="160">
          <template #default="{ row }">
            <el-input-number v-model="row.price" :min="0" :precision="2" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="80">
          <template #default="{ $index }">
            <el-button size="small" type="danger" @click="form.items.splice($index,1)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-button style="margin-top:10px" @click="addRow">添加商品</el-button>
      <template #footer>
        <el-button @click="dlg=false">取消</el-button>
        <el-button type="primary" @click="submit">提交入库</el-button>
      </template>
    </el-dialog>

    <div id="print-area" class="print-hidden">
      <div class="bill-header">入 库 单</div>
      <div class="bill-info">
        <span>入库单号：{{ printData.in_no }}</span>
        <span>供应商：{{ printData.supplier }}</span>
        <span>入库时间：{{ printData.create_time }}</span>
      </div>
      <table>
        <thead><tr><th>序号</th><th>商品</th><th>数量</th><th>单价</th><th>小计</th></tr></thead>
        <tbody>
          <tr v-for="(it,i) in printData.items" :key="i">
            <td>{{ i+1 }}</td>
            <td>{{ it.name }}</td><td>{{ it.num }}</td><td>{{ it.price }}</td>
            <td>{{ (it.num*it.price).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
      <div class="bill-total">合计金额：￥{{ printData.total_money }}</div>

      <div class="sign-notice">
        温馨提醒：请核对以上入库商品的数量、单价、批次是否与实物一致，确认无误后签字盖章存档。本单一式两联，供应商与仓库各留存一份。
      </div>

      <div class="sign-block">
        <div class="sign-item">制单人（签字）：<div class="sign-line"></div></div>
        <div class="sign-item">仓管员（签字）：<div class="sign-line"></div></div>
        <div class="sign-item">收货人（签字）：<div class="sign-line"></div></div>
        <div class="sign-item">日 期：<div class="sign-line"></div></div>
      </div>

      <div class="stamp-area">
        单位盖章：<span class="stamp-box">（加盖公章）</span>
      </div>
    </div>
  </el-card>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import req from '../utils/request'
import { printBill } from '../utils/print'
import { exportExcel } from '../utils/export'

const list = ref([])
const goods = ref([])
const dlg = ref(false)
const form = ref({ supplier: '', items: [] })
const printData = ref({ items: [] })
const keyword = ref('')
const sortKey = ref('time_desc')

const sumNum = (row) => (row.items || []).reduce((s, i) => s + Number(i.num || 0), 0)

const filteredList = computed(() => {
  const kw = keyword.value.trim().toLowerCase()
  let arr = list.value.filter(r => {
    if (!kw) return true
    // 匹配供应商名 或 商品名（任一命中即返回）
    if ((r.supplier || '').toLowerCase().includes(kw)) return true
    return (r.items || []).some(it => (it.name || '').toLowerCase().includes(kw))
  })
  const money = r => Number(r.total_money || 0)
  const num = r => sumNum(r)
  const cmp = {
    time_desc: (a, b) => b.id - a.id,
    num_asc:   (a, b) => num(a) - num(b),
    num_desc:  (a, b) => num(b) - num(a),
    money_asc: (a, b) => money(a) - money(b),
    money_desc:(a, b) => money(b) - money(a)
  }[sortKey.value]
  return [...arr].sort(cmp)
})

const load = async () => {
  const r = await req.get('/stock_in.php?act=list')
  list.value = r.data
  const g = await req.get('/goods.php?act=list')
  goods.value = g.data
}
const openAdd = () => {
  form.value = { supplier: '', items: [{ goods_id: null, num: 1, price: 0 }] }
  dlg.value = true
}
const addRow = () => form.value.items.push({ goods_id: null, num: 1, price: 0 })
const submit = async () => {
  if (form.value.items.some(i => !i.goods_id)) return ElMessage.warning('请选择商品')
  const r = await req.post('/stock_in.php?act=add', form.value)
  ElMessage.success('入库成功：' + r.data.in_no)
  dlg.value = false
  load()
}
const printOne = (row) => {
  printData.value = row
  setTimeout(() => printBill('print-area', '入库单 ' + row.in_no), 100)
}
const exportOne = (row) => {
  const rows = (row.items || []).map(it => ({
    ...it, subtotal: (Number(it.num) * Number(it.price)).toFixed(2)
  }))
  exportExcel('入库单_' + row.in_no, rows, [
    { key: 'name',     label: '商品',   width: 24 },
    { key: 'num',      label: '数量',   width: 10 },
    { key: 'price',    label: '单价',   width: 12 },
    { key: 'subtotal', label: '小计',   width: 14 }
  ], {
    '单号': row.in_no, '供应商': row.supplier || '',
    '入库时间': row.create_time, '合计': row.total_money
  })
  ElMessage.success('已导出：入库单_' + row.in_no + '.xlsx')
}
onMounted(load)
</script>
