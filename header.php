<?php function page_head($title = 'WordCards') { ?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> · WordCards</title><link rel="stylesheet" href="style.css"></head><body>
<header><a href="index.php" class="logo">WordCards</a>
<nav><a href="index.php">Browse</a>
<?php if (user()): ?>
  <a href="index.php?mine=1">My packs</a><a href="edit.php">+ New pack</a><span class="muted"><?= h($_SESSION['nick']) ?></span>
  <form method="post" action="auth.php" class="inline"><?= csrf_field() ?><button class="ghost" name="mode" value="logout">Log out</button></form>
<?php else: ?>
  <a href="auth.php?mode=login">Log in</a><a href="auth.php?mode=signup" class="btn">Sign up</a>
<?php endif; ?></nav></header><main>
<?php } function page_foot() { echo '</main></body></html>'; }
