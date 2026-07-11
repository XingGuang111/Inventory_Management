<?php
require_once __DIR__ . '/db.php';
$db = new DB();
// 实时库存总表
$rows = $db->query(
    'SELECT id,name,category,spec,buy_price,sell_price,stock_num,warn_num,
     (stock_num<=warn_num) AS is_warn
     FROM goods ORDER BY id DESC'
);
ret(200, 'ok', $rows);
