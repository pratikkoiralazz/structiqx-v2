<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/core/bootstrap.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$base = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base && str_starts_with($path, $base)) $path = trim(substr($path, strlen($base)), '/');
$route = $path === '' ? 'home' : $path;
$page = explode('/', $route)[0];

if ($route === 'forgot-password') $route = 'forgot';
if (str_starts_with($route, 'reset-password/')) $route = 'reset/' . substr($route, strlen('reset-password/'));

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

function spreadsheet_xml(string $title, array $headers, array $rows): string {
    $xml = '<?xml version="1.0"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    $xml .= '<Worksheet ss:Name="' . e(substr($title,0,31)) . '"><Table>';
    $xml .= '<Row>';
    foreach ($headers as $header) $xml .= '<Cell><Data ss:Type="String">' . e((string)$header) . '</Data></Cell>';
    $xml .= '</Row>';
    foreach ($rows as $row) {
        $xml .= '<Row>';
        foreach ($row as $value) {
            $numeric = is_numeric($value);
            $xml .= '<Cell><Data ss:Type="' . ($numeric?'Number':'String') . '">' . e((string)$value) . '</Data></Cell>';
        }
        $xml .= '</Row>';
    }
    return $xml . '</Table></Worksheet></Workbook>';
}

function simple_pdf(string $title, array $headers, array $rows): string {
    $lines = [$title, implode(' | ', $headers)];
    foreach ($rows as $row) $lines[] = implode(' | ', array_map('strval', $row));
    $stream = "BT\n/F1 10 Tf\n50 790 Td\n";
    $y = 0;
    foreach ($lines as $line) {
        $safe = str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], substr($line,0,145));
        $stream .= "($safe) Tj\n";
        $stream .= "0 -16 Td\n";
        $y++;
        if ($y >= 44) break;
    }
    $stream .= "ET";
    $objects = [
        '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
        '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
        '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
        '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        '5 0 obj << /Length ' . strlen($stream) . ' >> stream' . "\n" . $stream . "\n" . 'endstream endobj',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object . "\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach (array_slice($offsets,1) as $offset) $pdf .= sprintf("%010d 00000 n \n",$offset);
    $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $pdf;
}

function render_public(string $title, string $content, string $activePage = '', string $metaDescription = ''): void {
    require dirname(__DIR__) . '/app/views/public_layout.php';
}

function features_page_content(): string {
    ob_start();
    ?>
    <main class="sq-features">
      <section class="sq-page-hero">
        <div class="sq-container">
          <span class="sq-eyebrow">A guided tour of the workspace</span>
          <h1>See your whole project operation in one place</h1>
          <p>Explore the dashboard and the tools behind it: project delivery, schedules, costs, engineering review, site records, procurement and reporting.</p>
          <nav class="sq-feature-nav" aria-label="Feature sections">
            <a href="#overview">Dashboard</a><a href="#delivery">Projects &amp; schedule</a><a href="#commercial">Budgets &amp; BOQ</a><a href="#engineering">Engineering</a><a href="#site">Site &amp; documents</a><a href="#procurement">Procurement &amp; clients</a><a href="#reporting">Reports</a>
          </nav>
        </div>
      </section>
      <section class="sq-section sq-feature-section" id="overview">
        <div class="sq-container">
          <div class="sq-section-head"><span class="sq-eyebrow">The project command center</span><h2>Start with the signals that matter</h2><p>Portfolio metrics, active work, budget snapshots and approaching deadlines are brought together for a quick daily overview.</p></div>
          <figure class="sq-product-preview"><img src="<?= e(base_url('assets/features/workspace-dashboard.svg')) ?>" alt="Illustrative STRUCTIQX dashboard showing project and task metrics, progress chart, financial snapshot, recent projects and upcoming deadlines"><figcaption>Illustrative product preview. Figures and project names are examples.</figcaption></figure>
          <div class="sq-feature-points"><article><strong>Portfolio at a glance</strong><span>See total, active, completed and delayed projects alongside open tasks and clients.</span></article><article><strong>Track delivery and cost</strong><span>Compare project progress with budget and actual expenditure.</span></article><article><strong>Stay ahead of deadlines</strong><span>Review upcoming milestones and recent activity from the same workspace.</span></article></div>
        </div>
      </section>
      <section class="sq-section sq-section-alt sq-feature-section" id="delivery">
        <div class="sq-container">
          <div class="sq-section-head"><span class="sq-eyebrow">01 · Plan and deliver</span><h2>Projects, people and schedules stay connected</h2><p>Set up client-linked projects, assign team members, break work into tasks and milestones, and follow dependencies on a visual schedule.</p></div>
          <figure class="sq-product-preview"><img src="<?= e(base_url('assets/features/projects-schedule.svg')) ?>" alt="Illustrative project schedule with tasks, progress bars, milestones and a today marker"><figcaption>Illustrative product preview. Names, dates and progress are examples.</figcaption></figure>
          <div class="sq-feature-points"><article><strong>Projects and clients</strong><span>Keep scope, location, budget, client and project ownership together.</span></article><article><strong>Tasks, milestones and Gantt</strong><span>Follow owners, due dates, dependencies, progress and schedule status.</span></article><article><strong>Team coordination</strong><span>Use organization and project roles to make responsibilities clear.</span></article></div>
        </div>
      </section>
      <section class="sq-section sq-feature-section" id="commercial">
        <div class="sq-container">
          <div class="sq-section-head"><span class="sq-eyebrow">02 · Control the commercial work</span><h2>Understand estimate, commitment and actual cost</h2><p>Build quantity-based BOQs, track project budgets and revisions, record expenses and monitor procurement against the plan.</p></div>
          <figure class="sq-product-preview"><img src="<?= e(base_url('assets/features/budgets-boq.svg')) ?>" alt="Illustrative commercial dashboard showing project budget, expenses, cost categories and a bill of quantities"><figcaption>Illustrative product preview. Rates and amounts are examples.</figcaption></figure>
          <div class="sq-feature-points"><article><strong>Budget visibility</strong><span>Compare approved budget values with expenses recorded to date.</span></article><article><strong>BOQ and rate analysis</strong><span>Review item descriptions, units, quantities, rates and calculated amounts.</span></article><article><strong>Expenses and revisions</strong><span>Record expenses and keep budget changes visible to the project team.</span></article></div>
        </div>
      </section>
      <section class="sq-section sq-section-alt sq-feature-section" id="engineering">
        <div class="sq-container">
          <div class="sq-section-head"><span class="sq-eyebrow">03 · Document engineering work</span><h2>Keep calculation inputs and review status together</h2><p>Save structured calculation drafts against projects, with inputs, formulas, steps, results and explicit review status.</p></div>
          <figure class="sq-product-preview"><img src="<?= e(base_url('assets/features/engineering-studio.svg')) ?>" alt="Illustrative engineering calculation form with dimensional inputs, formula, result and a professional review workflow"><figcaption>Illustrative product preview. Calculation inputs and results are examples.</figcaption></figure>
          <aside class="sq-safety-note"><strong>Professional review matters.</strong> Calculations are draft tools, not approved engineering designs. Qualified professional verification is required.</aside>
        </div>
      </section>
      <section class="sq-section sq-feature-section" id="site">
        <div class="sq-container">
          <div class="sq-section-head"><span class="sq-eyebrow">04 · Record site activity</span><h2>Bring field updates and project files into view</h2><p>Capture daily work, weather, labour, issues and site photos alongside controlled project documents and approval status.</p></div>
          <figure class="sq-product-preview"><img src="<?= e(base_url('assets/features/site-documents.svg')) ?>" alt="Illustrative daily site report and document register with review status"><figcaption>Illustrative product preview. Report details and file names are examples.</figcaption></figure>
          <div class="sq-feature-points"><article><strong>Daily site reports</strong><span>Save work completed, labour, materials, equipment, safety observations and photos.</span></article><article><strong>Document register</strong><span>Organize project files by category, version, status and access level.</span></article><article><strong>Controlled sharing</strong><span>Separate private, team and client-access documents.</span></article></div>
        </div>
      </section>
      <section class="sq-section sq-section-alt sq-feature-section" id="procurement">
        <div class="sq-container">
          <div class="sq-section-head"><span class="sq-eyebrow">05 · Coordinate decisions and supply</span><h2>Track requests, approvals and client updates</h2><p>Follow purchase requests through supplier orders and delivery records, while sharing selected project progress and approved documents with clients.</p></div>
          <figure class="sq-product-preview"><img src="<?= e(base_url('assets/features/procurement-client.svg')) ?>" alt="Illustrative procurement screen showing purchase orders, approval history and a client project progress view"><figcaption>Illustrative product preview. Supplier, order and project details are examples.</figcaption></figure>
          <div class="sq-feature-points"><article><strong>Procurement flow</strong><span>Manage suppliers, purchase requests, orders and recorded deliveries.</span></article><article><strong>Approval history</strong><span>Keep decision status and responsibility easy to follow.</span></article><article><strong>Client portal</strong><span>Present relevant progress and only the information shared for client access.</span></article></div>
        </div>
      </section>
      <section class="sq-section sq-feature-section" id="reporting">
        <div class="sq-container">
          <div class="sq-section-head"><span class="sq-eyebrow">06 · Review and report</span><h2>Turn day-to-day project data into useful reports</h2><p>Get a clearer view of budgets, expenses, progress, open work and project health, then export information for further review.</p></div>
          <figure class="sq-product-preview"><img src="<?= e(base_url('assets/features/analytics-reports.svg')) ?>" alt="Illustrative project analytics screen comparing planned and actual spending, project health, progress and reporting"><figcaption>Illustrative product preview. Portfolio figures and trends are examples.</figcaption></figure>
          <div class="sq-feature-points"><article><strong>Project and cost summaries</strong><span>See portfolio budget, actual spend, task completion and delivery signals.</span></article><article><strong>Exportable reports</strong><span>Export available report data in CSV, Excel-compatible and PDF formats.</span></article><article><strong>One connected workspace</strong><span>Move from summary to project, schedule, cost or site records to follow up.</span></article></div>
        </div>
      </section>
      <section class="sq-section"><div class="sq-container"><div class="sq-cta"><div><h2>Ready to see it with your own projects?</h2><p>Set up your organization and explore the STRUCTIQX workspace.</p></div><div class="sq-hero-actions"><a class="sq-btn sq-btn-accent" href="<?= e(base_url('register')) ?>">Get started</a><a class="sq-btn" href="<?= e(base_url('pricing')) ?>">View pricing</a></div></div></div></section>
    </main>
    <?php
    return (string)ob_get_clean();
}

function cms_public_content(string $slug): ?array {
    $stmt=db()->prepare("SELECT * FROM cms_pages WHERE slug=? AND status='published' AND is_visible=1");
    $stmt->execute([$slug]);$page=$stmt->fetch();
    if (!$page) return null;
    $stmt=db()->prepare('SELECT * FROM cms_page_sections WHERE page_id=? AND is_visible=1 ORDER BY sort_order,id');
    $stmt->execute([(int)$page['id']]);$sections=$stmt->fetchAll();
    if (!$sections && $slug!=='pricing') return null;
    $html='<main>';
    foreach($sections as $index=>$section) {
        $heading=trim((string)$section['heading']);
        $description=trim((string)$section['description']);
        $isHero=$section['section_key']==='hero' || $index===0;
        $html.=$isHero?'<section class="sq-page-hero"><div class="sq-container">':'<section class="sq-section"><div class="sq-container">';
        if($heading!=='') $html.='<h'.($isHero?'1':'2').'>'.e($heading).'</h'.($isHero?'1':'2').'>';
        if($description!=='') $html.='<p>'.nl2br(e($description)).'</p>';
        $image=trim((string)$section['image_path']);
        if($image!=='' && preg_match('#^/?media/[a-zA-Z0-9._-]+$#',$image)) $html.='<img class="sq-cms-image" src="'.e(base_url(ltrim($image,'/'))).'" alt="">';
        if(trim((string)$section['cta_text'])!=='') {
            $url=trim((string)$section['cta_url']);
            if(preg_match('#^https://#i',$url) && filter_var($url,FILTER_VALIDATE_URL)) $href=$url;
            elseif(preg_match('#^/?[a-zA-Z0-9/_-]*$#',$url)) $href=base_url(ltrim($url,'/'));
            else $href='';
            if($href!=='') $html.='<p><a class="sq-btn sq-btn-accent" href="'.e($href).'">'.e($section['cta_text']).'</a></p>';
        }
        $items=json_decode((string)$section['content_json'],true);
        if(is_array($items) && $items) {
            $html.='<div class="sq-grid">';
            foreach($items as $item) {
                if(is_string($item)) $item=['heading'=>$item,'description'=>''];
                if(!is_array($item)) continue;
                $html.='<article class="sq-card">';
                if(!empty($item['heading'])) $html.='<h3>'.e($item['heading']).'</h3>';
                if(!empty($item['description'])) $html.='<p>'.nl2br(e($item['description'])).'</p>';
                $html.='</article>';
            }
            $html.='</div>';
        }
        $html.='</div></section>';
    }
    if($slug==='pricing') {
        $plans=db()->query('SELECT name,description,monthly_price,annual_price,currency,organization_limit,user_limit FROM subscription_plans WHERE is_enabled=1 ORDER BY monthly_price,id')->fetchAll();
        $html.='<section class="sq-section"><div class="sq-container"><div class="sq-pricing">';
        foreach($plans as $plan) $html.='<article class="sq-card sq-price"><h3>'.e($plan['name']).'</h3><div class="price">'.e($plan['currency']).' '.e(number_format((float)$plan['monthly_price'],2)).' / month</div><p>'.e($plan['description']).'</p><p>'.($plan['user_limit']===null?'Flexible users':(int)$plan['user_limit'].' users').' · '.($plan['organization_limit']===null?'Flexible organizations':(int)$plan['organization_limit'].' organization(s)').'</p><a class="sq-btn sq-btn-accent" href="'.e(base_url('contact')).'">Contact us</a></article>';
        $html.='</div></div></section>';
    }
    if($slug==='contact') {
        $html.='<section class="sq-section"><div class="sq-container" style="max-width:700px">';
        foreach(consume_flash() as $notice) $html.='<div class="'.($notice['type']==='error'?'sq-error':'sq-success').'">'.e($notice['message']).'</div>';
        $html.='<form class="sq-card" method="post">'.csrf_field().'<div class="sq-form-row"><div class="sq-field"><label>Name</label><input name="name" required></div><div class="sq-field"><label>Work email</label><input name="email" type="email" required></div></div><div class="sq-form-row"><div class="sq-field"><label>Phone</label><input name="phone"></div><div class="sq-field"><label>Organization</label><input name="organization"></div></div><div class="sq-field"><label>Subject</label><input name="subject" value="General enquiry"></div><div class="sq-field"><label>Message</label><textarea name="message" rows="6" required></textarea></div><button class="sq-btn sq-btn-accent" type="submit">Send message</button></form></div></section>';
    }
    $html.='</main>';
    return ['title'=>$page['meta_title']?:$page['page_title'],'description'=>$page['meta_description']?:'STRUCTIQX civil engineering project management platform.','html'=>$html];
}

