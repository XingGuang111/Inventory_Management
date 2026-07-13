<?php
// API 根路径：返回接口清单，避免访问 / 报 404（本接口不做鉴权，仅返回静态清单）
require_once __DIR__ . '/db.php';
ret(200, 'stock-manage api is running', [
    'endpoints' => [
        'POST /login.php?act=login'            => '登录（body: {username, password}）',
        'POST /login.php?act=logout'           => '登出',
        'GET  /login.php?act=me'               => '当前用户信息（含 csrf）',
        'POST /login.php?act=change_password'  => '修改自己的密码',
        'GET  /users.php?act=list'             => '用户列表（admin）',
        'POST /users.php?act=add'              => '新增用户（admin）',
        'POST /users.php?act=set_active'       => '启用/禁用用户（admin）',
        'POST /users.php?act=reset_password'   => '为他人重置密码（admin）',
        'POST /users.php?act=del'              => '删除用户（admin）',
        'GET  /goods.php?act=list'             => '商品列表',
        'POST /goods.php?act=add'              => '新增商品（admin）',
        'POST /goods.php?act=edit'             => '修改商品（admin）',
        'POST /goods.php?act=del'              => '删除商品（admin）',
        'GET  /stock_in.php?act=list'          => '入库记录',
        'POST /stock_in.php?act=add'           => '新建入库单',
        'GET  /stock_out.php?act=list'         => '出库记录',
        'POST /stock_out.php?act=add'          => '新建出库单（含库存校验）',
        'GET  /stock.php'                      => '实时库存',
        'GET  /check.php?act=list'             => '盘点记录',
        'GET  /check.php?act=detail'           => '盘点详情',
        'POST /check.php?act=save'             => '保存盘点并更新库存（admin）',
    ],
    'frontend' => 'http://localhost:5173'
]);
