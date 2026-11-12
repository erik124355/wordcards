<?php require 'config.php'; require 'header.php'; require_login();
$id = (int)($_GET['id'] ?? 0); $err = '';
$f = ['title' => '', 'description' => '', 'lang_a' => '', 'lang_b' => '', 'tags' => ''];
$terms = $defs = [];
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
    $f['lang_a'] = snap_language($f['lang_a']); $f['lang_b'] = snap_language($f['lang_b']);
    $terms = array_values($_POST['term'] ?? []); $defs = array_values($_POST['def'] ?? []);
    $a = $_POST['action'] ?? '';
    if ($a === 'delete' && $id) { db()->prepare('DELETE FROM words_lists WHERE id=? AND user_id=?')->execute([$id, user()]); go('index.php?mine=1'); }
    elseif ($a === 'save') {
        $words = [];
        foreach ($terms as $i => $t) if (trim($t) !== '' && trim($defs[$i] ?? '') !== '') $words[] = [mb_substr(trim($t), 0, 200), mb_substr(trim($defs[$i]), 0, 200)];
        $tags = array_unique(array_filter(array_map(fn($t) => mb_strtolower(trim($t)), explode(',', $f['tags']))));
        if ($f['title'] === '' || $f['lang_a'] === '' || $f['lang_b'] === '' || !$words) $err = t('Title, both languages and at least one full card are required');
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
while (count($terms) < 3) { $terms[] = ''; $defs[] = ''; }
page_head($id ? t('Edit pack') : t('New pack')); ?>
<h2><?= $id ? e('Edit pack') : e('New pack') ?></h2>
<form method="post" id="editform"><?= csrf_field() ?>
  <button name="action" value="save" class="hidden" tabindex="-1"><?= e('Save') ?></button>
  <div class="card">
    <label><?= e('Title') ?><input name="title" value="<?= h($f['title']) ?>" placeholder="<?= e('e.g. Food and drinks') ?>"></label>
    <label><?= e('Description') ?> <span class="muted"><?= e('(optional)') ?></span><input name="description" value="<?= h($f['description']) ?>" placeholder="<?= e('What is this pack about?') ?>"></label>
    <div class="row two">
      <label><?= e('Language 1') ?><input class="lang" name="lang_a" list="langs" value="<?= h($f['lang_a']) ?>" placeholder="<?= e('e.g. English') ?>" autocomplete="off"></label>
      <label><?= e('Language 2') ?><input class="lang" name="lang_b" list="langs" value="<?= h($f['lang_b']) ?>" placeholder="<?= e('e.g. Spanish') ?>" autocomplete="off"></label>
    </div>
    <label><?= e('Tags') ?> <span class="muted"><?= e('(comma separated)') ?></span><input name="tags" value="<?= h($f['tags']) ?>" placeholder="<?= e('e.g. spanish, english, high school, food') ?>"></label>
    <datalist id="langs"><?php foreach (language_list() as $l): ?><option value="<?= h($l) ?>"><?php endforeach; ?></datalist>
  </div>
  <div class="card"><div class="row between"><h3><?= e('Cards') ?></h3><button type="button" class="chip" id="bulkbtn">⇪ <?= e('Bulk paste') ?></button></div>
    <p class="muted"><?= e('Several accepted answers? Separate them with “/” (e.g.') ?> <code>house / building</code>).</p>
    <div id="rows">
    <?php foreach ($terms as $i => $t): ?>
      <div class="row"><input class="t-a" name="term[]" value="<?= h($t) ?>"><input class="t-b" name="def[]" value="<?= h($defs[$i] ?? '') ?>">
        <button type="button" class="icon" title="<?= e('Remove card') ?>">✕</button></div>
    <?php endforeach; ?>
    </div>
    <button type="button" class="chip" id="addrow"><?= e('+ Add card') ?></button>
  </div>
  <?php if ($err): ?><p class="alert"><?= h($err) ?></p><?php endif; ?>
  <div class="row"><button class="btn" name="action" value="save"><?= e('Save pack') ?></button>
  <?php if ($id): ?><button class="chip danger" name="action" value="delete" onclick="return confirm(<?= h(json_encode(t('Delete this pack?'), JSON_UNESCAPED_UNICODE)) ?>)"><?= e('Delete') ?></button><?php endif; ?></div>
