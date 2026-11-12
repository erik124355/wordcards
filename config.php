<?php
$envFile = __DIR__ . DIRECTORY_SEPARATOR . '.env';
$envContents = @file_get_contents($envFile);
if ($envContents === false) {
    throw new RuntimeException('Unable to read the application .env file.');
}

$env = [];
foreach (preg_split('/\r\n|\r|\n/', $envContents) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }

    if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*?)\s*$/', $line, $matches)) {
        throw new RuntimeException('Invalid entry in the application .env file.');
    }

    $key = $matches[1];
    $value = $matches[2];
    if (isset($env[$key])) {
        throw new RuntimeException('Duplicate entry in the application .env file.');
    }
    if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
        $value = substr($value, 1, -1);
    }
    $env[$key] = $value;
}

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
    if (!array_key_exists($key, $env)) {
        throw new RuntimeException('Missing ' . $key . ' in the application .env file.');
    }
    define($key, $env[$key]);
}

session_start();
function db() {
    static $p;
    return $p ??= new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function user() { return $_SESSION['uid'] ?? null; }
function go($url) { header('Location: ' . $url); exit; }
function require_login() { if (!user()) go('auth.php?mode=login'); }
function csrf() { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf(), $_POST['csrf'] ?? '')) { http_response_code(400); exit('Bad request'); }

// ---------- answer checking ----------
function chars($s) { return preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: []; }
function norm($s) { return preg_replace('/\s+/u', ' ', mb_strtolower(trim($s))); }
function strip_acc($s) {
    if (class_exists('Normalizer')) return preg_replace('/\p{Mn}/u', '', Normalizer::normalize($s, Normalizer::FORM_D));
    return strtr($s, ['ä'=>'a','ö'=>'o','å'=>'a','é'=>'e','è'=>'e','ñ'=>'n','ü'=>'u','ç'=>'c','á'=>'a','í'=>'i','ó'=>'o','ú'=>'u']);
}
function lev($a, $b) { // multibyte-safe Levenshtein
    $a = chars($a); $b = chars($b); $p = range(0, count($b));
    foreach ($a as $i => $x) {
        $c = [$i + 1];
        foreach ($b as $j => $y) $c[] = min($p[$j + 1] + 1, $c[$j] + 1, $p[$j] + ($x === $y ? 0 : 1));
        $p = $c;
    }
    return end($p);
}
// Several accepted answers are separated by "/". Returns [correct|almost|wrong, closest accepted answer]
function grade($in, $ans) {
    $n = norm($in);
    $alts = array_values(array_filter(array_map('norm', explode('/', $ans))));
    if (in_array($n, $alts, true)) return ['correct', $ans];
    usort($alts, fn($x, $y) => lev($x, $n) <=> lev($y, $n));
    $best = $alts[0] ?? $ans; $len = mb_strlen($best);
    $close = $n !== '' && (strip_acc($best) === strip_acc($n) || ($len >= 3 && lev($best, $n) <= ($len >= 8 ? 2 : 1)));
    return [$close ? 'almost' : 'wrong', $best];
}
function diff_marks($u, $t) { // marks typed characters that match the answer (longest common subsequence)
    $u = chars($u); $t = chars($t); $m = count($u); $n = count($t);
    $e = fn($x, $y) => mb_strtolower($u[$x]) === mb_strtolower($t[$y]);
    $d = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
    for ($x = $m - 1; $x >= 0; $x--) for ($y = $n - 1; $y >= 0; $y--)
        $d[$x][$y] = $e($x, $y) ? $d[$x + 1][$y + 1] + 1 : max($d[$x + 1][$y], $d[$x][$y + 1]);
    $o = []; $x = $y = 0;
    while ($x < $m) {
        if ($y < $n && $e($x, $y)) { $o[] = [$u[$x++], 1]; $y++; }
        elseif ($y < $n && $d[$x][$y + 1] >= $d[$x + 1][$y]) $y++;
        else $o[] = [$u[$x++], 0];
    }
    return $o;
}

