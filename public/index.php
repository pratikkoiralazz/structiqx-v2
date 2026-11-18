<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/core/bootstrap.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$base = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base && str_starts_with($path, $base)) $path = trim(substr($path, strlen($base)), '/');
$route = $path === '' ? 'dashboard' : $path;
$page = explode('/', $route)[0];

function render(string $title, string $content, string $page = 'dashboard'): void {
    require dirname(__DIR__) . '/app/views/layout.php';
}

function auth_redirect(): void {
    if (!current_user()) redirect(base_url('login'));
}

function form_field(string $label, string $name, string $value = '', string $type = 'text', bool $required = true): string {
    return '<label class="block"><span class="mb-1.5 block text-sm font-medium text-slate-700">' . e($label) . '</span><input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . ($required?' required':'') . ' class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100"></label>';
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function table_start(array $headers): string {
    $head = implode('', array_map(fn($h) => '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">' . e($h) . '</th>', $headers));
    return '<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="w-full text-sm"><thead class="bg-slate-50 border-b border-slate-200"><tr>' . $head . '</tr></thead><tbody class="divide-y divide-slate-100">';
}

if ($route === 'install' && file_exists(__DIR__ . '/install.php')) { require __DIR__ . '/install.php'; exit; }

if ($route === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT u.*,om.organization_id,om.role,o.name organization_name FROM users u JOIN organization_members om ON om.user_id=u.id JOIN organizations o ON o.id=om.organization_id WHERE u.email=? AND u.is_active=1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email']];
            $_SESSION['organization_id'] = (int)$user['organization_id'];
            $_SESSION['organization_name'] = $user['organization_name'];
            $_SESSION['role'] = $user['role'];
            db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
            log_activity('login','session',null,'User signed in');
            redirect(base_url('dashboard'));
        }
        $error = 'Invalid email or password.';
    }
    ?>
    <!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Sign in | structiQX</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="min-h-screen bg-slate-950 grid place-items-center p-5"><main class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl"><div class="mb-8 flex items-center gap-3"><div class="h-10 w-10 rounded-xl bg-orange-500 grid place-items-center font-bold text-white">SQ</div><div><p class="font-semibold">structiQX</p><p class="text-xs text-slate-500">Civil Engineering OS</p></div></div><h1 class="text-xl font-semibold">Welcome back</h1><p class="mt-1 text-sm text-slate-500">Sign in to your workspace.</p><?php if (!empty($error)): ?><p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></p><?php endif; ?><form method="post" class="mt-6 space-y-4"><?= csrf_field() ?><label class="block text-sm font-medium">Email<input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" type="email" name="email" required></label><label class="block text-sm font-medium">Password<input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" type="password" name="password" required></label><button class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Sign in</button></form><div class="mt-5 flex justify-between text-sm"><a class="text-slate-500" href="<?= e(base_url('forgot')) ?>">Forgot password?</a><a class="text-orange-600" href="<?= e(base_url('register')) ?>">Create organization</a></div><p class="mt-4 text-center text-xs text-slate-400">Demo: admin@structiqx.test / Password123!</p></main></body></html>
    <?php exit;
}

if ($route === 'forgot') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email=strtolower(trim((string)$_POST['email']));
        $stmt=db()->prepare('SELECT id FROM users WHERE email=?');
        $stmt->execute([$email]);$uid=(int)$stmt->fetchColumn();
        if ($uid) {
            $token=bin2hex(random_bytes(32));
            db()->prepare('INSERT INTO password_reset_tokens (user_id,token,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([$uid,$token]);
            flash('success','A password reset link was created: /public/reset/'.$token);
        } else flash('success','If that account exists, reset instructions have been prepared.');
        redirect(base_url('forgot'));
    }
    ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Forgot password | structiQX</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 grid place-items-center p-5"><main class="w-full max-w-md rounded-2xl bg-white p-8"><h1 class="text-xl font-semibold">Reset password</h1><p class="mt-1 text-sm text-slate-500">Enter your account email.</p><?php foreach(consume_flash() as $f):?><p class="mt-4 break-all rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700"><?= e($f['message']) ?></p><?php endforeach?><form method="post" class="mt-5 space-y-4"><?= csrf_field() ?><input type="email" name="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2"><button class="w-full rounded-lg bg-slate-950 py-2.5 text-sm font-semibold text-white">Prepare reset</button></form><a class="mt-5 block text-center text-sm text-slate-500" href="<?= e(base_url('login')) ?>">Back to sign in</a></main></body></html><?php exit;
}

if (str_starts_with($route,'reset/')) {
    $token=substr($route,6);
    $stmt=db()->prepare('SELECT rt.id,rt.user_id FROM password_reset_tokens rt WHERE rt.token=? AND rt.used_at IS NULL AND rt.expires_at>NOW()');
    $stmt->execute([$token]);$reset=$stmt->fetch();
    if (!$reset){http_response_code(410);die('Reset link is invalid or has expired.');}
    if ($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();$password=(string)$_POST['password'];
        if(strlen($password)<8){flash('error','Password must be at least 8 characters.');redirect(base_url('reset/'.$token));}
        db()->beginTransaction();
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$reset['user_id']]);
        db()->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE id=?')->execute([(int)$reset['id']]);
        db()->commit();redirect(base_url('login'));
    }
    ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Set password | structiQX</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 grid place-items-center p-5"><main class="w-full max-w-md rounded-2xl bg-white p-8"><h1 class="text-xl font-semibold">Choose a new password</h1><form method="post" class="mt-5 space-y-4"><?= csrf_field() ?><input type="password" name="password" minlength="8" required class="w-full rounded-lg border border-slate-300 px-3 py-2"><button class="w-full rounded-lg bg-slate-950 py-2.5 text-sm font-semibold text-white">Update password</button></form></main></body></html><?php exit;
}

if ($route === 'register') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name=trim((string)$_POST['name']); $email=strtolower(trim((string)$_POST['email'])); $orgName=trim((string)$_POST['organization']); $password=(string)$_POST['password'];
        if (strlen($password)<8) { flash('error','Password must be at least 8 characters.'); redirect(base_url('register')); }
        try {
            db()->beginTransaction();
            $slug=strtolower(preg_replace('/[^a-z0-9]+/i','-',$orgName)); $slug=trim($slug,'-').'-'.substr(bin2hex(random_bytes(3)),0,6);
            $stmt=db()->prepare('INSERT INTO organizations (name,slug) VALUES (?,?)'); $stmt->execute([$orgName,$slug]); $orgId=(int)db()->lastInsertId();
            $stmt=db()->prepare('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)'); $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]); $uid=(int)db()->lastInsertId();
            db()->prepare("INSERT INTO organization_members (organization_id,user_id,role) VALUES (?,?,'organization_admin')")->execute([$orgId,$uid]);
            db()->commit();
            $_SESSION['user']=['id'=>$uid,'name'=>$name,'email'=>$email]; $_SESSION['organization_id']=$orgId; $_SESSION['organization_name']=$orgName; $_SESSION['role']='organization_admin';
            session_regenerate_id(true); redirect(base_url('dashboard'));
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); flash('error','Could not create account. The email may already be registered.'); redirect(base_url('register')); }
    }
    ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Create organization | structiQX</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 grid place-items-center p-5"><main class="w-full max-w-md rounded-2xl bg-white p-8"><h1 class="text-xl font-semibold">Create your organization</h1><form method="post" class="mt-6 space-y-4"><?= csrf_field() ?><?= form_field('Organization name','organization') ?><?= form_field('Full name','name') ?><?= form_field('Work email','email','','email') ?><?= form_field('Password','password','','password') ?><button class="w-full rounded-lg bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white">Create workspace</button></form><p class="mt-5 text-center text-sm"><a class="text-slate-500" href="<?= e(base_url('login')) ?>">Back to sign in</a></p></main></body></html><?php exit;
}

