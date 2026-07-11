<?php
require_once __DIR__ . '/db.php';
$db = new DB();
$act = $_GET['act'] ?? $_POST['act'] ?? '';

if ($act === 'list') {
    $rows = $db->query(
        'SELECT so.*, (SELECT JSON_ARRAYAGG(JSON_OBJECT("goods_id",it.goods_id,"name",g.name,"num",it.num,"price",it.price))
         FROM stock_out_item it LEFT JOIN goods g ON g.id=it.goods_id WHERE it.out_id=so.id) AS items
         FROM stock_out so ORDER BY so.id DESC'
    );
    foreach ($rows as &$r) {
        $r['items'] = $r['items'] ? json_decode($r['items'], true) : [];
    }
    ret(200, 'ok', $rows);
}

if ($act === 'add') {
    $p = req();
    $items = $p['items'] ?? [];
    if (!is_array($items) || count($items) === 0) ret(400, '请添加出库商品');

    // 前置库存校验
    foreach ($items as $it) {
        $gid = intval($it['goods_id']);
        $num = intval($it['num']);
        $g = $db->queryOne('SELECT name, stock_num FROM goods WHERE id=?', [$gid]);
        if (!$g) ret(400, '商品不存在');
        if ($num <= 0) ret(400, '出库数量必须大于0');
        if ($num > $g['stock_num']) {
            ret(400, "商品【{$g['name']}】库存不足，当前库存{$g['stock_num']}，无法出库{$num}");
        }
    }

    $outNo = 'OUT' . date('YmdHis') . rand(100, 999);
    try {
        $db->begin();
        $outId = $db->exec(
            'INSERT INTO stock_out(out_no,customer,reason,total_money) VALUES(?,?,?,0)',
            [$outNo, $p['customer'] ?? '', $p['reason'] ?? '']
        );
        $total = 0;
        foreach ($items as $it) {
            $num = intval($it['num']);
            $price = floatval($it['price']);
            $gid = intval($it['goods_id']);
            $db->exec('INSERT INTO stock_out_item(out_id,goods_id,num,price) VALUES(?,?,?,?)',
                [$outId, $gid, $num, $price]);
            $db->exec('UPDATE goods SET stock_num=stock_num-? WHERE id=?', [$num, $gid]);
            $total += $num * $price;
        }
        $db->exec('UPDATE stock_out SET total_money=? WHERE id=?', [$total, $outId]);
        $db->commit();
        ret(200, '出库成功', ['out_no' => $outNo, 'total' => $total]);
    } catch (Exception $e) {
        $db->rollback();
        ret(400, '出库失败: ' . $e->getMessage());
    }
}

ret(400, '未知操作');
