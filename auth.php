<?php
require_once __DIR__ . '/db.php';

if (currentUser()) redirect('index.php');

// Store redirect destination before any form processing
if ($next = trim($_GET['next'] ?? '')) {
    $_SESSION['auth_next'] = $next;
}

$error = '';
$sent  = false;
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
        $dest = $_SESSION['auth_next'] ?? 'index.php';
        unset($_SESSION['auth_next']);
        redirect($dest);
    }
    $error = 'This link has expired or already been used. Please request a new one.';
}

// ── Send magic link ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $token  = bin2hex(random_bytes(32));
        $expiry = time() + 900; // 15 minutes
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_NAME ?> · Sign in</title>
<link rel="stylesheet" href="css/app.css">
</head>
<body class="auth-page">
<nav>
  <a class="brand" href="/">
    <svg class="brand-icon" viewBox="0 0 32 32" fill="none">
      <rect width="32" height="32" rx="8" fill="#15803d"/>
      <rect x="3" y="3" width="26" height="5" rx="2" fill="#4ade80"/>
      <rect x="3"  y="11" width="7" height="6" rx="1.5" fill="rgba(255,255,255,.2)"/>
      <rect x="13" y="11" width="7" height="6" rx="1.5" fill="white"/>
      <rect x="23" y="11" width="6" height="6" rx="1.5" fill="rgba(255,255,255,.2)"/>
      <rect x="3"  y="20" width="7" height="6" rx="1.5" fill="white"/>
      <rect x="13" y="20" width="7" height="6" rx="1.5" fill="rgba(255,255,255,.2)"/>
      <rect x="23" y="20" width="6" height="6" rx="1.5" fill="white"/>
    </svg>
    <span class="brand-name">When<span>Poll</span></span>
  </a>
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
      <p class="auth-sub">Enter your email — we'll send a sign-in link. No password needed.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="auth-form" style="margin-top:.75rem">
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" required placeholder="you@example.com" autofocus>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send sign-in link →</button>
      </form>

      <p class="hint" style="margin-top:1rem;text-align:center">
        New here? Just enter your email — your account is created automatically.
      </p>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
