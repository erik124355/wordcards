<?php require 'config.php'; require 'header.php';
$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare('SELECT l.*, u.nickname FROM words_lists l JOIN words_users u ON u.id=l.user_id WHERE l.id=?'); $s->execute([$id]); $list = $s->fetch();
if (!$list) { http_response_code(404); exit(t('Pack not found')); }
$s = db()->prepare('SELECT term,definition FROM words_words WHERE list_id=? ORDER BY position'); $s->execute([$id]); $words = $s->fetchAll();

// Study state lives in the session (per pack)
$S = &$_SESSION["study$id"]; $S = $S ?? [];
$touch = array_intersect_key($_GET, array_flip(['mode', 'front', 'shuf', 'start']));
if ($touch || empty($S['deck'])) {
    $old = $S['missed'] ?? [];
    $m = $_GET['mode'] ?? $S['mode'] ?? 'cards'; $S['mode'] = in_array($m, ['cards', 'write', 'choice'], true) ? $m : 'cards';
    $S['front'] = ($_GET['front'] ?? $S['front'] ?? 'a') === 'b' ? 'b' : 'a';   // which language is shown
    $S['shuf'] = ($_GET['shuf'] ?? $S['shuf'] ?? '1') === '0' ? '0' : '1';
    $idx = (($_GET['start'] ?? '') === 'missed' && $old) ? $old : array_keys($words);
    if ($S['shuf'] === '1') shuffle($idx);
    $S = array_merge($S, ['deck' => array_values($idx), 'i' => 0, 'score' => 0, 'missed' => [], 'res' => null, 'oi' => -1, 'opts' => []]);
    if ($touch) go("study.php?id=$id");
}
$mode = $S['mode']; $front = $S['front']; $n = count($S['deck']);
$ask = fn($w) => $front === 'a' ? $w['term'] : $w['definition'];
$ans = fn($w) => $front === 'a' ? $w['definition'] : $w['term'];
$langFront = lang_name($front === 'a' ? $list['lang_a'] : $list['lang_b']); $langBack = lang_name($front === 'a' ? $list['lang_b'] : $list['lang_a']);

if (isset($_GET['go'])) { $S['i'] = max(0, min($n - 1, $S['i'] + ($_GET['go'] === 'next' ? 1 : -1))); go("study.php?id=$id"); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $w = $words[$S['deck'][$S['i']] ?? -1] ?? null; $a = $_POST['action'] ?? '';
    if ($a === 'check' && $w && !$S['res']) { [$g, $b] = grade($_POST['answer'] ?? '', $ans($w)); $S['res'] = ['g' => $g, 'best' => $b, 'in' => trim($_POST['answer'] ?? '')]; }
    elseif (isset($_POST['opt']) && $w && !$S['res'] && isset($S['opts'][(int)$_POST['opt']])) {   // multiple choice pick
        $pick = $S['opts'][(int)$_POST['opt']]; $S['res'] = ['g' => $pick === $ans($w) ? 'correct' : 'wrong', 'pick' => $pick];
    }
    elseif ($a === 'next' && $S['res']) {
        if ($S['res']['g'] === 'wrong') $S['missed'][] = $S['deck'][$S['i']]; else $S['score']++;
        $S['res'] = null; $S['i']++;
    }
    go("study.php?id=$id");
}
$done = $S['i'] >= $n; $cur = $done ? null : $words[$S['deck'][$S['i']]]; $res = $S['res'];
if ($mode === 'choice' && !$done && $S['oi'] !== $S['i']) {   // 1 correct + up to 3 wrong options per question
    $correct = $ans($cur); $pool = [];
    foreach ($words as $w) { $x = $ans($w); if (mb_strtolower($x) !== mb_strtolower($correct)) $pool[mb_strtolower($x)] = $x; }
    $pool = array_values($pool); shuffle($pool); $opts = array_slice($pool, 0, 3); $opts[] = $correct; shuffle($opts);
    $S['opts'] = $opts; $S['oi'] = $S['i'];
}
$base = "study.php?id=$id";
page_head($list['title']); ?>
<div class="row between"><h2><?= h($list['title']) ?></h2>
  <?php if (user() == $list['user_id']): ?><a class="chip" href="edit.php?id=<?= $id ?>">✎ <?= e('Edit') ?></a><?php endif; ?></div>
<p class="muted"><?= h(lang_name($list['lang_a'])) ?> ⇄ <?= h(lang_name($list['lang_b'])) ?> · <?= e('by') ?> <?= h($list['nickname']) ?></p>
<?php if (trim($list['description'])): ?><p class="desc"><?= nl2br(h($list['description'])) ?></p><?php endif; ?>
<div class="bar">
  <div class="seg">
    <a class="<?= $mode === 'cards' ? 'on' : '' ?>" href="<?= $base ?>&mode=cards"><?= e('Flashcards') ?></a>
    <a class="<?= $mode === 'write' ? 'on' : '' ?>" href="<?= $base ?>&mode=write"><?= e('Write') ?></a>
    <a class="<?= $mode === 'choice' ? 'on' : '' ?>" href="<?= $base ?>&mode=choice"><?= e('Multiple choice') ?></a>
  </div>
  <a class="chip" href="<?= $base ?>&front=<?= $front === 'a' ? 'b' : 'a' ?>">⇄ <?= e('Showing:') ?> <?= h($langFront) ?></a>
  <a class="chip" href="<?= $base ?>&shuf=<?= $S['shuf'] === '1' ? '0' : '1' ?>"><?= e('Shuffle:') ?> <?= $S['shuf'] === '1' ? e('on') : e('off') ?></a>
