<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/core/bootstrap.php';

try {
    $config = require dirname(__DIR__) . '/app/config/database.php';
    $server = new PDO("mysql:host={$config['host']};port={$config['port']};charset=utf8mb4", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec('CREATE DATABASE IF NOT EXISTS `structiqx` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = db();
    $schemaReady = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='structiqx' AND table_name='users'")->fetchColumn();
    if (!$schemaReady) {
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id BIGINT UNSIGNED NOT NULL,
          token CHAR(64) NOT NULL UNIQUE,
          expires_at DATETIME NOT NULL,
          used_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          INDEX idx_reset_expiry (token,expires_at)
        ) ENGINE=InnoDB");
    }

    $orgId = (int)$pdo->query("SELECT id FROM organizations WHERE slug='himalayan-structures' LIMIT 1")->fetchColumn();
    if (!$orgId) {
        $pdo->prepare("INSERT INTO organizations (name,slug,address,currency) VALUES (?,?,?,?)")->execute(['Himalayan Structures','himalayan-structures','Kathmandu, Nepal','NPR']);
        $orgId = (int)$pdo->lastInsertId();
    }
    $userId = (int)$pdo->query("SELECT id FROM users WHERE email='admin@structiqx.test' LIMIT 1")->fetchColumn();
    if (!$userId) {
        $hash = password_hash('Password123!', PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (name,email,password_hash,phone) VALUES (?,?,?,?)')->execute(['Demo Administrator','admin@structiqx.test',$hash,'9800000000']);
        $userId = (int)$pdo->lastInsertId();
    }
    $pdo->prepare("INSERT IGNORE INTO organization_members (organization_id,user_id,role) VALUES (?,?,'organization_admin')")->execute([$orgId,$userId]);

    $clientId = (int)$pdo->query("SELECT id FROM clients WHERE organization_id={$orgId} LIMIT 1")->fetchColumn();
    if (!$clientId) {
        $pdo->prepare("INSERT INTO clients (organization_id,name,company,email,phone) VALUES (?,?,?,?,?)")->execute([$orgId,'Ramesh Shrestha','Green Valley Developers','ramesh@example.com','9811111111']);
        $clientId = (int)$pdo->lastInsertId();
    }
    $projectId = (int)$pdo->query("SELECT id FROM projects WHERE organization_id={$orgId} LIMIT 1")->fetchColumn();
    if (!$projectId) {
        $sql = "INSERT INTO projects (organization_id,client_id,title,description,category,location,start_date,end_date,budget,progress,status,project_manager_id,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sql)->execute([$orgId,$clientId,'Baneshwor Commercial Complex','Six-storey commercial and office development.','Building','Baneshwor, Kathmandu','2026-01-15','2026-12-20',25000000,38,'active',$userId,$userId]);
        $projectId = (int)$pdo->lastInsertId();
    }
    if (!(int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE organization_id={$orgId}")->fetchColumn()) {
        $stmt = $pdo->prepare("INSERT INTO tasks (organization_id,project_id,title,description,assigned_to,priority,status,start_date,due_date,progress,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$orgId,$projectId,'Foundation excavation','Complete excavation and verify formation level.', $userId,'high','in_progress','2026-10-01','2026-11-25',65,$userId]);
    }
    if (!(int)$pdo->query("SELECT COUNT(*) FROM expenses WHERE organization_id={$orgId}")->fetchColumn()) {
        $stmt = $pdo->prepare("INSERT INTO expenses (organization_id,project_id,category,description,amount,expense_date,status,created_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$orgId,$projectId,'Labour','Foundation labour payroll',485000,'2026-11-10','approved',$userId]);
    }
    if (!(int)$pdo->query("SELECT COUNT(*) FROM suppliers WHERE organization_id={$orgId}")->fetchColumn()) {
        $pdo->prepare("INSERT INTO suppliers (organization_id,name,contact_person,phone,email) VALUES (?,?,?,?,?)")->execute([$orgId,'Kathmandu Building Supplies','Sita Gurung','9822222222','supply@example.com']);
    }
    echo '<h1>structiQX installed successfully</h1><p>Demo login: <b>admin@structiqx.test</b> / <b>Password123!</b></p><p><a href="./">Open application</a></p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Installation failed</h1><pre>' . e($e->getMessage()) . '</pre>';
}