// ---------- languages (shown in the dropdown; typed input snaps to the closest one) ----------
function language_list() {
    return ['English','Finnish','Swedish','Spanish','French','German','Italian','Portuguese','Dutch','Norwegian','Danish','Icelandic',
        'Estonian','Latvian','Lithuanian','Polish','Czech','Slovak','Hungarian','Romanian','Bulgarian','Greek','Turkish','Russian','Ukrainian',
        'Serbian','Croatian','Slovenian','Albanian','Arabic','Hebrew','Persian','Hindi','Bengali','Urdu','Tamil','Telugu','Nepali','Thai',
        'Vietnamese','Indonesian','Malay','Filipino','Chinese','Japanese','Korean','Mongolian','Swahili','Somali','Afrikaans','Kurdish',
        'Armenian','Georgian','Latin','Irish','Welsh','Catalan','Basque','Sami','Esperanto'];
}
function lang_fi() { return ['English'=>'Englanti','Finnish'=>'Suomi','Swedish'=>'Ruotsi','Spanish'=>'Espanja','French'=>'Ranska','German'=>'Saksa','Italian'=>'Italia','Portuguese'=>'Portugali',
    'Dutch'=>'Hollanti','Norwegian'=>'Norja','Danish'=>'Tanska','Icelandic'=>'Islanti','Estonian'=>'Viro','Latvian'=>'Latvia','Lithuanian'=>'Liettua','Polish'=>'Puola','Czech'=>'Tšekki',
    'Slovak'=>'Slovakki','Hungarian'=>'Unkari','Romanian'=>'Romania','Bulgarian'=>'Bulgaria','Greek'=>'Kreikka','Turkish'=>'Turkki','Russian'=>'Venäjä','Ukrainian'=>'Ukraina',
    'Serbian'=>'Serbia','Croatian'=>'Kroatia','Slovenian'=>'Sloveeni','Albanian'=>'Albania','Arabic'=>'Arabia','Hebrew'=>'Heprea','Persian'=>'Persia','Hindi'=>'Hindi','Bengali'=>'Bengali',
    'Urdu'=>'Urdu','Tamil'=>'Tamili','Telugu'=>'Telugu','Nepali'=>'Nepali','Thai'=>'Thai','Vietnamese'=>'Vietnam','Indonesian'=>'Indonesia','Malay'=>'Malaiji','Filipino'=>'Filipino',
    'Chinese'=>'Kiina','Japanese'=>'Japani','Korean'=>'Korea','Mongolian'=>'Mongoli','Swahili'=>'Swahili','Somali'=>'Somali','Afrikaans'=>'Afrikaans','Kurdish'=>'Kurdi',
    'Armenian'=>'Armenia','Georgian'=>'Gruusia','Latin'=>'Latina','Irish'=>'Iiri','Welsh'=>'Kymri','Catalan'=>'Katalaani','Basque'=>'Baski','Sami'=>'Saame','Esperanto'=>'Esperanto']; }