if (preg_match('#^media/([a-f0-9]{40}\.(?:jpg|png|webp))$#',$route,$mediaMatch)) {
    $stmt=db()->prepare('SELECT stored_name,mime_type,file_size FROM cms_media WHERE stored_name=?');
    $stmt->execute([$mediaMatch[1]]);$media=$stmt->fetch();
    $file=dirname(__DIR__).'/storage/cms_media/'.($media['stored_name']??'');
    if(!$media || !is_file($file)){http_response_code(404);exit('Media not found.');}
    header('Content-Type: '.$media['mime_type']);
    header('Content-Length: '.(int)$media['file_size']);
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($file);exit;
}

if (str_starts_with($route,'accept-invitation/')) {
    $token=substr($route,strlen('accept-invitation/'));
    $stmt=db()->prepare('SELECT * FROM organization_invitations WHERE token=? AND accepted_at IS NULL AND expires_at>NOW()');
    $stmt->execute([$token]);$invitation=$stmt->fetch();
    if(!$invitation){http_response_code(410);die('Invitation is invalid or has expired.');}
    if(!current_user()){$_SESSION['intended_url']=base_url('accept-invitation/'.$token);redirect(base_url('login'));}
    $uid=(int)current_user()['id'];
    $stmt=db()->prepare('SELECT email FROM users WHERE id=?');$stmt->execute([$uid]);$email=(string)$stmt->fetchColumn();
    if(strtolower($email)!==strtolower($invitation['email'])){http_response_code(403);die('Sign in with the invited email address.');}
    db()->beginTransaction();
    db()->prepare("INSERT INTO organization_members (organization_id,user_id,role,status) VALUES (?,?,?, 'active') ON DUPLICATE KEY UPDATE role=VALUES(role),status='active'")->execute([(int)$invitation['organization_id'],$uid,$invitation['role']]);
    db()->prepare('UPDATE organization_invitations SET accepted_at=NOW() WHERE id=?')->execute([(int)$invitation['id']]);
    db()->commit();
    $_SESSION['organization_id']=(int)$invitation['organization_id'];
    $stmt=db()->prepare('SELECT name FROM organizations WHERE id=?');$stmt->execute([(int)$invitation['organization_id']]);$_SESSION['organization_name']=$stmt->fetchColumn();
    $_SESSION['role']=$invitation['role'];
    redirect(base_url('dashboard'));
}

if (str_starts_with($route,'verify-email/')) {
    $token=substr($route,strlen('verify-email/'));
    $stmt=db()->prepare('SELECT user_id FROM email_verification_tokens WHERE token=? AND used_at IS NULL AND expires_at>NOW()');
    $stmt->execute([$token]);$uid=(int)$stmt->fetchColumn();
    if($uid){
        db()->beginTransaction();
        db()->prepare('UPDATE users SET email_verified_at=COALESCE(email_verified_at,NOW()) WHERE id=?')->execute([$uid]);
        db()->prepare('UPDATE email_verification_tokens SET used_at=NOW() WHERE token=?')->execute([$token]);
        db()->commit();redirect(base_url('login'));
    }
    http_response_code(410);die('Verification link is invalid or has expired.');
}

$publicPages = ['home','features','solutions','pricing','about','contact'];
$customCmsRoute=false;
$reservedRoutes=['admin','login','register','logout','forgot','forgot-password','reset','reset-password','install','dashboard','projects','project','tasks','schedule','budgets','expenses','boq','calculations','site','documents','procurement','approvals','portal','reports','settings','profile','clients','team','download','export','media','accept-invitation','verify-email'];
if (!in_array($route,$publicPages,true) && !in_array($route,$reservedRoutes,true) && preg_match('/^[a-z0-9-]{1,80}$/',$route)) {
    $stmt=db()->prepare("SELECT 1 FROM cms_pages WHERE slug=? AND status='published' AND is_visible=1");
    $stmt->execute([$route]);$customCmsRoute=(bool)$stmt->fetchColumn();
}
if (in_array($route,$publicPages,true) || $customCmsRoute) {
    if (current_user() && $route === 'home') redirect(base_url('dashboard'));
    if ($route === 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS contact_messages (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(40),
              organization VARCHAR(160), subject VARCHAR(180), message TEXT NOT NULL,
              status ENUM('new','read','closed') NOT NULL DEFAULT 'new',
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            $stmt=db()->prepare('INSERT INTO contact_messages (name,email,phone,organization,subject,message) VALUES (?,?,?,?,?,?)');
            $stmt->execute([trim((string)$_POST['name']),strtolower(trim((string)$_POST['email'])),trim((string)($_POST['phone']??'')),trim((string)($_POST['organization']??'')),trim((string)($_POST['subject']??'General enquiry')),trim((string)$_POST['message'])]);
            flash('success','Your message has been sent. Our team will contact you shortly.');
        } catch (Throwable $e) { flash('error','The message could not be sent right now.'); }
        redirect(base_url('contact'));
    }
    $cmsPage=cms_public_content($route);
    if($cmsPage!==null) {
        render_public($cmsPage['title'],$cmsPage['html'],$route,$cmsPage['description']);
        exit;
    }
    if($customCmsRoute) {http_response_code(404);exit('This page has no published content yet.');}
    if ($route === 'features') {
        render_public('Features',features_page_content(),'features','Explore the STRUCTIQX project dashboard, scheduling, budgets, BOQ, engineering, site reports, documents, procurement and analytics.');
        exit;
    }
    ob_start();
    if ($route === 'home') {
        ?>
        <main>
          <section class="sq-hero"><div class="sq-container sq-hero-grid"><div><span class="sq-eyebrow">Civil engineering work, unified</span><h1>One Platform. Every Project. <span>Complete Control.</span></h1><p class="sq-hero-copy">Manage construction projects, engineering calculations, budgets, teams and site operations — all from one powerful workspace.</p><div class="sq-hero-actions"><a class="sq-btn sq-btn-accent" href="<?= e(base_url('register')) ?>">Get Started</a><a class="sq-btn" href="<?= e(base_url('features')) ?>">Explore Features</a></div></div><div class="sq-preview"><div class="sq-window"><div class="sq-window-bar"><i class="sq-dot"></i><i class="sq-dot"></i><i class="sq-dot"></i></div><div class="sq-app"><div class="sq-sidebar"><span></span><span></span><span></span><span></span><span></span></div><div class="sq-dashboard"><div class="sq-dash-head"></div><div class="sq-metrics"><div class="sq-metric"><i></i><b></b></div><div class="sq-metric"><i></i><b></b></div><div class="sq-metric"><i></i><b></b></div></div><div class="sq-chart"></div></div></div></div></div></section>
          <section class="sq-section"><div class="sq-container"><div class="sq-section-head"><span class="sq-eyebrow">The complete workspace</span><h2>From estimate to handover</h2><p>Keep every discipline, decision and deadline connected.</p></div><div class="sq-grid"><article class="sq-card"><div class="sq-card-icon">P</div><h3>Project delivery</h3><p>Plan projects, assign teams, track milestones and maintain full project visibility.</p></article><article class="sq-card"><div class="sq-card-icon">C</div><h3>Engineering studio</h3><p>Run calculation workflows with structured inputs, formulas and professional review controls.</p></article><article class="sq-card"><div class="sq-card-icon">B</div><h3>Commercial control</h3><p>Manage budgets, expenses, BOQs, procurement and planned versus actual cost.</p></article></div></div></section>
          <section class="sq-section sq-section-alt"><div class="sq-container sq-split"><div><span class="sq-eyebrow">How it works</span><h2 style="font-size:42px;letter-spacing:-.04em">One system for the whole project lifecycle</h2></div><div class="sq-list"><div class="sq-list-item"><div class="sq-number">1</div><div><h3>Set up your workspace</h3><p>Create your organization and invite your team with role-based access.</p></div></div><div class="sq-list-item"><div class="sq-number">2</div><div><h3>Plan and estimate</h3><p>Build projects, budgets, BOQs, schedules and assignments.</p></div></div><div class="sq-list-item"><div class="sq-number">3</div><div><h3>Deliver with confidence</h3><p>Capture site progress, approvals, costs and engineering reviews.</p></div></div></div></div></section>
          <section class="sq-section"><div class="sq-container"><div class="sq-section-head"><span class="sq-eyebrow">Transparent plans</span><h2>Start with the workspace you need</h2></div><div class="sq-pricing"><article class="sq-card sq-price"><h3>Essential</h3><div class="price">NPR 0</div><p>For teams evaluating STRUCTIQX.</p><ul><li>Project management</li><li>Tasks and milestones</li><li>Team workspace</li></ul><a class="sq-btn" href="<?= e(base_url('register')) ?>">Get Started</a></article><article class="sq-card sq-price featured"><h3>Professional</h3><div class="price">Custom</div><p>For growing engineering organizations.</p><ul><li>Everything in Essential</li><li>BOQ and procurement</li><li>Engineering reviews</li></ul><a class="sq-btn sq-btn-accent" href="<?= e(base_url('contact')) ?>">Contact Sales</a></article><article class="sq-card sq-price"><h3>Enterprise</h3><div class="price">Custom</div><p>For multi-office operations.</p><ul><li>Advanced organization controls</li><li>Custom workflows</li><li>Priority support</li></ul><a class="sq-btn" href="<?= e(base_url('contact')) ?>">Talk to Us</a></article></div></div></section>
          <section class="sq-section"><div class="sq-container"><div class="sq-cta"><div><h2>Put every project in one clear view.</h2><p>Create your STRUCTIQX organization and start delivering.</p></div><a class="sq-btn sq-btn-accent" href="<?= e(base_url('register')) ?>">Get Started</a></div></div></section>
          <section class="sq-section sq-section-alt"><div class="sq-container sq-faq"><div class="sq-section-head"><span class="sq-eyebrow">FAQ</span><h2>Frequently asked questions</h2></div><details><summary>Is STRUCTIQX only for large construction companies?</summary><p>No. It supports consultancies, contractors, project managers and growing engineering teams.</p></details><details><summary>Can one organization isolate its projects and users?</summary><p>Yes. Every project and application record is scoped to its organization.</p></details><details><summary>Can approved documents be shared with clients?</summary><p>Yes. Documents can be marked for client access and reviewed through the client portal.</p></details></div></section>
        </main>
        <?php
    } elseif ($route === 'solutions') {
        ?><main><section class="sq-page-hero"><div class="sq-container"><span class="sq-eyebrow">Solutions</span><h1>Built for real engineering operations</h1><p>Whether you consult, manage or construct, STRUCTIQX keeps project information aligned.</p></div></section><section class="sq-section"><div class="sq-container sq-grid"><article class="sq-card"><h3>Engineering consultancies</h3><p>Organize calculations, drawing reviews, BOQs, project teams and client communication.</p></article><article class="sq-card"><h3>Construction companies</h3><p>Coordinate site work, costs, procurement, daily reports and project progress.</p></article><article class="sq-card"><h3>Project managers</h3><p>View schedules, risks, approvals and delivery status from one dashboard.</p></article></div></section></main><?php
    } elseif ($route === 'pricing') {
        ?><main><section class="sq-page-hero"><div class="sq-container"><span class="sq-eyebrow">Pricing</span><h1>Flexible for every organization</h1><p>Choose the operating model that matches your team and project portfolio.</p></div></section><section class="sq-section"><div class="sq-container sq-pricing"><article class="sq-card sq-price"><h3>Essential</h3><div class="price">NPR 0</div><ul><li>Core project workspace</li><li>Team and task management</li></ul><a class="sq-btn" href="<?= e(base_url('register')) ?>">Get Started</a></article><article class="sq-card sq-price featured"><h3>Professional</h3><div class="price">Custom</div><ul><li>Full commercial suite</li><li>Site and document operations</li><li>Calculation reviews</li></ul><a class="sq-btn sq-btn-accent" href="<?= e(base_url('contact')) ?>">Contact Sales</a></article><article class="sq-card sq-price"><h3>Enterprise</h3><div class="price">Custom</div><ul><li>Multiple organizations</li><li>Advanced controls</li><li>Dedicated support</li></ul><a class="sq-btn" href="<?= e(base_url('contact')) ?>">Talk to Us</a></article></div></section></main><?php
    } elseif ($route === 'about') {
        ?><main><section class="sq-page-hero"><div class="sq-container"><span class="sq-eyebrow">About STRUCTIQX</span><h1>Engineering Projects. Simplified.</h1><p>STRUCTIQX brings construction and engineering work into one practical, secure operating system.</p></div></section><section class="sq-section"><div class="sq-container sq-split"><div><h2 style="font-size:42px;letter-spacing:-.04em">Built around how project teams work</h2><p style="color:var(--sq-muted);line-height:1.8">Project delivery requires clear ownership, reliable cost information and traceable engineering decisions. STRUCTIQX is designed to connect those parts in a single workspace.</p></div><div class="sq-card"><h3>Our design principles</h3><p>Organization isolation, professional workflows, clear responsibilities and practical reporting.</p></div></div></section></main><?php
    } elseif ($route === 'contact') {
        $notices=consume_flash();
        ?><main><section class="sq-page-hero"><div class="sq-container"><span class="sq-eyebrow">Contact</span><h1>Talk about your projects</h1><p>Tell us how your team works and we will help you evaluate STRUCTIQX.</p></div></section><section class="sq-section"><div class="sq-container" style="max-width:700px"><?php foreach($notices as $notice):?><div class="<?= $notice['type']==='error'?'sq-error':'sq-success' ?>"><?= e($notice['message']) ?></div><?php endforeach?><form class="sq-card" method="post"><?= csrf_field() ?><div class="sq-form-row"><div class="sq-field"><label>Name</label><input name="name" required></div><div class="sq-field"><label>Work email</label><input name="email" type="email" required></div></div><div class="sq-form-row"><div class="sq-field"><label>Phone</label><input name="phone"></div><div class="sq-field"><label>Organization</label><input name="organization"></div></div><div class="sq-field"><label>Subject</label><input name="subject" value="General enquiry"></div><div class="sq-field"><label>Message</label><textarea name="message" rows="6" required></textarea></div><button class="sq-btn sq-btn-accent" type="submit">Send message</button></form></div></section></main><?php
    }
    $content=ob_get_clean();
    render_public(ucwords(str_replace('-',' ', $route==='home'?'Home':$route)), $content, $route);
    exit;
}

