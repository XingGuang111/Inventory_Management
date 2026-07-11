<?php
require_once __DIR__ . '/db.php';
$db = new DB();
$act = $_GET['act'] ?? $_POST['act'] ?? '';

if ($act === 'list') {
    $rows = $db->query(
        'SELECT si.*, (SELECT JSON_ARRAYAGG(JSON_OBJECT("goods_id",it.goods_id,"name",g.name,"num",it.num,"price",it.price))
         FROM stock_in_item it LEFT JOIN goods g ON g.id=it.goods_id WHERE it.in_id=si.id) AS items
         FROM stock_in si ORDER BY si.id DESC'
    );
    foreach ($rows as &$r) {
        $r['items'] = $r['items'] ? json_decode($r['items'], true) : [];
    }
    ret(200, 'ok', $rows);
}

if ($act === 'add') {
    $p = req();
    $items = $p['items'] ?? [];
    if (!is_array($items) || count($items) === 0) ret(400, '请添加入库商品');
    $inNo = 'IN' . date('YmdHis') . rand(100, 999);
    try {
        $db->begin();
        $inId = $db->exec(
            'INSERT INTO stock_in(in_no,supplier,total_money) VALUES(?,?,0)',
            [$inNo, $p['supplier'] ?? '']
        );
        $total = 0;
        foreach ($items as $it) {
            $num = intval($it['num']);
            $price = floatval($it['price']);
            $gid = intval($it['goods_id']);
            if ($num <= 0 || $gid <= 0) throw new Exception('入库数据无效');
            $db->exec('INSERT INTO stock_in_item(in_id,goods_id,num,price) VALUES(?,?,?,?)',
                [$inId, $gid, $num, $price]);
            $db->exec('UPDATE goods SET stock_num=stock_num+? WHERE id=?', [$num, $gid]);
            $total += $num * $price;
        }
        $db->exec('UPDATE stock_in SET total_money=? WHERE id=?', [$total, $inId]);
        $db->commit();
        ret(200, '入库成功', ['in_no' => $inNo, 'total' => $total]);
    } catch (Exception $e) {
        $db->rollback();
        ret(400, '入库失败: ' . $e->getMessage());
    }
}

ret(400, '未知操作');
