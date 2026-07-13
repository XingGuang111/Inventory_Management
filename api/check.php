<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$db = new DB();
$act = $_GET['act'] ?? $_POST['act'] ?? '';

// 自动清理：删除 2 个月前的盘点记录（懒清理，随接口调用触发）
try {
    $db->exec('DELETE FROM stock_check WHERE check_time < DATE_SUB(NOW(), INTERVAL 2 MONTH)');
} catch (Exception $e) { /* 清理失败不影响主流程 */ }

// 历史列表：按盘点单号聚合，只返回单号 + 时间（+ 明细条数）
if ($act === 'list') {
    $rows = $db->query(
        'SELECT check_no, MIN(check_time) AS check_time, COUNT(*) AS item_count
         FROM stock_check GROUP BY check_no ORDER BY MIN(id) DESC'
    );
    ret(200, 'ok', $rows);
}

// 单据详情：按 check_no 返回全部商品明细
if ($act === 'detail') {
    $no = $_GET['check_no'] ?? '';
    if (!$no) ret(400, '缺少 check_no');
    $rows = $db->query(
        'SELECT sc.*, g.name AS goods_name, g.spec AS goods_spec
         FROM stock_check sc LEFT JOIN goods g ON g.id=sc.goods_id
         WHERE sc.check_no=? ORDER BY sc.id ASC',
        [$no]
    );
    ret(200, 'ok', $rows);
}

if ($act === 'save') {
    requirePerm('check');
    $p = req();
    $items = $p['items'] ?? [];
    if (!is_array($items) || count($items) === 0) ret(400, '无盘点数据');
    $checkNo = 'CK' . date('YmdHis') . rand(100, 999);
    try {
        $db->begin();
        foreach ($items as $it) {
            $gid = intval($it['goods_id']);
            $book = intval($it['book_num']);
            $real = intval($it['real_num']);
            $diff = $real - $book;
            $remark = trim((string)($it['remark'] ?? ''));
            $db->exec(
                'INSERT INTO stock_check(check_no,goods_id,book_num,real_num,diff_num,remark) VALUES(?,?,?,?,?,?)',
                [$checkNo, $gid, $book, $real, $diff, $remark]
            );
            $db->exec('UPDATE goods SET stock_num=? WHERE id=?', [$real, $gid]);
        }
        $db->commit();
        ret(200, '盘点成功', ['check_no' => $checkNo]);
    } catch (Exception $e) {
        $db->rollback();
        ret(400, '盘点失败: ' . $e->getMessage());
    }
}

ret(400, '未知操作');
