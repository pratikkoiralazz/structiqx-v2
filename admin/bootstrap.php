<?php
declare(strict_types=1);

if (!defined('STRUCTIQX_ADMIN_ENTRY')) {
    http_response_code(404);
    exit;
}
$adminCookiePath=rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/admin/index.php'))),'/');
session_name('STRUCTIQX_ADMIN');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $adminCookiePath===''?'/':$adminCookiePath,
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
require dirname(__DIR__) . '/app/core/bootstrap.php';

function admin_url(string $path = ''): string {
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/' . ltrim($path, '/');
}

function admin_user(): ?array {
    return $_SESSION['admin_user'] ?? null;
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function admin_audit(string $action, ?string $type = null, ?int $id = null, ?array $before = null, ?array $after = null): void {
    $stmt = db()->prepare('INSERT INTO admin_activity_logs (admin_user_id,action,target_type,target_id,previous_value,new_value,ip_address) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([
        admin_user()['id'] ?? null,
        $action,
        $type,
        $id,
        $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
        $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function admin_can(string $permission): bool {
    $user = admin_user();
    if (!$user) return false;
    if ($user['role'] === 'super_admin') return true;
    $stmt = db()->prepare('SELECT 1 FROM admin_role_permissions WHERE role=? AND permission=?');
    $stmt->execute([$user['role'], $permission]);
    return (bool)$stmt->fetchColumn();
}

function admin_require(string $permission): void {
    if (!admin_user()) redirect(admin_url('login'));
    $last = (int)($_SESSION['admin_last_activity'] ?? 0);
    if ($last && time() - $last > 1800) {
        unset($_SESSION['admin_user'], $_SESSION['admin_last_activity']);
        session_regenerate_id(true);
        redirect(admin_url('login?expired=1'));
    }
    $stmt=db()->prepare('SELECT role,is_active FROM admin_users WHERE id=?');
    $stmt->execute([(int)admin_user()['id']]);
    $current=$stmt->fetch();
    if (!$current || !(int)$current['is_active']) {
        unset($_SESSION['admin_user'], $_SESSION['admin_last_activity']);
        session_regenerate_id(true);
        redirect(admin_url('login'));
    }
    $_SESSION['admin_user']['role']=$current['role'];
    $_SESSION['admin_last_activity'] = time();
    if (!admin_can($permission)) {
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }
}

function admin_shell(string $title, string $content, string $active): never {
    $user = admin_user();
    $links = [
        ['dashboard', 'Overview', 'dashboard.read'],
        ['organizations', 'Organizations', 'organizations.read'],
        ['users', 'Users', 'users.read'],
        ['messages', 'Contact messages', 'messages.manage'],
        ['cms', 'Website CMS', 'cms.manage'],
        ['subscriptions', 'Plans & subscriptions', 'subscriptions.manage'],
        ['settings', 'Settings', 'settings.manage'],
        ['audit', 'Activity log', 'audit.read'],
    ];
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?> | STRUCTIQX Admin</title><link rel="stylesheet" href="<?= e(admin_url('admin.css')) ?>"></head><body><aside class="sidebar"><a class="brand" href="<?= e(admin_url()) ?>"><span>SQ</span> STRUCTIQX <small>PLATFORM ADMIN</small></a><nav><?php foreach($links as [$path,$label,$permission]): if(admin_can($permission)): ?><a class="<?= $active===$path?'active':'' ?>" href="<?= e(admin_url($path)) ?>"><?= e($label) ?></a><?php endif; endforeach; ?></nav><div class="sidebar-foot"><span><?= e($user['name']) ?> · <?= e(str_replace('_',' ',$user['role'])) ?></span><form method="post" action="<?= e(admin_url('logout')) ?>"><?= csrf_field() ?><button class="link-button">Sign out</button></form></div></aside><main class="main"><header class="topbar"><div><p class="eyebrow">STRUCTIQX PLATFORM</p><h1><?= e($title) ?></h1></div><a class="public-link" href="<?= e(str_replace('/admin','',admin_url())) ?>" target="_blank" rel="noopener">View public website ↗</a></header><?php foreach(consume_flash() as $notice): ?><div class="notice <?= $notice['type']==='error'?'error':'' ?>"><?= e($notice['message']) ?></div><?php endforeach; ?><?= $content ?></main></body></html><?php
    exit;
}

function admin_need_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
    verify_csrf();
}

function admin_status(string $status): string {
    return '<span class="badge badge-' . e(strtolower($status)) . '">' . e(str_replace('_',' ',$status)) . '</span>';
}
