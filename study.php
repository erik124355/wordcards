<?php require 'config.php'; require 'header.php';
$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare('SELECT l.*, u.nickname FROM words_lists l JOIN words_users u ON u.id=l.user_id WHERE l.id=?'); $s->execute([$id]); $list = $s->fetch();
if (!$list) { http_response_code(404); exit('Pack not found'); }
$s = db()->prepare('SELECT term,definition FROM words_words WHERE list_id=? ORDER BY position'); $s->execute([$id]); $words = $s->fetchAll();

// Study state lives in the session (per pack)
$S = &$_SESSION["study$id"]; $S = $S ?? [];
$touch = array_intersect_key($_GET, array_flip(['mode', 'front', 'shuf', 'start']));
if ($touch || empty($S['deck'])) {
    $old = $S['missed'] ?? [];
    $S['mode'] = ($_GET['mode'] ?? $S['mode'] ?? 'cards') === 'write' ? 'write' : 'cards';
    $S['front'] = ($_GET['front'] ?? $S['front'] ?? 'a') === 'b' ? 'b' : 'a';   // which language is shown
    $S['shuf'] = ($_GET['shuf'] ?? $S['shuf'] ?? '1') === '0' ? '0' : '1';
    $idx = (($_GET['start'] ?? '') === 'missed' && $old) ? $old : array_keys($words);
    if ($S['shuf'] === '1') shuffle($idx);
    $S = array_merge($S, ['deck' => array_values($idx), 'i' => 0, 'score' => 0, 'missed' => [], 'res' => null]);
    if ($touch) go("study.php?id=$id");
}
$mode = $S['mode']; $front = $S['front']; $n = count($S['deck']);
$ask = fn($w) => $front === 'a' ? $w['term'] : $w['definition'];
$ans = fn($w) => $front === 'a' ? $w['definition'] : $w['term'];
$langFront = $front === 'a' ? $list['lang_a'] : $list['lang_b']; $langBack = $front === 'a' ? $list['lang_b'] : $list['lang_a'];

if (isset($_GET['go'])) { $S['i'] = max(0, min($n - 1, $S['i'] + ($_GET['go'] === 'next' ? 1 : -1))); go("study.php?id=$id"); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $w = $words[$S['deck'][$S['i']] ?? -1] ?? null; $a = $_POST['action'] ?? '';
    if ($a === 'check' && $w && !$S['res']) { [$g, $b] = grade($_POST['answer'] ?? '', $ans($w)); $S['res'] = ['g' => $g, 'best' => $b, 'in' => trim($_POST['answer'] ?? '')]; }
    elseif ($a === 'next' && $S['res']) {
        if ($S['res']['g'] === 'wrong') $S['missed'][] = $S['deck'][$S['i']]; else $S['score']++;
        $S['res'] = null; $S['i']++;
    }
    go("study.php?id=$id");
}
$done = $S['i'] >= $n; $cur = $done ? null : $words[$S['deck'][$S['i']]]; $res = $S['res'];
$base = "study.php?id=$id";
page_head($list['title']); ?>
<h2><?= h($list['title']) ?></h2>
<p class="muted"><?= h($list['lang_a']) ?> ⇄ <?= h($list['lang_b']) ?> · by <?= h($list['nickname']) ?>
  <?php if (user() == $list['user_id']): ?>· <a href="edit.php?id=<?= $id ?>">Edit</a><?php endif; ?></p>
<div class="row bar">
  <a class="<?= $mode === 'cards' ? 'btn' : 'ghost' ?> b" href="<?= $base ?>&mode=cards">Flashcards</a>
  <a class="<?= $mode === 'write' ? 'btn' : 'ghost' ?> b" href="<?= $base ?>&mode=write">Write</a>
  <a class="ghost b" href="<?= $base ?>&front=<?= $front === 'a' ? 'b' : 'a' ?>">⇄ Showing: <?= h($langFront) ?></a>
  <a class="ghost b" href="<?= $base ?>&shuf=<?= $S['shuf'] === '1' ? '0' : '1' ?>">Shuffle: <?= $S['shuf'] === '1' ? 'on' : 'off' ?></a>
</div>

<?php if ($done): ?>
  <div class="card center"><h3>Done! <?= $S['score'] ?> / <?= $n ?> correct</h3>
    <?php if ($S['missed']): ?><a class="btn b" href="<?= $base ?>&start=missed">Practice <?= count($S['missed']) ?> missed</a><?php endif; ?>
    <a class="ghost b" href="<?= $base ?>&start=1">Restart</a></div>

<?php elseif ($mode === 'cards'): ?>
  <p class="muted center"><?= $S['i'] + 1 ?> / <?= $n ?></p>
  <input type="checkbox" id="flip" class="flipbox">
  <label for="flip" class="flash"><small class="front"><?= h($langFront) ?></small><div class="big front"><?= h($ask($cur)) ?></div>
    <small class="back"><?= h($langBack) ?></small><div class="big back"><?= h($ans($cur)) ?></div></label>
  <div class="row center"><a class="ghost b" href="<?= $base ?>&go=prev">←</a><label for="flip" class="ghost b">Flip</label><a class="ghost b" href="<?= $base ?>&go=next">→</a></div>

<?php else: ?>
  <p class="muted center"><?= $S['i'] + 1 ?> / <?= $n ?></p>
  <form method="post" class="card center"><?= csrf_field() ?>
    <small><?= h($langFront) ?></small><div class="big"><?= h($ask($cur)) ?></div>
    <?php if (!$res): ?>
      <input id="answer" name="answer" placeholder="Write in <?= h($langBack) ?>" autofocus autocomplete="off" autocapitalize="off" spellcheck="false">
      <div class="row center"><?php foreach (['ä','ö','å','é','è','ñ','ü','ß','ç','¿','¡'] as $c): ?>
        <button type="button" class="ghost ch" onclick="var a=document.getElementById('answer');a.value+='<?= $c ?>';a.focus()"><?= $c ?></button><?php endforeach; ?></div>
      <button class="btn" name="action" value="check">Check</button>
    <?php else: ?>
      <div class="<?= $res['g'] ?>">
        <?php if ($res['g'] === 'correct'): ?><p>✓ Correct!</p>
        <?php else: ?>
          <p><?= $res['g'] === 'almost' ? '≈ Almost! Small spelling mistake.' : '✗ Not quite.' ?></p>
          <p>You wrote: <?php foreach (diff_marks($res['in'], $res['best']) as [$c, $ok]): ?><span class="<?= $ok ? '' : 'x' ?>"><?= h($c) ?></span><?php endforeach; ?></p>
          <p>Correct: <b><?= h($ans($cur)) ?></b></p>
        <?php endif; ?></div>
      <button class="btn" name="action" value="next" autofocus>Next</button>
    <?php endif; ?>
  </form>
<?php endif; page_foot();
