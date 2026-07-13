<?php
// 登录 / 登出 / 查询当前用户 / 修改自己的密码
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$db  = new DB();
$act = $_GET['act'] ?? $_POST['act'] ?? '';

// ===== 常量：失败限流策略 =====
const LOGIN_MAX_FAILS   = 5;    // 连续失败上限
const LOGIN_LOCK_MINUTE = 15;   // 达到上限锁定分钟数

if ($act === 'login') {
    $p  = req();
    $u  = trim($p['username'] ?? '');
    $pw = (string)($p['password'] ?? '');
    if ($u === '' || $pw === '') ret(400, '账号密码不能为空');

    $ip = clientIp();

    // ---- 1) 限流检查（先看 (ip, username) 是否在锁定中）----
    $att = $db->queryOne(
        'SELECT fail_count, locked_until FROM login_attempts WHERE ip=? AND username=?',
        [$ip, $u]
    );
    if ($att && !empty($att['locked_until']) && strtotime($att['locked_until']) > time()) {
        $left = ceil((strtotime($att['locked_until']) - time()) / 60);
        ret(429, "尝试次数过多，请约 {$left} 分钟后再试");
    }

    // ---- 2) 校验账号密码 ----
    $row = $db->queryOne('SELECT * FROM users WHERE username=? AND is_active=1', [$u]);
    $ok  = $row && password_verify($pw, $row['password_hash']);

    if (!$ok) {
        // 记录失败，可能触发锁定
        recordLoginFail($db, $ip, $u);
        ret(400, '账号或密码错误');
    }

    // ---- 3) 登录成功：清计数、更新哈希（若算法升级）----
    $db->exec('DELETE FROM login_attempts WHERE ip=? AND username=?', [$ip, $u]);
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $db->exec('UPDATE users SET password_hash=? WHERE id=?',
            [password_hash($pw, PASSWORD_DEFAULT), $row['id']]);
    }
    $db->exec('UPDATE users SET last_login_at=NOW() WHERE id=?', [$row['id']]);

    session_regenerate_id(true);
    $_SESSION['uid']         = (int)$row['id'];
    $_SESSION['username']    = $row['username'];
    $_SESSION['role']        = $row['role'];
    $_SESSION['permissions'] = normalizePerms($row['permissions'] ?? '');
    $csrf = issueCsrfToken();

    ret(200, '登录成功', array_merge(currentUser(), ['csrf' => $csrf]));
}

if ($act === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $c = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $c['path'], $c['domain'] ?? '', !empty($c['secure']), !empty($c['httponly']));
    }
    session_destroy();
    ret(200, '已退出登录');
}

if ($act === 'me') {
    $u = currentUser();
    if (!$u) ret(401, '未登录');
    // 未登录不会走到这里；已登录则确保有 CSRF token（页面刷新后能从这里补拿）
    if (empty($_SESSION['csrf'])) issueCsrfToken();
    ret(200, 'ok', array_merge($u, ['csrf' => $_SESSION['csrf']]));
}

if ($act === 'change_password') {
    requireLogin();
    $p   = req();
    $old = (string)($p['old_password'] ?? '');
    $new = (string)($p['new_password'] ?? '');
    if ($old === '' || $new === '') ret(400, '请填写原密码与新密码');
    if (strlen($new) < 6)           ret(400, '新密码长度至少 6 位');
    if ($old === $new)              ret(400, '新密码不能与原密码相同');

    $row = $db->queryOne('SELECT password_hash FROM users WHERE id=?', [$_SESSION['uid']]);
    if (!$row || !password_verify($old, $row['password_hash'])) {
        ret(400, '原密码不正确');
    }
    $db->exec('UPDATE users SET password_hash=? WHERE id=?',
        [password_hash($new, PASSWORD_DEFAULT), $_SESSION['uid']]);
    ret(200, '密码已修改，请重新登录');
}

ret(400, '未知操作');

// ===== 内部工具：记录一次登录失败 =====
function recordLoginFail(DB $db, string $ip, string $u): void {
    $row = $db->queryOne(
        'SELECT fail_count FROM login_attempts WHERE ip=? AND username=?',
        [$ip, $u]
    );
    if (!$row) {
        $db->exec('INSERT INTO login_attempts(ip,username,fail_count) VALUES(?,?,1)', [$ip, $u]);
        return;
    }
    $next = (int)$row['fail_count'] + 1;
    if ($next >= LOGIN_MAX_FAILS) {
        $db->exec(
            'UPDATE login_attempts SET fail_count=?, locked_until=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE ip=? AND username=?',
            [$next, LOGIN_LOCK_MINUTE, $ip, $u]
        );
    } else {
        $db->exec('UPDATE login_attempts SET fail_count=? WHERE ip=? AND username=?',
            [$next, $ip, $u]);
    }
}
