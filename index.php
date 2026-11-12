<?php require 'config.php'; require 'header.php';
$mine = isset($_GET['mine']); if ($mine) require_login();
$q = trim($_GET['q'] ?? ''); $tag = strtolower(trim($_GET['tag'] ?? ''));
$sql = 'SELECT l.*, u.nickname, (SELECT COUNT(*) FROM words_words w WHERE w.list_id=l.id) AS wc
        FROM words_lists l JOIN words_users u ON u.id=l.user_id WHERE 1'; $p = [];
if ($mine) { $sql .= ' AND l.user_id=?'; $p[] = user(); }
else {
    if ($q !== '') { $sql .= ' AND (l.title LIKE ? OR l.description LIKE ?)'; $p[] = $p[] = "%$q%"; }
    if ($tag !== '') { $sql .= " AND CONCAT(',',l.tags,',') LIKE ?"; $p[] = "%,$tag,%"; }
}
$s = db()->prepare($sql . ' ORDER BY l.created_at DESC LIMIT 100'); $s->execute($p); $lists = $s->fetchAll();
page_head($mine ? t('My packs') : t('Browse')); ?>
<h2><?= $mine ? e('My packs') : e('Browse word packs') ?></h2>
<?php if (!$mine): ?>
<form class="row" method="get">
  <input name="q" value="<?= h($q) ?>" placeholder="<?= e('Search title or description') ?>">
  <input name="tag" value="<?= h($tag) ?>" placeholder="<?= e('Tag (e.g. spanish, high school)') ?>"><button class="btn"><?= e('Search') ?></button>
</form>
<?php endif; ?>
<div class="grid">
<?php foreach ($lists as $l): ?>
  <div class="card pack">
    <a href="study.php?id=<?= $l['id'] ?>"><b><?= h($l['title']) ?></b></a>
    <small class="muted"><?= h(lang_name($l['lang_a'])) ?> ⇄ <?= h(lang_name($l['lang_b'])) ?> · <?= $l['wc'] ?> <?= e('cards') ?> · <?= e('by') ?> <?= h($l['nickname']) ?></small>
    <?php if (trim($l['description'])): ?><p class="desc"><?= h($l['description']) ?></p><?php endif; ?>
    <span><?php foreach (array_filter(explode(',', $l['tags'])) as $t): ?><a class="tag" href="index.php?tag=<?= urlencode($t) ?>"><?= h($t) ?></a><?php endforeach; ?></span>
    <?php if ($mine): ?><a class="chip" href="edit.php?id=<?= $l['id'] ?>">✎ <?= e('Edit') ?></a><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php if (!$lists): ?><p class="muted"><?= e('No packs found.') ?></p><?php endif; page_foot();
