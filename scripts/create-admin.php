<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/core/bootstrap.php';

$name=trim((string)getenv('STRUCTIQX_ADMIN_NAME'));
$email=strtolower(trim((string)getenv('STRUCTIQX_ADMIN_EMAIL')));
$password=(string)getenv('STRUCTIQX_ADMIN_PASSWORD');
$role=(string)(getenv('STRUCTIQX_ADMIN_ROLE')?:'super_admin');
if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12 || !in_array($role,['super_admin','content_manager','support_manager'],true)) {
    fwrite(STDERR,"Set STRUCTIQX_ADMIN_NAME, STRUCTIQX_ADMIN_EMAIL, and STRUCTIQX_ADMIN_PASSWORD (12+ characters); optional STRUCTIQX_ADMIN_ROLE.\n");
    exit(2);
}
$stmt=db()->prepare('SELECT id FROM admin_users WHERE email=?');
$stmt->execute([$email]);
if ($stmt->fetchColumn()) {
    fwrite(STDERR,"An administrator with that email already exists; no changes were made.\n");
    exit(1);
}
$stmt=db()->prepare('INSERT INTO admin_users (name,email,password_hash,role,is_active) VALUES (?,?,?,?,1)');
$stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
fwrite(STDOUT,"Administrator created: {$email} ({$role})\n");
