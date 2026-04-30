<?php
require_once __DIR__ . '/db.php';
$db    = getDB();
$user  = currentUser();

$pid   = $_GET['id']    ?? '';
$token = $_GET['token'] ?? '';

$poll  = $db->querySingle("SELECT * FROM polls WHERE public_id='" . $db->escapeString($pid) . "'", true);
if (!$poll) { http_response_code(404); die('Poll not found.'); }

// Must be owner (via session) or have admin token
$isOwner = $user && $user['id'] == $poll['user_id'];
$hasToken = hash_equals($poll['admin_token'], $token);
if (!$isOwner && !$hasToken) { http_response_code(403); die('Access denied.'); }

$pollId = $poll['id'];

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'send_admin_link' && !$isOwner && $hasToken) {
        $email = trim($_POST['admin_email'] ?? '');
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $adminUrl = APP_URL . "/results.php?id=$pid&token=$token";
            $voteUrl  = APP_URL . "/vote.php?id=$pid";
            $body = "Hi,\n\nHere is your admin link for the WhenPoll \"{$poll['title']}\" — bookmark it:\n$adminUrl\n\n"
                  . "Share this voting link with participants:\n$voteUrl\n\n— WhenPoll";
            $headers = implode("\r\n", [
                'From: WhenPoll <info@whenpoll.com>',
                'Content-Type: text/plain; charset=UTF-8',
            ]);
            mail($email, "Your WhenPoll: {$poll['title']}", $body, $headers);
            flash('success', 'Admin link sent — check your inbox!');
        }
        redirect("results.php?id=$pid&token=$token");
    }
    if ($action === 'finalize') {
        $sid = (int)($_POST['slot_id'] ?? 0);
        $db->exec("UPDATE polls SET finalized_slot_id=$sid WHERE id=$pollId");
        redirect("results.php?id=$pid&token=$token");
    }
    if ($action === 'unfinalize') {
        $db->exec("UPDATE polls SET finalized_slot_id=NULL WHERE id=$pollId");
        redirect("results.php?id=$pid&token=$token");
    }
}

// Reload finalized
$poll = $db->querySingle("SELECT * FROM polls WHERE id=$pollId", true);

// Load slots
$slots = [];
$res   = $db->query("SELECT * FROM slots WHERE poll_id=$pollId ORDER BY slot_dt");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) $slots[] = $r;

// Load votes
$voteMap   = [];
$allPeople = [];
$vres = $db->query("SELECT * FROM votes WHERE poll_id=$pollId");
while ($r = $vres->fetchArray(SQLITE3_ASSOC)) {
    $voteMap[$r['slot_id']][$r['participant']] = $r['status'];
    $allPeople[$r['participant']] = true;
}
$allPeople = array_keys($allPeople);

// Score each slot
$scored = array_map(function($s) use ($voteMap, $allPeople) {
    $vm = $voteMap[$s['id']] ?? [];
    return [
        'slot'  => $s,
        'yes'   => count(array_filter($vm, fn($v) => $v === 'yes')),
        'maybe' => count(array_filter($vm, fn($v) => $v === 'maybe')),
        'no'    => count(array_filter($vm, fn($v) => $v === 'no')),
        'total' => count($allPeople),
    ];
}, $slots);
usort($scored, fn($a,$b) => ($b['yes'] + .5*$b['maybe']) <=> ($a['yes'] + .5*$a['maybe']));

$voteUrl = APP_URL . '/' . urlencode($pid);
$success = flash('success');

// Load comments
$comments = [];
$cres = $db->query("SELECT participant, comment, created_at FROM poll_comments WHERE poll_id=$pollId ORDER BY created_at ASC");
while ($cr = $cres->fetchArray(SQLITE3_ASSOC)) $comments[] = $cr;

