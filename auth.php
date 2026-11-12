<?php require 'config.php'; require 'header.php';
$mode = $_REQUEST['mode'] ?? 'login'; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'logout') { $ui = ui(); session_destroy(); session_start(); $_SESSION['ui'] = $ui; go('index.php'); }
    $nick = trim($_POST['nickname'] ?? ''); $pw = $_POST['password'] ?? '';
    if ($mode === 'signup') {
        if (mb_strlen($nick) < 3 || mb_strlen($nick) > 30) $err = t('Nickname must be 3-30 characters');
        elseif (strlen($pw) < 6) $err = t('Password must be at least 6 characters');
        else try {
            db()->prepare('INSERT INTO words_users (nickname,password_hash) VALUES (?,?)')->execute([$nick, password_hash($pw, PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['uid'] = (int)db()->lastInsertId(); $_SESSION['nick'] = $nick; go('index.php');
        } catch (PDOException $e) { if ($e->getCode() == 23000) $err = t('Nickname already taken'); else throw $e; }
    } else {
        $s = db()->prepare('SELECT * FROM words_users WHERE nickname=?'); $s->execute([$nick]); $u = $s->fetch();
        if ($u && password_verify($pw, $u['password_hash'])) {
            session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; $_SESSION['nick'] = $u['nickname']; go('index.php');
        } else $err = t('Wrong nickname or password');
    }
}
$signup = $mode === 'signup'; page_head($signup ? t('Sign up') : t('Log in')); ?>
<div class="auth"><form method="post" class="card auth-card"><?= csrf_field() ?><input type="hidden" name="mode" value="<?= $signup ? 'signup' : 'login' ?>">
  <h2><?= $signup ? e('Create your account') : e('Welcome back') ?></h2>
  <p class="muted"><?= $signup ? e('Just a nickname and a password, nothing else.') : e('Log in to create and edit your own packs.') ?></p>
  <label><?= e('Nickname') ?><input name="nickname" value="<?= h($_POST['nickname'] ?? '') ?>" autocomplete="username" required autofocus></label>
  <label><?= e('Password') ?><input name="password" type="password" autocomplete="<?= $signup ? 'new-password' : 'current-password' ?>" required></label>
  <?php if ($err): ?><p class="alert"><?= h($err) ?></p><?php endif; ?>
  <button class="btn wide"><?= $signup ? e('Sign up') : e('Log in') ?></button>
  <p class="switch muted"><?= $signup ? e('Already have an account?') . ' <a href="auth.php?mode=login">' . e('Log in') . '</a>' : e('New here?') . ' <a href="auth.php?mode=signup">' . e('Create an account') . '</a>' ?></p>
</form></div>
<?php page_foot();
