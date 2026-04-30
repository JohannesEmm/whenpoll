<?php
require_once __DIR__ . '/db.php';
$user = requireLogin();
$db   = getDB();
$uid  = $user['id'];

$provider = $_GET['provider'] ?? '';
$action   = $_GET['action']   ?? '';

// ── OAuth: Google ─────────────────────────────────────────────────────────────
if ($provider === 'google') {
    if ($action === 'connect') {
        $state = genId(8);
        $_SESSION['oauth_state'] = $state;
        $params = http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/calendar.readonly',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ]);
        redirect("https://accounts.google.com/o/oauth2/v2/auth?$params");
    }
    if ($action === 'callback') {
        if (($_GET['state'] ?? '') !== ($_SESSION['oauth_state'] ?? '')) die('Invalid state.');
        $tok = httpPost('https://oauth2.googleapis.com/token', [
            'code'          => $_GET['code'],
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]);
        if (empty($tok['access_token'])) { flash('error', 'Google auth failed.'); redirect('calendar.php'); }
        $expiry = time() + ($tok['expires_in'] ?? 3600);
        $stmt   = $db->prepare("INSERT INTO calendars (user_id,provider,label,access_token,refresh_token,token_expiry) VALUES (?,?,?,?,?,?)");
        $stmt->bindValue(1, $uid); $stmt->bindValue(2, 'google');
        $stmt->bindValue(3, 'Google Calendar');
        $stmt->bindValue(4, $tok['access_token']); $stmt->bindValue(5, $tok['refresh_token'] ?? null);
        $stmt->bindValue(6, $expiry);
        $stmt->execute();
        flash('success', 'Google Calendar connected.');
        redirect('calendar.php');
    }
}

// ── OAuth: Microsoft ──────────────────────────────────────────────────────────
if ($provider === 'microsoft') {
    $tenant = MS_TENANT;
    if ($action === 'connect') {
        $state = genId(8);
        $_SESSION['oauth_state'] = $state;
        $params = http_build_query([
            'client_id'     => MS_CLIENT_ID,
            'redirect_uri'  => MS_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'offline_access Calendars.Read',
            'state'         => $state,
        ]);
        redirect("https://login.microsoftonline.com/$tenant/oauth2/v2.0/authorize?$params");
    }
    if ($action === 'callback') {
        if (($_GET['state'] ?? '') !== ($_SESSION['oauth_state'] ?? '')) die('Invalid state.');
        $tok = httpPost("https://login.microsoftonline.com/$tenant/oauth2/v2.0/token", [
            'code'          => $_GET['code'],
            'client_id'     => MS_CLIENT_ID,
            'client_secret' => MS_CLIENT_SECRET,
            'redirect_uri'  => MS_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
            'scope'         => 'offline_access Calendars.Read',
        ]);
        if (empty($tok['access_token'])) { flash('error', 'Microsoft auth failed.'); redirect('calendar.php'); }
        $expiry = time() + ($tok['expires_in'] ?? 3600);
        $stmt   = $db->prepare("INSERT INTO calendars (user_id,provider,label,access_token,refresh_token,token_expiry) VALUES (?,?,?,?,?,?)");
        $stmt->bindValue(1, $uid); $stmt->bindValue(2, 'microsoft');
        $stmt->bindValue(3, 'Outlook / Microsoft 365');
        $stmt->bindValue(4, $tok['access_token']); $stmt->bindValue(5, $tok['refresh_token'] ?? null);
        $stmt->bindValue(6, $expiry);
        $stmt->execute();
        flash('success', 'Microsoft calendar connected.');
        redirect('calendar.php');
    }
}

// ── CalDAV: manual entry ──────────────────────────────────────────────────────
if ($provider === 'caldav' && $action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $url  = trim($_POST['caldav_url']  ?? '');
    $cu   = trim($_POST['caldav_user'] ?? '');
    $cp   = trim($_POST['caldav_pass'] ?? '');
    $lbl  = trim($_POST['label']       ?? 'CalDAV Calendar');
    if ($url) {
        $stmt = $db->prepare("INSERT INTO calendars (user_id,provider,label,caldav_url,caldav_user,caldav_pass) VALUES (?,?,?,?,?,?)");
        $stmt->bindValue(1, $uid); $stmt->bindValue(2, 'caldav');
        $stmt->bindValue(3, $lbl);  $stmt->bindValue(4, $url);
        $stmt->bindValue(5, $cu);   $stmt->bindValue(6, $cp);
        $stmt->execute();
        flash('success', 'CalDAV calendar saved.');
    }
    redirect('calendar.php');
}

