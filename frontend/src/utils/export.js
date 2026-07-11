import * as XLSX from 'xlsx'

/**
 * 导出 Excel
 * @param {string} filename    文件名（不含扩展名）
 * @param {Array}  rows        数据行（每行一个对象）
 * @param {Array}  columns     [{key, label, width}]，key=行字段，label=表头
 * @param {Object} meta        可选：附加在表格顶部的元信息 {单号, 时间, 供应商...}
 */
export function exportExcel(filename, rows, columns, meta = null) {
  const aoa = []
  // 元信息
  if (meta) {
    Object.entries(meta).forEach(([k, v]) => aoa.push([k, v]))
    aoa.push([])
  }
  // 表头
  aoa.push(columns.map(c => c.label))
  // 数据
  rows.forEach(r => aoa.push(columns.map(c => {
    const v = r[c.key]
    return v === null || v === undefined ? '' : v
  })))

  const ws = XLSX.utils.aoa_to_sheet(aoa)
  ws['!cols'] = columns.map(c => ({ wch: c.width || 16 }))

  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Sheet1')
  XLSX.writeFile(wb, filename + '.xlsx')
}
