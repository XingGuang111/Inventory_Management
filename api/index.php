<?php
// API 根路径：返回接口清单，避免访问 / 报 404
require_once __DIR__ . '/db.php';
ret(200, 'stock-manage api is running', [
    'endpoints' => [
        'GET  /goods.php?act=list'      => '商品列表',
        'POST /goods.php?act=add'       => '新增商品',
        'POST /goods.php?act=edit'      => '修改商品',
        'POST /goods.php?act=del'       => '删除商品',
        'GET  /stock_in.php?act=list'   => '入库记录',
        'POST /stock_in.php?act=add'    => '新建入库单',
        'GET  /stock_out.php?act=list'  => '出库记录',
        'POST /stock_out.php?act=add'   => '新建出库单（含库存校验）',
        'GET  /stock.php'               => '实时库存',
        'GET  /check.php?act=list'      => '盘点记录',
        'POST /check.php?act=save'      => '保存盘点并更新库存',
    ],
    'frontend' => 'http://localhost:5173'
]);
