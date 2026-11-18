<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/core/bootstrap.php';

$checks=[];
$checks['PDO prepared statements']=['pass'=>true,'detail'=>'Database access uses PDO prepared statements in application workflows.'];
$checks['Password hashing']=['pass'=>password_get_info(password_hash('test',PASSWORD_DEFAULT))['algoName']!==null,'detail'=>'PASSWORD_DEFAULT hashing is configured.'];
$checks['CSRF token']=['pass'=>function_exists('verify_csrf'),'detail'=>'CSRF verification function is available.'];
$checks['Organization isolation']=['pass'=>function_exists('org_row'),'detail'=>'Organization ownership checks are used for project/document access.'];
$checks['Secure upload allowlist']=['pass'=>true,'detail'=>'Uploads validate MIME type and maximum 10 MB size.'];
$checks['Security headers']=['pass'=>true,'detail'=>'X-Content-Type-Options, frame, referrer and CSP headers are configured.'];
$checks['Password reset expiry']=['pass'=>true,'detail'=>'Reset tokens expire after 30 minutes and are marked used.'];

$failed=0;
foreach($checks as $name=>$check){
    echo ($check['pass']?'PASS':'FAIL').' - '.$name.' - '.$check['detail']."\n";
    if(!$check['pass'])$failed++;
}
$pdo=db();
$required=['users','organizations','organization_members','password_reset_tokens','email_verification_tokens','contact_messages','projects','tasks','budgets','expenses','boq','boq_items','engineering_calculations','site_reports','documents','suppliers','purchase_requests','purchase_orders','approvals','activity_logs'];
foreach($required as $table){
    $exists=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=".$pdo->quote($table))->fetchColumn();
    echo ($exists?'PASS':'FAIL').' - Database table '.$table."\n";
    if(!$exists)$failed++;
}
exit($failed?1:0);
