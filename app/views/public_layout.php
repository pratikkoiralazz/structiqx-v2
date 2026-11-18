<?php
$title = $title ?? 'STRUCTIQX';
$activePage = $activePage ?? '';
$brand = '<span class="sq-mark">SQ</span><span>STRUCTIQX</span>';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | STRUCTIQX</title>
  <meta name="description" content="StructiqX manages construction projects, engineering calculations, budgets, teams and site operations in one workspace.">
  <link rel="stylesheet" href="<?= e(base_url('assets/marketing.css')) ?>">
</head>
<body class="marketing">
  <header class="sq-container">
    <nav class="sq-nav" aria-label="Primary navigation">
      <a class="sq-brand" href="<?= e(base_url('')) ?>"><?= $brand ?></a>
      <div class="sq-links">
        <a href="<?= e(base_url('features')) ?>">Features</a>
        <a href="<?= e(base_url('solutions')) ?>">Solutions</a>
        <a href="<?= e(base_url('pricing')) ?>">Pricing</a>
        <a href="<?= e(base_url('about')) ?>">About</a>
        <a href="<?= e(base_url('contact')) ?>">Contact</a>
      </div>
      <div class="sq-actions">
        <a class="sq-btn" href="<?= e(base_url('login')) ?>">Login</a>
        <a class="sq-btn sq-btn-accent" href="<?= e(base_url('register')) ?>">Get Started</a>
      </div>
      <button class="sq-menu" type="button" aria-label="Open menu" onclick="document.querySelector('.sq-links').style.display=document.querySelector('.sq-links').style.display==='flex'?'none':'flex'">≡</button>
    </nav>
  </header>
  <?= $content ?>
  <footer class="sq-footer">
    <div class="sq-container">
      <div class="sq-footer-grid">
        <div><a class="sq-brand" href="<?= e(base_url('')) ?>"><?= $brand ?></a><p>Engineering Projects. Simplified.</p></div>
        <div><strong>Product</strong><a href="<?= e(base_url('features')) ?>">Features</a><a href="<?= e(base_url('solutions')) ?>">Solutions</a><a href="<?= e(base_url('pricing')) ?>">Pricing</a></div>
        <div><strong>Company</strong><a href="<?= e(base_url('about')) ?>">About Us</a><a href="<?= e(base_url('contact')) ?>">Contact</a></div>
        <div><strong>Access</strong><a href="<?= e(base_url('login')) ?>">Login</a><a href="<?= e(base_url('register')) ?>">Create workspace</a></div>
      </div>
      <div class="sq-footer-bottom"><span>© <?= date('Y') ?> STRUCTIQX</span><span>Built for civil engineering teams</span></div>
    </div>
  </footer>
</body>
</html>
