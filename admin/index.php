<?php
declare(strict_types=1);
define('STRUCTIQX_ADMIN_ENTRY', true);
require __DIR__ . '/bootstrap.php';

$route = trim((string)($_GET['r'] ?? 'dashboard'), '/');
$segments = $route === '' ? [] : explode('/', $route);
$section = $segments[0] ?? 'dashboard';

if ($section==='preview' && isset($segments[1])) {
    admin_require('cms.manage');
    $stmt=db()->prepare('SELECT * FROM cms_pages WHERE id=?');$stmt->execute([(int)$segments[1]]);$preview=$stmt->fetch();
    if(!$preview){http_response_code(404);exit('CMS page not found.');}
    $stmt=db()->prepare('SELECT * FROM cms_page_sections WHERE page_id=? ORDER BY sort_order,id');$stmt->execute([(int)$preview['id']]);
    $html='<main><section class="sq-page-hero"><div class="sq-container"><span class="sq-eyebrow">Preview · '.e($preview['status']).'</span><h1>'.e($preview['page_title']).'</h1></div></section>';
    foreach($stmt->fetchAll() as $item) {
        $html.='<section class="sq-section"><div class="sq-container"><h2>'.e($item['heading']).'</h2><p>'.nl2br(e($item['description'])).'</p>';
        $list=json_decode((string)$item['content_json'],true);
        if(is_array($list))foreach($list as $card)if(is_array($card))$html.='<article class="sq-card"><h3>'.e($card['heading']??'').'</h3><p>'.nl2br(e($card['description']??'')).'</p></article>';
        $html.='</div></section>';
    }
    $html.='</main>';
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Preview | STRUCTIQX</title><link rel="stylesheet" href="<?= e(str_replace('/admin/','/',admin_url()).'public/assets/marketing.css') ?>"></head><body class="marketing"><?= $html ?><p style="padding:20px"><a href="<?= e(admin_url('cms/'.$preview['id'])) ?>">Back to editor</a></p></body></html><?php exit;
}

