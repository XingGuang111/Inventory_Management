<?php
// 会话、鉴权、角色、权限、CSRF 工具
// 必须在 db.php 之后加载（依赖 ret()）。
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 所有合法权限码；供前后端校验与 UI 渲染
const ALL_PERMS = ['goods', 'stock_in', 'stock_out', 'check'];

// 返回当前登录用户（未登录返回 null）
function currentUser() {
    if (empty($_SESSION['uid'])) return null;
    return [
        'id'          => (int)$_SESSION['uid'],
        'username'    => $_SESSION['username'] ?? '',
        'role'        => $_SESSION['role'] ?? '',
        'permissions' => $_SESSION['permissions'] ?? [],
    ];
}

// 未登录 → 401；登录后对 POST 校验 CSRF
function requireLogin() {
    if (empty($_SESSION['uid'])) {
        ret(401, '未登录或会话已过期');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tokenHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $tokenSess   = $_SESSION['csrf'] ?? '';
        if (!$tokenSess || !$tokenHeader || !hash_equals($tokenSess, $tokenHeader)) {
            ret(403, 'CSRF 校验失败，请刷新后重试');
        }
    }
}

// 仅按角色校验（用户管理等 admin 专属功能用这个）
function requireRole($role) {
    requireLogin();
    $need = is_array($role) ? $role : [$role];
    if (!in_array($_SESSION['role'] ?? '', $need, true)) {
        ret(403, '当前账号无权限执行此操作');
    }
}

// 按模块权限码校验：admin 一律放行；非 admin 需在 permissions 列表里
function requirePerm($perm) {
    requireLogin();
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') return;
    $perms = $_SESSION['permissions'] ?? [];
    if (!in_array($perm, $perms, true)) {
        ret(403, '当前账号无权限访问该模块');
    }
}

// 生成 CSRF token
function issueCsrfToken() {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

// 客户端 IP
function clientIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
    }
    return substr($ip, 0, 63);
}

// 规范化权限字符串（DB 里存逗号分隔）为数组，只保留合法权限码
function normalizePerms($raw): array {
    if (is_array($raw)) $arr = $raw;
    else                $arr = array_filter(array_map('trim', explode(',', (string)$raw)));
    return array_values(array_intersect(ALL_PERMS, $arr));
}
