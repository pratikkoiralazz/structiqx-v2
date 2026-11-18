<?php
$user = current_user();
$orgName = $_SESSION['organization_name'] ?? 'structiQX';
$flash = consume_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'structiQX') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,sans-serif}
  </style>
</head>
<body class="bg-slate-50 text-slate-900">
  <div class="min-h-screen flex">
    <aside class="hidden md:flex w-64 shrink-0 flex-col border-r border-slate-200 bg-white">
      <div class="h-16 flex items-center gap-3 px-5 border-b border-slate-100">
        <div class="h-9 w-9 rounded-lg bg-orange-500 text-white grid place-items-center font-bold">SQ</div>
        <div class="min-w-0"><p class="font-semibold truncate"><?= e($orgName) ?></p><p class="text-xs text-slate-500">Civil OS</p></div>
      </div>
      <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
        <?php
        $items = [
          'dashboard'=>'LayoutDashboard','projects'=>'Building2','tasks'=>'CircleCheckBig','milestones'=>'Milestone',
          'team'=>'Users','gantt'=>'ChartGantt','budgets'=>'Wallet','expenses'=>'ReceiptText','boq'=>'ListTree','calculations'=>'Calculator',
          'site'=>'HardHat','documents'=>'FolderOpen','procurement'=>'Package','approvals'=>'BadgeCheck','portal'=>'UsersRound','reports'=>'ChartNoAxesCombined'
        ];
        foreach ($items as $route=>$icon):
          $active = ($route === $page) ? 'bg-slate-100 text-slate-950' : 'text-slate-600 hover:bg-slate-50';
        ?>
        <a href="<?= e(base_url($route)) ?>" class="<?= $active ?> flex items-center gap-3 rounded-lg px-3 py-2">
          <i data-lucide="<?= $icon ?>" class="h-4 w-4"></i><?= e(ucwords(str_replace('_',' ', $route))) ?>
        </a>
        <?php endforeach; ?>
      </nav>
      <a href="<?= e(base_url('logout')) ?>" class="m-3 flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><i data-lucide="LogOut" class="h-4 w-4"></i>Sign out</a>
    </aside>
    <div class="flex-1 min-w-0">
      <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-5 md:px-8">
        <div class="text-sm text-slate-500"><span class="hidden sm:inline">Workspace / </span><span class="font-medium text-slate-800"><?= e(ucwords(str_replace('_',' ', $page))) ?></span></div>
        <div class="flex items-center gap-3">
          <button class="h-9 w-9 grid place-items-center rounded-lg border border-slate-200 text-slate-500" title="Notifications"><i data-lucide="Bell" class="h-4 w-4"></i></button>
          <div class="h-9 w-9 rounded-full bg-slate-900 text-white grid place-items-center text-xs font-semibold"><?= e(strtoupper(substr($user['name'] ?? 'U',0,2))) ?></div>
        </div>
      </header>
      <main class="p-5 md:p-8">
        <?php foreach ($flash as $notice): ?>
          <div class="mb-5 rounded-lg border <?= $notice['type']==='error'?'border-red-200 bg-red-50 text-red-700':'border-emerald-200 bg-emerald-50 text-emerald-700' ?> px-4 py-3 text-sm"><?= e($notice['message']) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
      </main>
    </div>
  </div>
  <script>lucide.createIcons();</script>
</body>
</html>