if ($section === 'login') {
    if (admin_user()) redirect(admin_url());
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $fingerprint = hash('sha256', strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $limiter = db()->prepare('SELECT COUNT(*) FROM admin_login_attempts WHERE identity_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE) AND successful=0');
        $limiter->execute([$fingerprint]);
        if ((int)$limiter->fetchColumn() >= 5) {
            $error = 'Too many sign-in attempts. Try again in 15 minutes.';
        } else {
            $stmt = db()->prepare('SELECT id,name,email,password_hash,role,is_active FROM admin_users WHERE email=? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $valid = $user && (int)$user['is_active'] === 1 && password_verify($password, $user['password_hash']);
            db()->prepare('INSERT INTO admin_login_attempts (identity_hash,attempted_at,successful) VALUES (?,NOW(),?)')->execute([$fingerprint,$valid?1:0]);
            db()->exec('DELETE FROM admin_login_attempts WHERE attempted_at<DATE_SUB(NOW(),INTERVAL 30 DAY)');
            if ($valid) {
                session_regenerate_id(true);
                $_SESSION['admin_user'] = ['id'=>(int)$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']];
                $_SESSION['admin_last_activity'] = time();
                db()->prepare('UPDATE admin_users SET last_login_at=NOW() WHERE id=?')->execute([(int)$user['id']]);
                admin_audit('admin.login','admin_user',(int)$user['id']);
                redirect(admin_url());
            }
            $error = 'Invalid email or password.';
        }
    }
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin sign in | STRUCTIQX</title><link rel="stylesheet" href="<?= e(admin_url('admin.css')) ?>"></head><body class="login-page"><main class="login-card"><a class="brand login-brand" href="<?= e(admin_url()) ?>"><span>SQ</span> STRUCTIQX</a><p class="eyebrow">PLATFORM ADMINISTRATION</p><h1>Sign in</h1><p class="muted">Use your administrator account.</p><?php if(isset($_GET['expired'])): ?><div class="notice error">Your admin session expired. Please sign in again.</div><?php endif; ?><?php if($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?><form method="post" class="stack"><?= csrf_field() ?><label>Email<input type="email" name="email" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="button primary">Sign in</button></form></main></body></html><?php exit;
}

if ($section === 'logout') {
    admin_need_post();
    if (admin_user()) admin_audit('admin.logout','admin_user',(int)admin_user()['id']);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    redirect(admin_url('login'));
}

$permissions = [
    'dashboard'=>'dashboard.read',
    'organizations'=>'organizations.read',
    'users'=>'users.read',
    'messages'=>'messages.manage',
    'cms'=>'cms.manage',
    'subscriptions'=>'subscriptions.manage',
    'settings'=>'settings.manage',
    'audit'=>'audit.read',
];
if (!isset($permissions[$section])) {
    http_response_code(404);
    exit('Page not found.');
}
admin_require($permissions[$section]);
if ($_SERVER['REQUEST_METHOD']==='POST' && $section==='organizations') admin_require('organizations.manage');
if ($_SERVER['REQUEST_METHOD']==='POST' && $section==='users') admin_require('users.manage');
if ($_SERVER['REQUEST_METHOD'] === 'POST') admin_need_post();

if ($section === 'organizations' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id=(int)($_POST['organization_id']??0);
    $status=(string)($_POST['status']??'');
    $reason=trim((string)($_POST['reason']??''));
    if (!in_array($status,['approved','rejected','suspended'],true) || $reason==='') {
        flash('error','Choose a valid decision and provide a reason.');
        redirect(admin_url('organizations'));
    }
    $stmt=db()->prepare('SELECT id,name,approval_status FROM organizations WHERE id=?');
    $stmt->execute([$id]);$org=$stmt->fetch();
    if (!$org) {flash('error','Organization not found.');redirect(admin_url('organizations'));}
    if ($status==='suspended' && $org['approval_status']!=='approved') {
        flash('error','Only an approved organization can be suspended.');
        redirect(admin_url('organizations'));
    }
    $before=['approval_status'=>$org['approval_status']];
    db()->beginTransaction();
    db()->prepare('UPDATE organizations SET approval_status=?,approval_reason=?,approved_at=?,approved_by=? WHERE id=?')
        ->execute([$status,$reason,$status==='approved'?date('Y-m-d H:i:s'):null,$status==='approved'?(int)admin_user()['id']:null,$id]);
    db()->prepare('INSERT INTO organization_approval_logs (organization_id,admin_user_id,previous_status,new_status,reason) VALUES (?,?,?,?,?)')
        ->execute([$id,(int)admin_user()['id'],$org['approval_status'],$status,$reason]);
    admin_audit('organization.status_changed','organization',$id,$before,['approval_status'=>$status,'reason'=>$reason]);
    db()->commit();
    flash('success','Organization status updated.');
    redirect(admin_url('organizations'));
}

if ($section === 'users' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id=(int)($_POST['user_id']??0);
    $reason=trim((string)($_POST['reason']??''));
    $stmt=db()->prepare('SELECT id,name,email,is_active FROM users WHERE id=?');
    $stmt->execute([$id]);$target=$stmt->fetch();
    if (!$target || $reason==='') {flash('error','User not found or a reason was not provided.');redirect(admin_url('users'));}
    $newStatus=(int)!$target['is_active'];
    if (!$newStatus) {
        $guard=db()->prepare("SELECT COUNT(*) FROM organization_members om WHERE om.user_id=? AND om.role='organization_admin' AND om.status='active'");
        $guard->execute([$id]);
        if ((int)$guard->fetchColumn()>0) {
            flash('error','Organization administrators cannot be disabled here; transfer organization ownership first.');
            redirect(admin_url('users'));
        }
    }
    db()->prepare('UPDATE users SET is_active=? WHERE id=?')->execute([$newStatus,$id]);
    admin_audit('user.access_changed','user',$id,['is_active'=>(int)$target['is_active']],['is_active'=>$newStatus,'reason'=>$reason]);
    flash('success','Customer user access updated.');
    redirect(admin_url('users'));
}

if ($section === 'messages' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id=(int)($_POST['message_id']??0);
    $action=(string)($_POST['action']??'');
    $stmt=db()->prepare('SELECT id,status FROM contact_messages WHERE id=?');
    $stmt->execute([$id]);$message=$stmt->fetch();
    if (!$message) {flash('error','Contact message not found.');redirect(admin_url('messages'));}
    if ($action==='note') {
        $note=trim((string)($_POST['note']??''));
        if ($note==='') {flash('error','Internal note cannot be empty.');redirect(admin_url('messages'));}
        db()->prepare('INSERT INTO contact_message_notes (message_id,admin_user_id,note) VALUES (?,?,?)')->execute([$id,(int)admin_user()['id'],$note]);
        admin_audit('contact.internal_note_added','contact_message',$id);
    } elseif (in_array($action,['unread','pending','resolved','archived'],true)) {
        db()->prepare('UPDATE contact_messages SET status=? WHERE id=?')->execute([$action,$id]);
        admin_audit('contact.status_changed','contact_message',$id,['status'=>$message['status']],['status'=>$action]);
    } else {
        flash('error','Invalid message action.');
        redirect(admin_url('messages'));
    }
    flash('success','Message updated.');
    redirect(admin_url('messages'));
}

if ($section === 'cms' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action=(string)($_POST['action']??'save_page');
    if ($action==='media_upload') {
        $file=$_FILES['image']??null;
        if (!$file || $file['error']!==UPLOAD_ERR_OK || $file['size']>4*1024*1024) {
            flash('error','Choose an image up to 4 MB.');
            redirect(admin_url('cms'));
        }
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($extensions[$mime])) {flash('error','Only JPEG, PNG, and WebP images are accepted.');redirect(admin_url('cms'));}
        $name=bin2hex(random_bytes(20)).'.'.$extensions[$mime];
        $dir=dirname(__DIR__).'/storage/cms_media';
        if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to prepare CMS media storage.');
        }
        if (!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('CMS image could not be stored.');
        db()->prepare('INSERT INTO cms_media (original_name,stored_name,mime_type,file_size,uploaded_by) VALUES (?,?,?,?,?)')
            ->execute([basename((string)$file['name']),$name,$mime,(int)$file['size'],(int)admin_user()['id']]);
        admin_audit('cms.media_uploaded','cms_media',(int)db()->lastInsertId());
        flash('success','Image uploaded. Use this path in a section image_path: /media/'.$name);
        redirect(admin_url('cms'));
    }
    $id=(int)($_POST['page_id']??0);
    $slug=strtolower(trim((string)($_POST['slug']??'')));
    $title=trim((string)($_POST['page_title']??''));
    $status=(string)($_POST['status']??'draft');
    $json=(string)($_POST['sections_json']??'[]');
    $sections=json_decode($json,true);
    $reservedSlugs=['admin','login','register','logout','forgot','forgot-password','reset','reset-password','install','dashboard','projects','project','tasks','schedule','budgets','expenses','boq','calculations','site','documents','procurement','approvals','portal','reports','settings','profile','clients','team','download','export','media','accept-invitation','verify-email'];
    if (!preg_match('/^[a-z0-9-]{1,80}$/',$slug) || in_array($slug,$reservedSlugs,true) || $title==='' || strlen($title)>180 || !in_array($status,['draft','published'],true) || !is_array($sections) || count($sections)>30 || strlen($json)>100000) {
        flash('error','Check the page fields and valid JSON section list (maximum 30 sections).');
        redirect(admin_url('cms'.($id?'/'.$id:'')));
    }
    foreach ($sections as $sectionData) {
        if (!is_array($sectionData) || !preg_match('/^[a-z0-9_-]{1,80}$/',(string)($sectionData['key']??''))) {
            flash('error','Each section must be an object with a valid key.');
            redirect(admin_url('cms'.($id?'/'.$id:'')));
        }
        foreach(['heading','description','cta_text','cta_url','image_path'] as $field) {
            if (isset($sectionData[$field]) && !is_string($sectionData[$field])) {
                flash('error','Section text, links and image paths must be strings.');
                redirect(admin_url('cms'.($id?'/'.$id:'')));
            }
        }
        $image=(string)($sectionData['image_path']??'');
        if ($image!=='' && !preg_match('#^/?media/[a-zA-Z0-9._-]+$#',$image)) {
            flash('error','Section images must use a CMS media path.');
            redirect(admin_url('cms'.($id?'/'.$id:'')));
        }
        $cta=trim((string)($sectionData['cta_url']??''));
        $validCta=$cta==='' || (preg_match('#^/?[a-zA-Z0-9/_-]+$#',$cta)===1) || (str_starts_with($cta,'https://') && filter_var($cta,FILTER_VALIDATE_URL));
        if (!$validCta) {
            flash('error','Section links must be HTTPS URLs or local application paths.');
            redirect(admin_url('cms'.($id?'/'.$id:'')));
        }
        if (isset($sectionData['items'])) {
            if (!is_array($sectionData['items']) || count($sectionData['items'])>50) {
                flash('error','Section items must be a JSON array with no more than 50 entries.');
                redirect(admin_url('cms'.($id?'/'.$id:'')));
            }
            foreach($sectionData['items'] as $entry) {
                if (!is_string($entry) && (!is_array($entry) || (isset($entry['heading'])&&!is_string($entry['heading'])) || (isset($entry['description'])&&!is_string($entry['description'])))) {
                    flash('error','Each section item must be text or an object with string heading and description fields.');
                    redirect(admin_url('cms'.($id?'/'.$id:'')));
                }
            }
        }
    }
    $before=null;
    if ($id) {
        $stmt=db()->prepare('SELECT * FROM cms_pages WHERE id=?');$stmt->execute([$id]);$before=$stmt->fetch();
        if (!$before) {flash('error','CMS page not found.');redirect(admin_url('cms'));}
    }
    db()->beginTransaction();
    if ($id) {
        db()->prepare('UPDATE cms_pages SET slug=?,page_title=?,meta_title=?,meta_description=?,status=?,is_visible=?,updated_by=?,published_at=? WHERE id=?')
            ->execute([$slug,$title,trim((string)($_POST['meta_title']??'')),trim((string)($_POST['meta_description']??'')),$status,isset($_POST['is_visible'])?1:0,(int)admin_user()['id'],$status==='published'?date('Y-m-d H:i:s'):null,$id]);
        db()->prepare('DELETE FROM cms_page_sections WHERE page_id=?')->execute([$id]);
    } else {
        db()->prepare('INSERT INTO cms_pages (slug,page_title,meta_title,meta_description,status,is_visible,updated_by,published_at) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$slug,$title,trim((string)($_POST['meta_title']??'')),trim((string)($_POST['meta_description']??'')),$status,isset($_POST['is_visible'])?1:0,(int)admin_user()['id'],$status==='published'?date('Y-m-d H:i:s'):null]);
        $id=(int)db()->lastInsertId();
    }
    $insert=db()->prepare('INSERT INTO cms_page_sections (page_id,section_key,heading,description,cta_text,cta_url,image_path,content_json,sort_order,is_visible) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($sections as $i=>$item) {
        $insert->execute([$id,(string)$item['key'],(string)($item['heading']??''),(string)($item['description']??''),(string)($item['cta_text']??''),(string)($item['cta_url']??''),(string)($item['image_path']??''),json_encode($item['items']??[],JSON_THROW_ON_ERROR),$i,isset($item['is_visible'])?(int)(bool)$item['is_visible']:1]);
    }
    admin_audit('cms.page_saved','cms_page',$id,$before?['status'=>$before['status'],'slug'=>$before['slug']]:null,['status'=>$status,'slug'=>$slug]);
    db()->commit();
    flash('success','CMS page saved.');
    redirect(admin_url('cms/'.$id));
}

if ($section === 'subscriptions' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action=(string)($_POST['action']??'');
    if ($action==='save_plan') {
        $id=(int)($_POST['plan_id']??0);
        $name=trim((string)($_POST['name']??''));
        $slug=strtolower(trim((string)($_POST['slug']??'')));
        $price=max(0,(float)($_POST['monthly_price']??0));
        $annual=max(0,(float)($_POST['annual_price']??0));
        if ($name==='' || !preg_match('/^[a-z0-9-]{1,100}$/',$slug)) {flash('error','Provide a plan name and valid URL slug.');redirect(admin_url('subscriptions'));}
        if ($id) {
            db()->prepare('UPDATE subscription_plans SET name=?,slug=?,description=?,monthly_price=?,annual_price=?,currency=?,organization_limit=?,user_limit=?,is_enabled=? WHERE id=?')
                ->execute([$name,$slug,trim((string)($_POST['description']??'')),$price,$annual,strtoupper(trim((string)($_POST['currency']??'NPR'))),$_POST['organization_limit']!==''?(int)$_POST['organization_limit']:null,$_POST['user_limit']!==''?(int)$_POST['user_limit']:null,isset($_POST['is_enabled'])?1:0,$id]);
        } else {
            db()->prepare('INSERT INTO subscription_plans (name,slug,description,monthly_price,annual_price,currency,organization_limit,user_limit,is_enabled) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$name,$slug,trim((string)($_POST['description']??'')),$price,$annual,strtoupper(trim((string)($_POST['currency']??'NPR'))),$_POST['organization_limit']!==''?(int)$_POST['organization_limit']:null,$_POST['user_limit']!==''?(int)$_POST['user_limit']:null,isset($_POST['is_enabled'])?1:0]);
            $id=(int)db()->lastInsertId();
        }
        admin_audit('subscription.plan_saved','subscription_plan',$id);
        flash('success','Plan saved.');
    } elseif ($action==='assign') {
        $orgId=(int)($_POST['organization_id']??0);$planId=(int)($_POST['plan_id']??0);
        $reason=trim((string)($_POST['reason']??''));
        if ($reason==='' || !in_array($_POST['status']??'', ['trialing','active','past_due','cancelled','expired'],true)) {flash('error','Provide a valid subscription status and reason.');redirect(admin_url('subscriptions'));}
        $verify=(int)($_POST['payment_verified']??0);
        $reference=trim((string)($_POST['payment_reference']??''));
        if ($verify && $reference==='') {flash('error','A payment reference is required before marking payment verified.');redirect(admin_url('subscriptions'));}
        $endsAt=trim((string)($_POST['ends_at']??''));
        if($endsAt!=='') $endsAt=str_replace('T',' ',$endsAt).(strlen($endsAt)===16?':00':'');
        db()->prepare('INSERT INTO organization_subscriptions (organization_id,plan_id,status,billing_cycle,payment_verified,payment_reference,starts_at,ends_at,changed_by,change_reason) VALUES (?,?,?,?,?,?,NOW(),?,?,?)')
            ->execute([$orgId,$planId,$_POST['status'],$_POST['billing_cycle'], $verify,$reference?:null,$endsAt?:null,(int)admin_user()['id'],$reason]);
        admin_audit('subscription.assigned','organization_subscription',(int)db()->lastInsertId(),null,['organization_id'=>$orgId,'plan_id'=>$planId,'status'=>$_POST['status'],'payment_verified'=>$verify,'reason'=>$reason]);
        flash('success','Subscription record created. Payment status is shown separately from subscription status.');
    } elseif ($action==='subscription_status') {
        $id=(int)($_POST['subscription_id']??0);$reason=trim((string)($_POST['reason']??''));
        $status=(string)($_POST['status']??'');
        $verify=(int)($_POST['payment_verified']??0);$reference=trim((string)($_POST['payment_reference']??''));
        $stmt=db()->prepare('SELECT status,payment_verified FROM organization_subscriptions WHERE id=?');$stmt->execute([$id]);$before=$stmt->fetch();
        if (!$before || $reason==='' || !in_array($status,['trialing','active','past_due','cancelled','expired'],true) || ($verify && $reference==='')) {flash('error','Provide a valid status, reason and payment reference when confirming payment.');redirect(admin_url('subscriptions'));}
        db()->prepare('UPDATE organization_subscriptions SET status=?,payment_verified=?,payment_reference=?,changed_by=?,change_reason=? WHERE id=?')
            ->execute([$status,$verify,$reference?:null,(int)admin_user()['id'],$reason,$id]);
        admin_audit('subscription.updated','organization_subscription',$id,$before,['status'=>$status,'payment_verified'=>$verify,'payment_reference'=>$reference,'reason'=>$reason]);
        flash('success','Subscription record updated.');
    } else {flash('error','Invalid subscription action.');}
    redirect(admin_url('subscriptions'));
}

if ($section === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed=['brand_name','support_email','support_phone','default_currency','registration_enabled'];
    $before=[];
    $upsert=db()->prepare('INSERT INTO admin_settings (setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
    foreach ($allowed as $key) {
        $value=trim((string)($_POST[$key]??''));
        if ($key==='registration_enabled' && !in_array($value,['0','1'],true)) $value='0';
        if ($key==='support_email' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL)) {flash('error','Enter a valid support email.');redirect(admin_url('settings'));}
        $stmt=db()->prepare('SELECT setting_value FROM admin_settings WHERE setting_key=?');$stmt->execute([$key]);$before[$key]=$stmt->fetchColumn()?:'';
        $upsert->execute([$key,$value,(int)admin_user()['id']]);
    }
    admin_audit('settings.updated','settings',null,$before,array_intersect_key($_POST,array_flip($allowed)));
    flash('success','Platform settings saved.');
    redirect(admin_url('settings'));
}

$content='';
if ($section==='dashboard') {
    $counts=[
        'Organizations'=>(int)db()->query('SELECT COUNT(*) FROM organizations')->fetchColumn(),
        'Pending approval'=>(int)db()->query("SELECT COUNT(*) FROM organizations WHERE approval_status='pending'")->fetchColumn(),
        'Customer users'=>(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'Open messages'=>(int)db()->query("SELECT COUNT(*) FROM contact_messages WHERE status IN ('new','unread','pending')")->fetchColumn(),
        'Active subscriptions'=>(int)db()->query("SELECT COUNT(*) FROM organization_subscriptions WHERE status='active'")->fetchColumn(),
    ];
    $pending=db()->query("SELECT id,name,slug,created_at FROM organizations WHERE approval_status='pending' ORDER BY created_at ASC LIMIT 8")->fetchAll();
    $messages=db()->query("SELECT id,name,email,subject,status,created_at FROM contact_messages WHERE status IN ('new','unread','pending') ORDER BY created_at DESC LIMIT 6")->fetchAll();
    ob_start(); ?><section class="cards"><?php foreach($counts as $label=>$value): ?><article class="metric"><span><?= e($label) ?></span><strong><?= number_format($value) ?></strong></article><?php endforeach; ?></section><section class="panel"><div class="panel-heading"><h2>Organizations awaiting review</h2><a href="<?= e(admin_url('organizations')) ?>">All organizations →</a></div><?php if(!$pending): ?><p class="muted">No pending organization approvals.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Organization</th><th>Submitted</th><th>Status</th></tr></thead><tbody><?php foreach($pending as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= e($row['created_at']) ?></td><td><?= admin_status('pending') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section><section class="panel"><div class="panel-heading"><h2>Recent contact messages</h2><a href="<?= e(admin_url('messages')) ?>">Inbox →</a></div><?php if(!$messages): ?><p class="muted">No open contact messages.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>From</th><th>Subject</th><th>Status</th><th>Received</th></tr></thead><tbody><?php foreach($messages as $row): ?><tr><td><?= e($row['name']) ?><small><?= e($row['email']) ?></small></td><td><?= e($row['subject']) ?></td><td><?= admin_status($row['status']) ?></td><td><?= e($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section><?php     $content=ob_get_clean();
} elseif ($section==='organizations') {
    $status=(string)($_GET['status']??'');$search=trim((string)($_GET['q']??''));
    $where=[];$params=[];
    if(in_array($status,['pending','approved','rejected','suspended'],true)){$where[]='o.approval_status=?';$params[]=$status;}
    if($search!==''){$where[]='(o.name LIKE ? OR o.slug LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';}
    $sql='SELECT o.id,o.name,o.slug,o.approval_status,o.approval_reason,o.created_at,(SELECT COUNT(*) FROM organization_members om WHERE om.organization_id=o.id) member_count,(SELECT COUNT(*) FROM projects p WHERE p.organization_id=o.id) project_count FROM organizations o'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY o.created_at DESC LIMIT 300';
    $stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
    ob_start(); ?><section class="panel"><form class="filters" method="get" action="<?= e(admin_url('organizations')) ?>"><input type="hidden" name="r" value="organizations"><input name="q" value="<?= e($search) ?>" placeholder="Search organization"><select name="status"><option value="">All statuses</option><?php foreach(['pending','approved','rejected','suspended'] as $s): ?><option value="<?= e($s) ?>" <?= $status===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select><button class="button">Filter</button></form><div class="table-wrap"><table><thead><tr><th>Organization</th><th>Members</th><th>Projects</th><th>Status</th><th>Created</th><th>Review / status action</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?= e($row['name']) ?></strong><small><?= e($row['slug']) ?></small><?php if($row['approval_reason']): ?><small>Last note: <?= e($row['approval_reason']) ?></small><?php endif; ?></td><td><?= (int)$row['member_count'] ?></td><td><?= (int)$row['project_count'] ?></td><td><?= admin_status($row['approval_status']) ?></td><td><?= e($row['created_at']) ?></td><td><?php if(admin_can('organizations.manage')): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="organization_id" value="<?= (int)$row['id'] ?>"><select name="status" required><option value="">Choose</option><?php if($row['approval_status']!=='approved'): ?><option value="approved">Approve / reactivate</option><?php endif; ?><?php if($row['approval_status']==='pending'): ?><option value="rejected">Reject</option><?php endif; ?><?php if($row['approval_status']==='approved'): ?><option value="suspended">Suspend</option><?php endif; ?></select><input name="reason" placeholder="Decision reason" required><button class="button primary">Save</button></form><?php else: ?><span class="muted">Read only</span><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="6" class="muted">No organizations found.</td></tr><?php endif; ?></tbody></table></div></section><?php $content=ob_get_clean();
} elseif ($section==='users') {
    $search=trim((string)($_GET['q']??''));
    $stmt=db()->prepare('SELECT u.id,u.name,u.email,u.is_active,u.created_at,GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR \', \') organizations FROM users u LEFT JOIN organization_members om ON om.user_id=u.id LEFT JOIN organizations o ON o.id=om.organization_id WHERE (?=\'\' OR u.name LIKE ? OR u.email LIKE ?) GROUP BY u.id ORDER BY u.created_at DESC LIMIT 300');
    $stmt->execute([$search,'%'.$search.'%','%'.$search.'%']);$rows=$stmt->fetchAll();
    ob_start(); ?><section class="panel"><form class="filters"><input name="q" value="<?= e($search) ?>" placeholder="Search customer users"><button class="button">Search</button></form><p class="muted">Customer users only. Passwords are never displayed. Organization administrators must transfer ownership before their access can be disabled.</p><div class="table-wrap"><table><thead><tr><th>User</th><th>Organization(s)</th><th>Created</th><th>Access</th><th>Moderation</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['name']) ?><small><?= e($row['email']) ?></small></td><td><?= e($row['organizations']?:'—') ?></td><td><?= e($row['created_at']) ?></td><td><?= admin_status((int)$row['is_active']?'active':'disabled') ?></td><td><?php if(admin_can('users.manage')): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><input name="reason" placeholder="Reason required" required><button class="button"><?= (int)$row['is_active']?'Disable':'Reactivate' ?></button></form><?php else: ?><span class="muted">Read only</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php $content=ob_get_clean();
} elseif ($section==='messages') {
    $status=(string)($_GET['status']??'');$q=trim((string)($_GET['q']??''));
    $validStatuses=['unread','new','pending','resolved','archived','read','closed'];
    $where=[];$params=[];
    if(in_array($status,$validStatuses,true)){$where[]='m.status=?';$params[]=$status;}
    if($q!==''){$where[]='(m.name LIKE ? OR m.email LIKE ? OR m.subject LIKE ? OR m.message LIKE ?)';foreach(range(1,4) as $_)$params[]='%'.$q.'%';}
    $stmt=db()->prepare('SELECT m.* FROM contact_messages m'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY m.created_at DESC LIMIT 300');$stmt->execute($params);$rows=$stmt->fetchAll();
    ob_start(); ?><section class="panel"><form class="filters"><input name="q" value="<?= e($q) ?>" placeholder="Search messages"><select name="status"><option value="">All statuses</option><?php foreach($validStatuses as $s): ?><option <?= $status===$s?'selected':'' ?> value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select><button class="button">Filter</button></form><?php foreach($rows as $row): $notes=db()->prepare('SELECT n.note,n.created_at,a.name admin_name FROM contact_message_notes n JOIN admin_users a ON a.id=n.admin_user_id WHERE n.message_id=? ORDER BY n.created_at DESC');$notes->execute([(int)$row['id']]); ?><article class="message"><div class="panel-heading"><div><h2><?= e($row['subject']?:'General enquiry') ?></h2><p class="muted"><?= e($row['name']) ?> · <?= e($row['email']) ?> · <?= e($row['created_at']) ?></p></div><?= admin_status($row['status']) ?></div><p><?= nl2br(e($row['message'])) ?></p><div class="message-actions"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$row['id'] ?>"><select name="action"><?php foreach(['unread','pending','resolved','archived'] as $s): ?><option value="<?= e($s) ?>" <?= $row['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select><button class="button">Update status</button></form><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="note"><input name="note" placeholder="Add private internal note" required><button class="button">Add note</button></form></div><?php foreach($notes->fetchAll() as $note): ?><p class="internal-note"><strong>Internal note · <?= e($note['admin_name']) ?> · <?= e($note['created_at']) ?></strong><br><?= nl2br(e($note['note'])) ?></p><?php endforeach; ?></article><?php endforeach; if(!$rows): ?><p class="muted">No messages match these filters.</p><?php endif; ?></section><?php $content=ob_get_clean();
} elseif ($section==='cms') {
    $id=(int)($segments[1]??0);
    if($id){$stmt=db()->prepare('SELECT * FROM cms_pages WHERE id=?');$stmt->execute([$id]);$page=$stmt->fetch();if(!$page){http_response_code(404);exit('CMS page not found.');}$stmt=db()->prepare('SELECT section_key,heading,description,cta_text,cta_url,image_path,content_json,is_visible FROM cms_page_sections WHERE page_id=? ORDER BY sort_order,id');$stmt->execute([$id]);$sections=[];foreach($stmt->fetchAll() as $s){$items=json_decode((string)$s['content_json'],true);$sections[]=['key'=>$s['section_key'],'heading'=>$s['heading'],'description'=>$s['description'],'cta_text'=>$s['cta_text'],'cta_url'=>$s['cta_url'],'image_path'=>$s['image_path'],'is_visible'=>(bool)$s['is_visible'],'items'=>$items?:[]];}
        $sectionJson=json_encode($sections,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    } else {$page=['id'=>0,'slug'=>'','page_title'=>'','meta_title'=>'','meta_description'=>'','status'=>'draft','is_visible'=>1];$sectionJson="[\n  {\"key\":\"hero\",\"heading\":\"Page heading\",\"description\":\"Page introduction\",\"cta_text\":\"Get started\",\"cta_url\":\"/register\",\"items\":[]}\n]";}
    ob_start(); ?><section class="panel"><div class="panel-heading"><h2><?= $id?'Edit page':'Create page' ?></h2><div><?php if($id): ?><a href="<?= e(admin_url('preview/'.$id)) ?>" target="_blank" rel="noopener">Preview ↗</a> · <?php endif; ?><a href="<?= e(admin_url('cms')) ?>">All CMS pages</a></div></div><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>"><label>URL slug<input name="slug" value="<?= e($page['slug']) ?>" placeholder="features" required></label><label>Page title<input name="page_title" value="<?= e($page['page_title']) ?>" required></label><label>Meta title<input name="meta_title" maxlength="180" value="<?= e($page['meta_title']) ?>"></label><label>Meta description<input name="meta_description" maxlength="320" value="<?= e($page['meta_description']) ?>"></label><label>Status<select name="status"><option value="draft" <?= $page['status']==='draft'?'selected':'' ?>>Draft</option><option value="published" <?= $page['status']==='published'?'selected':'' ?>>Published</option></select></label><label class="check-label"><input type="checkbox" name="is_visible" value="1" <?= $page['is_visible']?'checked':'' ?>> Visible on public site</label><label class="wide">Sections JSON<textarea name="sections_json" rows="18" spellcheck="false" required><?= e($sectionJson) ?></textarea><small>Use an array of objects: key, heading, description, cta_text, cta_url, image_path, is_visible and items. Each item may have heading and description. Text is escaped on the public site.</small></label><div class="wide"><button class="button primary">Save page</button></div></form></section><section class="panel"><h2>Upload CMS image</h2><form method="post" enctype="multipart/form-data" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="media_upload"><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required><button class="button">Upload image (4 MB max)</button></form><p class="muted">Uploaded files are verified by MIME signature and stored outside the public directory. Use the resulting /media/&lt;filename&gt; path as image_path.</p></section><?php $content=ob_get_clean();
    if(!$id){$stmt=db()->query('SELECT id,slug,page_title,status,is_visible,updated_at FROM cms_pages ORDER BY slug');$pages=$stmt->fetchAll();ob_start(); ?><section class="panel"><div class="panel-heading"><h2>Website pages</h2><a class="button primary" href="<?= e(admin_url('cms/new')) ?>">Create page</a></div><div class="table-wrap"><table><thead><tr><th>Page</th><th>Status</th><th>Visible</th><th>Last updated</th><th></th></tr></thead><tbody><?php foreach($pages as $item): ?><tr><td><?= e($item['page_title']) ?><small>/<?= e($item['slug']) ?></small></td><td><?= admin_status($item['status']) ?></td><td><?= (int)$item['is_visible']?'Yes':'No' ?></td><td><?= e($item['updated_at']) ?></td><td><a href="<?= e(admin_url('cms/'.$item['id'])) ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></section><?php $content=ob_get_clean().$content;}
} elseif ($section==='subscriptions') {
    $plans=db()->query('SELECT * FROM subscription_plans ORDER BY monthly_price,id')->fetchAll();
    $orgs=db()->query('SELECT id,name FROM organizations ORDER BY name')->fetchAll();
    $subs=db()->query('SELECT s.*,o.name organization_name,p.name plan_name FROM organization_subscriptions s JOIN organizations o ON o.id=s.organization_id JOIN subscription_plans p ON p.id=s.plan_id ORDER BY s.created_at DESC LIMIT 200')->fetchAll();
    ob_start(); ?><section class="panel"><h2><?= isset($_GET['edit_plan'])?'Edit plan':'Create subscription plan' ?></h2><?php $editPlan=null;if(isset($_GET['edit_plan']))foreach($plans as $plan)if((int)$plan['id']===(int)$_GET['edit_plan'])$editPlan=$plan; ?><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save_plan"><input type="hidden" name="plan_id" value="<?= (int)($editPlan['id']??0) ?>"><label>Name<input name="name" value="<?= e($editPlan['name']??'') ?>" required></label><label>Slug<input name="slug" value="<?= e($editPlan['slug']??'') ?>" required></label><label>Monthly price<input type="number" step="0.01" min="0" name="monthly_price" value="<?= e($editPlan['monthly_price']??'0') ?>" required></label><label>Annual price<input type="number" step="0.01" min="0" name="annual_price" value="<?= e($editPlan['annual_price']??'0') ?>" required></label><label>Currency<input name="currency" maxlength="3" value="<?= e($editPlan['currency']??'NPR') ?>" required></label><label>Organization limit<input type="number" min="0" name="organization_limit" value="<?= e($editPlan['organization_limit']??'') ?>"></label><label>User limit<input type="number" min="0" name="user_limit" value="<?= e($editPlan['user_limit']??'') ?>"></label><label>Description<input name="description" value="<?= e($editPlan['description']??'') ?>"></label><label class="check-label"><input type="checkbox" name="is_enabled" value="1" <?= !isset($editPlan['is_enabled'])||(int)$editPlan['is_enabled']?'checked':'' ?>> Enabled</label><div class="wide"><button class="button primary">Save plan</button></div></form><div class="table-wrap"><table><thead><tr><th>Plan</th><th>Price (monthly / annual)</th><th>Enabled</th><th></th></tr></thead><tbody><?php foreach($plans as $p): ?><tr><td><?= e($p['name']) ?><small><?= e($p['slug']) ?></small></td><td><?= e($p['currency']) ?> <?= e($p['monthly_price']) ?> / <?= e($p['annual_price']) ?></td><td><?= (int)$p['is_enabled']?'Yes':'No' ?></td><td><a href="<?= e(admin_url('subscriptions?edit_plan='.$p['id'])) ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></section><section class="panel"><h2>Assign / record subscription</h2><p class="muted">Subscription status does not mean payment was received. Mark payment verified only after manual verification and a reference.</p><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><label>Organization<select name="organization_id" required><option value="">Choose</option><?php foreach($orgs as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select></label><label>Plan<select name="plan_id" required><option value="">Choose</option><?php foreach($plans as $p): if($p['is_enabled']): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endif; endforeach; ?></select></label><label>Status<select name="status"><option>trialing</option><option>active</option><option>past_due</option><option>cancelled</option><option>expired</option></select></label><label>Billing cycle<select name="billing_cycle"><option>monthly</option><option>annual</option></select></label><label>End date<input type="datetime-local" name="ends_at"></label><label>Payment reference (only if verified)<input name="payment_reference"></label><label class="check-label"><input type="checkbox" name="payment_verified" value="1"> Manually verified payment</label><label>Reason<input name="reason" required></label><div class="wide"><button class="button primary">Save subscription</button></div></form></section><section class="panel"><h2>Organization subscriptions</h2><div class="table-wrap"><table><thead><tr><th>Organization / plan</th><th>Billing</th><th>Subscription status</th><th>Payment</th><th>Update status</th></tr></thead><tbody><?php foreach($subs as $s): ?><tr><td><?= e($s['organization_name']) ?><small><?= e($s['plan_name']) ?></small></td><td><?= e($s['billing_cycle']) ?></td><td><?= admin_status($s['status']) ?></td><td><?= (int)$s['payment_verified']?'Verified · '.e($s['payment_reference']):'Not verified' ?></td><td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="subscription_status"><input type="hidden" name="subscription_id" value="<?= (int)$s['id'] ?>"><select name="status"><?php foreach(['trialing','active','past_due','cancelled','expired'] as $state): ?><option <?= $s['status']===$state?'selected':'' ?>><?= e($state) ?></option><?php endforeach; ?></select><input type="hidden" name="payment_reference" value="<?= e($s['payment_reference']) ?>"><label class="check-label"><input type="checkbox" name="payment_verified" value="1" <?= $s['payment_verified']?'checked':'' ?>>Paid/verified</label><input name="reason" placeholder="Reason" required><button class="button">Update</button></form></td></tr><?php endforeach; ?></tbody></table></div></section><?php $content=ob_get_clean();
} elseif ($section==='settings') {
    $settings=db()->query('SELECT setting_key,setting_value FROM admin_settings')->fetchAll();$values=[];foreach($settings as $s)$values[$s['setting_key']]=$s['setting_value'];
    $defaults=['brand_name'=>'STRUCTIQX','support_email'=>'','support_phone'=>'','default_currency'=>'NPR','registration_enabled'=>'1'];
    $values=array_merge($defaults,$values);
    ob_start(); ?><section class="panel"><form method="post" class="form-grid"><?= csrf_field() ?><label>Brand name<input name="brand_name" value="<?= e($values['brand_name']) ?>"></label><label>Support email<input type="email" name="support_email" value="<?= e($values['support_email']) ?>"></label><label>Support phone<input name="support_phone" value="<?= e($values['support_phone']) ?>"></label><label>Default currency<input name="default_currency" maxlength="3" value="<?= e($values['default_currency']) ?>"></label><label class="check-label"><input type="checkbox" name="registration_enabled" value="1" <?= $values['registration_enabled']==='1'?'checked':'' ?>> Allow new organization registrations</label><div class="wide"><button class="button primary">Save settings</button></div></form></section><?php $content=ob_get_clean();
} elseif ($section==='audit') {
    $rows=db()->query('SELECT l.*,a.name admin_name FROM admin_activity_logs l LEFT JOIN admin_users a ON a.id=l.admin_user_id ORDER BY l.created_at DESC LIMIT 300')->fetchAll();
    ob_start(); ?><section class="panel"><p class="muted">Append-only administrator action history. Activity records cannot be edited or deleted from this interface.</p><div class="table-wrap"><table><thead><tr><th>When</th><th>Administrator</th><th>Action</th><th>Target</th><th>Changes</th><th>IP</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['created_at']) ?></td><td><?= e($row['admin_name']??'Removed administrator') ?></td><td><?= e($row['action']) ?></td><td><?= e(($row['target_type']??'').($row['target_id']?' #'.$row['target_id']:'')) ?></td><td><small><?= e(substr((string)$row['new_value'],0,500)) ?></small></td><td><?= e($row['ip_address']) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php $content=ob_get_clean();
}

$titles=['dashboard'=>'Overview','organizations'=>'Organizations','users'=>'Customer users','messages'=>'Contact messages','cms'=>'Website CMS','subscriptions'=>'Plans & subscriptions','settings'=>'Platform settings','audit'=>'Administrator activity'];
admin_shell($titles[$section],$content,$section);
