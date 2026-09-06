<?php
// 用户管理：仅 admin 角色可访问
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireRole('admin');

$db  = new DB();
$act = $_GET['act'] ?? $_POST['act'] ?? '';

if ($act === 'list') {
    $rows = $db->query(
        'SELECT id, username, role, permissions, is_active, last_login_at, created_at
         FROM users ORDER BY id ASC'
    );
    foreach ($rows as &$r) {
        $r['permissions'] = normalizePerms($r['permissions']);
    }
    ret(200, 'ok', $rows);
}

// 返回所有可选权限码，供前端渲染多选框
if ($act === 'perms') {
    ret(200, 'ok', [
        ['code' => 'goods',     'name' => '商品清单'],
        ['code' => 'stock_in',  'name' => '入库管理'],
        ['code' => 'stock_out', 'name' => '出库管理'],
        ['code' => 'check',     'name' => '库存盘点'],
    ]);
}

if ($act === 'add') {
    $p = req();
    $u = trim($p['username'] ?? '');
    $pw = (string)($p['password'] ?? '');
    $role = trim($p['role'] ?? 'keeper');
    $perms = normalizePerms($p['permissions'] ?? []);

    if ($u === '' || $pw === '') ret(400, '账号密码不能为空');
    if (strlen($pw) < 6)         ret(400, '密码长度至少 6 位');
    if (!in_array($role, ['admin', 'keeper'], true)) ret(400, '角色只能是 admin 或 keeper');
    // admin 天然全权限，可忽略此字段；非 admin 建议至少给一个权限，否则登录后什么模块都进不去
    $permStr = $role === 'admin' ? '' : implode(',', $perms);

    if ($db->queryOne('SELECT id FROM users WHERE username=?', [$u])) {
        ret(400, '账号已存在');
    }
    $id = $db->exec(
        'INSERT INTO users(username, password_hash, role, permissions, is_active) VALUES(?,?,?,?,1)',
        [$u, password_hash($pw, PASSWORD_DEFAULT), $role, $permStr]
    );
    ret(200, '新增用户成功', ['id' => $id]);
}

// 修改用户角色 + 权限（不改密码、不改账号名）
if ($act === 'update') {
    $p = req();
    $id = (int)($p['id'] ?? 0);
    if ($id <= 0) ret(400, '缺少用户 ID');
    $role  = trim($p['role'] ?? '');
    $perms = normalizePerms($p['permissions'] ?? []);
    if (!in_array($role, ['admin', 'keeper'], true)) ret(400, '角色只能是 admin 或 keeper');

    // 不允许把自己降级为非 admin（防止最后一个 admin 自锁）
    if ($id === (int)$_SESSION['uid'] && $role !== 'admin') {
        ret(400, '不能修改自己的角色');
    }
    // 若目标当前是 admin 而要改成 keeper，需保证系统至少还有一个 admin
    $target = $db->queryOne('SELECT role FROM users WHERE id=?', [$id]);
    if (!$target) ret(400, '用户不存在');
    if ($target['role'] === 'admin' && $role !== 'admin') {
        $left = $db->queryOne('SELECT COUNT(*) c FROM users WHERE role="admin" AND id<>?', [$id]);
        if ((int)$left['c'] === 0) ret(400, '不能取消最后一个管理员的 admin 角色');
    }

    // 防御性编程：如果目标原本就是 admin，但是收到的 role 为空，
    // 保持原有 role 不变，防止前端BUG把 admin 变成空权限
    if ($target['role'] === 'admin' && $role === '') {
        $role = 'admin';
    }

    $permStr = $role === 'admin' ? '' : implode(',', $perms);
    $db->exec('UPDATE users SET role=?, permissions=? WHERE id=?', [$role, $permStr, $id]);

    // 若被改的正是当前登录会话，同步刷新 session；并把最新 role/permissions
    // 回传给前端，让 authState 一起更新（避免"session 是新权限、localStorage
    // 是旧权限"导致 UI 与实际权限脱节）。
    $payload = null;
    if ($id === (int)$_SESSION['uid']) {
        $_SESSION['role']        = $role;
        $_SESSION['permissions'] = $perms;
        $payload = ['id' => $id, 'role' => $role, 'permissions' => $perms];
    }
    ret(200, '已保存', $payload);
}

if ($act === 'set_active') {
    $p = req();
    $id = (int)($p['id'] ?? 0);
    $active = (int)!!($p['is_active'] ?? 0);
    if ($id <= 0) ret(400, '缺少用户 ID');
    if ($id === (int)$_SESSION['uid']) ret(400, '不能禁用/启用自己的账号');
    $db->exec('UPDATE users SET is_active=? WHERE id=?', [$active, $id]);
    ret(200, $active ? '已启用' : '已禁用');
}

if ($act === 'reset_password') {
    $p = req();
    $id = (int)($p['id'] ?? 0);
    $pw = (string)($p['new_password'] ?? '');
    if ($id <= 0)         ret(400, '缺少用户 ID');
    if (strlen($pw) < 6)  ret(400, '密码长度至少 6 位');
    $db->exec('UPDATE users SET password_hash=? WHERE id=?',
        [password_hash($pw, PASSWORD_DEFAULT), $id]);
    $db->exec('DELETE FROM login_attempts WHERE username=(SELECT username FROM users WHERE id=?)', [$id]);
    ret(200, '密码已重置');
}

if ($act === 'del') {
    $p = req();
    $id = (int)($p['id'] ?? 0);
    if ($id <= 0) ret(400, '缺少用户 ID');
    if ($id === (int)$_SESSION['uid']) ret(400, '不能删除自己的账号');

    $target = $db->queryOne('SELECT username, role FROM users WHERE id=?', [$id]);
    if (!$target) ret(400, '用户不存在');
    if ($target['role'] === 'admin') {
        $left = $db->queryOne('SELECT COUNT(*) c FROM users WHERE role="admin" AND id<>?', [$id]);
        if ((int)$left['c'] === 0) ret(400, '不能删除最后一个管理员');
    }
    $db->exec('DELETE FROM users WHERE id=?', [$id]);
    $db->exec('DELETE FROM login_attempts WHERE username=?', [$target['username']]);
    ret(200, '已删除');
}

ret(400, '未知操作');
