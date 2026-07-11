<template>
  <el-card>
    <div style="margin-bottom:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <el-button type="primary" @click="loadGoods">加载账面库存</el-button>
      <el-button type="success" @click="submit" :disabled="!items.length">确认盘点</el-button>
      <el-button type="warning" @click="exportCurrent" :disabled="!items.length">导出当前盘点表</el-button>
      <el-button @click="toggleHist">{{ showHist?'关闭历史':'查看历史' }}</el-button>
      <el-input v-model="keyword" placeholder="按商品名/单号搜索" clearable style="width:220px" />
      <span style="color:#909399">共 {{ showHist ? filteredHistory.length : filteredItems.length }} 条</span>
    </div>

    <!-- 盘点录入表 -->
    <el-table v-if="!showHist" :data="filteredItems" border stripe>
      <el-table-column prop="name" label="商品" />
      <el-table-column prop="spec" label="规格" />
      <el-table-column prop="book_num" label="账面库存" width="110" />
      <el-table-column label="实际库存" width="170">
        <template #default="{ row }">
          <el-input-number v-model="row.real_num" :min="0" />
        </template>
      </el-table-column>
      <el-table-column label="盘盈盘亏" width="110">
        <template #default="{ row }">
          <span :style="diffStyle(row.real_num - row.book_num)">
            {{ diffText(row.real_num - row.book_num) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column label="备注（盈亏原因）">
        <template #default="{ row }">
          <el-input v-model="row.remark" placeholder="例如：破损/失窃/补录入库" clearable />
        </template>
      </el-table-column>
    </el-table>

    <!-- 历史列表：只显示单号 + 时间，行可点开 -->
    <el-table v-else :data="filteredHistory" border stripe highlight-current-row
              style="cursor:pointer" @row-click="openDetail">
      <el-table-column prop="check_no" label="盘点单号" width="260" />
      <el-table-column prop="check_time" label="盘点时间" width="200" />
      <el-table-column label="商品数" width="100">
        <template #default="{ row }">{{ row.item_count }}</template>
      </el-table-column>
      <el-table-column label="操作">
        <template #default="{ row }">
          <el-button size="small" type="primary" link @click.stop="openDetail(row)">查看详情</el-button>
          <el-button size="small" type="success" link @click.stop="exportHist(row)">导出</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 单据详情弹窗 -->
    <el-dialog v-model="detailDlg" :title="'盘点详情 ' + detailNo" width="820px">
      <el-table :data="detailItems" border stripe>
        <el-table-column prop="goods_name" label="商品" />
        <el-table-column prop="goods_spec" label="规格" width="120" />
        <el-table-column prop="book_num" label="账面" width="90" />
        <el-table-column prop="real_num" label="实际" width="90" />
        <el-table-column label="盘盈盘亏" width="120">
          <template #default="{ row }">
            <span :style="diffStyle(row.diff_num)">{{ diffText(row.diff_num) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" />
      </el-table>
      <div style="margin-top:8px;color:#909399">时间：{{ detailTime }}</div>
    </el-dialog>

    <!-- 打印区 -->
    <div id="print-area" class="print-hidden">
      <div class="bill-header">库 存 盘 点 单</div>
      <div class="bill-info">
        <span>盘点单号：{{ lastNo }}</span>
        <span>盘点时间：{{ new Date().toLocaleString() }}</span>
      </div>
      <table>
        <thead><tr><th>序号</th><th>商品</th><th>规格</th><th>账面</th><th>实际</th><th>盘盈盘亏</th><th>备注</th></tr></thead>
        <tbody>
          <tr v-for="(it,i) in items" :key="i">
            <td>{{ i+1 }}</td>
            <td>{{ it.name }}</td>
            <td>{{ it.spec }}</td>
            <td>{{ it.book_num }}</td>
            <td>{{ it.real_num }}</td>
            <td>{{ it.real_num - it.book_num }}</td>
            <td>{{ it.remark }}</td>
          </tr>
        </tbody>
      </table>

      <div class="sign-notice">
        温馨提醒：本盘点单由现场实盘生成，请核对账面数与实际数差异及备注原因；异常盈亏须由复核人二次确认后方可入账，签字盖章后归档。
      </div>

      <div class="sign-block">
        <div class="sign-item">盘点人（签字）：<div class="sign-line"></div></div>
        <div class="sign-item">复核人（签字）：<div class="sign-line"></div></div>
        <div class="sign-item">负责人（签字）：<div class="sign-line"></div></div>
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
import { ElMessage, ElMessageBox } from 'element-plus'
import req from '../utils/request'
import { printBill } from '../utils/print'
import { exportExcel } from '../utils/export'

const items = ref([])
const history = ref([])
const showHist = ref(false)
const lastNo = ref('')
const keyword = ref('')

const detailDlg = ref(false)
const detailNo = ref('')
const detailTime = ref('')
const detailItems = ref([])

const diffStyle = (d) => ({
  color: d > 0 ? '#67c23a' : d < 0 ? '#f56c6c' : '#606266',
  fontWeight: d === 0 ? 'normal' : 'bold'
})
const diffText = (d) => (d > 0 ? '+' + d : String(d))

const filteredItems = computed(() => {
  const kw = keyword.value.trim().toLowerCase()
  if (!kw) return items.value
  return items.value.filter(r => (r.name || '').toLowerCase().includes(kw))
})
const filteredHistory = computed(() => {
  const kw = keyword.value.trim().toLowerCase()
  if (!kw) return history.value
  return history.value.filter(r => (r.check_no || '').toLowerCase().includes(kw))
})

const loadGoods = async () => {
  const r = await req.get('/goods.php?act=list')
  items.value = r.data.map(g => ({
    goods_id: g.id, name: g.name, spec: g.spec,
    book_num: g.stock_num, real_num: g.stock_num, remark: ''
  }))
}
const loadHist = async () => {
  const r = await req.get('/check.php?act=list')
  history.value = r.data
}
const toggleHist = () => { showHist.value = !showHist.value; if (showHist.value) loadHist() }

const openDetail = async (row) => {
  detailNo.value = row.check_no
  detailTime.value = row.check_time
  const r = await req.get('/check.php?act=detail&check_no=' + encodeURIComponent(row.check_no))
  detailItems.value = r.data
  detailDlg.value = true
}

const exportCurrent = () => {
  const rows = items.value.map(it => ({
    name: it.name, spec: it.spec,
    book_num: it.book_num, real_num: it.real_num,
    diff_num: it.real_num - it.book_num, remark: it.remark || ''
  }))
  const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14)
  exportExcel('盘点表_' + stamp, rows, [
    { key: 'name',     label: '商品',     width: 24 },
    { key: 'spec',     label: '规格',     width: 12 },
    { key: 'book_num', label: '账面库存', width: 12 },
    { key: 'real_num', label: '实际库存', width: 12 },
    { key: 'diff_num', label: '盘盈盘亏', width: 12 },
    { key: 'remark',   label: '备注',     width: 30 }
  ], { '导出时间': new Date().toLocaleString() })
  ElMessage.success('已导出当前盘点表')
}

const exportHist = async (row) => {
  const r = await req.get('/check.php?act=detail&check_no=' + encodeURIComponent(row.check_no))
  const rows = (r.data || []).map(it => ({
    name: it.goods_name, spec: it.goods_spec,
    book_num: it.book_num, real_num: it.real_num,
    diff_num: it.diff_num, remark: it.remark || ''
  }))
  exportExcel('盘点单_' + row.check_no, rows, [
    { key: 'name',     label: '商品',     width: 24 },
    { key: 'spec',     label: '规格',     width: 12 },
    { key: 'book_num', label: '账面库存', width: 12 },
    { key: 'real_num', label: '实际库存', width: 12 },
    { key: 'diff_num', label: '盘盈盘亏', width: 12 },
    { key: 'remark',   label: '备注',     width: 30 }
  ], { '单号': row.check_no, '盘点时间': row.check_time })
  ElMessage.success('已导出：盘点单_' + row.check_no + '.xlsx')
}

const submit = () => {
  ElMessageBox.confirm('确认按当前实际库存数据完成盘点并更新库存？', '提示', { type: 'warning' })
    .then(async () => {
      const r = await req.post('/check.php?act=save', { items: items.value })
      lastNo.value = r.data.check_no
      ElMessage.success('盘点成功：' + r.data.check_no)
      setTimeout(() => printBill('print-area', '盘点单 ' + r.data.check_no), 300)
      loadGoods()
      if (showHist.value) loadHist()
    }).catch(() => {})
}
onMounted(() => { loadGoods() })
</script>
