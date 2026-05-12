<?php
require_once __DIR__ . '/db.php';
$user = requireLogin();
$db   = getDB();

if ($_GET['action'] ?? '' === 'logout') {
    clearRememberCookie();
    session_destroy();
    redirect('auth.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_poll') {
    $uid   = $user['id'];
    $delId = $db->escapeString(trim($_POST['poll_id'] ?? ''));
    $p     = $db->querySingle("SELECT id FROM polls WHERE public_id='$delId' AND user_id=$uid", true);
    if ($p) {
        $pid_int = (int)$p['id'];
        $db->exec("DELETE FROM votes WHERE poll_id=$pid_int");
        $db->exec("DELETE FROM slots WHERE poll_id=$pid_int");
        $db->exec("DELETE FROM poll_comments WHERE poll_id=$pid_int");
        $db->exec("DELETE FROM polls WHERE id=$pid_int");
        flash('success', 'Poll deleted.');
    }
    redirect('index.php');
}

$uid   = $user['id'];
$polls = [];
$res   = $db->query("
    SELECT p.*, COUNT(DISTINCT v.participant) as respondents
    FROM polls p
    LEFT JOIN votes v ON v.poll_id = p.id
    WHERE p.user_id = $uid
    GROUP BY p.id
    ORDER BY p.created DESC
");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) $polls[] = $row;

$success = flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_NAME ?> · Dashboard</title>
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
  <div class="dashboard-header">
    <div>
      <h1>Your polls</h1>
      <p>Manage scheduling polls and connect your calendar.</p>
    </div>
    <div class="header-actions">
      <a href="calendar.php" class="btn btn-ghost">🗓 Calendars</a>
      <a href="create.php"   class="btn btn-primary">+ New poll</a>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
  <?php endif; ?>

  <?php if (!$polls): ?>
    <div class="empty-state card">
      <div class="empty-icon">📅</div>
      <h2>No polls yet</h2>
      <p>Create your first scheduling poll and share it with participants.</p>
      <a href="create.php" class="btn btn-primary" style="margin-top:1rem">Create a poll</a>
    </div>
  <?php else: ?>
    <div class="poll-list">
      <?php foreach ($polls as $p): ?>
        <div class="poll-card card">
          <div class="poll-card-body">
            <div class="poll-meta">
              <?php if ($p['finalized_slot_id']): ?>
                <span class="badge badge-finalized">Finalized</span>
              <?php elseif ($p['deadline'] && strtotime($p['deadline']) < time()): ?>
                <span class="badge badge-expired">Expired</span>
              <?php else: ?>
                <span class="badge badge-open">Open</span>
              <?php endif; ?>
            </div>
            <h3 class="poll-title"><?= h($p['title']) ?></h3>
            <p class="poll-stats">
              <?= (int)$p['respondents'] ?> respondent<?= $p['respondents'] != 1 ? 's' : '' ?>
              · Created <?= date('d M Y', $p['created']) ?>
              <?php if ($p['deadline']): ?>· Deadline <?= date('d M', strtotime($p['deadline'])) ?><?php endif; ?>
            </p>
          </div>
          <div class="poll-card-actions">
            <a href="<?= APP_URL ?>/<?= h($p['public_id']) ?>" class="btn btn-ghost btn-sm" target="_blank">Vote link ↗</a>
            <a href="results.php?id=<?= h($p['public_id']) ?>&token=<?= h($p['admin_token']) ?>" class="btn btn-secondary btn-sm">Results</a>
            <form method="POST" style="margin:0" onsubmit="return confirm('Delete this poll and all its data? This cannot be undone.')">
              <input type="hidden" name="action"  value="delete_poll">
              <input type="hidden" name="poll_id" value="<?= h($p['public_id']) ?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--no)">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