if ($route === 'install' && file_exists(__DIR__ . '/install.php')) { require __DIR__ . '/install.php'; exit; }

if ($route === 'login') {
    if (current_user()) redirect(base_url('dashboard'));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $stmt = db()->prepare("SELECT u.*,om.organization_id,om.role,o.name organization_name,o.approval_status FROM users u JOIN organization_members om ON om.user_id=u.id JOIN organizations o ON o.id=om.organization_id WHERE u.email=? AND u.is_active=1 AND om.status='active' ORDER BY (o.approval_status='approved') DESC LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['approval_status'] !== 'approved') {
                $error = $user['approval_status'] === 'pending'
                    ? 'Your organization is awaiting administrator approval.'
                    : 'Workspace access is unavailable. Contact support for assistance.';
            } else {
            session_regenerate_id(true);
            if (!empty($_POST['remember'])) {
                setcookie(session_name(), session_id(), [
                    'expires'=>time()+60*60*24*30,
                    'path'=>'/',
                    'httponly'=>true,
                    'samesite'=>'Lax',
                ]);
            }
            $_SESSION['user'] = ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email']];
            $_SESSION['organization_id'] = (int)$user['organization_id'];
            $_SESSION['organization_name'] = $user['organization_name'];
            $_SESSION['role'] = $user['role'];
            db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
            log_activity('login','session',null,'User signed in');
            if (!empty($_SESSION['intended_url'])) {$intended=$_SESSION['intended_url'];unset($_SESSION['intended_url']);redirect($intended);}
            redirect(base_url('dashboard'));
            }
        }
        if (empty($error)) $error = 'Invalid email or password.';
    }
    ?>
    <!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Sign in | structiQX</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="min-h-screen bg-slate-950 grid place-items-center p-5"><main class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl"><a href="<?= e(base_url('')) ?>" class="mb-8 flex items-center gap-3 text-slate-900 no-underline"><div class="h-10 w-10 rounded-xl bg-orange-500 grid place-items-center font-bold text-white">SQ</div><div><p class="font-semibold">STRUCTIQX</p><p class="text-xs text-slate-500">Civil Engineering OS</p></div></a><h1 class="text-xl font-semibold">Welcome back</h1><p class="mt-1 text-sm text-slate-500">Sign in to your workspace.</p><?php foreach(consume_flash() as $notice): ?><p class="mt-4 rounded-lg <?= $notice['type']==='error'?'bg-red-50 text-red-700':'bg-emerald-50 text-emerald-700' ?> px-3 py-2 text-sm"><?= e($notice['message']) ?></p><?php endforeach; ?><?php if (!empty($error)): ?><p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></p><?php endif; ?><form method="post" class="mt-6 space-y-4"><?= csrf_field() ?><label class="block text-sm font-medium">Email<input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" type="email" name="email" required></label><label class="block text-sm font-medium">Password<input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" type="password" name="password" required></label><div class="flex items-center justify-between text-sm"><label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1"> Remember me</label><a class="text-slate-500" href="<?= e(base_url('forgot-password')) ?>">Forgot password?</a></div><button class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Sign in</button></form><p class="mt-5 text-center text-sm text-slate-500">New to STRUCTIQX? <a class="text-orange-600" href="<?= e(base_url('register')) ?>">Create organization</a></p><p class="mt-4 text-center text-xs text-slate-400">Demo: admin@structiqx.test / Password123!</p></main></body></html>
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
    if (current_user()) redirect(base_url('dashboard'));
    $registrationSetting=db()->prepare("SELECT setting_value FROM admin_settings WHERE setting_key='registration_enabled'");
    $registrationSetting->execute();
    if ($registrationSetting->fetchColumn()==='0') {
        flash('error','New organization registration is currently unavailable.');
        redirect(base_url('login'));
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name=trim((string)$_POST['name']); $email=strtolower(trim((string)$_POST['email'])); $orgName=trim((string)$_POST['organization']); $phone=trim((string)($_POST['phone']??'')); $password=(string)$_POST['password']; $confirm=(string)$_POST['confirm_password'];
        if (strlen($password)<8) { flash('error','Password must be at least 8 characters.'); redirect(base_url('register')); }
        if (!hash_equals($password,$confirm)) { flash('error','Password confirmation does not match.'); redirect(base_url('register')); }
        try {
            db()->beginTransaction();
            $slug=strtolower(preg_replace('/[^a-z0-9]+/i','-',$orgName)); $slug=trim($slug,'-').'-'.substr(bin2hex(random_bytes(3)),0,6);
            $stmt=db()->prepare("INSERT INTO organizations (name,slug,phone,approval_status,registration_source) VALUES (?,?,?,'pending','self_signup')"); $stmt->execute([$orgName,$slug,$phone]); $orgId=(int)db()->lastInsertId();
            $stmt=db()->prepare('INSERT INTO users (name,email,password_hash,phone) VALUES (?,?,?,?)'); $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$phone]); $uid=(int)db()->lastInsertId();
            try {
                $verification=bin2hex(random_bytes(32));
                db()->prepare('INSERT INTO email_verification_tokens (user_id,token,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))')->execute([$uid,$verification]);
            } catch (Throwable $e) {}
            db()->prepare("INSERT INTO organization_members (organization_id,user_id,role) VALUES (?,?,'organization_admin')")->execute([$orgId,$uid]);
            db()->commit();
            flash('success','Your account was created. Your organization is awaiting administrator approval before workspace access is enabled.');
            redirect(base_url('login'));
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); flash('error','Could not create account. The email may already be registered.'); redirect(base_url('register')); }
    }
    ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Create organization | STRUCTIQX</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 grid place-items-center p-5"><main class="w-full max-w-lg rounded-2xl bg-white p-8"><?php foreach(consume_flash() as $notice):?><p class="mb-4 rounded-lg <?= $notice['type']==='error'?'bg-red-50 text-red-700':'bg-emerald-50 text-emerald-700' ?> px-3 py-2 text-sm"><?= e($notice['message']) ?></p><?php endforeach?><a href="<?= e(base_url('')) ?>" class="mb-6 flex items-center gap-3 text-slate-900 no-underline"><div class="h-10 w-10 rounded-xl bg-orange-500 grid place-items-center font-bold text-white">SQ</div><div><p class="font-semibold">STRUCTIQX</p><p class="text-xs text-slate-500">Civil Engineering OS</p></div></a><h1 class="text-xl font-semibold">Create your organization</h1><p class="mt-1 text-sm text-slate-500">You will become the Organization Admin.</p><form method="post" class="mt-6 grid gap-4 sm:grid-cols-2"><?= csrf_field() ?><div class="sm:col-span-2"><?= form_field('Organization name','organization') ?></div><?= form_field('Full name','name') ?><?= form_field('Phone number','phone','','tel',false) ?><?= form_field('Work email','email','','email') ?><span></span><?= form_field('Password','password','','password') ?><?= form_field('Confirm password','confirm_password','','password') ?><button class="sm:col-span-2 w-full rounded-lg bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white">Create organization workspace</button></form><p class="mt-5 text-center text-sm"><a class="text-slate-500" href="<?= e(base_url('login')) ?>">Already have an account? Sign in</a></p></main></body></html><?php exit;
}

if ($route === 'logout') { session_destroy(); redirect(base_url('login')); }
auth_redirect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') verify_csrf();
$org = organization_id();
$orgStatus = db()->prepare('SELECT approval_status FROM organizations WHERE id=?');
$orgStatus->execute([$org]);
if ($orgStatus->fetchColumn() !== 'approved') {
    unset($_SESSION['user'],$_SESSION['organization_id'],$_SESSION['organization_name'],$_SESSION['role']);
    session_regenerate_id(true);
    redirect(base_url('login'));
}

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
    $parts=explode('/',$route);
    $format=$parts[1] ?? 'csv';
    $kind=$parts[2] ?? 'projects';
    $headers=[];$rows=[];$title='structiQX report';
    if($kind==='expenses'){
        $title='Expense report';$headers=['Project','Category','Description','Amount','Date','Status'];
        $result=db()->query("SELECT e.*,p.title project_title FROM expenses e JOIN projects p ON p.id=e.project_id WHERE e.organization_id=$org")->fetchAll();
        foreach($result as $r)$rows[]=[$r['project_title'],$r['category'],$r['description'],$r['amount'],$r['expense_date'],$r['status']];
    } elseif($kind==='boq'){
        $title='BOQ report';$headers=['BOQ','Project','Description','Unit','Quantity','Unit Rate','Total'];
        $result=db()->query("SELECT b.title boq_title,p.title project_title,i.description,i.unit,i.quantity,i.unit_rate,i.total FROM boq b JOIN projects p ON p.id=b.project_id JOIN boq_items i ON i.boq_id=b.id WHERE b.organization_id=$org")->fetchAll();
        foreach($result as $r)$rows[]=[$r['boq_title'],$r['project_title'],$r['description'],$r['unit'],$r['quantity'],$r['unit_rate'],$r['total']];
    } else {
        $title='Project report';$headers=['Title','Status','Progress','Budget','Start','End'];
        $result=db()->query("SELECT * FROM projects WHERE organization_id=$org")->fetchAll();
        foreach($result as $r)$rows[]=[$r['title'],$r['status'],$r['progress'],$r['budget'],$r['start_date'],$r['end_date']];
    }
    if($format==='pdf'){
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename=structiqx-'.$kind.'.pdf');
        echo simple_pdf($title,$headers,$rows);exit;
    }
    if($format==='xls'||$format==='xlsx'||$format==='excel'){
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename=structiqx-'.$kind.'.xls');
        echo spreadsheet_xml($title,$headers,$rows);exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=structiqx-'.$kind.'.csv');
    $out=fopen('php://output','w');
    fputcsv($out,$headers);
    foreach($rows as $row)fputcsv($out,$row);
    fclose($out);exit;
}

