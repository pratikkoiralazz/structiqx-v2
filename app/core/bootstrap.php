<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $config = require dirname(__DIR__) . '/config/database.php';
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        die('Invalid or expired form token.');
    }
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

function base_url(string $path = ''): string {
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $base = rtrim($script, '/');
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) redirect(base_url('login'));
}

function organization_id(): int {
    return (int)($_SESSION['organization_id'] ?? 0);
}

function require_role(array $roles): void {
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $roles, true) && $role !== 'organization_admin') {
        http_response_code(403);
        die('You do not have permission to perform this action.');
    }
}

function org_row(string $table, int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM `$table` WHERE id = ? AND organization_id = ?");
    $stmt->execute([$id, organization_id()]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function log_activity(string $action, string $entityType, ?int $entityId, string $description): void {
    $stmt = db()->prepare('INSERT INTO activity_logs (organization_id,user_id,action,entity_type,entity_id,description,ip_address) VALUES (?,?,?,?,?,?,?)');
    $user = current_user();
    $stmt->execute([organization_id(), $user['id'] ?? null, $action, $entityType, $entityId, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
}
