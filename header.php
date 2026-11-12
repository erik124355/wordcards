<?php function page_head($title = 'WordCards') {
    $cur = basename($_SERVER['SCRIPT_NAME']); $mine = isset($_GET['mine']);
    $on = fn($c) => $c ? ' class="active"' : ''; ?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> · WordCards</title><link rel="stylesheet" href="style.css"></head><body>
<header><a href="index.php" class="logo"><span class="mark">W</span>WordCards</a>
<nav>
  <a href="index.php"<?= $on(($cur === 'index.php' && !$mine) || $cur === 'study.php') ?>>Browse</a>
<?php if (user()): ?>
  <a href="index.php?mine=1"<?= $on($cur === 'index.php' && $mine) ?>>My packs</a>
  <a href="edit.php"<?= $on($cur === 'edit.php' && empty($_GET['id'])) ?>>+ New pack</a>
  <span class="user"><?= h($_SESSION['nick']) ?></span>
  <form method="post" action="auth.php" class="inline"><?= csrf_field() ?><button class="chip" name="mode" value="logout">Log out</button></form>
<?php else: ?>
  <a href="auth.php?mode=login"<?= $on($cur === 'auth.php' && ($_GET['mode'] ?? 'login') === 'login') ?>>Log in</a>
  <a href="auth.php?mode=signup" class="btn">Sign up</a>
<?php endif; ?></nav></header><main>
<?php } function page_foot() { echo '</main></body></html>'; }