</form>

<dialog id="bulkdlg"><h3><?= e('Bulk paste') ?></h3>
  <p class="muted"><?= e('One pair per line, separated by a tab, “;” or “ - ”.') ?></p>
  <textarea id="bulktext" rows="8" placeholder="cat;gato&#10;dog;perro"></textarea>
  <div class="row end"><button type="button" class="chip" id="bulkcancel"><?= e('Cancel') ?></button><button type="button" class="btn" id="bulkadd"><?= e('Add cards') ?></button></div>
</dialog>

<script>
const PH = [<?= json_encode(t('Language 1'), JSON_UNESCAPED_UNICODE) ?>, <?= json_encode(t('Language 2'), JSON_UNESCAPED_UNICODE) ?>];
const LANGS = <?= json_encode(language_list(), JSON_UNESCAPED_UNICODE) ?>;
let dirty = <?= $err ? 'true' : 'false' ?>;
const rows = document.getElementById('rows'), form = document.getElementById('editform');
const la = form.elements.lang_a, lb = form.elements.lang_b;

function lev(a, b) { const d = Array.from({length: a.length + 1}, (_, i) => [i, ...Array(b.length).fill(0)]);
  for (let j = 1; j <= b.length; j++) d[0][j] = j;
  for (let i = 1; i <= a.length; i++) for (let j = 1; j <= b.length; j++)
    d[i][j] = Math.min(d[i-1][j] + 1, d[i][j-1] + 1, d[i-1][j-1] + (a[i-1] === b[j-1] ? 0 : 1));
  return d[a.length][b.length]; }
function snap(s) { s = s.trim().replace(/\s+/g, ' '); const v = s.toLowerCase(); if (!v) return '';
  const ex = LANGS.find(l => l.toLowerCase() === v); if (ex) return ex;
  if (v.length >= 2) { const m = LANGS.filter(l => l.toLowerCase().startsWith(v)); if (m.length === 1) return m[0]; }
  if (v.length >= 4) { let best = '', bd = 99; for (const l of LANGS) { const d = lev(l.toLowerCase(), v); if (d < bd) { bd = d; best = l; } } if (bd <= 2) return best; }
  return s.charAt(0).toUpperCase() + s.slice(1); }

function placeholders() { // card placeholders follow the language fields live
  document.querySelectorAll('.t-a').forEach(i => i.placeholder = la.value.trim() || PH[0]);
  document.querySelectorAll('.t-b').forEach(i => i.placeholder = lb.value.trim() || PH[1]); }
[la, lb].forEach(el => { el.addEventListener('input', placeholders); el.addEventListener('change', () => { el.value = snap(el.value); placeholders(); }); });

function addRow(t = '', d = '') {
  const r = document.createElement('div'); r.className = 'row';
  r.innerHTML = '<input class="t-a" name="term[]"><input class="t-b" name="def[]"><button type="button" class="icon" title="<?= e('Remove card') ?>">✕</button>';
  r.children[0].value = t; r.children[1].value = d; rows.appendChild(r); placeholders(); return r; }
document.getElementById('addrow').onclick = () => { addRow().children[0].focus(); dirty = true; };
rows.addEventListener('click', e => { if (e.target.matches('.icon')) { e.target.parentElement.remove(); dirty = true; } });

const dlg = document.getElementById('bulkdlg'), bt = document.getElementById('bulktext');
document.getElementById('bulkbtn').onclick = () => dlg.showModal();
document.getElementById('bulkcancel').onclick = () => dlg.close();
document.getElementById('bulkadd').onclick = () => {
  const pairs = bt.value.split(/\r?\n/).map(l => l.split(/\t|;| - /)).filter(p => p.length >= 2 && p[0].trim());
  if (pairs.length) {
    [...rows.children].forEach(r => { if (!r.children[0].value.trim() && !r.children[1].value.trim()) r.remove(); });
    pairs.forEach(p => addRow(p[0].trim(), p.slice(1).join(' ').trim())); dirty = true; }
  bt.value = ''; dlg.close(); };

form.addEventListener('input', () => dirty = true);
form.addEventListener('submit', () => dirty = false);
window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } }); // browser shows "Leave site? Changes may not be saved"
placeholders();
</script>
<?php page_foot();