switch ($page) {
case 'dashboard':
    $stmt=db()->prepare('SELECT COUNT(*) FROM projects WHERE organization_id=?');$stmt->execute([$org]);$counts['projects']=$stmt->fetchColumn();
    $stmt=db()->prepare("SELECT COUNT(*) FROM projects WHERE organization_id=? AND status='active'");$stmt->execute([$org]);$counts['active']=$stmt->fetchColumn();
    $stmt=db()->prepare("SELECT COUNT(*) FROM projects WHERE organization_id=? AND status='completed'");$stmt->execute([$org]);$counts['completed']=$stmt->fetchColumn();
    $stmt=db()->prepare("SELECT COUNT(*) FROM projects WHERE organization_id=? AND status='active' AND end_date IS NOT NULL AND end_date<CURDATE()");$stmt->execute([$org]);$counts['delayed']=$stmt->fetchColumn();
    $stmt=db()->prepare('SELECT COALESCE(SUM(budget),0) FROM projects WHERE organization_id=?');$stmt->execute([$org]);$budget=(float)$stmt->fetchColumn();
    $stmt=db()->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE organization_id=?');$stmt->execute([$org]);$spent=(float)$stmt->fetchColumn();
    $stmt=db()->prepare('SELECT COUNT(*) FROM tasks WHERE organization_id=? AND status IN (\'todo\',\'in_progress\',\'in_review\',\'blocked\')');$stmt->execute([$org]);$openTasks=(int)$stmt->fetchColumn();
    $stmt=db()->prepare('SELECT COUNT(*) FROM clients WHERE organization_id=?');$stmt->execute([$org]);$clientCount=(int)$stmt->fetchColumn();
    $stmt=db()->prepare('SELECT id,title,status,progress,end_date,budget FROM projects WHERE organization_id=? ORDER BY created_at DESC LIMIT 5');$stmt->execute([$org]);$projects=$stmt->fetchAll();
    $stmt=db()->prepare("SELECT id,title,end_date,status FROM projects WHERE organization_id=? AND end_date IS NOT NULL AND end_date>=CURDATE() AND status NOT IN ('completed','cancelled') ORDER BY end_date LIMIT 5");$stmt->execute([$org]);$upcoming=$stmt->fetchAll();
    $stmt=db()->prepare('SELECT id,action,entity_type,description,created_at FROM activity_logs WHERE organization_id=? ORDER BY created_at DESC LIMIT 6');$stmt->execute([$org]);$activities=$stmt->fetchAll();
    $cards=[
      ['Total projects',$counts['projects'],'Building2','text-slate-900'],
      ['Active projects',$counts['active'],'Activity','text-emerald-600'],
      ['Completed',$counts['completed'],'CircleCheckBig','text-blue-600'],
      ['Delayed',$counts['delayed'],'Clock3','text-red-600'],
    ];
    ob_start(); ?>
    <div class="mb-6 flex items-end justify-between"><div><p class="text-sm text-slate-500">Overview</p><h1 class="mt-1 text-2xl font-semibold">Good morning, <?= e(current_user()['name']) ?></h1></div><a href="<?= e(base_url('projects/create')) ?>" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">New project</a></div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6"><?php foreach($cards as $card): ?><div class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex justify-between"><p class="text-sm text-slate-500"><?= e($card[0]) ?></p><i data-lucide="<?= $card[2] ?>" class="h-4 w-4 <?= $card[3] ?>"></i></div><p class="mt-3 text-3xl font-semibold"><?= e((string)$card[1]) ?></p></div><?php endforeach; ?><div class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex justify-between"><p class="text-sm text-slate-500">Open tasks</p><i data-lucide="CircleCheckBig" class="h-4 w-4 text-orange-500"></i></div><p class="mt-3 text-3xl font-semibold"><?= $openTasks ?></p></div><div class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex justify-between"><p class="text-sm text-slate-500">Clients</p><i data-lucide="ContactRound" class="h-4 w-4 text-slate-700"></i></div><p class="mt-3 text-3xl font-semibold"><?= $clientCount ?></p></div></div>
    <div class="mt-6 grid gap-6 lg:grid-cols-3"><section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2"><div class="flex items-center justify-between"><h2 class="font-semibold">Recent projects</h2><a class="text-sm text-slate-500" href="<?= e(base_url('projects')) ?>">View all</a></div><div class="mt-5 space-y-4"><?php if(!$projects): ?><p class="py-8 text-center text-sm text-slate-500">No projects yet.</p><?php endif; foreach($projects as $project): ?><a href="<?= e(base_url('projects/'.$project['id'])) ?>" class="block"><div class="flex items-center justify-between text-sm"><span class="font-medium"><?= e($project['title']) ?></span><span class="capitalize text-slate-500"><?= e($project['status']) ?></span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-orange-500" style="width:<?= (int)$project['progress'] ?>%"></div></div></a><?php endforeach; ?></div></section><section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Financial snapshot</h2><div class="mt-5 space-y-4"><div><p class="text-xs text-slate-500">Project budget</p><p class="mt-1 text-xl font-semibold">NPR <?= number_format($budget) ?></p></div><div><p class="text-xs text-slate-500">Actual expenditure</p><p class="mt-1 text-xl font-semibold">NPR <?= number_format($spent) ?></p></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width:<?= $budget>0?min(100,$spent/$budget*100):0 ?>%"></div></div></div></section></div>
    <div class="mt-6 grid gap-6 lg:grid-cols-2"><section class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex items-center justify-between"><h2 class="font-semibold">Upcoming deadlines</h2><a class="text-sm text-slate-500" href="<?= e(base_url('milestones')) ?>">View milestones</a></div><div class="mt-4 divide-y"><?php foreach($upcoming as $item):?><a href="<?= e(base_url('projects/'.$item['id'])) ?>" class="flex items-center justify-between py-3"><span class="text-sm font-medium"><?= e($item['title']) ?></span><span class="text-xs text-slate-500"><?= e($item['end_date']) ?></span></a><?php endforeach;if(!$upcoming):?><p class="py-8 text-center text-sm text-slate-500">No upcoming deadlines.</p><?php endif?></div></section><section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Recent activity</h2><div class="mt-4 divide-y"><?php foreach($activities as $activity):?><div class="py-3"><p class="text-sm font-medium"><?= e($activity['description']) ?></p><p class="mt-1 text-xs text-slate-500"><?= e($activity['created_at']) ?></p></div><?php endforeach;if(!$activities):?><p class="py-8 text-center text-sm text-slate-500">No recent activity.</p><?php endif?></div></section></div>
    <?php $content=ob_get_clean(); render('Dashboard',$content,'dashboard'); break;

case 'projects':
    if ($route === 'projects/create') {
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            if(($_POST['action']??'')==='create_client'){
                $stmt=db()->prepare('INSERT INTO clients (organization_id,name,company,email,phone,address) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$org,trim((string)$_POST['client_name']),trim((string)($_POST['company']??'')),trim((string)($_POST['email']??'')),trim((string)($_POST['phone']??'')),trim((string)($_POST['address']??''))]);
                $clientId=(int)db()->lastInsertId();
                log_activity('create','client',$clientId,'Added project client');
                flash('success','Client added successfully.');
                redirect(base_url('projects/create'));
            }
            $stmt=db()->prepare('INSERT INTO projects (organization_id,client_id,title,description,category,location,start_date,end_date,budget,status,project_manager_id,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$org,$_POST['client_id']?:null,trim($_POST['title']),trim($_POST['description']??''),trim($_POST['category']??''),trim($_POST['location']??''),$_POST['start_date']?:null,$_POST['end_date']?:null,(float)$_POST['budget'],$_POST['status'],$_POST['project_manager_id']?:null,(int)current_user()['id']]);
            $id=(int)db()->lastInsertId(); log_activity('create','project',$id,'Created project');
            flash('success','Project created successfully.'); redirect(base_url('projects/'.$id));
        }
        $clients=db()->query("SELECT id,name FROM clients WHERE organization_id=$org ORDER BY name")->fetchAll();
        $members=db()->query("SELECT u.id,u.name FROM users u JOIN organization_members om ON om.user_id=u.id WHERE om.organization_id=$org")->fetchAll();
        ob_start(); ?><a href="<?= e(base_url('projects')) ?>" class="text-sm text-slate-500">← Projects</a><div class="mt-4 grid max-w-5xl gap-6 lg:grid-cols-[2fr_1fr]"><div class="rounded-xl border border-slate-200 bg-white p-6"><h1 class="text-xl font-semibold">Create project</h1><form method="post" class="mt-6 grid gap-5 sm:grid-cols-2"><?= csrf_field() ?><?= form_field('Project title','title') ?><label class="block"><span class="mb-1.5 block text-sm font-medium">Client</span><select name="client_id" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">No client</option><?php foreach($clients as $c):?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach?></select></label><?= form_field('Category','category') ?><?= form_field('Location','location') ?><?= form_field('Start date','start_date','','date') ?><?= form_field('End date','end_date','','date') ?><?= form_field('Budget','budget','0','number') ?><label class="block"><span class="mb-1.5 block text-sm font-medium">Status</span><select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option>planning</option><option>active</option><option>on_hold</option><option>completed</option></select></label><label class="block sm:col-span-2"><span class="mb-1.5 block text-sm font-medium">Project manager</span><select name="project_manager_id" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Unassigned</option><?php foreach($members as $m):?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach?></select></label><label class="block sm:col-span-2"><span class="mb-1.5 block text-sm font-medium">Description</span><textarea name="description" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea></label><div class="sm:col-span-2"><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Create project</button></div></form></div><aside class="h-fit rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Add new client</h2><p class="mt-1 text-xs text-slate-500">Create a client without leaving project setup.</p><form method="post" class="mt-4 space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="create_client"><input name="client_name" required placeholder="Client name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="company" placeholder="Company" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="email" type="email" placeholder="Email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="phone" placeholder="Phone" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><textarea name="address" rows="2" placeholder="Address" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Add client</button></form></aside></div><?php $content=ob_get_clean(); render('Create project',$content,'projects'); break;
    }
    $parts=explode('/',$route);
    if (isset($parts[1]) && ctype_digit($parts[1]) && isset($parts[2]) && $parts[2]==='edit') {
        $project=org_row('projects',(int)$parts[1]);if(!$project){http_response_code(404);die('Project not found');}
        if($_SERVER['REQUEST_METHOD']==='POST'){
            if(($_POST['action']??'')==='create_client'){
                $stmt=db()->prepare('INSERT INTO clients (organization_id,name,company,email,phone,address) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$org,trim((string)$_POST['client_name']),trim((string)($_POST['company']??'')),trim((string)($_POST['email']??'')),trim((string)($_POST['phone']??'')),trim((string)($_POST['address']??''))]);
                $clientId=(int)db()->lastInsertId();
                log_activity('create','client',$clientId,'Added project client');
                flash('success','Client added successfully.');
                redirect(base_url('projects/'.$project['id'].'/edit'));
            }
            $stmt=db()->prepare('UPDATE projects SET client_id=?,title=?,description=?,category=?,location=?,start_date=?,end_date=?,budget=?,progress=?,status=?,project_manager_id=? WHERE id=? AND organization_id=?');
            $stmt->execute([$_POST['client_id']?:null,trim($_POST['title']),trim($_POST['description']??''),trim($_POST['category']??''),trim($_POST['location']??''),$_POST['start_date']?:null,$_POST['end_date']?:null,(float)$_POST['budget'],min(100,max(0,(int)$_POST['progress'])),$_POST['status'],$_POST['project_manager_id']?:null,$project['id'],$org]);
            log_activity('update','project',$project['id'],'Updated project');flash('success','Project updated.');redirect(base_url('projects/'.$project['id']));
        }
        $clients=db()->query("SELECT id,name FROM clients WHERE organization_id=$org")->fetchAll();
        $members=db()->query("SELECT u.id,u.name FROM users u JOIN organization_members om ON om.user_id=u.id WHERE om.organization_id=$org")->fetchAll();
        ob_start();?><a href="<?= e(base_url('projects/'.$project['id'])) ?>" class="text-sm text-slate-500">← Project</a><div class="mt-4 max-w-3xl rounded-xl border border-slate-200 bg-white p-6"><h1 class="text-xl font-semibold">Edit project</h1><form method="post" class="mt-6 grid gap-5 sm:grid-cols-2"><?= csrf_field() ?><?= form_field('Project title','title',$project['title']) ?><label class="block"><span class="mb-1.5 block text-sm font-medium">Client</span><select name="client_id" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">No client</option><?php foreach($clients as $c):?><option value="<?= $c['id'] ?>" <?= $project['client_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach?></select></label><?= form_field('Category','category',$project['category']??'') ?><?= form_field('Location','location',$project['location']??'') ?><?= form_field('Start date','start_date',$project['start_date']??'','date',false) ?><?= form_field('End date','end_date',$project['end_date']??'','date',false) ?><?= form_field('Budget','budget',(string)$project['budget'],'number') ?><?= form_field('Progress','progress',(string)$project['progress'],'number') ?><label class="block"><span class="mb-1.5 block text-sm font-medium">Status</span><select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2"><?php foreach(['planning','active','on_hold','completed','cancelled'] as $s):?><option <?= $project['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach?></select></label><label class="block"><span class="mb-1.5 block text-sm font-medium">Project manager</span><select name="project_manager_id" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Unassigned</option><?php foreach($members as $m):?><option value="<?= $m['id'] ?>" <?= $project['project_manager_id']==$m['id']?'selected':'' ?>><?= e($m['name']) ?></option><?php endforeach?></select></label><label class="block sm:col-span-2"><span class="mb-1.5 block text-sm font-medium">Description</span><textarea name="description" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2"><?= e($project['description']??'') ?></textarea></label>        <button class="w-fit rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Save changes</button></form></div><aside class="mt-6 max-w-3xl rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Add new client</h2><form method="post" class="mt-4 grid gap-3 sm:grid-cols-2"><?= csrf_field() ?><input type="hidden" name="action" value="create_client"><input name="client_name" required placeholder="Client name" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="company" placeholder="Company" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="email" type="email" placeholder="Email" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="phone" placeholder="Phone" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><textarea name="address" rows="2" placeholder="Address" class="rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2"></textarea><button class="w-fit rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Add client</button></form></aside><?php $content=ob_get_clean();render('Edit project',$content,'projects');break;
    }
    if (isset($parts[1]) && ctype_digit($parts[1])) {
        $project=org_row('projects',(int)$parts[1]); if(!$project){http_response_code(404);die('Project not found');}
        $clientName='No client';
        if(!empty($project['client_id'])){
            $stmt=db()->prepare('SELECT name FROM clients WHERE id=? AND organization_id=?');
            $stmt->execute([(int)$project['client_id'],$org]);$clientRow=$stmt->fetch();
            if($clientRow)$clientName=$clientRow['name'];
        }
        if($_SERVER['REQUEST_METHOD']==='POST' && trim((string)($_POST['body'] ?? '')) !== ''){
            db()->prepare('INSERT INTO comments (organization_id,entity_type,entity_id,user_id,body) VALUES (? ,\'project\',?,?,?)')->execute([$org,$project['id'],(int)current_user()['id'],trim($_POST['body'])]);
            log_activity('comment','project',$project['id'],'Added project comment');flash('success','Comment added.');
        }
        $tasks=db()->prepare('SELECT t.*,u.name assignee FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to WHERE t.organization_id=? AND t.project_id=? ORDER BY t.created_at DESC');$tasks->execute([$org,$project['id']]);$tasks=$tasks->fetchAll();
        $comments=db()->prepare('SELECT c.*,u.name FROM comments c JOIN users u ON u.id=c.user_id WHERE c.organization_id=? AND c.entity_type=\'project\' AND c.entity_id=? ORDER BY c.created_at DESC');
        $comments->execute([$org,$project['id']]);$comments=$comments->fetchAll();
        ob_start();?><a href="<?= e(base_url('projects')) ?>" class="text-sm text-slate-500">← Projects</a><div class="mt-4 rounded-xl border border-slate-200 bg-white p-6"><div class="flex flex-wrap justify-between gap-4"><div><p class="text-sm text-slate-500">        <?= e($project['category']) ?> · <?= e($project['location']) ?> · Client: <?= e($clientName) ?></p><h1 class="mt-1 text-2xl font-semibold"><?= e($project['title']) ?></h1></div><div class="flex items-center gap-2"><span class="h-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-medium capitalize"><?= e($project['status']) ?></span><a href="<?= e(base_url('projects/'.$project['id'].'/edit')) ?>" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium">Edit</a></div></div><p class="mt-4 max-w-3xl text-sm leading-6 text-slate-600"><?= e($project['description'] ?: 'No description provided.') ?></p><div class="mt-6 grid grid-cols-2 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-4"><div><p class="text-xs text-slate-500">Progress</p><p class="mt-1 font-semibold"><?= (int)$project['progress'] ?>%</p></div><div><p class="text-xs text-slate-500">Budget</p><p class="mt-1 font-semibold">NPR <?= number_format((float)$project['budget']) ?></p></div><div><p class="text-xs text-slate-500">Start</p><p class="mt-1 font-semibold"><?= e($project['start_date'] ?: '—') ?></p></div><div><p class="text-xs text-slate-500">End</p><p class="mt-1 font-semibold"><?= e($project['end_date'] ?: '—') ?></p></div></div></div><section class="mt-6"><div class="mb-3 flex items-center justify-between"><h2 class="font-semibold">Tasks</h2><a class="rounded-lg border border-slate-300 px-3 py-2 text-sm" href="<?= e(base_url('tasks')) ?>">View tasks</a></div><?php echo table_start(['Task','Assignee','Priority','Status','Due']); foreach($tasks as $t):?><tr><td class="px-4 py-3 font-medium"><?= e($t['title']) ?></td><td class="px-4 py-3"><?= e($t['assignee'] ?: 'Unassigned') ?></td><td class="px-4 py-3 capitalize"><?= e($t['priority']) ?></td><td class="px-4 py-3 capitalize"><?= e(str_replace('_',' ', $t['status'])) ?></td><td class="px-4 py-3"><?= e($t['due_date'] ?: '—') ?></td></tr><?php endforeach; echo '</tbody></table></div>';?></section><section class="mt-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Project comments</h2><form method="post" class="mt-4 flex gap-3"><?= csrf_field() ?><input name="body" required placeholder="Add a comment" class="flex-1 rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Post</button></form><div class="mt-4 divide-y"><?php foreach($comments as $c):?><article class="py-3"><p class="text-sm font-medium"><?= e($c['name']) ?></p><p class="mt-1 text-sm text-slate-600"><?= e($c['body']) ?></p></article><?php endforeach;if(!$comments):?><p class="py-4 text-sm text-slate-500">No comments yet.</p><?php endif?></div></section><?php $content=ob_get_clean(); render($project['title'],$content,'projects'); break;
    }
    $search=trim((string)($_GET['q']??''));
    $sql='SELECT p.*,c.name client_name FROM projects p LEFT JOIN clients c ON c.id=p.client_id WHERE p.organization_id=?';
    $params=[$org];
    if($search!==''){$sql.=" AND (p.title LIKE ? OR p.location LIKE ? OR p.category LIKE ?)";$like='%'.$search.'%';$params[]=$like;$params[]=$like;$params[]=$like;}
    $sql.=' ORDER BY p.created_at DESC';
    $projects=db()->prepare($sql);$projects->execute($params);$projects=$projects->fetchAll();
    ob_start();?><div class="mb-5 flex items-center justify-between"><div><p class="text-sm text-slate-500">Manage delivery</p><h1 class="mt-1 text-2xl font-semibold">Projects</h1></div><div class="flex gap-2"><form method="get" class="flex"><input name="q" value="<?= e($search) ?>" placeholder="Search projects" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></form><a href="<?= e(base_url('projects/create')) ?>" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">New project</a></div></div><?= table_start(['Project','Client','Status','Progress','End date']) ?><?php foreach($projects as $p):?><tr><td class="px-4 py-3"><a class="font-medium hover:text-orange-600" href="<?= e(base_url('projects/'.$p['id'])) ?>"><?= e($p['title']) ?></a></td><td class="px-4 py-3"><?= e($p['client_name'] ?: '—') ?></td><td class="px-4 py-3 capitalize"><?= e($p['status']) ?></td><td class="px-4 py-3"><div class="flex items-center gap-2"><div class="h-1.5 w-20 rounded-full bg-slate-100"><div class="h-full rounded-full bg-orange-500" style="width:<?= (int)$p['progress'] ?>%"></div></div><?= (int)$p['progress'] ?>%</div></td><td class="px-4 py-3"><?= e($p['end_date'] ?: '—') ?></td></tr><?php endforeach; if(!$projects):?><tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No projects found.</td></tr><?php endif;?></tbody></table></div><?php $content=ob_get_clean(); render('Projects',$content,'projects'); break;

case 'clients':
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(($_POST['action']??'')==='delete'){
            db()->prepare('UPDATE projects SET client_id=NULL WHERE organization_id=? AND client_id=?')->execute([$org,(int)$_POST['client_id']]);
            db()->prepare('DELETE FROM clients WHERE id=? AND organization_id=?')->execute([(int)$_POST['client_id'],$org]);
            log_activity('delete','client',(int)$_POST['client_id'],'Deleted client');flash('success','Client deleted.');
        } else {
            if(($_POST['action']??'')==='update'){
                db()->prepare('UPDATE clients SET name=?,company=?,email=?,phone=?,address=? WHERE id=? AND organization_id=?')->execute([trim((string)$_POST['client_name']),trim((string)($_POST['company']??'')),trim((string)($_POST['email']??'')),trim((string)($_POST['phone']??'')),trim((string)($_POST['address']??'')),(int)$_POST['client_id'],$org]);
                log_activity('update','client',(int)$_POST['client_id'],'Updated client');flash('success','Client updated.');
            } else {
                db()->prepare('INSERT INTO clients (organization_id,name,company,email,phone,address) VALUES (?,?,?,?,?,?)')->execute([$org,trim((string)$_POST['client_name']),trim((string)($_POST['company']??'')),trim((string)($_POST['email']??'')),trim((string)($_POST['phone']??'')),trim((string)($_POST['address']??''))]);
                $clientId=(int)db()->lastInsertId();log_activity('create','client',$clientId,'Added client');flash('success','Client created.');
            }
        }
    }
    $stmt=db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM projects p WHERE p.organization_id=? AND p.client_id=c.id) project_count FROM clients c WHERE c.organization_id=? ORDER BY c.name');
    $stmt->execute([$org,$org]);$rows=$stmt->fetchAll();
    ob_start();?><div class="mb-5 flex items-center justify-between"><div><p class="text-sm text-slate-500">Customers and organizations</p><h1 class="mt-1 text-2xl font-semibold">Clients</h1></div></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Add client</h2><form method="post" class="mt-4 grid gap-3 md:grid-cols-5"><?= csrf_field() ?><input name="client_name" required placeholder="Client name" class="rounded-lg border border-slate-300 px-3 py-2"><input name="company" placeholder="Company" class="rounded-lg border border-slate-300 px-3 py-2"><input name="email" type="email" placeholder="Email" class="rounded-lg border border-slate-300 px-3 py-2"><input name="phone" placeholder="Phone" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Add client</button><input name="address" placeholder="Address" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-5"></form></div><?= table_start(['Name','Company','Email','Phone','Projects','Actions']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3"><?= e($r['company'] ?: '—') ?></td><td class="px-4 py-3"><?= e($r['email'] ?: '—') ?></td><td class="px-4 py-3"><?= e($r['phone'] ?: '—') ?></td><td class="px-4 py-3"><?= (int)$r['project_count'] ?></td><td class="px-4 py-3"><details><summary class="cursor-pointer text-xs text-orange-600">Edit / delete</summary><form method="post" class="mt-2 grid min-w-64 gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="client_id" value="<?= $r['id'] ?>"><input name="client_name" required value="<?= e($r['name']) ?>" class="rounded border border-slate-300 px-2 py-1 text-xs"><input name="company" value="<?= e($r['company']??'') ?>" class="rounded border border-slate-300 px-2 py-1 text-xs"><input name="email" value="<?= e($r['email']??'') ?>" class="rounded border border-slate-300 px-2 py-1 text-xs"><input name="phone" value="<?= e($r['phone']??'') ?>" class="rounded border border-slate-300 px-2 py-1 text-xs"><textarea name="address" class="rounded border border-slate-300 px-2 py-1 text-xs"><?= e($r['address']??'') ?></textarea><button class="rounded bg-slate-100 px-2 py-1 text-xs">Save</button></form><form method="post" class="mt-2" onsubmit="return confirm('Delete this client?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="client_id" value="<?= $r['id'] ?>"><button class="rounded bg-red-50 px-2 py-1 text-xs text-red-700">Delete</button></form></details></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No clients yet.</td></tr><?php endif?></tbody></table></div><?php $content=ob_get_clean();render('Clients',$content,'clients');break;

case 'tasks':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if(($_POST['action']??'')==='comment'){
            db()->prepare("INSERT INTO comments (organization_id,entity_type,entity_id,user_id,body) VALUES (?, 'task', ?, ?, ?)")->execute([$org,(int)$_POST['task_id'],(int)current_user()['id'],trim((string)$_POST['body'])]);
            flash('success','Task comment added.');
        } elseif(($_POST['action']??'')==='update_status'){
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
    ob_start();?><div class="mb-5 flex items-center justify-between"><h1 class="text-2xl font-semibold">Tasks</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Create task</h2><form method="post" class="mt-4 grid gap-4 md:grid-cols-4"><?= csrf_field() ?><label class="md:col-span-2"><span class="mb-1.5 block text-sm font-medium">Title</span><input name="title" required class="w-full rounded-lg border border-slate-300 px-3 py-2"></label><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><select name="assigned_to" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Assignee</option><?php foreach($members as $m):?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach?></select><select name="priority" class="rounded-lg border border-slate-300 px-3 py-2"><option>low</option><option selected>medium</option><option>high</option><option>critical</option></select><select name="status" class="rounded-lg border border-slate-300 px-3 py-2"><option>todo</option><option>in_progress</option><option>in_review</option><option>completed</option><option>blocked</option></select><input type="date" name="due_date" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Add task</button></form></div><?= table_start(    ['Task','Project','Assignee','Priority','Status','Progress','Comment','Due']) ?><?php foreach($tasks as $t):?><tr><td class="px-4 py-3 font-medium"><?= e($t['title']) ?></td><td class="px-4 py-3"><?= e($t['project_title']) ?></td><td class="px-4 py-3"><?= e($t['assignee'] ?: 'Unassigned') ?></td><td class="px-4 py-3 capitalize"><?= e($t['priority']) ?></td><td class="px-4 py-3" colspan="1"><form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="task_id" value="<?= $t['id'] ?>"><select name="status" class="rounded border border-slate-300 px-1 py-1 text-xs"><option <?= $t['status']==='todo'?'selected':'' ?>>todo</option><option <?= $t['status']==='in_progress'?'selected':'' ?>>in_progress</option><option <?= $t['status']==='in_review'?'selected':'' ?>>in_review</option><option <?= $t['status']==='completed'?'selected':'' ?>>completed</option><option <?= $t['status']==='blocked'?'selected':'' ?>>blocked</option></select><input name="progress" type="number" min="0" max="100" value="<?= (int)$t['progress'] ?>" class="w-14 rounded border border-slate-300 px-1 py-1 text-xs"><button class="rounded bg-slate-100 px-2 text-xs">Save</button></form></td>    <td class="px-4 py-3"><?= (int)$t['progress'] ?>%</td><td class="px-4 py-3"><form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="comment"><input type="hidden" name="task_id" value="<?= $t['id'] ?>"><input name="body" required placeholder="Comment" class="w-24 rounded border border-slate-300 px-1 py-1 text-xs"><button class="rounded bg-slate-100 px-2 text-xs">Post</button></form></td><td class="px-4 py-3"><?= e($t['due_date'] ?: '—') ?></td></tr><?php endforeach;?></tbody></table></div><?php $content=ob_get_clean();render('Tasks',$content,'tasks');break;

case 'milestones':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if(($_POST['action']??'')==='update'){
            db()->prepare('UPDATE milestones SET status=? WHERE id=? AND organization_id=?')->execute([$_POST['status'],(int)$_POST['milestone_id'],$org]);
            log_activity('update','milestone',(int)$_POST['milestone_id'],'Updated milestone status');flash('success','Milestone updated.');
        } else {
        $stmt=db()->prepare('INSERT INTO milestones (organization_id,project_id,title,due_date,status) VALUES (?,?,?,?,?)');
        $stmt->execute([$org,(int)$_POST['project_id'],trim($_POST['title']),$_POST['due_date'],$_POST['status']]);
        log_activity('create','milestone',(int)db()->lastInsertId(),'Created milestone'); flash('success','Milestone created.');
        }
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT m.*,p.title project_title FROM milestones m JOIN projects p ON p.id=m.project_id WHERE m.organization_id=$org ORDER BY m.due_date")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Milestones</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-5"><?= csrf_field() ?><input name="title" required placeholder="Milestone title" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-2"><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input type="date" name="due_date" required class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-3 text-sm font-semibold text-white">Add</button><input type="hidden" name="status" value="planned"></form></div>    <?= table_start(['Milestone','Project','Due','Status']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['title']) ?></td><td class="px-4 py-3"><?= e($r['project_title']) ?></td><td class="px-4 py-3"><?= e($r['due_date']) ?></td><td class="px-4 py-3"><form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="milestone_id" value="<?= $r['id'] ?>"><select name="status" class="rounded border border-slate-300 px-2 py-1 text-xs"><option <?= $r['status']==='planned'?'selected':'' ?>>planned</option><option <?= $r['status']==='achieved'?'selected':'' ?>>achieved</option><option <?= $r['status']==='overdue'?'selected':'' ?>>overdue</option></select><button class="rounded bg-slate-100 px-2 text-xs">Save</button></form></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Milestones',$content,'milestones');break;

case 'team':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        require_role(['organization_admin']);
        if(($_POST['action']??'')==='update_role'){
            db()->prepare('UPDATE organization_members SET role=? WHERE organization_id=? AND user_id=?')->execute([$_POST['role'],$org,(int)$_POST['user_id']]);
            log_activity('update','permission',(int)$_POST['user_id'],'Updated organization role');flash('success','Role updated.');
        } else {
        $email=strtolower(trim((string)$_POST['email']));
        $stmt=db()->prepare('SELECT id FROM users WHERE email=?');$stmt->execute([$email]);$uid=(int)($stmt->fetchColumn() ?: 0);
        if (!$uid) {$s=db()->prepare('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)');$s->execute([trim($_POST['name']),$email,password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);$uid=(int)db()->lastInsertId();}
        db()->prepare('INSERT IGNORE INTO organization_members (organization_id,user_id,role,status) VALUES (?,?,?,\'active\')')->execute([$org,$uid,$_POST['role']]);
        $token=bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO organization_invitations (organization_id,email,role,token,expires_at) VALUES (?,?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY))')->execute([$org,$email,$_POST['role'],$token]);
        log_activity('create','team_member',$uid,'Added organization team member');flash('success','Team member added.');
        }
    }
    $rows=db()->query("SELECT u.id,u.name,u.email,om.role,om.status FROM users u JOIN organization_members om ON om.user_id=u.id WHERE om.organization_id=$org ORDER BY u.name")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Team</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Add member</h2><form method="post" class="mt-4 grid gap-3 md:grid-cols-4"><?= csrf_field() ?><input name="name" required placeholder="Full name" class="rounded-lg border border-slate-300 px-3 py-2"><input name="email" type="email" required placeholder="Email" class="rounded-lg border border-slate-300 px-3 py-2"><select name="role" class="rounded-lg border border-slate-300 px-3 py-2"><option>project_manager</option><option>civil_engineer</option><option>site_engineer</option><option>site_supervisor</option><option>accountant</option><option>procurement_officer</option><option>client</option></select><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Add member</button></form></div>    <?= table_start(['Name','Email','Role','Status']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3"><?= e($r['email']) ?></td><td class="px-4 py-3"><form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="update_role"><input type="hidden" name="user_id" value="<?= $r['id'] ?>"><select name="role" class="rounded border border-slate-300 px-2 py-1 text-xs"><?php foreach(['organization_admin','project_manager','civil_engineer','site_engineer','site_supervisor','accountant','procurement_officer','client'] as $role):?><option <?= $r['role']===$role?'selected':'' ?>><?= $role ?></option><?php endforeach?></select><button class="rounded bg-slate-100 px-2 text-xs">Save</button></form></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Team',$content,'team');break;

case 'budgets':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if(($_POST['action']??'')==='revision'){
            db()->beginTransaction();
            db()->prepare('UPDATE budgets SET amount=?,revision_no=revision_no+1 WHERE id=? AND organization_id=?')->execute([(float)$_POST['amount'],(int)$_POST['budget_id'],$org]);
            db()->prepare('INSERT INTO budget_revisions (budget_id,amount,reason,changed_by) VALUES (?,?,?,?)')->execute([(int)$_POST['budget_id'],(float)$_POST['amount'],trim((string)($_POST['reason']??'')),(int)current_user()['id']]);
            db()->commit();log_activity('update','budget',(int)$_POST['budget_id'],'Created budget revision');flash('success','Budget revision saved.');
        } else {
        $stmt=db()->prepare('INSERT INTO budgets (organization_id,project_id,name,amount) VALUES (?,?,?,?)');$stmt->execute([$org,(int)$_POST['project_id'],trim($_POST['name']),(float)$_POST['amount']]);
        log_activity('create','budget',(int)db()->lastInsertId(),'Created project budget');flash('success','Budget created.');
        }
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT b.*,p.title project_title FROM budgets b JOIN projects p ON p.id=b.project_id WHERE b.organization_id=$org ORDER BY b.created_at DESC")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Budgets</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-4"><?= csrf_field() ?><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input name="name" required placeholder="Budget name" class="rounded-lg border border-slate-300 px-3 py-2"><input name="amount" type="number" step="0.01" required placeholder="Amount" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Add budget</button></form></div><?= table_start(['Budget','Project','Amount','Revision']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3"><?= e($r['project_title']) ?></td>    <td class="px-4 py-3">NPR <?= number_format((float)$r['amount'],2) ?></td><td class="px-4 py-3"><form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="revision"><input type="hidden" name="budget_id" value="<?= $r['id'] ?>"><input name="amount" type="number" step="0.01" value="<?= e((string)$r['amount']) ?>" class="w-24 rounded border border-slate-300 px-1 py-1 text-xs"><input name="reason" placeholder="Reason" class="w-24 rounded border border-slate-300 px-1 py-1 text-xs"><button class="rounded bg-slate-100 px-2 text-xs">Revise</button></form><span class="text-xs text-slate-500">Rev. <?= (int)$r['revision_no'] ?></span></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Budgets',$content,'budgets');break;

case 'boq':
    $boqParts=explode('/',$route);
    if (isset($boqParts[1]) && ctype_digit($boqParts[1])) {
        $boqId=(int)$boqParts[1];
        $stmt=db()->prepare('SELECT b.*,p.title project_title FROM boq b JOIN projects p ON p.id=b.project_id WHERE b.id=? AND b.organization_id=?');
        $stmt->execute([$boqId,$org]);$boq=$stmt->fetch();
        if(!$boq){http_response_code(404);die('BOQ not found');}
        if($_SERVER['REQUEST_METHOD']==='POST'){
            if(($_POST['action']??'')==='rate'){
                db()->prepare('INSERT INTO rate_analyses (organization_id,boq_item_id,component_type,name,unit,quantity,rate) VALUES (?,?,?,?,?,?,?)')->execute([$org,(int)$_POST['boq_item_id'],$_POST['component_type'],trim((string)$_POST['name']),trim((string)$_POST['unit']),(float)$_POST['quantity'],(float)$_POST['rate']]);
                flash('success','Rate analysis component added.');
            } else {
            $stmt=db()->prepare('INSERT INTO boq_items (boq_id,item_code,description,unit,quantity,unit_rate) VALUES (?,?,?,?,?,?)');
            $itemCode = trim((string)($_POST['item_code'] ?? ''));
            $itemDescription = trim((string)$_POST['description']);
            $itemUnit = trim((string)$_POST['unit']);
            $itemQuantity = (float)$_POST['quantity'];
            $itemRate = (float)$_POST['unit_rate'];
            $stmt->execute([$boqId,$itemCode,$itemDescription,$itemUnit,$itemQuantity,$itemRate]);
            log_activity('create','boq_item',(int)db()->lastInsertId(),'Added BOQ item');flash('success','BOQ item added.');
            }
        }
        $stmt=db()->prepare('SELECT * FROM boq_items WHERE boq_id=? ORDER BY id');
        $stmt->execute([$boqId]);$items=$stmt->fetchAll();
        $stmt=db()->prepare('SELECT ra.*,bi.description item_description FROM rate_analyses ra JOIN boq_items bi ON bi.id=ra.boq_item_id WHERE ra.organization_id=? AND bi.boq_id=? ORDER BY ra.id');
        $stmt->execute([$org,$boqId]);$rates=$stmt->fetchAll();
        ob_start();?><a href="<?= e(base_url('boq')) ?>" class="text-sm text-slate-500">← BOQ</a><div class="mt-4 mb-5 flex justify-between"><div><p class="text-sm text-slate-500"><?= e($boq['project_title']) ?></p><h1 class="text-2xl font-semibold"><?= e($boq['title']) ?></h1></div>        <a href="<?= e(base_url('export/csv/boq')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">CSV</a><a href="<?= e(base_url('export/xls/boq')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Excel</a><a href="<?= e(base_url('export/pdf/boq')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">PDF</a></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-6"><?= csrf_field() ?><input name="item_code" placeholder="Code" class="rounded-lg border border-slate-300 px-3 py-2"><input name="description" required placeholder="Description" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-2"><input name="unit" required placeholder="Unit" class="rounded-lg border border-slate-300 px-3 py-2"><input name="quantity" type="number" step="0.001" required placeholder="Quantity" class="rounded-lg border border-slate-300 px-3 py-2"><input name="unit_rate" type="number" step="0.01" required placeholder="Rate" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white md:col-span-6">Add item</button>        </form></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Rate analysis</h2><form method="post" class="mt-4 grid gap-3 md:grid-cols-6"><?= csrf_field() ?><input type="hidden" name="action" value="rate"><select name="boq_item_id" required class="rounded-lg border border-slate-300 px-3 py-2"><?php foreach($items as $i):?><option value="<?= $i['id'] ?>"><?= e($i['description']) ?></option><?php endforeach?></select><select name="component_type" class="rounded-lg border border-slate-300 px-3 py-2"><option>material</option><option>labour</option><option>equipment</option></select><input name="name" required placeholder="Component" class="rounded-lg border border-slate-300 px-3 py-2"><input name="unit" placeholder="Unit" class="rounded-lg border border-slate-300 px-3 py-2"><input name="quantity" type="number" step="0.001" required placeholder="Qty" class="rounded-lg border border-slate-300 px-3 py-2"><input name="rate" type="number" step="0.01" required placeholder="Rate" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white md:col-span-6">Add component</button></form></div><?= table_start(['Code','Description','Unit','Quantity','Rate','Total']) ?><?php foreach($items as $i):?><tr><td class="px-4 py-3"><?= e($i['item_code']) ?></td><td class="px-4 py-3 font-medium"><?= e($i['description']) ?></td><td class="px-4 py-3"><?= e($i['unit']) ?></td><td class="px-4 py-3"><?= number_format((float)$i['quantity'],3) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$i['unit_rate'],2) ?></td><td class="px-4 py-3 font-medium">NPR <?= number_format((float)$i['total'],2) ?></td></tr>        <?php endforeach?></tbody></table></div><?= table_start(['BOQ item','Component','Type','Quantity','Rate','Amount']) ?><?php foreach($rates as $r):?><tr><td class="px-4 py-3"><?= e($r['item_description']) ?></td><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['component_type']) ?></td><td class="px-4 py-3"><?= number_format((float)$r['quantity'],3).' '.e($r['unit']) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$r['rate'],2) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$r['quantity']*(float)$r['rate'],2) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('BOQ detail',$content,'boq');break;
    }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $stmt=db()->prepare('INSERT INTO boq (organization_id,project_id,title,status) VALUES (?,?,?,\'draft\')');$stmt->execute([$org,(int)$_POST['project_id'],trim($_POST['title'])]);$boqId=(int)db()->lastInsertId();
        log_activity('create','boq',$boqId,'Created BOQ');flash('success','BOQ created.');
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT b.*,p.title project_title,(SELECT COALESCE(SUM(quantity*unit_rate),0) FROM boq_items i WHERE i.boq_id=b.id) total FROM boq b JOIN projects p ON p.id=b.project_id WHERE b.organization_id=$org")->fetchAll();
    ob_start();?><div class="mb-5 flex justify-between"><h1 class="text-2xl font-semibold">BOQ & Estimation</h1>    <a href="<?= e(base_url('export/csv/boq')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">CSV</a><a href="<?= e(base_url('export/xls/boq')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Excel</a><a href="<?= e(base_url('export/pdf/boq')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">PDF</a></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-3"><?= csrf_field() ?><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input name="title" required placeholder="BOQ title" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Create BOQ</button></form></div><?= table_start(['BOQ','Project','Status','Estimated total']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3"><a class="font-medium text-orange-600" href="<?= e(base_url('boq/'.$r['id'])) ?>"><?= e($r['title']) ?></a></td><td class="px-4 py-3"><?= e($r['project_title']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$r['total'],2) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('BOQ',$content,'boq');break;

case 'calculations':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if(($_POST['action']??'')==='review'){
            $status=in_array($_POST['status'],['in_review','verified','approved','rejected'],true)?$_POST['status']:'in_review';
            db()->prepare('UPDATE engineering_calculations SET status=?,reviewed_by=? WHERE id=? AND organization_id=?')->execute([$status,(int)current_user()['id'],(int)$_POST['calculation_id'],$org]);
            log_activity('review','calculation',(int)$_POST['calculation_id'],'Updated calculation review status');
            flash('success','Calculation review status updated.');
        } else {
        $type=trim((string)$_POST['calculation_type']);
        $inputs=['width'=>(float)($_POST['width']??0),'depth'=>(float)($_POST['depth']??0),'length'=>(float)($_POST['length']??0),'thickness'=>(float)($_POST['thickness']??0)];
        $result=0;$formula='';$steps=[];
        if ($type==='rcc_beam') {$result=$inputs['width']*$inputs['depth']*$inputs['length'];$formula='Volume = width × depth × length';$steps=['width='.$inputs['width'].' m','depth='.$inputs['depth'].' m','length='.$inputs['length'].' m','volume='.$result.' m³'];}
        if ($type==='rcc_slab') {$result=$inputs['length']*$inputs['width']*$inputs['thickness'];$formula='Volume = length × width × thickness';$steps=['length='.$inputs['length'].' m','width='.$inputs['width'].' m','thickness='.$inputs['thickness'].' m','volume='.$result.' m³'];}
        if ($type==='rcc_column') {$result=$inputs['width']*$inputs['depth']*$inputs['length'];$formula='Volume = section width × section depth × column height';$steps=['width='.$inputs['width'].' m','depth='.$inputs['depth'].' m','height='.$inputs['length'].' m','volume='.$result.' m³'];}
        if ($type==='foundation') {$result=$inputs['width']*$inputs['length']*$inputs['thickness'];$formula='Volume = foundation length × width × thickness';$steps=['length='.$inputs['length'].' m','width='.$inputs['width'].' m','thickness='.$inputs['thickness'].' m','volume='.$result.' m³'];}
        if ($type==='retaining_wall') {$result=$inputs['length']*$inputs['depth']*$inputs['thickness'];$formula='Volume = wall length × height × thickness';$steps=['length='.$inputs['length'].' m','height='.$inputs['depth'].' m','thickness='.$inputs['thickness'].' m','volume='.$result.' m³'];}
        if ($type==='road_geometry') {$result=$inputs['length']*$inputs['width'];$formula='Surface area = carriageway length × width';$steps=['length='.$inputs['length'].' m','width='.$inputs['width'].' m','area='.$result.' m²'];}
        if ($type==='pavement') {$result=$inputs['length']*$inputs['width']*$inputs['thickness'];$formula='Volume = road length × width × pavement thickness';$steps=['length='.$inputs['length'].' m','width='.$inputs['width'].' m','thickness='.$inputs['thickness'].' m','volume='.$result.' m³'];}
        if ($type==='drainage') {$result=$inputs['width']*$inputs['depth']*$inputs['length'];$formula='Flow-section approximation = width × depth × length';$steps=['width='.$inputs['width'].' m','depth='.$inputs['depth'].' m','length='.$inputs['length'].' m','volume='.$result.' m³'];}
        if ($type==='water_supply') {$result=$inputs['width']*$inputs['length']*$inputs['depth'];$formula='Storage volume = length × width × water depth';$steps=['length='.$inputs['length'].' m','width='.$inputs['width'].' m','depth='.$inputs['depth'].' m','volume='.$result.' m³'];}
        if ($type==='earthwork') {$netDepth=$inputs['depth']-$inputs['thickness'];$result=$inputs['length']*$inputs['width']*$netDepth;$formula='Cut/fill volume = length × width × net depth (depth minus thickness)';$steps=['length='.$inputs['length'].' m','width='.$inputs['width'].' m','net depth='.$netDepth.' m','volume='.$result.' m³'];}
        if ($type==='soil_mechanics') {$result=$inputs['width']*$inputs['length']*$inputs['thickness'];$formula='Sampling/zone volume = length × width × sample thickness';$steps=['length='.$inputs['length'].' m','width='.$inputs['width'].' m','thickness='.$inputs['thickness'].' m','volume='.$result.' m³'];}
        $stmt=db()->prepare('INSERT INTO engineering_calculations (organization_id,project_id,calculation_type,title,inputs_json,formula,steps_json,results_json,unit,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,\'draft\',?)');
        $unit=in_array($type,['road_geometry'],true)?'m²':'m³';
        $stmt->execute([$org,$_POST['project_id']?:null,$type,trim($_POST['title']),json_encode($inputs),$formula,json_encode($steps),json_encode(['value'=>$result]),$unit,current_user()['id']]);
        log_activity('create','calculation',(int)db()->lastInsertId(),'Created engineering calculation');flash('success','Calculation draft saved. Professional review is required.');
        }
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT c.*,p.title project_title FROM engineering_calculations c LEFT JOIN projects p ON p.id=c.project_id WHERE c.organization_id=$org ORDER BY c.created_at DESC")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Engineering Calculation Studio</h1><p class="mt-1 text-sm text-amber-700">Draft calculations are not approved engineering designs. Obtain qualified professional review.</p></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-3"><?= csrf_field() ?><input name="title" required placeholder="Calculation title" class="rounded-lg border border-slate-300 px-3 py-2"><select name="calculation_type" class="rounded-lg border border-slate-300 px-3 py-2">    <option value="rcc_beam">RCC beam volume</option><option value="rcc_slab">RCC slab volume</option><option value="rcc_column">RCC column volume</option><option value="foundation">Foundation volume</option><option value="retaining_wall">Retaining wall volume</option><option value="road_geometry">Road geometry area</option><option value="pavement">Pavement volume</option><option value="drainage">Drainage volume</option><option value="water_supply">Water supply volume</option><option value="earthwork">Earthwork cut/fill</option><option value="soil_mechanics">Soil mechanics volume</option></select><select name="project_id" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">No project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input name="width" type="number" step="0.001" placeholder="Width (m)" class="rounded-lg border border-slate-300 px-3 py-2"><input name="depth" type="number" step="0.001" placeholder="Depth (m)" class="rounded-lg border border-slate-300 px-3 py-2"><input name="length" type="number" step="0.001" placeholder="Length (m)" class="rounded-lg border border-slate-300 px-3 py-2"><input name="thickness" type="number" step="0.001" placeholder="Thickness (m)" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white md:col-start-3">Save calculation</button></form></div><?= table_start(['Title','Type','Status','Standard','Unit','Review']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['title']) ?></td><td class="px-4 py-3"><?= e(str_replace('_',' ',ucwords($r['calculation_type'],'_'))) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td><td class="px-4 py-3"><?= e($r['standard_reference'] ?: 'Not specified') ?></td><td class="px-4 py-3"><?= e($r['unit'] ?: '—') ?></td><td class="px-4 py-3"><form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="review"><input type="hidden" name="calculation_id" value="<?= $r['id'] ?>"><select name="status" class="rounded border border-slate-300 px-1 py-1 text-xs"><option <?= $r['status']==='in_review'?'selected':'' ?>>in_review</option><option <?= $r['status']==='verified'?'selected':'' ?>>verified</option><option <?= $r['status']==='approved'?'selected':'' ?>>approved</option><option <?= $r['status']==='rejected'?'selected':'' ?>>rejected</option></select><button class="rounded bg-slate-100 px-2 text-xs">Save</button></form></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Calculations',$content,'calculations');break;

case 'site':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $stmt=db()->prepare('INSERT INTO site_reports (organization_id,project_id,report_date,weather,work_completed,labour_count,materials_used,equipment_used,issues,safety_observations,supervisor_remarks,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$org,(int)$_POST['project_id'],$_POST['report_date'],trim($_POST['weather']??''),trim($_POST['work_completed']??''),(int)$_POST['labour_count'],trim($_POST['materials_used']??''),trim($_POST['equipment_used']??''),trim($_POST['issues']??''),trim($_POST['safety_observations']??''),trim($_POST['supervisor_remarks']??''),current_user()['id']]);
        $reportId=(int)db()->lastInsertId();
        if(isset($_FILES['photo']) && $_FILES['photo']['error']===UPLOAD_ERR_OK){
            $photo=$_FILES['photo'];
            if($photo['size']<=10*1024*1024 && in_array($photo['type'],['image/jpeg','image/png'],true)){
                $ext=strtolower(pathinfo($photo['name'],PATHINFO_EXTENSION));
                $name='site-'.bin2hex(random_bytes(12)).'.'.$ext;
                if(move_uploaded_file($photo['tmp_name'],dirname(__DIR__).'/storage/uploads/'.$name)){
                    db()->prepare('INSERT INTO site_report_photos (site_report_id,file_path,caption) VALUES (?,?,?)')->execute([$reportId,$name,trim((string)($_POST['photo_caption']??''))]);
                }
            }
        }
        log_activity('create','site_report',$reportId,'Created daily site report');flash('success','Site report saved.');
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $rows=db()->query("SELECT s.*,p.title project_title FROM site_reports s JOIN projects p ON p.id=s.project_id WHERE s.organization_id=$org ORDER BY s.report_date DESC")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Daily Site Reports</h1></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-3"><?= csrf_field() ?><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input type="date" name="report_date" required value="<?= date('Y-m-d') ?>" class="rounded-lg border border-slate-300 px-3 py-2"><input name="weather" placeholder="Weather" class="rounded-lg border border-slate-300 px-3 py-2"><input name="labour_count" type="number" placeholder="Labour count" class="rounded-lg border border-slate-300 px-3 py-2"><textarea name="work_completed" rows="2" placeholder="Work completed" class="rounded-lg border border-slate-300 px-3 py-2"></textarea><textarea name="issues" rows="2" placeholder="Site issues" class="rounded-lg border border-slate-300 px-3 py-2"></textarea><textarea name="equipment_used" rows="2" placeholder="Equipment used" class="rounded-lg border border-slate-300 px-3 py-2"></textarea>    <textarea name="supervisor_remarks" rows="2" placeholder="Supervisor remarks" class="rounded-lg border border-slate-300 px-3 py-2"></textarea><input type="file" name="photo" accept="image/jpeg,image/png" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="photo_caption" placeholder="Photo caption" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white md:col-start-3">Save report</button></form></div><?= table_start(['Date','Project','Weather','Labour','Remarks']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3"><?= e($r['report_date']) ?></td><td class="px-4 py-3 font-medium"><?= e($r['project_title']) ?></td><td class="px-4 py-3"><?= e($r['weather'] ?: '—') ?></td><td class="px-4 py-3"><?= (int)$r['labour_count'] ?></td><td class="px-4 py-3"><?= e($r['supervisor_remarks'] ?: '—') ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Site reports',$content,'site');break;

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
        elseif (($_POST['action']??'')==='delivery') {
            db()->prepare('INSERT INTO material_deliveries (purchase_order_id,delivery_date,received_by,notes) VALUES (?,?,?,?)')->execute([(int)$_POST['purchase_order_id'],$_POST['delivery_date'],(int)current_user()['id'],trim((string)($_POST['notes']??''))]);
            db()->prepare("UPDATE purchase_orders SET status='received' WHERE id=? AND organization_id=?")->execute([(int)$_POST['purchase_order_id'],$org]);
            log_activity('create','material_delivery',(int)$_POST['purchase_order_id'],'Recorded material delivery');flash('success','Material delivery recorded.');
        }
        else {db()->prepare('INSERT INTO purchase_requests (organization_id,project_id,title,description,status,requested_by) VALUES (?,?,?,?,?,?)')->execute([$org,(int)$_POST['project_id'],trim($_POST['title']),trim($_POST['description']??''),'pending',current_user()['id']]);log_activity('create','purchase_request',(int)db()->lastInsertId(),'Created purchase request');flash('success','Purchase request submitted.');}
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $suppliers=db()->query("SELECT * FROM suppliers WHERE organization_id=$org ORDER BY name")->fetchAll();
    $requests=db()->query("SELECT pr.*,p.title project_title FROM purchase_requests pr JOIN projects p ON p.id=pr.project_id WHERE pr.organization_id=$org ORDER BY pr.created_at DESC")->fetchAll();
    $orders=db()->query("SELECT po.*,s.name supplier_name FROM purchase_orders po LEFT JOIN suppliers s ON s.id=po.supplier_id WHERE po.organization_id=$org ORDER BY po.created_at DESC")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Procurement</h1></div><div class="grid gap-6 lg:grid-cols-2"><div class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Purchase request</h2><form method="post" class="mt-4 space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="request"><select name="project_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><input name="title" required placeholder="Request title" class="w-full rounded-lg border border-slate-300 px-3 py-2"><textarea name="description" rows="3" placeholder="Materials or items required" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Submit request</button></form></div><div class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Add supplier</h2><form method="post" class="mt-4 grid gap-3 sm:grid-cols-2"><?= csrf_field() ?><input type="hidden" name="action" value="supplier"><input name="name" required placeholder="Supplier name" class="rounded-lg border border-slate-300 px-3 py-2"><input name="contact_person" placeholder="Contact person" class="rounded-lg border border-slate-300 px-3 py-2"><input name="email" type="email" placeholder="Email" class="rounded-lg border border-slate-300 px-3 py-2"><input name="phone" placeholder="Phone" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white sm:col-span-2">Add supplier</button></form></div></div><div class="mt-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Create purchase order</h2><form method="post" class="mt-4 grid gap-3 md:grid-cols-4"><?= csrf_field() ?><input type="hidden" name="action" value="order"><select name="purchase_request_id" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Request</option><?php foreach($requests as $r):?><option value="<?= $r['id'] ?>"><?= e($r['title']) ?></option><?php endforeach?></select><select name="supplier_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Supplier</option><?php foreach($suppliers as $s):?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach?></select><input type="date" name="order_date" required value="<?= date('Y-m-d') ?>" class="rounded-lg border border-slate-300 px-3 py-2"><input name="total" type="number" step="0.01" required placeholder="Total" class="rounded-lg border border-slate-300 px-3 py-2"><input name="description" placeholder="Item description" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-2"><input name="quantity" type="number" step="0.001" placeholder="Quantity" class="rounded-lg border border-slate-300 px-3 py-2"><input name="unit_price" type="number" step="0.01" placeholder="Unit price" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white md:col-span-4">Issue order</button></form></div>    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Record material delivery</h2><form method="post" class="mt-4 grid gap-3 md:grid-cols-4"><?= csrf_field() ?><input type="hidden" name="action" value="delivery"><select name="purchase_order_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Purchase order</option><?php foreach($orders as $o):?><option value="<?= $o['id'] ?>"><?= e($o['order_number']) ?></option><?php endforeach?></select><input type="date" name="delivery_date" required value="<?= date('Y-m-d') ?>" class="rounded-lg border border-slate-300 px-3 py-2"><input name="notes" placeholder="Delivery notes" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Record delivery</button></form></div><div class="mt-6 grid gap-6 lg:grid-cols-3"><?= table_start(['Request','Project','Status']) ?><?php foreach($requests as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['title']) ?></td><td class="px-4 py-3"><?= e($r['project_title']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td></tr><?php endforeach?></tbody></table><?= table_start(['Order','Supplier','Status','Total']) ?><?php foreach($orders as $o):?><tr><td class="px-4 py-3 font-medium"><?= e($o['order_number']) ?></td><td class="px-4 py-3"><?= e($o['supplier_name']) ?></td><td class="px-4 py-3 capitalize"><?= e($o['status']) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$o['total'],2) ?></td></tr><?php endforeach?></tbody></table><?= table_start(['Supplier','Contact','Phone']) ?><?php foreach($suppliers as $s):?><tr><td class="px-4 py-3 font-medium"><?= e($s['name']) ?></td><td class="px-4 py-3"><?= e($s['contact_person'] ?: '—') ?></td><td class="px-4 py-3"><?= e($s['phone'] ?: '—') ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Procurement',$content,'procurement');break;

case 'expenses':
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $stmt=db()->prepare('INSERT INTO expenses (organization_id,project_id,category,description,amount,expense_date,status,created_by) VALUES (?,?,?,?,?,?,\'pending\',?)');
        $stmt->execute([$org,(int)$_POST['project_id'],trim($_POST['category']),trim($_POST['description']??''),(float)$_POST['amount'],$_POST['expense_date'],current_user()['id']]);
        log_activity('create','expense',(int)db()->lastInsertId(),'Recorded expense'); flash('success','Expense recorded for approval.');
    }
    $projects=db()->query("SELECT id,title FROM projects WHERE organization_id=$org")->fetchAll();
    $stmt=db()->prepare('SELECT e.*,p.title project_title FROM expenses e JOIN projects p ON p.id=e.project_id WHERE e.organization_id=? ORDER BY e.expense_date DESC');
    $stmt->execute([$org]);$rows=$stmt->fetchAll();
    ob_start();?><div class="mb-5 flex justify-between">    <h1 class="text-2xl font-semibold">Expenses</h1><div class="flex gap-2"><a href="<?= e(base_url('export/csv/expenses')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">CSV</a><a href="<?= e(base_url('export/xls/expenses')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Excel</a><a href="<?= e(base_url('export/pdf/expenses')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">PDF</a></div></div><div class="mb-6 rounded-xl border border-slate-200 bg-white p-5"><form method="post" class="grid gap-3 md:grid-cols-5"><?= csrf_field() ?><select name="project_id" required class="rounded-lg border border-slate-300 px-3 py-2"><option value="">Project</option><?php foreach($projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach?></select><select name="category" class="rounded-lg border border-slate-300 px-3 py-2"><option>Labour</option><option>Material</option><option>Equipment</option><option>Contractor</option><option>Other</option></select><input name="amount" type="number" step="0.01" required placeholder="Amount" class="rounded-lg border border-slate-300 px-3 py-2"><input name="expense_date" type="date" required value="<?= date('Y-m-d') ?>" class="rounded-lg border border-slate-300 px-3 py-2"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Record</button><input name="description" placeholder="Description" class="rounded-lg border border-slate-300 px-3 py-2 md:col-span-5"></form></div><?= table_start(['Expense','Project','Category','Amount','Date','Status']) ?><?php foreach($rows as $r):?><tr><td class="px-4 py-3 font-medium"><?= e($r['description'] ?: 'Expense') ?></td><td class="px-4 py-3"><?= e($r['project_title']) ?></td><td class="px-4 py-3"><?= e($r['category']) ?></td><td class="px-4 py-3">NPR <?= number_format((float)$r['amount'],2) ?></td><td class="px-4 py-3"><?= e($r['expense_date']) ?></td><td class="px-4 py-3 capitalize"><?= e($r['status']) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Expenses',$content,'expenses');break;

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
    $documents=db()->query("SELECT d.*,p.title project_title FROM documents d LEFT JOIN projects p ON p.id=d.project_id WHERE d.organization_id=$org AND d.status='approved' AND d.access_level IN ('client','public') ORDER BY d.created_at DESC")->fetchAll();
    $milestones=db()->query("SELECT m.*,p.title project_title FROM milestones m JOIN projects p ON p.id=m.project_id WHERE m.organization_id=$org ORDER BY m.due_date")->fetchAll();
    ob_start();?><div class="mb-5"><h1 class="text-2xl font-semibold">Client Portal</h1><p class="mt-1 text-sm text-slate-500">Assigned projects, approved documents and milestones.</p></div><div class="grid gap-4 md:grid-cols-2"><?php foreach($projects as $p):?><article class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex justify-between"><div><p class="text-xs text-slate-500"><?= e($p['client_name'] ?: 'Organization project') ?></p><h2 class="mt-1 font-semibold"><?= e($p['title']) ?></h2></div><span class="text-sm text-slate-500"><?= (int)$p['progress'] ?>%</span></div><div class="mt-4 h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width:<?= (int)$p['progress'] ?>%"></div></div></article><?php endforeach?></div><div class="mt-6 grid gap-6 lg:grid-cols-2"><?= table_start(['Approved document','Project','Category']) ?><?php foreach($documents as $d):?><tr><td class="px-4 py-3 font-medium"><?= e($d['title']) ?></td><td class="px-4 py-3"><?= e($d['project_title'] ?: 'Organization') ?></td><td class="px-4 py-3"><?= e($d['category']) ?></td></tr><?php endforeach?></tbody></table><?= table_start(['Milestone','Project','Due','Status']) ?><?php foreach($milestones as $m):?><tr><td class="px-4 py-3 font-medium"><?= e($m['title']) ?></td><td class="px-4 py-3"><?= e($m['project_title']) ?></td><td class="px-4 py-3"><?= e($m['due_date']) ?></td><td class="px-4 py-3"><?= e($m['status']) ?></td></tr><?php endforeach?></tbody></table></div><?php $content=ob_get_clean();render('Client portal',$content,'portal');break;

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
    ob_start();?><div class="mb-5 flex justify-between"><h1 class="text-2xl font-semibold">Reports & Analytics</h1><div class="flex gap-2">    <a href="<?= e(base_url('export/pdf/projects')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Project PDF</a><a href="<?= e(base_url('export/xls/projects')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Project Excel</a><a href="<?= e(base_url('export/pdf/expenses')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Expense PDF</a></div></div><div class="grid gap-6 lg:grid-cols-2"><section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Project progress</h2><div class="mt-5 space-y-4"><?php foreach($progress as $p):?><div><div class="flex justify-between text-sm"><span><?= e($p['title']) ?></span><span><?= (int)$p['progress'] ?>%</span></div><div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-orange-500" style="width:<?= (int)$p['progress'] ?>%"></div></div></div><?php endforeach;if(!$progress):?><p class="text-sm text-slate-500">No project data.</p><?php endif?></div></section><section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Expenditure by category</h2><?= table_start(['Category','Amount']) ?><?php foreach($expenses as $e):?><tr><td class="px-4 py-3"><?= e($e['category']) ?></td><td class="px-4 py-3 font-medium">NPR <?= number_format((float)$e['total'],2) ?></td></tr><?php endforeach?></tbody></table></section></div><?php $content=ob_get_clean();render('Reports',$content,'reports');break;

default:
    http_response_code(404); echo '<main style="padding:40px;font-family:sans-serif"><h1>Page not found</h1><a href="'.e(base_url('dashboard')).'">Return to dashboard</a></main>';
}
