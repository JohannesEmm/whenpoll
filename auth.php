<?php
require_once __DIR__ . '/db.php';

if (currentUser()) redirect('index.php');

if ($next = trim($_GET['next'] ?? '')) {
    $_SESSION['auth_next'] = $next;
}

$error = '';
$sent  = false;
$tab   = 'password'; // default tab
$db    = getDB();

// ── Magic link verification ───────────────────────────────────────────────────
if (isset($_GET['magic'])) {
    $token = trim($_GET['magic']);
    $esc   = $db->escapeString($token);
    $row   = $db->querySingle("SELECT * FROM magic_tokens WHERE token='$esc' AND expires_at > " . time(), true);

    if ($row) {
        $db->exec("DELETE FROM magic_tokens WHERE token='$esc'");
        $email = $row['email'];
        $eesc  = $db->escapeString($email);
        $user  = $db->querySingle("SELECT id FROM users WHERE email='$eesc'", true);
        if (!$user) {
            $name = explode('@', $email)[0];
            $stmt = $db->prepare("INSERT INTO users (name,email,password) VALUES (?,?,'')");
            $stmt->bindValue(1, $name); $stmt->bindValue(2, $email);
            $stmt->execute();
            $_SESSION['user_id'] = $db->lastInsertRowID();
        } else {
            $_SESSION['user_id'] = $user['id'];
        }
        setRememberCookie((int)$_SESSION['user_id']);
        $dest = $_SESSION['auth_next'] ?? 'index.php';
        unset($_SESSION['auth_next']);
        redirect($dest);
    }
    $error = 'This link has expired or already been used. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode  = $_POST['mode'] ?? 'magic';
    $email = trim($_POST['email'] ?? '');
    $tab   = $mode;

    // ── Password login / register ─────────────────────────────────────────────
    if ($mode === 'password') {
        $pass = $_POST['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $eesc = $db->escapeString($email);
            $user = $db->querySingle("SELECT * FROM users WHERE email='$eesc'", true);
            if (!$user) {
                // New user — create account with password
                $hash        = password_hash($pass, PASSWORD_DEFAULT);
                $displayName = trim($_POST['display_name'] ?? '');
                $name        = $displayName ?: explode('@', $email)[0];
                $ustmt = $db->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)");
                $ustmt->bindValue(1, $name); $ustmt->bindValue(2, $email); $ustmt->bindValue(3, $hash);
                $ustmt->execute();
                $_SESSION['user_id'] = $db->lastInsertRowID();
                setRememberCookie((int)$_SESSION['user_id']);
                $dest = $_SESSION['auth_next'] ?? 'index.php';
                unset($_SESSION['auth_next']);
                redirect($dest);
            } elseif (empty($user['password'])) {
                $error = 'This account was created via magic link and has no password. Use the magic link tab, or request a link to set a password.';
            } elseif (!password_verify($pass, $user['password'])) {
                $error = 'Incorrect password.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                setRememberCookie((int)$_SESSION['user_id']);
                $dest = $_SESSION['auth_next'] ?? 'index.php';
                unset($_SESSION['auth_next']);
                redirect($dest);
            }
        }

    // ── Send magic link ───────────────────────────────────────────────────────
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $token  = bin2hex(random_bytes(32));
            $expiry = time() + 900;
            $eesc   = $db->escapeString($email);
            $tesc   = $db->escapeString($token);
            $db->exec("DELETE FROM magic_tokens WHERE email='$eesc'");
            $db->exec("INSERT INTO magic_tokens (email,token,expires_at) VALUES ('$eesc','$tesc',$expiry)");

            $link    = APP_URL . '/auth.php?magic=' . $token;
            $subject = 'Your WhenPoll sign-in link';
            $body    = "Hi,\n\nClick this link to sign in to WhenPoll:\n\n$link\n\n"
                     . "Valid for 15 minutes. If you didn't request this, ignore this email.\n\n— WhenPoll";
            $headers = implode("\r\n", [
                'From: WhenPoll <info@whenpoll.com>',
                'Content-Type: text/plain; charset=UTF-8',
                'X-Mailer: WhenPoll',
            ]);
            mail($email, $subject, $body, $headers);
            $sent = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_NAME ?> · Sign in</title>
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="stylesheet" href="css/app.css">
</head>
<body class="auth-page">
<nav>
  <a class="brand" href="/">
    <svg class="brand-icon" viewBox="0 0 32 32" fill="none" aria-hidden="true">
      <rect width="32" height="32" rx="8" fill="#15803d"/>
      <rect x="3" y="3" width="26" height="5" rx="2" fill="#4ade80"/>
      <rect x="3"  y="11" width="7" height="6" rx="1.5" fill="rgba(255,255,255,.2)"/>
      <rect x="13" y="11" width="7" height="6" rx="1.5" fill="white"/>
      <rect x="23" y="11" width="6" height="6" rx="1.5" fill="rgba(255,255,255,.2)"/>
      <rect x="3"  y="20" width="7" height="6" rx="1.5" fill="white"/>
      <rect x="13" y="20" width="7" height="6" rx="1.5" fill="rgba(255,255,255,.2)"/>
      <rect x="23" y="20" width="6" height="6" rx="1.5" fill="white"/>
    </svg><span class="brand-name">When<span>Poll</span></span>
  </a>
  <span class="nav-eco" title="Hosted in EU · 100% renewable energy">🌿 EU · 100% renewable energy</span>
</nav>

<div class="auth-wrap">
  <div class="auth-card card">

    <?php if ($sent): ?>
      <h1>Check your inbox</h1>
      <p style="margin-top:.5rem;line-height:1.6">
        We sent a sign-in link to <strong><?= h($email) ?></strong>.<br>
        Valid for 15 minutes.
      </p>
      <p class="hint" style="margin-top:.75rem">
        No email? Check spam or <a href="auth.php">try again</a>.
      </p>
    <?php else: ?>
      <h1>Sign in</h1>

      <!-- Tab switcher -->
      <div class="auth-tabs">
        <button type="button" class="auth-tab <?= $tab === 'magic' ? 'active' : '' ?>" onclick="switchTab('magic')">Magic link</button>
        <button type="button" class="auth-tab <?= $tab === 'password' ? 'active' : '' ?>" onclick="switchTab('password')">Password</button>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-error" style="margin-top:.75rem"><?= h($error) ?></div>
      <?php endif; ?>

      <!-- Magic link form -->
      <div id="tab-magic" class="auth-tab-panel" <?= $tab === 'password' ? 'style="display:none"' : '' ?>>
        <p class="auth-sub">Enter your email — we'll send a one-click sign-in link.</p>
        <form method="POST" class="auth-form">
          <input type="hidden" name="mode" value="magic">
          <div class="field">
            <label>Email</label>
            <input type="email" name="email" required placeholder="you@example.com" autofocus>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Send sign-in link →</button>
        </form>
        <p class="hint" style="margin-top:.75rem;text-align:center">No account yet? One will be created automatically.</p>
      </div>

      <!-- Password form -->
      <div id="tab-password" class="auth-tab-panel" <?= $tab !== 'password' ? 'style="display:none"' : '' ?>>
        <p class="auth-sub">Sign in with your email and password. New users are registered automatically.</p>
        <form method="POST" class="auth-form">
          <input type="hidden" name="mode" value="password">
          <div class="field">
            <label>Email</label>
            <input type="email" name="email" required placeholder="you@example.com" value="<?= h($_POST['email'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Password</label>
            <input type="password" name="password" required placeholder="min. 6 characters">
          </div>
          <div class="field">
            <label>Your name <span class="muted">(only required for new accounts)</span></label>
            <input type="text" name="display_name" placeholder="e.g. Jane Smith" value="<?= h($_POST['display_name'] ?? '') ?>">
          </div>
          <button type="submit" class="btn btn-primary btn-block">Sign in / Register →</button>
        </form>
      </div>

    <?php endif; ?>
  </div>
</div>
<script>
function switchTab(t) {
  document.getElementById('tab-magic').style.display    = t === 'magic'    ? '' : 'none';
  document.getElementById('tab-password').style.display = t === 'password' ? '' : 'none';
  document.querySelectorAll('.auth-tab').forEach(b => b.classList.toggle('active', b.textContent.toLowerCase().startsWith(t)));
}
</script>
</body>
</html>
