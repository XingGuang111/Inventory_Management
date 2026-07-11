import printJS from 'print-js'

export function printBill(elementId, title = '单据') {
  printJS({
    printable: elementId,
    type: 'html',
    header: title,
    targetStyles: ['*'],
    scanStyles: false,
    style: `
      @page { size: A4; margin: 15mm; }
      body { font-family: "Microsoft YaHei", "SimSun", sans-serif; color:#000; font-size: 13px; }
      .bill-header { text-align: center; font-size: 22px; font-weight: bold; margin: 6px 0 10px; letter-spacing: 4px; }
      .bill-info { display: flex; flex-wrap: wrap; gap: 20px; margin: 6px 0 10px; font-size: 13px; }
      .bill-info span { min-width: 200px; }
      table { width: 100%; border-collapse: collapse; margin-top: 6px; }
      th, td { border: 1px solid #333; padding: 6px 8px; text-align: center; font-size: 13px; }
      th { background: #f2f2f2; }
      .bill-total { text-align: right; margin-top: 8px; font-size: 14px; font-weight: bold; }
      .sign-block { margin-top: 40px; display: flex; justify-content: space-between; font-size: 13px; }
      .sign-block .sign-item { flex: 1; padding: 0 8px; }
      .sign-block .sign-line { border-bottom: 1px solid #000; height: 26px; margin-top: 6px; }
      .sign-notice { margin-top: 18px; font-size: 12px; color: #555; line-height: 1.8; }
      .stamp-area { margin-top: 30px; text-align: right; font-size: 13px; }
      .stamp-area .stamp-box { display: inline-block; width: 130px; height: 130px; border: 1px dashed #999; text-align: center; line-height: 130px; color: #999; margin-left: 8px; vertical-align: middle; }
    `
  })
}