if ($route === 'logout') { session_destroy(); redirect(base_url('login')); }
auth_redirect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') verify_csrf();
$org = organization_id();

if ($page === 'download') {
    $id=(int)(explode('/',$route)[1] ?? 0);
    $stmt=db()->prepare('SELECT * FROM documents WHERE id=? AND organization_id=?');
    $stmt->execute([$id,$org]);$doc=$stmt->fetch();
    if (!$doc){http_response_code(404);die('Document not found');}
    $path=dirname(__DIR__).'/storage/uploads/'.$doc['file_path'];
    if (!is_file($path)){http_response_code(404);die('File is missing');}
    header('Content-Type: '.$doc['mime_type']);header('Content-Disposition: attachment; filename="'.basename($doc['title']).'.'.pathinfo($doc['file_path'],PATHINFO_EXTENSION).'"');header('Content-Length: '.filesize($path));readfile($path);exit;
}

if ($page === 'export') {
    $kind=explode('/',$route)[1] ?? 'projects';
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename=structiqx-'.$kind.'.csv');
    $out=fopen('php://output','w');
    if($kind==='expenses'){fputcsv($out,['Project','Category','Description','Amount','Date','Status']);$rows=db()->query("SELECT e.*,p.title project_title FROM expenses e JOIN projects p ON p.id=e.project_id WHERE e.organization_id=$org")->fetchAll();foreach($rows as $r)fputcsv($out,[$r['project_title'],$r['category'],$r['description'],$r['amount'],$r['expense_date'],$r['status']]);}
    elseif($kind==='projects'){fputcsv($out,['Title','Status','Progress','Budget','Start','End']);$rows=db()->query("SELECT * FROM projects WHERE organization_id=$org")->fetchAll();foreach($rows as $r)fputcsv($out,[$r['title'],$r['status'],$r['progress'],$r['budget'],$r['start_date'],$r['end_date']]);}
    elseif($kind==='boq'){fputcsv($out,['BOQ','Project','Description','Unit','Quantity','Unit Rate','Total']);$rows=db()->query("SELECT b.title boq_title,p.title project_title,i.description,i.unit,i.quantity,i.unit_rate,i.total FROM boq b JOIN projects p ON p.id=b.project_id JOIN boq_items i ON i.boq_id=b.id WHERE b.organization_id=$org")->fetchAll();foreach($rows as $r)fputcsv($out,[$r['boq_title'],$r['project_title'],$r['description'],$r['unit'],$r['quantity'],$r['unit_rate'],$r['total']]);}
    fclose($out);exit;
}

