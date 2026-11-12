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
