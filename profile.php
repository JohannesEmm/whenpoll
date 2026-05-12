<?php
require_once __DIR__ . '/db.php';
$user = requireLogin();
$db   = getDB();
$uid  = $user['id'];
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'name') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $esc = $db->escapeString($name);
            $db->exec("UPDATE users SET name='$esc' WHERE id=$uid");
            $user['name'] = $name;
            $success = 'Name updated.';
        } else { $error = 'Name cannot be empty.'; }
    }
    if ($act === 'timezone') {
        $tz = trim($_POST['timezone'] ?? '');
        if ($tz && in_array($tz, timezone_identifiers_list())) {
            try {
                $esc = $db->escapeString($tz);
                $db->exec("UPDATE users SET timezone='$esc' WHERE id=$uid");
                $success = 'Timezone saved.';
            } catch (Exception $e) { $error = 'Run migrations first (timezone column missing).'; }
        } else { $error = 'Invalid timezone.'; }
    }
}

// Try to get timezone from DB
$userTz = 'UTC';
try {
    $row = $db->querySingle("SELECT timezone FROM users WHERE id=$uid", true);
    $userTz = $row['timezone'] ?? 'UTC';
} catch (Exception $e) { /* column may not exist */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_NAME ?> · Profile</title>
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="stylesheet" href="css/app.css">
</head>
<body>
<?php $_navUser = currentUser(); ?>
<nav>
  <a class="brand" href="index.php">
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
  <span class="nav-spacer"></span>
  <?php if ($_navUser): ?>
    <span class="nav-user"><?= h($_navUser['name']) ?></span>
    <a href="index.php" class="nav-link">Polls</a>
    <a href="calendar.php" class="nav-link">Calendars</a>
    <a href="profile.php" class="nav-link">Profile</a>
    <a href="index.php?action=logout" class="nav-link">Sign out</a>
  <?php endif; ?>
</nav>
<div class="page-wrap">
  <h1>Profile</h1>

  <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:1rem">
    <h2>Display name</h2>
    <form method="POST" class="auth-form">
      <input type="hidden" name="action" value="name">
      <div class="field">
        <label>Name</label>
        <input type="text" name="name" required value="<?= h($user['name']) ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Save name</button>
    </form>
  </div>

  <div class="card" style="margin-bottom:1rem">
    <h2>Timezone</h2>
    <form method="POST" class="auth-form" id="tzForm">
      <input type="hidden" name="action" value="timezone">
      <div class="field">
        <label>Your timezone</label>
        <select name="timezone" id="tzInput">
          <?php
          $regions = [];
          foreach (timezone_identifiers_list() as $tz) {
              $parts = explode('/', $tz, 2);
              $regions[$parts[0]][] = $tz;
          }
          ksort($regions);
          foreach ($regions as $region => $zones): ?>
            <optgroup label="<?= h($region) ?>">
              <?php foreach ($zones as $z): ?>
                <option value="<?= h($z) ?>" <?= $z === $userTz ? 'selected' : '' ?>>
                  <?= h(str_replace('_', ' ', $z)) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary btn-sm">Save timezone</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="detectTz()">Auto-detect</button>
      </div>
    </form>
  </div>

  <div class="card" style="margin-bottom:1rem">
    <h2>Account</h2>
    <p style="margin-bottom:.75rem">Email: <strong><?= h($user['email']) ?></strong></p>
    <a href="calendar.php" class="btn btn-ghost btn-sm">Manage calendars →</a>
  </div>

  <a href="index.php" class="btn btn-ghost btn-sm">← Back to polls</a>
</div>
<script>
function detectTz() {
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
  if (!tz) return;
  const sel = document.getElementById('tzInput');
  const opt = [...sel.options].find(o => o.value === tz);
  if (opt) { sel.value = tz; }
}
window.addEventListener('load', () => {
  const sel = document.getElementById('tzInput');
  if (!sel.value || sel.value === 'UTC') detectTz();
});
</script>
</body>
</html>
