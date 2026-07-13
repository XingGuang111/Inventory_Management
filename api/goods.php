<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$db = new DB();
$act = $_GET['act'] ?? $_POST['act'] ?? '';

switch ($act) {
    // 商品清单是"库存查询"页也要用的基础数据，登录即可读；写操作才需要 goods 权限
    case 'list':
        $rows = $db->query('SELECT * FROM goods ORDER BY id DESC');
        ret(200, 'ok', $rows);
        break;
    case 'add': {
        requirePerm('goods');
        $p = req();
        if (empty($p['name'])) ret(400, '商品名称不能为空');
        $id = $db->exec(
            'INSERT INTO goods(name,category,spec,buy_price,sell_price,stock_num,warn_num) VALUES(?,?,?,?,?,?,?)',
            [$p['name'], $p['category'] ?? '', $p['spec'] ?? '',
             $p['buy_price'] ?? 0, $p['sell_price'] ?? 0,
             $p['stock_num'] ?? 0, $p['warn_num'] ?? 10]
        );
        ret(200, '新增成功', ['id' => $id]);
        break;
    }
    case 'edit': {
        requirePerm('goods');
        $p = req();
        if (empty($p['id'])) ret(400, '缺少商品ID');
        $db->exec(
            'UPDATE goods SET name=?,category=?,spec=?,buy_price=?,sell_price=?,warn_num=? WHERE id=?',
            [$p['name'], $p['category'] ?? '', $p['spec'] ?? '',
             $p['buy_price'] ?? 0, $p['sell_price'] ?? 0,
             $p['warn_num'] ?? 10, $p['id']]
        );
        ret(200, '修改成功');
        break;
    }
    case 'del': {
        requirePerm('goods');
        $p = req();
        if (empty($p['id'])) ret(400, '缺少商品ID');
        try {
            $db->exec('DELETE FROM goods WHERE id=?', [$p['id']]);
            ret(200, '删除成功');
        } catch (Exception $e) {
            ret(400, '该商品已有出入库记录，无法删除');
        }
        break;
    }
    default:
        ret(400, '未知操作');
}