// Database always stores the English name; the site shows it in the interface language.
function lang_name($c) { return ui() === 'fi' ? (lang_fi()[$c] ?? $c) : $c; }
// Accepts English or Finnish names (typos allowed) and returns the English name, or the typed text if unknown.
function snap_language($s) {
    $s = trim(preg_replace('/\s+/u', ' ', $s)); if ($s === '') return '';
    $v = mb_strtolower($s); $fi = lang_fi(); $names = [];
    foreach (language_list() as $c) $names[$c] = [mb_strtolower($c), mb_strtolower($fi[$c] ?? $c)];
    foreach ($names as $c => $n) if ($v === $n[0] || $v === $n[1]) return $c;
    if (mb_strlen($v) >= 2) {
        $m = []; foreach ($names as $c => $n) if (mb_strpos($n[0], $v) === 0 || mb_strpos($n[1], $v) === 0) $m[] = $c;
        if (count($m) === 1) return $m[0];
    }
    if (mb_strlen($v) >= 4) {
        $best = null; $bd = 99;
        foreach ($names as $c => $n) foreach ($n as $x) { $d = lev($x, $v); if ($d < $bd) { $bd = $d; $best = $c; } }
        if ($bd <= 2) return $best;
    }
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

// ---------- interface language: Finnish (default) / English ----------
function ui() { return ($_SESSION['ui'] ?? 'fi') === 'en' ? 'en' : 'fi'; }
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fi', 'en'], true)) {
    $_SESSION['ui'] = $_GET['lang']; $q = $_GET; unset($q['lang']);
    go(basename($_SERVER['SCRIPT_NAME']) . ($q ? '?' . http_build_query($q) : ''));
}
function lang_url($l) { return basename($_SERVER['SCRIPT_NAME']) . '?' . http_build_query(array_merge($_GET, ['lang' => $l])); }
// t('English text') returns the Finnish version when the UI is Finnish; extra args fill %s / %d
function t($s, ...$a) { static $fi; $fi ??= fi_dict(); $x = ui() === 'fi' ? ($fi[$s] ?? $s) : $s; return $a ? vsprintf($x, $a) : $x; }
function e($s, ...$a) { return h(t($s, ...$a)); }
function fi_dict() { return [
 'Browse'=>'Selaa','My packs'=>'Omat paketit','+ New pack'=>'+ Uusi paketti','Log out'=>'Kirjaudu ulos','Log in'=>'Kirjaudu sisään','Sign up'=>'Rekisteröidy',
 'Create your account'=>'Luo tili','Welcome back'=>'Tervetuloa takaisin','Just a nickname and a password, nothing else.'=>'Vain nimimerkki ja salasana, ei muuta.',
 'Log in to create and edit your own packs.'=>'Kirjaudu sisään luodaksesi ja muokataksesi omia pakettejasi.','Nickname'=>'Nimimerkki','Password'=>'Salasana',
 'Already have an account?'=>'Onko sinulla jo tili?','New here?'=>'Uusi täällä?','Create an account'=>'Luo tili',
 'Nickname must be 3-30 characters'=>'Nimimerkin pituus on 3–30 merkkiä','Password must be at least 6 characters'=>'Salasanan on oltava vähintään 6 merkkiä',
 'Nickname already taken'=>'Nimimerkki on jo käytössä','Wrong nickname or password'=>'Väärä nimimerkki tai salasana',
 'Browse word packs'=>'Selaa sanapaketteja','Search title or description'=>'Hae otsikosta tai kuvauksesta','Tag (e.g. spanish, high school)'=>'Tagi (esim. espanja, lukio)',
 'Search'=>'Hae','cards'=>'korttia','by'=>'tekijä','Edit'=>'Muokkaa','No packs found.'=>'Paketteja ei löytynyt.',
 'Edit pack'=>'Muokkaa pakettia','New pack'=>'Uusi paketti','Title'=>'Otsikko','e.g. Food and drinks'=>'esim. Ruoka ja juoma','Description'=>'Kuvaus','(optional)'=>'(valinnainen)',
 'What is this pack about?'=>'Mistä paketti kertoo?','Language 1'=>'Kieli 1','Language 2'=>'Kieli 2','e.g. English'=>'esim. Englanti','e.g. Spanish'=>'esim. Espanja',
 'Tags'=>'Tagit','(comma separated)'=>'(pilkulla erotettuna)','e.g. spanish, english, high school, food'=>'esim. espanja, englanti, lukio, ruoka',
 'Cards'=>'Kortit','Bulk paste'=>'Liitä useita','Several accepted answers? Separate them with “/” (e.g.'=>'Useita hyväksyttyjä vastauksia? Erota ne merkillä “/” (esim.',
 '+ Add card'=>'+ Lisää kortti','Remove card'=>'Poista kortti','Save pack'=>'Tallenna paketti','Save'=>'Tallenna','Delete'=>'Poista','Delete this pack?'=>'Poistetaanko tämä paketti?',
 'One pair per line, separated by a tab, “;” or “ - ”.'=>'Yksi pari per rivi, erotettuna sarkaimella, “;” tai “ - ”.','Cancel'=>'Peruuta','Add cards'=>'Lisää kortit',
 'Title, both languages and at least one full card are required'=>'Otsikko, molemmat kielet ja vähintään yksi täysi kortti vaaditaan',
 'Pack not found'=>'Pakettia ei löytynyt','Flashcards'=>'Muistikortit','Write'=>'Kirjoita','Multiple choice'=>'Monivalinta','Showing:'=>'Näytetään:','Shuffle:'=>'Sekoita:',
 'on'=>'päällä','off'=>'pois','Done! %d / %d correct'=>'Valmis! %d / %d oikein','Practice %d missed'=>'Harjoittele %d väärin mennyttä','Restart'=>'Aloita alusta',
 'Flip'=>'Käännä','Write your answer (%s)'=>'Kirjoita vastauksesi (%s)','Check'=>'Tarkista','✓ Correct!'=>'✓ Oikein!','≈ Almost! Small spelling mistake.'=>'≈ Melkein! Pieni kirjoitusvirhe.',
 '✗ Not quite.'=>'✗ Ei aivan.','You wrote:'=>'Kirjoitit:','Correct:'=>'Oikein:','Next'=>'Seuraava',
 'Add more cards with different answers to use multiple choice.'=>'Lisää kortteja eri vastauksilla käyttääksesi monivalintaa.',
]; }
