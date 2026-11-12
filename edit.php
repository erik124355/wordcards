<?php require 'config.php'; require 'header.php'; require_login();
$id = (int)($_GET['id'] ?? 0); $err = '';
$f = ['title' => '', 'description' => '', 'lang_a' => 'Finnish', 'lang_b' => 'Swedish', 'tags' => ''];
$terms = $defs = []; $bulk = '';
if ($id) {
    $s = db()->prepare('SELECT * FROM words_lists WHERE id=?'); $s->execute([$id]); $l = $s->fetch();
    if (!$l || $l['user_id'] != user()) { http_response_code(403); exit('Not allowed'); }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($id) {
        $f = array_intersect_key($l, $f);
        $s = db()->prepare('SELECT term,definition FROM words_words WHERE list_id=? ORDER BY position'); $s->execute([$id]);
        foreach ($s->fetchAll() as $w) { $terms[] = $w['term']; $defs[] = $w['definition']; }
    }
} else {
    foreach ($f as $k => $_) $f[$k] = trim($_POST[$k] ?? '');
    $terms = array_values($_POST['term'] ?? []); $defs = array_values($_POST['def'] ?? []); $bulk = $_POST['bulk'] ?? '';
    $a = $_POST['action'] ?? '';
    if (isset($_POST['remove'])) { array_splice($terms, (int)$_POST['remove'], 1); array_splice($defs, (int)$_POST['remove'], 1); }
    elseif ($a === 'addrow') { for ($i = 0; $i < 5; $i++) { $terms[] = ''; $defs[] = ''; } }
    elseif ($a === 'bulk') { // one pair per line: tab, ";" or " - "
        foreach (preg_split('/\R/', $bulk) as $line) {
            $p = preg_split('/\t|;| - /', $line, 2);
            if (count($p) == 2 && trim($p[0]) !== '') { $terms[] = trim($p[0]); $defs[] = trim($p[1]); }
        }
        $bulk = '';
    }
    elseif ($a === 'delete' && $id) { db()->prepare('DELETE FROM words_lists WHERE id=? AND user_id=?')->execute([$id, user()]); go('index.php?mine=1'); }
    elseif ($a === 'save') {
        $words = [];
        foreach ($terms as $i => $t) if (trim($t) !== '' && trim($defs[$i] ?? '') !== '') $words[] = [mb_substr(trim($t), 0, 200), mb_substr(trim($defs[$i]), 0, 200)];
        $tags = array_unique(array_filter(array_map(fn($t) => mb_strtolower(trim($t)), explode(',', $f['tags']))));
        if ($f['title'] === '' || $f['lang_a'] === '' || $f['lang_b'] === '' || !$words) $err = 'Title, both languages and at least one full card are required';
        else {
            $pdo = db(); $pdo->beginTransaction();
            $vals = [mb_substr($f['title'], 0, 100), mb_substr($f['description'], 0, 500), mb_substr($f['lang_a'], 0, 40), mb_substr($f['lang_b'], 0, 40), mb_substr(implode(',', $tags), 0, 300)];
            if ($id) { $pdo->prepare('UPDATE words_lists SET title=?,description=?,lang_a=?,lang_b=?,tags=? WHERE id=?')->execute([...$vals, $id]); $pdo->prepare('DELETE FROM words_words WHERE list_id=?')->execute([$id]); }
            else { $pdo->prepare('INSERT INTO words_lists (title,description,lang_a,lang_b,tags,user_id) VALUES (?,?,?,?,?,?)')->execute([...$vals, user()]); $id = (int)$pdo->lastInsertId(); }
            $ins = $pdo->prepare('INSERT INTO words_words (list_id,position,term,definition) VALUES (?,?,?,?)');
            foreach ($words as $i => $w) $ins->execute([$id, $i, $w[0], $w[1]]);
            $pdo->commit(); go('study.php?id=' . $id);
        }
    }
}
while (count($terms) < 5) { $terms[] = ''; $defs[] = ''; }
page_head($id ? 'Edit pack' : 'New pack'); ?>
<h2><?= $id ? 'Edit pack' : 'New pack' ?></h2>
<form method="post"><?= csrf_field() ?>
  <button name="action" value="save" class="hidden" tabindex="-1">Save</button>
  <div class="card">
    <input name="title" value="<?= h($f['title']) ?>" placeholder="Title">
    <input name="description" value="<?= h($f['description']) ?>" placeholder="Description (optional)">
    <div class="row"><input name="lang_a" value="<?= h($f['lang_a']) ?>" placeholder="Language 1"><input name="lang_b" value="<?= h($f['lang_b']) ?>" placeholder="Language 2"></div>
    <input name="tags" value="<?= h($f['tags']) ?>" placeholder="Tags, comma separated (e.g. finnish, swedish, high school, food)">
  </div>
  <div class="card"><h3>Cards</h3>
    <p class="muted">Several accepted answers? Separate with “/” (e.g. <code>hus / byggnad</code>).</p>
    <?php foreach ($terms as $i => $t): ?>
    <div class="row"><input name="term[]" value="<?= h($t) ?>" placeholder="<?= h($f['lang_a'] ?: 'Language 1') ?>">
      <input name="def[]" value="<?= h($defs[$i] ?? '') ?>" placeholder="<?= h($f['lang_b'] ?: 'Language 2') ?>">
      <button class="ghost" name="remove" value="<?= $i ?>" title="Remove">✕</button></div>
    <?php endforeach; ?>
    <button class="ghost" name="action" value="addrow">+ Add 5 rows</button>
    <details <?= $bulk !== '' ? 'open' : '' ?>><summary>Bulk paste</summary>
      <p class="muted">One pair per line, separated by a tab, “;” or “ - ”.</p>
      <textarea name="bulk" rows="6" placeholder="talo;hus&#10;kissa;katt"><?= h($bulk) ?></textarea>
      <button class="ghost" name="action" value="bulk">Add pasted cards</button>
    </details>
  </div>
  <?php if ($err): ?><p class="bad"><?= h($err) ?></p><?php endif; ?>
  <button class="btn" name="action" value="save">Save pack</button>
  <?php if ($id): ?><button class="ghost bad" name="action" value="delete" onclick="return confirm('Delete this pack?')">Delete</button><?php endif; ?>
</form>
<?php page_foot();
