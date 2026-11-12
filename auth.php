<?php require 'config.php'; require 'header.php';
$mode = $_REQUEST['mode'] ?? 'login'; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'logout') { session_destroy(); go('index.php'); }
    $nick = trim($_POST['nickname'] ?? ''); $pw = $_POST['password'] ?? '';
    if ($mode === 'signup') {
        if (mb_strlen($nick) < 3 || mb_strlen($nick) > 30) $err = 'Nickname must be 3-30 characters';
        elseif (strlen($pw) < 6) $err = 'Password must be at least 6 characters';
        else try {
            db()->prepare('INSERT INTO words_users (nickname,password_hash) VALUES (?,?)')->execute([$nick, password_hash($pw, PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['uid'] = (int)db()->lastInsertId(); $_SESSION['nick'] = $nick; go('index.php');
        } catch (PDOException $e) { if ($e->getCode() == 23000) $err = 'Nickname already taken'; else throw $e; }
    } else {
        $s = db()->prepare('SELECT * FROM words_users WHERE nickname=?'); $s->execute([$nick]); $u = $s->fetch();
        if ($u && password_verify($pw, $u['password_hash'])) {
            session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; $_SESSION['nick'] = $u['nickname']; go('index.php');
        } else $err = 'Wrong nickname or password';
    }
}
$signup = $mode === 'signup'; page_head($signup ? 'Sign up' : 'Log in'); ?>
<form method="post" class="card narrow"><?= csrf_field() ?><input type="hidden" name="mode" value="<?= $signup ? 'signup' : 'login' ?>">
  <h2><?= $signup ? 'Create account' : 'Log in' ?></h2>
  <input name="nickname" placeholder="Nickname" value="<?= h($_POST['nickname'] ?? '') ?>" required>
  <input name="password" type="password" placeholder="Password" required>
  <?php if ($err): ?><p class="bad"><?= h($err) ?></p><?php endif; ?>
  <button class="btn"><?= $signup ? 'Sign up' : 'Log in' ?></button>
  <p class="muted">No email or phone number needed.</p>
</form>
<?php page_foot();