// ── Disconnect ────────────────────────────────────────────────────────────────
if ($action === 'disconnect') {
    $cid = (int)($_GET['cal_id'] ?? 0);
    $db->exec("DELETE FROM calendars WHERE id=$cid AND user_id=$uid");
    redirect('calendar.php');
}

// Load connected calendars
$cals = [];
$res  = $db->query("SELECT * FROM calendars WHERE user_id=$uid");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) $cals[] = $r;

$success = flash('success');
$error   = flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_NAME ?> · Calendars</title>
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
    <a href="calendar.php" class="nav-link">Calendars</a>
    <a href="profile.php" class="nav-link">Profile</a>
    <a href="index.php?action=logout" class="nav-link">Sign out</a>
  <?php endif; ?>
</nav>

<div class="page-wrap">
  <h1>Calendar connections</h1>
  <p style="margin-bottom:1.5rem">Connect your calendars so WhenPoll can suggest slots where you're free.</p>

  <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

  <!-- Connected calendars -->
  <?php if ($cals): ?>
  <div class="card" style="margin-bottom:1.25rem">
    <h2>Connected</h2>
    <?php foreach ($cals as $c): ?>
      <div class="cal-row">
        <div class="cal-icon cal-<?= h($c['provider']) ?>">
          <?= ['google'=>'G', 'microsoft'=>'M', 'caldav'=>'📅'][$c['provider']] ?? '?' ?>
        </div>
        <div class="cal-info">
          <strong><?= h($c['label'] ?: $c['provider']) ?></strong>
          <span class="hint"><?= ucfirst(h($c['provider'])) ?></span>
        </div>
        <a href="calendar.php?action=disconnect&cal_id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm"
           onclick="return confirm('Disconnect this calendar?')">Disconnect</a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Add Google -->
  <?php if (GOOGLE_CLIENT_ID): ?>
  <div class="card provider-card" style="margin-bottom:1rem">
    <div class="provider-header">
      <div class="provider-logo google-logo">G</div>
      <div>
        <h3>Google Calendar</h3>
        <p>Connect via OAuth — read-only access to check free/busy.</p>
      </div>
    </div>
    <a href="calendar.php?provider=google&action=connect" class="btn btn-secondary btn-sm">Connect Google Calendar</a>
  </div>
  <?php endif; ?>

  <!-- Add Microsoft -->
  <?php if (MS_CLIENT_ID): ?>
  <div class="card provider-card" style="margin-bottom:1rem">
    <div class="provider-header">
      <div class="provider-logo ms-logo">M</div>
      <div>
        <h3>Outlook / Microsoft 365</h3>
        <p>Connect via OAuth — read-only access to check free/busy.</p>
      </div>
    </div>
    <a href="calendar.php?provider=microsoft&action=connect" class="btn btn-secondary btn-sm">Connect Microsoft Calendar</a>
  </div>
  <?php endif; ?>

  <!-- Add CalDAV -->
  <div class="card provider-card" style="margin-bottom:1rem">
    <div class="provider-header">
      <div class="provider-logo caldav-logo">📅</div>
      <div>
        <h3>CalDAV <span class="hint">(Nextcloud, Apple iCloud, Fastmail, etc.)</span></h3>
        <p>Enter your CalDAV server URL and credentials.</p>
      </div>
    </div>
    <details>
      <summary class="btn btn-ghost btn-sm" style="cursor:pointer;display:inline-flex;align-items:center">+ Add CalDAV</summary>
      <form method="POST" action="calendar.php?provider=caldav&action=save" style="margin-top:1rem">
        <div class="field">
          <label>Label</label>
          <input type="text" name="label" placeholder="e.g. Nextcloud" value="CalDAV Calendar">
        </div>
        <div class="field">
          <label>CalDAV URL</label>
          <input type="url" name="caldav_url" required placeholder="https://cloud.example.com/remote.php/dav/calendars/user/personal/">
        </div>
        <div class="row-2">
          <div class="field">
            <label>Username</label>
            <input type="text" name="caldav_user" placeholder="your-username">
          </div>
          <div class="field">
            <label>Password / App token</label>
            <input type="password" name="caldav_pass" placeholder="••••••••">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save CalDAV calendar</button>
      </form>
    </details>
  </div>

  <a href="index.php" class="btn btn-ghost btn-sm">← Back to dashboard</a>
</div>
</body>
</html>