switch ($page) {
case 'dashboard':
    $counts = [
      'projects'=>(int)db()->prepare('SELECT COUNT(*) FROM projects WHERE organization_id=?')->execute([$org])?0:0,
    ];
    $counts['projects']=$p=(int)db()->query("SELECT COUNT(*) FROM projects WHERE organization_id=$org")->fetchColumn();
    $counts['active']=(int)db()->query("SELECT COUNT(*) FROM projects WHERE organization_id=$org AND status='active'")->fetchColumn();
    $counts['completed']=(int)db()->query("SELECT COUNT(*) FROM projects WHERE organization_id=$org AND status='completed'")->fetchColumn();
    $counts['delayed']=(int)db()->query("SELECT COUNT(*) FROM projects WHERE organization_id=$org AND status='active' AND end_date<CURDATE()")->fetchColumn();
    $budget=(float)db()->query("SELECT COALESCE(SUM(budget),0) FROM projects WHERE organization_id=$org")->fetchColumn();
    $spent=(float)db()->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE organization_id=$org")->fetchColumn();
    $projects=db()->query("SELECT id,title,status,progress,end_date,budget FROM projects WHERE organization_id=$org ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $cards=[
      ['Total projects',$counts['projects'],'Building2','text-slate-900'],
      ['Active projects',$counts['active'],'Activity','text-emerald-600'],
      ['Completed',$counts['completed'],'CircleCheckBig','text-blue-600'],
      ['Delayed',$counts['delayed'],'Clock3','text-red-600'],
    ];
    ob_start(); ?>
    <div class="mb-6 flex items-end justify-between"><div><p class="text-sm text-slate-500">Overview</p><h1 class="mt-1 text-2xl font-semibold">Good morning, <?= e(current_user()['name']) ?></h1></div><a href="<?= e(base_url('projects/create')) ?>" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">New project</a></div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><?php foreach($cards as $card): ?><div class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex justify-between"><p class="text-sm text-slate-500"><?= e($card[0]) ?></p><i data-lucide="<?= $card[2] ?>" class="h-4 w-4 <?= $card[3] ?>"></i></div><p class="mt-3 text-3xl font-semibold"><?= e((string)$card[1]) ?></p></div><?php endforeach; ?></div>
    <div class="mt-6 grid gap-6 lg:grid-cols-3"><section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2"><div class="flex items-center justify-between"><h2 class="font-semibold">Recent projects</h2><a class="text-sm text-slate-500" href="<?= e(base_url('projects')) ?>">View all</a></div><div class="mt-5 space-y-4"><?php if(!$projects): ?><p class="py-8 text-center text-sm text-slate-500">No projects yet.</p><?php endif; foreach($projects as $project): ?><a href="<?= e(base_url('projects/'.$project['id'])) ?>" class="block"><div class="flex items-center justify-between text-sm"><span class="font-medium"><?= e($project['title']) ?></span><span class="capitalize text-slate-500"><?= e($project['status']) ?></span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-orange-500" style="width:<?= (int)$project['progress'] ?>%"></div></div></a><?php endforeach; ?></div></section><section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Financial snapshot</h2><div class="mt-5 space-y-4"><div><p class="text-xs text-slate-500">Project budget</p><p class="mt-1 text-xl font-semibold">NPR <?= number_format($budget) ?></p></div><div><p class="text-xs text-slate-500">Actual expenditure</p><p class="mt-1 text-xl font-semibold">NPR <?= number_format($spent) ?></p></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width:<?= $budget>0?min(100,$spent/$budget*100):0 ?>%"></div></div></div></section></div>
    <?php $content=ob_get_clean(); render('Dashboard',$content,'dashboard'); break;

case 'projects':
    if ($route === 'projects/create') {
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $stmt=db()->prepare('INSERT INTO projects (organization_id,client_id,title,description,category,location,start_date,end_date,budget,status,project_manager_id,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$org,$_POST['client_id']?:null,trim($_POST['title']),trim($_POST['description']??''),trim($_POST['category']??''),trim($_POST['location']??''),$_POST['start_date']?:null,$_POST['end_date']?:null,(float)$_POST['budget'],$_POST['status'],$_POST['project_manager_id']?:null,(int)current_user()['id']]);
            $id=(int)db()->lastInsertId(); log_activity('create','project',$id,'Created project');
            flash('success','Project created successfully.'); redirect(base_url('projects/'.$id));
        }
        $clients=db()->query("SELECT id,name FROM clients WHERE organization_id=$org ORDER BY name")->fetchAll();
        $members=db()->query("SELECT u.id,u.name FROM users u JOIN organization_members om ON om.user_id=u.id WHERE om.organization_id=$org")->fetchAll();
        ob_start(); ?><a href="<?= e(base_url('projects')) ?>" class="text-sm text-slate-500">← Projects</a><div class="mt-4 max-w-3xl rounded-xl border border-slate-200 bg-white p-6"><h1 class="text-xl font-semibold">Create project</h1><form method="post" class="mt-6 grid gap-5 sm:grid-cols-2"><?= csrf_field() ?><?= form_field('Project title','title') ?><label class="block"><span class="mb-1.5 block text-sm font-medium">Client</span><select name="client_id" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">No client</option><?php foreach($clients as $c):?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach?></select></label><?= form_field('Category','category') ?><?= form_field('Location','location') ?><?= form_field('Start date','start_date','','date') ?><?= form_field('End date','end_date','','date') ?><?= form_field('Budget','budget','0','number') ?><label class="block"><span class="mb-1.5 block text-sm font-medium">Status</span><select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option>planning</option><option>active</option><option>on_hold</option><option>completed</option></select></label><label class="block sm:col-span-2"><span class="mb-1.5 block text-sm font-medium">Project manager</span><select name="project_manager_id" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Unassigned</option><?php foreach($members as $m):?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach?></select></label><label class="block sm:col-span-2"><span class="mb-1.5 block text-sm font-medium">Description</span><textarea name="description" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea></label><div class="sm:col-span-2"><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Create project</button></div></form></div><?php $content=ob_get_clean(); render('Create project',$content,'projects'); break;
    }
    $parts=explode('/',$route);
    if (isset($parts[1]) && ctype_digit($parts[1])) {
        $project=org_row('projects',(int)$parts[1]); if(!$project){http_response_code(404);die('Project not found');}
        if($_SERVER['REQUEST_METHOD']==='POST' && trim((string)($_POST['body'] ?? '')) !== ''){
            db()->prepare('INSERT INTO comments (organization_id,entity_type,entity_id,user_id,body) VALUES (? ,\'project\',?,?,?)')->execute([$org,$project['id'],(int)current_user()['id'],trim($_POST['body'])]);
            log_activity('comment','project',$project['id'],'Added project comment');flash('success','Comment added.');
        }
        $tasks=db()->prepare('SELECT t.*,u.name assignee FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to WHERE t.organization_id=? AND t.project_id=? ORDER BY t.created_at DESC');$tasks->execute([$org,$project['id']]);$tasks=$tasks->fetchAll();
        $comments=db()->prepare('SELECT c.*,u.name FROM comments c JOIN users u ON u.id=c.user_id WHERE c.organization_id=? AND c.entity_type=\'project\' AND c.entity_id=? ORDER BY c.created_at DESC');
        $comments->execute([$org,$project['id']]);$comments=$comments->fetchAll();
        ob_start();?><a href="<?= e(base_url('projects')) ?>" class="text-sm text-slate-500">← Projects</a><div class="mt-4 rounded-xl border border-slate-200 bg-white p-6"><div class="flex flex-wrap justify-between gap-4"><div><p class="text-sm text-slate-500"><?= e($project['category']) ?> · <?= e($project['location']) ?></p><h1 class="mt-1 text-2xl font-semibold"><?= e($project['title']) ?></h1></div><span class="h-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-medium capitalize"><?= e($project['status']) ?></span></div><p class="mt-4 max-w-3xl text-sm leading-6 text-slate-600"><?= e($project['description'] ?: 'No description provided.') ?></p><div class="mt-6 grid grid-cols-2 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-4"><div><p class="text-xs text-slate-500">Progress</p><p class="mt-1 font-semibold"><?= (int)$project['progress'] ?>%</p></div><div><p class="text-xs text-slate-500">Budget</p><p class="mt-1 font-semibold">NPR <?= number_format((float)$project['budget']) ?></p></div><div><p class="text-xs text-slate-500">Start</p><p class="mt-1 font-semibold"><?= e($project['start_date'] ?: '—') ?></p></div><div><p class="text-xs text-slate-500">End</p><p class="mt-1 font-semibold"><?= e($project['end_date'] ?: '—') ?></p></div></div></div><section class="mt-6"><div class="mb-3 flex items-center justify-between"><h2 class="font-semibold">Tasks</h2><a class="rounded-lg border border-slate-300 px-3 py-2 text-sm" href="<?= e(base_url('tasks')) ?>">View tasks</a></div><?php echo table_start(['Task','Assignee','Priority','Status','Due']); foreach($tasks as $t):?><tr><td class="px-4 py-3 font-medium"><?= e($t['title']) ?></td><td class="px-4 py-3"><?= e($t['assignee'] ?: 'Unassigned') ?></td><td class="px-4 py-3 capitalize"><?= e($t['priority']) ?></td><td class="px-4 py-3 capitalize"><?= e(str_replace('_',' ', $t['status'])) ?></td><td class="px-4 py-3"><?= e($t['due_date'] ?: '—') ?></td></tr><?php endforeach; echo '</tbody></table></div>';?></section><section class="mt-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Project comments</h2><form method="post" class="mt-4 flex gap-3"><?= csrf_field() ?><input name="body" required placeholder="Add a comment" class="flex-1 rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Post</button></form><div class="mt-4 divide-y"><?php foreach($comments as $c):?><article class="py-3"><p class="text-sm font-medium"><?= e($c['name']) ?></p><p class="mt-1 text-sm text-slate-600"><?= e($c['body']) ?></p></article><?php endforeach;if(!$comments):?><p class="py-4 text-sm text-slate-500">No comments yet.</p><?php endif?></div></section><?php $content=ob_get_clean(); render($project['title'],$content,'projects'); break;
    }
    $projects=db()->prepare('SELECT p.*,c.name client_name FROM projects p LEFT JOIN clients c ON c.id=p.client_id WHERE p.organization_id=? ORDER BY p.created_at DESC');$projects->execute([$org]);$projects=$projects->fetchAll();
    ob_start();?><div class="mb-5 flex items-center justify-between"><div><p class="text-sm text-slate-500">Manage delivery</p><h1 class="mt-1 text-2xl font-semibold">Projects</h1></div><a href="<?= e(base_url('projects/create')) ?>" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">New project</a></div><?= table_start(['Project','Client','Status','Progress','End date']) ?><?php foreach($projects as $p):?><tr><td class="px-4 py-3"><a class="font-medium hover:text-orange-600" href="<?= e(base_url('projects/'.$p['id'])) ?>"><?= e($p['title']) ?></a></td><td class="px-4 py-3"><?= e($p['client_name'] ?: '—') ?></td><td class="px-4 py-3 capitalize"><?= e($p['status']) ?></td><td class="px-4 py-3"><div class="flex items-center gap-2"><div class="h-1.5 w-20 rounded-full bg-slate-100"><div class="h-full rounded-full bg-orange-500" style="width:<?= (int)$p['progress'] ?>%"></div></div><?= (int)$p['progress'] ?>%</div></td><td class="px-4 py-3"><?= e($p['end_date'] ?: '—') ?></td></tr><?php endforeach; if(!$projects):?><tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No projects found.</td></tr><?php endif;?></tbody></table></div><?php $content=ob_get_clean(); render('Projects',$content,'projects'); break;

case 'tasks':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if(($_POST['action']??'')==='update_status'){
            $allowed=['todo','in_progress','in_review','completed','blocked','cancelled'];
            if(in_array($_POST['status'],$allowed,true)){db()->prepare('UPDATE tasks SET status=?,progress=? WHERE id=? AND organization_id=?')->execute([$_POST['status'],(int)$_POST['progress'],(int)$_POST['task_id'],$org]);log_activity('update','task',(int)$_POST['task_id'],'Updated task status');flash('success','Task updated.');}
        } else {
        $stmt=db()->prepare('INSERT INTO tasks (organization_id,project_id,title,description,assigned_to,priority,status,start_date,due_date,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$org,(int)$_POST['project_id'],trim($_POST['title']),trim($_POST['description']??''),$_POST['assigned_to']?:null,$_POST['priority'],$_POST['status'],$_POST['start_date']?:null,$_POST['due_date']?:null,current_user()['id']]);
        log_activity('create','task',(int)db()->lastInsertId(),'Created task'); flash('success','Task created successfully.');
        }
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org ORDER BY title")->fetchAll();$members=db()->query("SELECT u.id,u.name FROM users u JOIN organization_members om ON om.user_id=u.id WHERE om.organization_id=$org")->fetchAll();
    $stmt=db()->prepare('SELECT t.*,p.title project_title,u.name assignee FROM tasks t JOIN projects p ON p.id=t.project_id LEFT JOIN users u ON u.id=t.assigned_to WHERE t.organization_id=? ORDER BY t.due_date');$stmt->execute([$org]);$tasks=$stmt->fetchAll();
    ob_start();?><div class="mb-5 flex items-center justify-between"><h1 class="text-2xl font-semibold">Tasks</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Create task</h2><form method="post" class="mt-4 grid gap-4 md:grid-cols-4"><?= csrf_field() ?><label class="md:col-span-2"><span class="mb-1.5 block text-sm font-medium">Title</span><input name="title" required class="w-full rounded-lg border border-slate-300 px-3 py-2"></label><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><select name="assigned_to" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Assignee</option><?php foreach($members as $m):?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach?></select><select name="priority" class="rounded-lg border border-slate-300 px-3 py-2"><option>low</option><option selected>medium</option><option>high</option><option>critical</option></select><select name="status" class="rounded-lg border border-slate-300 px-3 py-2"><option>todo</option><option>in_progress</option><option>in_review</option><option>completed</option><option>blocked</option></select><input type="date" name="due_date" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Add task</button></form></div><?= table_start(['Task','Project','Assignee','Priority','Status','Progress','Due']) ?><?php foreach($tasks as $t):?><tr><td class="px-4 py-3 font-medium"><?= e($t['title']) ?></td><td class="px-4 py-3"><?= e($t['project_title']) ?></td><td class="px-4 py-3"><?= e($t['assignee'] ?: 'Unassigned') ?></td><td class="px-4 py-3 capitalize"><?= e($t['priority']) ?></td><td class="px-4 py-3" colspan="1"><form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="task_id" value="<?= $t['id'] ?>"><select name="status" class="rounded border border-slate-300 px-1 py-1 text-xs"><option <?= $t['status']==='todo'?'selected':'' ?>>todo</option><option <?= $t['status']==='in_progress'?'selected':'' ?>>in_progress</option><option <?= $t['status']==='in_review'?'selected':'' ?>>in_review</option><option <?= $t['status']==='completed'?'selected':'' ?>>completed</option><option <?= $t['status']==='blocked'?'selected':'' ?>>blocked</option></select><input name="progress" type="number" min="0" max="100" value="<?= (int)$t['progress'] ?>" class="w-14 rounded border border-slate-300 px-1 py-1 text-xs"><button class="rounded bg-slate-100 px-2 text-xs">Save</button></form></td><td class="px-4 py-3"><?= (int)$t['progress'] ?>%</td><td class="px-4 py-3"><?= e($t['due_date'] ?: '—') ?></td></tr><?php endforeach;?></tbody></table></div><?php $content=ob_get_clean();render('Tasks',$content,'tasks');break;

case 'milestones':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $stmt=db()->prepare('INSERT INTO milestones (organization_id,project_id,title,due_date,status) VALUES (?,?,?,?,?)');
        $stmt->execute([$org,(int)$_POST['project_id'],trim($_POST['title']),$_POST['due_date'],$_POST['status']]);
        log_activity('create','milestone',(int)db()->lastInsertId(),'Created milestone'); flash('success','Milestone created.');
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT m.*,p.title project_title FROM milestones m JOIN projects p ON p.id=m.project_id WHERE m.organization_id=$org ORDER BY m.due_date")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Milestones</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-5"><?= csrf_field() ?><input name="title" required placeholder="Milestone title" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-2"><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input type="date" name="due_date" required class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-3 text-sm font-semibold text-white">Add</button><input type="hidden" name="status" value="planned"></form></div><?= table_start(['Milestone','Project','Due','Status']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['title']) ?></td><td class="px-4 py-3"><?= e($r['project_title']) ?></td><td class="px-4 py-3"><?= e($r['due_date']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Milestones',$content,'milestones');break;

case 'team':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        require_role(['organization_admin']);
        $email=strtolower(trim((string)$_POST['email']));
        $stmt=db()->prepare('SELECT id FROM users WHERE email=?');$stmt->execute([$email]);$uid=(int)($stmt->fetchColumn() ?: 0);
        if (!$uid) {$s=db()->prepare('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)');$s->execute([trim($_POST['name']),$email,password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);$uid=(int)db()->lastInsertId();}
        db()->prepare('INSERT IGNORE INTO organization_members (organization_id,user_id,role,status) VALUES (?,?,?,\'active\')')->execute([$org,$uid,$_POST['role']]);
        $token=bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO organization_invitations (organization_id,email,role,token,expires_at) VALUES (?,?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY))')->execute([$org,$email,$_POST['role'],$token]);
        log_activity('create','team_member',$uid,'Added organization team member');flash('success','Team member added.');
    }
    $rows=db()->query("SELECT u.id,u.name,u.email,om.role,om.status FROM users u JOIN organization_members om ON om.user_id=u.id WHERE om.organization_id=$org ORDER BY u.name")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Team</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Add member</h2><form method="post" class="mt-4 grid gap-3 md:grid-cols-4"><?= csrf_field() ?><input name="name" required placeholder="Full name" class="rounded-lg border border-slate-300 px-3 py-2"><input name="email" type="email" required placeholder="Email" class="rounded-lg border border-slate-300 px-3 py-2"><select name="role" class="rounded-lg border border-slate-300 px-3 py-2"><option>project_manager</option><option>civil_engineer</option><option>site_engineer</option><option>site_supervisor</option><option>accountant</option><option>procurement_officer</option><option>client</option></select><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Add member</button></form></div><?= table_start(['Name','Email','Role','Status']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3"><?= e($r['email']) ?></td><td class="px-4 py-3"><?= e(str_replace('_',' ',ucwords($r['role'],'_'))) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Team',$content,'team');break;

case 'budgets':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $stmt=db()->prepare('INSERT INTO budgets (organization_id,project_id,name,amount) VALUES (?,?,?,?)');$stmt->execute([$org,(int)$_POST['project_id'],trim($_POST['name']),(float)$_POST['amount']]);
        log_activity('create','budget',(int)db()->lastInsertId(),'Created project budget');flash('success','Budget created.');
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT b.*,p.title project_title FROM budgets b JOIN projects p ON p.id=b.project_id WHERE b.organization_id=$org ORDER BY b.created_at DESC")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Budgets</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-4"><?= csrf_field() ?><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input name="name" required placeholder="Budget name" class="rounded-lg border border-slate-300 px-3 py-2"><input name="amount" type="number" step="0.01" required placeholder="Amount" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Add budget</button></form></div><?= table_start(['Budget','Project','Amount','Revision']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3"><?= e($r['project_title']) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$r['amount'],2) ?></td><td class="px-4 py-3"><?= (int)$r['revision_no'] ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Budgets',$content,'budgets');break;

case 'boq':
    $boqParts=explode('/',$route);
    if (isset($boqParts[1]) && ctype_digit($boqParts[1])) {
        $boqId=(int)$boqParts[1];
        $stmt=db()->prepare('SELECT b.*,p.title project_title FROM boq b JOIN projects p ON p.id=b.project_id WHERE b.id=? AND b.organization_id=?');
        $stmt->execute([$boqId,$org]);$boq=$stmt->fetch();
        if(!$boq){http_response_code(404);die('BOQ not found');}
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $stmt=db()->prepare('INSERT INTO boq_items (boq_id,item_code,description,unit,quantity,unit_rate) VALUES (?,?,?,?,?,?)');
            $itemCode = trim((string)($_POST['item_code'] ?? ''));
            $itemDescription = trim((string)$_POST['description']);
            $itemUnit = trim((string)$_POST['unit']);
            $itemQuantity = (float)$_POST['quantity'];
            $itemRate = (float)$_POST['unit_rate'];
            $stmt->execute([$boqId,$itemCode,$itemDescription,$itemUnit,$itemQuantity,$itemRate]);
            log_activity('create','boq_item',(int)db()->lastInsertId(),'Added BOQ item');flash('success','BOQ item added.');
        }
        $stmt=db()->prepare('SELECT * FROM boq_items WHERE boq_id=? ORDER BY id');
        $stmt->execute([$boqId]);$items=$stmt->fetchAll();
        ob_start();?><a href="<?= e(base_url('boq')) ?>" class="text-sm text-slate-500">← BOQ</a><div class="mt-4 mb-5 flex justify-between"><div><p class="text-sm text-slate-500"><?= e($boq['project_title']) ?></p><h1 class="text-2xl font-semibold"><?= e($boq['title']) ?></h1></div><a href="<?= e(base_url('export/boq')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Export CSV</a></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-6"><?= csrf_field() ?><input name="item_code" placeholder="Code" class="rounded-lg border border-slate-300 px-3 py-2"><input name="description" required placeholder="Description" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-2"><input name="unit" required placeholder="Unit" class="rounded-lg border border-slate-300 px-3 py-2"><input name="quantity" type="number" step="0.001" required placeholder="Quantity" class="rounded-lg border border-slate-300 px-3 py-2"><input name="unit_rate" type="number" step="0.01" required placeholder="Rate" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white md:col-span-6">Add item</button></form></div><?= table_start(['Code','Description','Unit','Quantity','Rate','Total']) ?><?php foreach($items as $i):?><tr><td class="px-4 py-3"><?= e($i['item_code']) ?></td><td class="px-4 py-3 font-medium"><?= e($i['description']) ?></td><td class="px-4 py-3"><?= e($i['unit']) ?></td><td class="px-4 py-3"><?= number_format((float)$i['quantity'],3) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$i['unit_rate'],2) ?></td><td class="px-4 py-3 font-medium">NPR <?= number_format((float)$i['total'],2) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('BOQ detail',$content,'boq');break;
    }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $stmt=db()->prepare('INSERT INTO boq (organization_id,project_id,title,status) VALUES (?,?,?,\'draft\')');$stmt->execute([$org,(int)$_POST['project_id'],trim($_POST['title'])]);$boqId=(int)db()->lastInsertId();
        log_activity('create','boq',$boqId,'Created BOQ');flash('success','BOQ created.');
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT b.*,p.title project_title,(SELECT COALESCE(SUM(quantity*unit_rate),0) FROM boq_items i WHERE i.boq_id=b.id) total FROM boq b JOIN projects p ON p.id=b.project_id WHERE b.organization_id=$org")->fetchAll();
    ob_start();?><div class="mb-5 flex justify-between"><h1 class="text-2xl font-semibold">BOQ & Estimation</h1><a href="<?= e(base_url('export/boq')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Export CSV</a></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-3"><?= csrf_field() ?><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input name="title" required placeholder="BOQ title" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Create BOQ</button></form></div><?= table_start(['BOQ','Project','Status','Estimated total']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3"><a class="font-medium text-orange-600" href="<?= e(base_url('boq/'.$r['id'])) ?>"><?= e($r['title']) ?></a></td><td class="px-4 py-3"><?= e($r['project_title']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$r['total'],2) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('BOQ',$content,'boq');break;

case 'calculations':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if(($_POST['action']??'')==='review'){
            $status=in_array($_POST['status'],['in_review','verified','approved','rejected'],true)?$_POST['status']:'in_review';
            db()->prepare('UPDATE engineering_calculations SET status=?,reviewed_by=? WHERE id=? AND organization_id=?')->execute([$status,(int)current_user()['id'],(int)$_POST['calculation_id'],$org]);
            log_activity('review','calculation',(int)$_POST['calculation_id'],'Updated calculation review status');
            flash('success','Calculation review status updated.');
        } else {
        $type=trim($_POST['calculation_type']);
        $inputs=['width'=>(float)($_POST['width']??0),'depth'=>(float)($_POST['depth']??0),'length'=>(float)($_POST['length']??0),'thickness'=>(float)($_POST['thickness']??0)];
        $result=0;$formula='';$steps=[];
        if ($type==='rcc_beam') {$result=$inputs['width']*$inputs['depth']*$inputs['length'];$formula='Volume = width × depth × length';$steps=['width='.$inputs['width'].' m','depth='.$inputs['depth'].' m','length='.$inputs['length'].' m','volume='.$result.' m³'];}
        if ($type==='rcc_slab') {$result=$inputs['length']*$inputs['width']*$inputs['thickness'];$formula='Volume = length × width × thickness';$steps=['length='.$inputs['length'].' m','width='.$inputs['width'].' m','thickness='.$inputs['thickness'].' m','volume='.$result.' m³'];}
        $stmt=db()->prepare('INSERT INTO engineering_calculations (organization_id,project_id,calculation_type,title,inputs_json,formula,steps_json,results_json,unit,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,\'draft\',?)');
        $stmt->execute([$org,$_POST['project_id']?:null,$type,trim($_POST['title']),json_encode($inputs),$formula,json_encode($steps),json_encode(['value'=>$result]),$type==='rcc_slab'?'m3':($type==='rcc_beam'?'m3':'—'),current_user()['id']]);
        log_activity('create','calculation',(int)db()->lastInsertId(),'Created engineering calculation');flash('success','Calculation draft saved. Professional review is required.');
        }
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT c.*,p.title project_title FROM engineering_calculations c LEFT JOIN projects p ON p.id=c.project_id WHERE c.organization_id=$org ORDER BY c.created_at DESC")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Engineering Calculation Studio</h1><p class="mt-1 text-sm text-amber-700">Draft calculations are not approved engineering designs. Obtain qualified professional review.</p></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-3"><?= csrf_field() ?><input name="title" required placeholder="Calculation title" class="rounded-lg border border-slate-300 px-3 py-2"><select name="calculation_type" class="rounded-lg border border-slate-300 px-3 py-2"><option value="rcc_beam">RCC beam volume</option><option value="rcc_slab">RCC slab volume</option></select><select name="project_id" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">No project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input name="width" type="number" step="0.001" placeholder="Width (m)" class="rounded-lg border border-slate-300 px-3 py-2"><input name="depth" type="number" step="0.001" placeholder="Depth (m)" class="rounded-lg border border-slate-300 px-3 py-2"><input name="length" type="number" step="0.001" placeholder="Length (m)" class="rounded-lg border border-slate-300 px-3 py-2"><input name="thickness" type="number" step="0.001" placeholder="Thickness (m)" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white md:col-start-3">Save calculation</button></form></div><?= table_start(['Title','Type','Status','Standard','Unit','Review']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['title']) ?></td><td class="px-4 py-3"><?= e(str_replace('_',' ',ucwords($r['calculation_type'],'_'))) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td><td class="px-4 py-3"><?= e($r['standard_reference'] ?: 'Not specified') ?></td><td class="px-4 py-3"><?= e($r['unit'] ?: '—') ?></td><td class="px-4 py-3"><form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="review"><input type="hidden" name="calculation_id" value="<?= $r['id'] ?>"><select name="status" class="rounded border border-slate-300 px-1 py-1 text-xs"><option <?= $r['status']==='in_review'?'selected':'' ?>>in_review</option><option <?= $r['status']==='verified'?'selected':'' ?>>verified</option><option <?= $r['status']==='approved'?'selected':'' ?>>approved</option><option <?= $r['status']==='rejected'?'selected':'' ?>>rejected</option></select><button class="rounded bg-slate-100 px-2 text-xs">Save</button></form></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Calculations',$content,'calculations');break;

case 'site':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $stmt=db()->prepare('INSERT INTO site_reports (organization_id,project_id,report_date,weather,work_completed,labour_count,materials_used,equipment_used,issues,safety_observations,supervisor_remarks,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$org,(int)$_POST['project_id'],$_POST['report_date'],trim($_POST['weather']??''),trim($_POST['work_completed']??''),(int)$_POST['labour_count'],trim($_POST['materials_used']??''),trim($_POST['equipment_used']??''),trim($_POST['issues']??''),trim($_POST['safety_observations']??''),trim($_POST['supervisor_remarks']??''),current_user()['id']]);
        log_activity('create','site_report',(int)db()->lastInsertId(),'Created daily site report');flash('success','Site report saved.');
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT s.*,p.title project_title FROM site_reports s JOIN projects p ON p.id=s.project_id WHERE s.organization_id=$org ORDER BY s.report_date DESC")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Daily Site Reports</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-3"><?= csrf_field() ?><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input type="date" name="report_date" required value="<?= date('Y-m-d') ?>" class="rounded-lg border border-slate-300 px-3 py-2"><input name="weather" placeholder="Weather" class="rounded-lg border border-slate-300 px-3 py-2"><input name="labour_count" type="number" placeholder="Labour count" class="rounded-lg border border-slate-300 px-3 py-2"><textarea name="work_completed" rows="2" placeholder="Work completed" class="rounded-lg border border-slate-300 px-3 py-2"></textarea><textarea name="issues" rows="2" placeholder="Site issues" class="rounded-lg border border-slate-300 px-3 py-2"></textarea><textarea name="equipment_used" rows="2" placeholder="Equipment used" class="rounded-lg border border-slate-300 px-3 py-2"></textarea><textarea name="supervisor_remarks" rows="2" placeholder="Supervisor remarks" class="rounded-lg border border-slate-300 px-3 py-2"></textarea><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white md:col-start-3">Save report</button></form></div><?= table_start(['Date','Project','Weather','Labour','Remarks']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3"><?= e($r['report_date']) ?></td><td class="px-4 py-3 font-medium"><?= e($r['project_title']) ?></td><td class="px-4 py-3"><?= e($r['weather'] ?: '—') ?></td><td class="px-4 py-3"><?= (int)$r['labour_count'] ?></td><td class="px-4 py-3"><?= e($r['supervisor_remarks'] ?: '—') ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Site reports',$content,'site');break;

case 'documents':
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['file'])) {
        $file=$_FILES['file'];
        $allowed=['application/pdf','image/jpeg','image/png','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if ($file['error']!==UPLOAD_ERR_OK || $file['size']>10*1024*1024 || !in_array($file['type'],$allowed,true)) {flash('error','Upload rejected. Use a PDF, Office document, or JPG/PNG under 10 MB.');}
        else {$ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));$name=bin2hex(random_bytes(16)).'.'.$ext;$dest=dirname(__DIR__).'/storage/uploads/'.$name;if(move_uploaded_file($file['tmp_name'],$dest)){db()->prepare('INSERT INTO documents (organization_id,project_id,title,category,file_path,mime_type,file_size,access_level,status,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$org,$_POST['project_id']?:null,trim($_POST['title']),trim($_POST['category']??''),$name,$file['type'],$file['size'],$_POST['access_level'],$_POST['status'],current_user()['id']]);log_activity('upload','document',(int)db()->lastInsertId(),'Uploaded document');flash('success','Document uploaded.');}}
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT d.*,p.title project_title FROM documents d LEFT JOIN projects p ON p.id=d.project_id WHERE d.organization_id=$org ORDER BY d.created_at DESC")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Documents</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-4"><?= csrf_field() ?><input name="title" required placeholder="Document title" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-2"><select name="project_id" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Organization</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><select name="category" class="rounded-lg border border-slate-300 px-3 py-2"><option>Drawing</option><option>Contract</option><option>BOQ</option><option>Structural report</option><option>Soil report</option><option>Certificate</option></select><select name="access_level" class="rounded-lg border border-slate-300 px-3 py-2"><option>team</option><option>private</option><option>client</option></select><select name="status" class="rounded-lg border border-slate-300 px-3 py-2"><option>draft</option><option>pending</option><option>approved</option></select><input type="file" name="file" required class="md:col-span-2 text-sm"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Upload</button></form></div><?= table_start(['Title','Project','Category','Access','Status','Version','']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['title']) ?></td><td class="px-4 py-3"><?= e($r['project_title'] ?: 'Organization') ?></td><td class="px-4 py-3"><?= e($r['category']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['access_level']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td><td class="px-4 py-3"><?= (int)$r['version'] ?></td><td class="px-4 py-3"><a class="text-orange-600" href="<?= e(base_url('download/'.$r['id'])) ?>">Download</a></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Documents',$content,'documents');break;

case 'procurement':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (($_POST['action']??'')==='supplier') {db()->prepare('INSERT INTO suppliers (organization_id,name,contact_person,email,phone,address) VALUES (?,?,?,?,?,?)')->execute([$org,trim($_POST['name']),trim($_POST['contact_person']??''),trim($_POST['email']??''),trim($_POST['phone']??''),trim($_POST['address']??'')]);flash('success','Supplier created.');}
        elseif (($_POST['action']??'')==='order') {
            $orderNumber='PO-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,6));
            $stmt=db()->prepare('INSERT INTO purchase_orders (organization_id,purchase_request_id,supplier_id,order_number,order_date,status,total) VALUES (?,?,?,?,?,\'issued\',?)');
            $stmt->execute([$org,$_POST['purchase_request_id']?:null,$_POST['supplier_id']?:null,$orderNumber,$_POST['order_date'],(float)$_POST['total']]);
            $poId=(int)db()->lastInsertId();
            if(trim($_POST['description']??'')!==''){
                db()->prepare('INSERT INTO purchase_order_items (purchase_order_id,description,unit,quantity,unit_price) VALUES (?,?,?,?,?)')->execute([$poId,trim($_POST['description']),trim($_POST['unit']??''),(float)$_POST['quantity'],(float)$_POST['unit_price']]);
            }
            log_activity('create','purchase_order',$poId,'Issued purchase order');flash('success','Purchase order '.$orderNumber.' issued.');
        }
        else {db()->prepare('INSERT INTO purchase_requests (organization_id,project_id,title,description,status,requested_by) VALUES (?,?,?,?,?,?)')->execute([$org,(int)$_POST['project_id'],trim($_POST['title']),trim($_POST['description']??''),'pending',current_user()['id']]);log_activity('create','purchase_request',(int)db()->lastInsertId(),'Created purchase request');flash('success','Purchase request submitted.');}
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $suppliers=db()->query("SELECT * FROM suppliers WHERE organization_id=$org ORDER BY name")->fetchAll();
    $requests=db()->query("SELECT pr.*,p.title project_title FROM purchase_requests pr JOIN projects p ON p.id=pr.project_id WHERE pr.organization_id=$org ORDER BY pr.created_at DESC")->fetchAll();
    $orders=db()->query("SELECT po.*,s.name supplier_name FROM purchase_orders po LEFT JOIN suppliers s ON s.id=po.supplier_id WHERE po.organization_id=$org ORDER BY po.created_at DESC")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Procurement</h1></div><div class="grid gap-6 lg:grid-cols-2"><div class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Purchase request</h2><form method="post" class="mt-4 space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="request"><select name="project_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input name="title" required placeholder="Request title" class="w-full rounded-lg border border-slate-300 px-3 py-2"><textarea name="description" rows="3" placeholder="Materials or items required" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Submit request</button></form></div><div class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Add supplier</h2><form method="post" class="mt-4 grid gap-3 sm:grid-cols-2"><?= csrf_field() ?><input type="hidden" name="action" value="supplier"><input name="name" required placeholder="Supplier name" class="rounded-lg border border-slate-300 px-3 py-2"><input name="contact_person" placeholder="Contact person" class="rounded-lg border border-slate-300 px-3 py-2"><input name="email" type="email" placeholder="Email" class="rounded-lg border border-slate-300 px-3 py-2"><input name="phone" placeholder="Phone" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white sm:col-span-2">Add supplier</button></form></div></div><div class="mt-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Create purchase order</h2><form method="post" class="mt-4 grid gap-3 md:grid-cols-4"><?= csrf_field() ?><input type="hidden" name="action" value="order"><select name="purchase_request_id" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Request</option><?php foreach($requests as $r):?><option value="<?= $r['id'] ?>"><?= e($r['title']) ?></option><?php endforeach?></select><select name="supplier_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Supplier</option><?php foreach($suppliers as $s):?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach?></select><input type="date" name="order_date" required value="<?= date('Y-m-d') ?>" class="rounded-lg border border-slate-300 px-3 py-2"><input name="total" type="number" step="0.01" required placeholder="Total" class="rounded-lg border border-slate-300 px-3 py-2"><input name="description" placeholder="Item description" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-2"><input name="quantity" type="number" step="0.001" placeholder="Quantity" class="rounded-lg border border-slate-300 px-3 py-2"><input name="unit_price" type="number" step="0.01" placeholder="Unit price" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white md:col-span-4">Issue order</button></form></div><div class="mt-6 grid gap-6 lg:grid-cols-3"><?= table_start(['Request','Project','Status']) ?><?php foreach($requests as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['title']) ?></td><td class="px-4 py-3"><?= e($r['project_title']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td></tr><?php endforeach?></tbody></table><?= table_start(['Order','Supplier','Status','Total']) ?><?php foreach($orders as $o):?><tr><td class="px-4 py-3 font-medium"><?= e($o['order_number']) ?></td><td class="px-4 py-3"><?= e($o['supplier_name']) ?></td><td class="px-4 py-3 capitalize"><?= e($o['status']) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$o['total'],2) ?></td></tr><?php endforeach?></tbody></table><?= table_start(['Supplier','Contact','Phone']) ?><?php foreach($suppliers as $s):?><tr><td class="px-4 py-3 font-medium"><?= e($s['name']) ?></td><td class="px-4 py-3"><?= e($s['contact_person'] ?: '—') ?></td><td class="px-4 py-3"><?= e($s['phone'] ?: '—') ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Procurement',$content,'procurement');break;

case 'expenses':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $stmt=db()->prepare('INSERT INTO expenses (organization_id,project_id,category,description,amount,expense_date,status,created_by) VALUES (?,?,?,?,?,?,\'pending\',?)');
        $stmt->execute([$org,(int)$_POST['project_id'],trim($_POST['category']),trim($_POST['description']??''),(float)$_POST['amount'],$_POST['expense_date'],current_user()['id']]);
        log_activity('create','expense',(int)db()->lastInsertId(),'Recorded expense'); flash('success','Expense recorded for approval.');
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $stmt=db()->prepare('SELECT e.*,p.title project_title FROM expenses e JOIN projects p ON p.id=e.project_id WHERE e.organization_id=? ORDER BY e.expense_date DESC');
    $stmt->execute([$org]);$rows=$stmt->fetchAll();
    ob_start();?><div class="mb-5 flex justify-between"><h1 class="text-2xl font-semibold">Expenses</h1><a href="<?= e(base_url('export/expenses')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Export CSV</a></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-5"><?= csrf_field() ?><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><select name="category" class="rounded-lg border border-slate-300 px-3 py-2"><option>Labour</option><option>Material</option><option>Equipment</option><option>Contractor</option><option>Other</option></select><input name="amount" type="number" step="0.01" required placeholder="Amount" class="rounded-lg border border-slate-300 px-3 py-2"><input name="expense_date" type="date" required value="<?= date('Y-m-d') ?>" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Record</button><input name="description" placeholder="Description" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-5"></form></div><?= table_start(['Expense','Project','Category','Amount','Date','Status']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['description'] ?: 'Expense') ?></td><td class="px-4 py-3"><?= e($r['project_title']) ?></td><td class="px-4 py-3"><?= e($r['category']) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$r['amount'],2) ?></td><td class="px-4 py-3"><?= e($r['expense_date']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Expenses',$content,'expenses');break;

case 'gantt':
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(($_POST['action']??'')==='depend'){
            $stmt=db()->prepare('INSERT IGNORE INTO task_dependencies (task_id,depends_on_task_id) VALUES (?,?)');
            $stmt->execute([(int)$_POST['task_id'],(int)$_POST['depends_on']]);flash('success','Task dependency added.');
        }
    }
    $stmt=db()->prepare('SELECT t.*,p.title project_title FROM tasks t JOIN projects p ON p.id=t.project_id WHERE t.organization_id=? ORDER BY p.title,t.start_date,t.due_date');
    $stmt->execute([$org]);$tasks=$stmt->fetchAll();
    $deps=db()->query("SELECT td.*,t.title task_title,d.title depends_title FROM task_dependencies td JOIN tasks t ON t.id=td.task_id JOIN tasks d ON d.id=td.depends_on_task_id WHERE t.organization_id=$org")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Schedule & Gantt</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-4"><?= csrf_field() ?><input type="hidden" name="action" value="depend"><select name="task_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Task</option><?php foreach($tasks as $t):?><option value="<?= $t['id'] ?>"><?= e($t['title']) ?></option><?php endforeach?></select><select name="depends_on" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Depends on</option><?php foreach($tasks as $t):?><option value="<?= $t['id'] ?>"><?= e($t['title']) ?></option><?php endforeach?></select><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Add dependency</button></form></div><div class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-5"><div class="grid min-w-[720px] grid-cols-[220px_1fr] gap-y-3"><?php foreach($tasks as $t):?><div class="pr-4 text-sm"><p class="font-medium"><?= e($t['title']) ?></p><p class="text-xs text-slate-500"><?= e($t['project_title']) ?></p></div><div class="flex h-10 items-center"><div class="h-6 min-w-10 rounded-md border border-orange-200 bg-orange-100 px-2 py-1 text-xs" style="width:<?= max(8,(int)$t['progress']) ?>%"><?= (int)$t['progress'] ?>%</div></div><?php endforeach?></div></div><div class="mt-6"><?= table_start(['Task','Depends on']) ?><?php foreach($deps as $d):?><tr><td class="px-4 py-3"><?= e($d['task_title']) ?></td><td class="px-4 py-3"><?= e($d['depends_title']) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Gantt',$content,'gantt');break;

case 'profile':
    $uid=(int)current_user()['id'];
    if($_SERVER['REQUEST_METHOD']==='POST'){
        db()->prepare('UPDATE users SET name=?,phone=? WHERE id=?')->execute([trim($_POST['name']),trim($_POST['phone']??''),$uid]);
        if(!empty($_POST['password'])){
            if(strlen($_POST['password'])<8)flash('error','Password must be at least 8 characters.');
            else db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$uid]);
        }
        $_SESSION['user']['name']=trim($_POST['name']);flash('success','Profile updated.');
    }
    $stmt=db()->prepare('SELECT * FROM users WHERE id=?');$stmt->execute([$uid]);$userRow=$stmt->fetch();
    ob_start();?><div class="max-w-xl"><h1 class="mb-5 text-2xl font-semibold">Profile</h1><form method="post" class="rounded-xl border border-slate-200 bg-white p-6 space-y-4"><?= csrf_field() ?><?= form_field('Full name','name',$userRow['name']) ?><?= form_field('Phone','phone',$userRow['phone']??'','text',false) ?><?= form_field('Email','email',$userRow['email'],'email',false) ?><script>document.querySelector('[name=email]').disabled=true;</script><?= form_field('New password','password','','password',false) ?><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Save profile</button></form></div><?php $content=ob_get_clean();render('Profile',$content,'profile');break;

case 'organization':
    require_role(['organization_admin']);
    $stmt=db()->prepare('SELECT * FROM organizations WHERE id=?');$stmt->execute([$org]);$orgRow=$stmt->fetch();
    if($_SERVER['REQUEST_METHOD']==='POST'){
        db()->prepare('UPDATE organizations SET name=?,address=?,phone=?,email=?,currency=?,timezone=? WHERE id=?')->execute([trim($_POST['name']),trim($_POST['address']??''),trim($_POST['phone']??''),trim($_POST['email']??''),trim($_POST['currency']??'NPR'),trim($_POST['timezone']??'Asia/Kathmandu'),$org]);
        $_SESSION['organization_name']=trim($_POST['name']);flash('success','Organization settings saved.');
        redirect(base_url('organization'));
    }
    ob_start();?><h1 class="mb-5 text-2xl font-semibold">Organization settings</h1><form method="post" class="grid max-w-3xl gap-4 rounded-xl border border-slate-200 bg-white p-6 md:grid-cols-2"><?= csrf_field() ?><?= form_field('Organization name','name',$orgRow['name']) ?><?= form_field('Email','email',$orgRow['email']??'','email',false) ?><?= form_field('Phone','phone',$orgRow['phone']??'','text',false) ?><?= form_field('Currency','currency',$orgRow['currency'],'text') ?><label class="md:col-span-2"><span class="mb-1.5 block text-sm font-medium">Address</span><textarea name="address" class="w-full rounded-lg border border-slate-300 px-3 py-2"><?= e($orgRow['address']??'') ?></textarea></label><input type="hidden" name="timezone" value="<?= e($orgRow['timezone']) ?>"><button class="w-fit rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white md:col-span-2">Save settings</button></form><?php $content=ob_get_clean();render('Organization',$content,'organization');break;

case 'portal':
    $role=$_SESSION['role']??'';
    $stmt=db()->prepare('SELECT p.*,c.name client_name FROM projects p LEFT JOIN clients c ON c.id=p.client_id WHERE p.organization_id=? AND p.status<>\'cancelled\' ORDER BY p.created_at DESC');
    $stmt->execute([$org]);$projects=$stmt->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Client Portal</h1><p class="mt-1 text-sm text-slate-500">Shared project and approved document view.</p></div><div class="grid gap-4 md:grid-cols-2"><?php foreach($projects as $p):?><article class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex justify-between"><div><p class="text-xs text-slate-500"><?= e($p['client_name'] ?: 'Organization project') ?></p><h2 class="mt-1 font-semibold"><?= e($p['title']) ?></h2></div><span class="text-sm text-slate-500"><?= (int)$p['progress'] ?>%</span></div><div class="mt-4 h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width:<?= (int)$p['progress'] ?>%"></div></div></article><?php endforeach?></div><?php $content=ob_get_clean();render('Client portal',$content,'portal');break;

case 'notifications':
    $uid=(int)current_user()['id'];
    if($_SERVER['REQUEST_METHOD']==='POST'){db()->prepare('UPDATE notifications SET is_read=1 WHERE organization_id=? AND user_id=?')->execute([$org,$uid]);flash('success','Notifications marked as read.');}
    $stmt=db()->prepare('SELECT * FROM notifications WHERE organization_id=? AND user_id=? ORDER BY created_at DESC');$stmt->execute([$org,$uid]);$rows=$stmt->fetchAll();
    ob_start();?><div class="mb-5 flex justify-between"><h1 class="text-2xl font-semibold">Notifications</h1><form method="post"><?= csrf_field() ?><button class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Mark all read</button></form></div><div class="divide-y rounded-xl border border-slate-200 bg-white"><?php foreach($rows as $r):?><article class="p-4"><p class="font-medium"><?= e($r['title']) ?></p><p class="mt-1 text-sm text-slate-500"><?= e($r['body']) ?></p></article><?php endforeach;if(!$rows):?><p class="p-8 text-center text-sm text-slate-500">No notifications.</p><?php endif?></div><?php $content=ob_get_clean();render('Notifications',$content,'notifications');break;

case 'approvals':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        require_role(['organization_admin','project_manager','civil_engineer','accountant']);
        if (isset($_POST['approve_id'])) {db()->prepare("UPDATE approvals SET status=? WHERE id=? AND organization_id=?")->execute([$_POST['decision'],(int)$_POST['approve_id'],$org]);flash('success','Approval decision recorded.');}
        else {db()->prepare('INSERT INTO approvals (organization_id,entity_type,entity_id,title,status,requested_by) VALUES (?,?,?,?,\'pending\',?)')->execute([$org,trim($_POST['entity_type']),1,trim($_POST['title']),current_user()['id']]);flash('success','Approval request created.');}
    }
    $rows=db()->query("SELECT * FROM approvals WHERE organization_id=$org ORDER BY created_at DESC")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Approvals</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-4"><?= csrf_field() ?><input name="title" required placeholder="Approval title" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-2"><select name="entity_type" class="rounded-lg border border-slate-300 px-3 py-2"><option>expense</option><option>purchase</option><option>boq</option><option>document</option><option>calculation</option></select><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Create request</button></form></div><?= table_start(['Title','Type','Status','Decision']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['title']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['entity_type']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td><td class="px-4 py-3"><?php if($r['status']==='pending'):?><form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="approve_id" value="<?= $r['id'] ?>"><button name="decision" value="approved" class="rounded-md bg-emerald-50 px-2 py-1 text-xs text-emerald-700">Approve</button><button name="decision" value="rejected" class="rounded-md bg-red-50 px-2 py-1 text-xs text-red-700">Reject</button></form><?php else:?>—<?php endif?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Approvals',$content,'approvals');break;

case 'reports':
    $progress=db()->query("SELECT title,status,progress,budget FROM projects WHERE organization_id=$org ORDER BY created_at DESC")->fetchAll();
    $expenses=db()->query("SELECT category,COALESCE(SUM(amount),0) total FROM expenses WHERE organization_id=$org GROUP BY category")->fetchAll();
    ob_start();?><div class="mb-5 flex justify-between"><h1 class="text-2xl font-semibold">Reports & Analytics</h1><div class="flex gap-2"><a href="<?= e(base_url('export/projects')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Project CSV</a><a href="<?= e(base_url('export/expenses')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Expense CSV</a></div></div><div class="grid gap-6 lg:grid-cols-2"><section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Project progress</h2><div class="mt-5 space-y-4"><?php foreach($progress as $p):?><div><div class="flex justify-between text-sm"><span><?= e($p['title']) ?></span><span><?= (int)$p['progress'] ?>%</span></div><div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-orange-500" style="width:<?= (int)$p['progress'] ?>%"></div></div></div><?php endforeach;if(!$progress):?><p class="text-sm text-slate-500">No project data.</p><?php endif?></div></section><section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Expenditure by category</h2><?= table_start(['Category','Amount']) ?><?php foreach($expenses as $e):?><tr><td class="px-4 py-3"><?= e($e['category']) ?></td><td class="px-4 py-3 font-medium">NPR <?= number_format((float)$e['total'],2) ?></td></tr><?php endforeach?></tbody></table></section></div><?php $content=ob_get_clean();render('Reports',$content,'reports');break;

default:
    http_response_code(404); echo '<main style="padding:40px;font-family:sans-serif"><h1>Page not found</h1><a href="'.e(base_url('dashboard')).'">Return to dashboard</a></main>';
}