// Group all slots by date for display
$byDate = [];
foreach ($slots as $s) $byDate[date('Y-m-d', strtotime($s['slot_dt']))][] = $s;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($poll['title']) ?> · Results</title>
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
  <div class="dashboard-header" style="margin-bottom:1.25rem">
    <div>
      <h1><?= h($poll['title']) ?></h1>
      <p><?= count($allPeople) ?> respondent<?= count($allPeople) != 1 ? 's' : '' ?>
        <?php if ($poll['deadline']): ?>· Deadline <?= date('d M Y', strtotime($poll['deadline'])) ?><?php endif; ?>
      </p>
    </div>
    <div class="header-actions">
      <a href="vote.php?id=<?= h($pid) ?>" class="btn btn-ghost btn-sm" target="_blank">Vote link ↗</a>
      <?php if ($isOwner): ?><a href="index.php" class="btn btn-ghost btn-sm">← Dashboard</a><?php endif; ?>
    </div>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

  <!-- Save access prompt for token-only (non-logged-in) visitors -->
  <?php if (!$isOwner && $hasToken): ?>
  <div class="card" style="margin-bottom:1.25rem;border-left:3px solid var(--accent)">
    <h2 style="margin-bottom:.4rem">Save your admin access</h2>
    <p class="hint" style="margin-bottom:.9rem">Email yourself the admin link, or login to attach this poll to your account.</p>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
      <form method="POST" style="display:flex;gap:.5rem;align-items:center;flex:1;min-width:220px">
        <input type="hidden" name="action" value="send_admin_link">
        <input type="email" name="admin_email" required placeholder="you@example.com" style="flex:1;min-width:0">
        <button type="submit" class="btn btn-secondary btn-sm">Send link</button>
      </form>
      <a href="auth.php?next=<?= urlencode("results.php?id=$pid&token=$token") ?>" class="btn btn-ghost btn-sm">Login →</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Share link -->
  <div class="card share-card" style="margin-bottom:1.25rem">
    <label>Share with participants</label>
    <div class="share-row">
      <input type="text" id="shareUrl" value="<?= h($voteUrl) ?>" readonly>
      <button class="btn btn-ghost btn-sm" onclick="copyLink()">Copy</button>
    </div>
  </div>

  <?php if ($poll['finalized_slot_id']): ?>
    <?php $fslot = $db->querySingle("SELECT * FROM slots WHERE id=" . (int)$poll['finalized_slot_id'], true); ?>
    <div class="alert alert-success finalized-banner">
      <strong>📅 Finalized:</strong> <?= slotLabel($fslot['slot_dt'], $fslot['duration']) ?>
      <form method="POST" style="margin-top:.5rem">
        <input type="hidden" name="action" value="unfinalize">
        <button class="btn btn-ghost btn-sm">Unfinalize</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Best slots -->
  <div class="card" style="margin-bottom:1.25rem">
    <h2>Best slots</h2>
    <?php if (!$allPeople): ?>
      <p class="hint">No responses yet — share the link above.</p>
    <?php else: ?>
    <div class="ranked-slots">
      <?php foreach (array_slice($scored, 0, 5) as $i => $sc): ?>
        <?php $s = $sc['slot']; $isFinal = $poll['finalized_slot_id'] == $s['id']; ?>
        <div class="ranked-row <?= $isFinal ? 'is-final' : '' ?>">
          <div class="rank-num"><?= $i + 1 ?></div>
          <div class="rank-info">
            <div class="rank-label"><?= h(slotLabel($s['slot_dt'], $s['duration'])) ?></div>
            <div class="rank-bar-wrap">
              <div class="rank-bar yes-bar"  style="width:<?= $sc['total'] ? round($sc['yes']   / $sc['total'] * 100) : 0 ?>%"></div>
              <div class="rank-bar maybe-bar"style="width:<?= $sc['total'] ? round($sc['maybe'] / $sc['total'] * 100) : 0 ?>%"></div>
            </div>
            <div class="rank-counts">
              <span class="yes-text">✓ <?= $sc['yes'] ?></span>
              <span class="maybe-text">~ <?= $sc['maybe'] ?></span>
              <span class="no-text">✗ <?= $sc['no'] ?></span>
            </div>
            <?php
              $vm = $voteMap[$s['id']] ?? [];
              $byStatus = ['yes'=>[],'maybe'=>[],'no'=>[]];
              foreach ($vm as $pn => $st) $byStatus[$st][] = $pn;
            ?>
            <?php if ($vm): ?>
            <div class="rank-people">
              <?php foreach (['yes','maybe','no'] as $st): ?>
                <?php foreach ($byStatus[$st] as $pn): ?>
                  <span class="rp rp-<?= $st ?>"><?= h($pn) ?></span>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php if (!$poll['finalized_slot_id']): ?>
          <form method="POST" style="margin-left:auto">
            <input type="hidden" name="action"  value="finalize">
            <input type="hidden" name="slot_id" value="<?= $s['id'] ?>">
            <button class="btn btn-primary btn-sm">Finalize</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Full response table -->
  <?php if ($allPeople): ?>
  <div class="card">
    <h2>All responses</h2>
    <div class="vote-table-wrap">
    <table class="vote-table">
      <thead>
        <tr>
          <th class="name-th">Name</th>
          <?php foreach ($slots as $s): ?>
            <?php
              $sDur   = (int)$s['duration'];
              $sTs    = strtotime($s['slot_dt']);
              $sMulti = $allDay && $sDur > 1;
            ?>
            <th class="slot-th">
              <div class="th-date">
                <?= date('d M', $sTs) ?>
                <?php if ($sMulti): ?>
                  <span style="font-size:.7rem;display:block;opacity:.75">→ <?= date('d M', $sTs + ($sDur-1)*86400) ?></span>
                <?php endif; ?>
              </div>
              <?php if (!$allDay): ?>
              <div class="th-time"><?= date('H:i', $sTs) ?></div>
              <?php endif; ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allPeople as $p): ?>
        <tr>
          <td class="name-td"><?= h($p) ?></td>
          <?php foreach ($slots as $s): ?>
            <?php $st = $voteMap[$s['id']][$p] ?? 'no'; ?>
            <td class="vote-cell status-<?= $st ?>"><?= ['yes'=>'✓','maybe'=>'~','no'=>'✗'][$st] ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="name-td tfoot-label">✓ yes</td>
          <?php foreach ($slots as $s): ?>
            <?php $cnt = count(array_filter($voteMap[$s['id']] ?? [], fn($v) => $v === 'yes')); ?>
            <td class="vote-cell tfoot-count"><?= $cnt ?></td>
          <?php endforeach; ?>
        </tr>
      </tfoot>
    </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($comments): ?>
  <div class="card comments-card">
    <h2>Comments</h2>
    <?php foreach ($comments as $c): ?>
      <div class="comment-item">
        <span class="comment-name"><?= h($c['participant']) ?></span>
        <p class="comment-text"><?= nl2br(h($c['comment'])) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<script>
function copyLink() {
  navigator.clipboard.writeText(document.getElementById('shareUrl').value)
    .then(() => { const b = event.target; b.textContent = 'Copied!'; setTimeout(()=>b.textContent='Copy', 2000); });
}
</script>
</body>
</html>