</div>

<?php if ($done): ?>
  <div class="card center"><h3><?= e('Done! %d / %d correct', $S['score'], $n) ?></h3>
    <div class="row center"><?php if ($S['missed']): ?><a class="btn" href="<?= $base ?>&start=missed"><?= e('Practice %d missed', count($S['missed'])) ?></a><?php endif; ?>
    <a class="chip" href="<?= $base ?>&start=1"><?= e('Restart') ?></a></div></div>

<?php elseif ($mode === 'cards'): ?>
  <p class="muted center"><?= $S['i'] + 1 ?> / <?= $n ?></p>
  <input type="checkbox" id="flip" class="flipbox">
  <label for="flip" class="flash"><div class="inner">
    <div class="face front"><small><?= h($langFront) ?></small><div class="big"><?= h($ask($cur)) ?></div></div>
    <div class="face back"><small><?= h($langBack) ?></small><div class="big"><?= h($ans($cur)) ?></div></div>
  </div></label>
  <div class="row center"><a class="chip" href="<?= $base ?>&go=prev">←</a><label for="flip" class="chip"><?= e('Flip') ?></label><a class="chip" href="<?= $base ?>&go=next">→</a></div>

<?php elseif ($mode === 'write'): ?>
  <p class="muted center"><?= $S['i'] + 1 ?> / <?= $n ?></p>
  <form method="post" class="card center"><?= csrf_field() ?>
    <small><?= h($langFront) ?></small><div class="big"><?= h($ask($cur)) ?></div>
    <?php if (!$res): ?>
      <input id="answer" name="answer" placeholder="<?= e('Write your answer (%s)', $langBack) ?>" autofocus autocomplete="off" autocapitalize="off" spellcheck="false">
      <div class="row center"><?php foreach (['ä','ö','å','é','è','ñ','ü','ß','ç','¿','¡'] as $c): ?>
        <button type="button" class="chip ch" onclick="var a=document.getElementById('answer');a.value+='<?= $c ?>';a.focus()"><?= $c ?></button><?php endforeach; ?></div>
      <button class="btn" name="action" value="check"><?= e('Check') ?></button>
    <?php else: ?>
      <div class="<?= $res['g'] ?>">
        <?php if ($res['g'] === 'correct'): ?><p><?= e('✓ Correct!') ?></p>
        <?php else: ?>
          <p><?= $res['g'] === 'almost' ? e('≈ Almost! Small spelling mistake.') : e('✗ Not quite.') ?></p>
          <p><?= e('You wrote:') ?> <?php foreach (diff_marks($res['in'], $res['best']) as [$c, $ok]): ?><span class="<?= $ok ? '' : 'x' ?>"><?= h($c) ?></span><?php endforeach; ?></p>
          <p><?= e('Correct:') ?> <b><?= h($ans($cur)) ?></b></p>
        <?php endif; ?></div>
      <button class="btn" name="action" value="next" autofocus><?= e('Next') ?></button>
    <?php endif; ?>
  </form>

<?php else: ?>
  <p class="muted center"><?= $S['i'] + 1 ?> / <?= $n ?></p>
  <form method="post" class="card center"><?= csrf_field() ?>
    <small><?= h($langFront) ?></small><div class="big"><?= h($ask($cur)) ?></div>
    <?php if (count($S['opts']) < 2): ?><p class="muted"><?= e('Add more cards with different answers to use multiple choice.') ?></p><?php else: ?>
    <div class="opts"><?php foreach ($S['opts'] as $k => $o):
        $cls = $res ? ($o === $ans($cur) ? 'right' : ($o === $res['pick'] ? 'miss' : '')) : ''; ?>
      <button class="opt <?= $cls ?>" name="opt" value="<?= $k ?>" <?= $res ? 'disabled' : '' ?>><kbd><?= $k + 1 ?></kbd><?= h($o) ?></button>
    <?php endforeach; ?></div>
    <?php if ($res): ?><p class="<?= $res['g'] ?>"><?= $res['g'] === 'correct' ? e('✓ Correct!') : e('✗ Not quite.') ?></p>
      <button class="btn" name="action" value="next" autofocus><?= e('Next') ?></button>
    <?php else: ?><script>addEventListener('keydown',e=>{const b=document.querySelectorAll('.opt')[+e.key-1];if(b)b.click()})</script><?php endif; endif; ?>
  </form>
<?php endif; page_foot();
