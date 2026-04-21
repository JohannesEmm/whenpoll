<?php
require_once __DIR__ . '/db.php';
$db = getDB();

$pid  = $_GET['id'] ?? '';
$poll = $db->querySingle("SELECT * FROM polls WHERE public_id='" . $db->escapeString($pid) . "'", true);
if (!$poll) { http_response_code(404); die('Poll not found.'); }

$pollId  = $poll['id'];
$expired = $poll['deadline'] && strtotime($poll['deadline']) < strtotime('today');
$final   = (bool)$poll['finalized_slot_id'];

$slots = [];
$res   = $db->query("SELECT * FROM slots WHERE poll_id=$pollId ORDER BY slot_dt");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) $slots[] = $r;

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$expired && !$final) {
    $name  = trim($_POST['participant'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $votes = $_POST['vote'] ?? [];
    if (!$name) { $error = 'Please enter your name.'; }
    else {
        $esc = $db->escapeString($name);
        $db->exec("DELETE FROM votes WHERE poll_id=$pollId AND participant='$esc'");
        $vstmt = $db->prepare("INSERT INTO votes (poll_id,participant,email,slot_id,status) VALUES (?,?,?,?,?)");
        foreach ($slots as $s) {
            $status = $votes[$s['id']] ?? 'no';
            if (!in_array($status, ['yes','maybe','no'])) $status = 'no';
            $vstmt->bindValue(1, $pollId); $vstmt->bindValue(2, $name);
            $vstmt->bindValue(3, $email ?: null); $vstmt->bindValue(4, $s['id']);
            $vstmt->bindValue(5, $status);
            $vstmt->execute(); $vstmt->reset();
        }
        $success = "Saved! Thanks, {$name}.";
        $_SESSION['voted_'.$pollId] = true;
    }
}

$voteMap = []; $allPeople = [];
$vres = $db->query("SELECT * FROM votes WHERE poll_id=$pollId");
while ($r = $vres->fetchArray(SQLITE3_ASSOC)) {
    $voteMap[$r['slot_id']][$r['participant']] = $r['status'];
    $allPeople[$r['participant']] = true;
}
$allPeople = array_keys($allPeople);

$anon      = !empty($poll['anonymous']);
$allDay    = !empty($poll['all_day']);
$pollTz    = $poll['timezone'] ?? 'UTC';
$hasVoted  = !empty($_SESSION['voted_'.$pollId]);
$showNames = !$anon || $hasVoted || (bool)$success;

// Viewer's own calendar (if logged in)
$viewer     = currentUser();
$viewerCals = [];
if ($viewer && $slots) {
    $res2 = $db->query("SELECT id FROM calendars WHERE user_id=" . (int)$viewer['id']);
    while ($r2 = $res2->fetchArray(SQLITE3_ASSOC)) $viewerCals[] = $r2;
}
$calFrom = $slots ? date('Y-m-d', strtotime(reset($slots)['slot_dt'])) : '';
$calTo   = $slots ? date('Y-m-d', strtotime(end($slots)['slot_dt']))   : '';
$minDur  = $slots ? max(30, min(array_map(fn($s) => (int)($s['duration'] ?: 60), $slots))) : 60;

function slotIso(string $dt, string $tz): string {
    try { $d = new DateTime($dt, new DateTimeZone($tz)); return $d->format('c'); }
    catch (Exception $e) { return $dt . 'Z'; }
}

// Group slots by date for colspan headers
$slotsByDate = [];
foreach ($slots as $s) {
    $d = date('Y-m-d', strtotime($s['slot_dt']));
    $slotsByDate[$d][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($poll['title']) ?> · <?= APP_NAME ?></title>
<link rel="stylesheet" href="css/app.css">
</head>
<body>
<nav><a class="brand" href="index.php"><span class="brand-name">When<span>Poll</span></span></a></nav>
<div class="page-wrap">

  <div class="vote-header">
    <div>
      <h1><?= h($poll['title']) ?></h1>
      <?php if ($poll['description']): ?><p><?= h($poll['description']) ?></p><?php endif; ?>
    </div>
    <?php if ($poll['deadline']): ?>
      <div class="deadline-badge <?= $expired ? 'expired' : '' ?>">
        <?= $expired ? 'Closed' : 'Deadline' ?> <?= date('d M', strtotime($poll['deadline'])) ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($final):   ?><div class="alert alert-success">Poll finalized — no more responses.</div><?php endif; ?>
  <?php if ($expired && !$final): ?><div class="alert alert-error">Deadline passed.</div><?php endif; ?>

  <div class="card">
    <?php if (!$final && !$expired): ?>
    <form method="POST" id="vf">
      <div class="vf-identity">
        <input type="text"  name="participant" required placeholder="Your name *">
        <input type="email" name="email" placeholder="Email (optional)">
      </div>
    <?php endif; ?>

    <div class="vgrid-scroll">
      <table class="vgrid">
        <thead>
          <!-- Date row -->
          <tr>
            <th class="vg-corner" rowspan="<?= $allDay ? 1 : 2 ?>"></th>
            <?php foreach ($slotsByDate as $d => $daySlots): ?>
              <th class="vg-date-h" colspan="<?= count($daySlots) ?>">
                <div class="vg-dow"><?= date('D', strtotime($d)) ?></div>
                <div class="vg-dm"><?= date('j M', strtotime($d)) ?></div>
              </th>
            <?php endforeach; ?>
          </tr>
          <?php if (!$allDay): ?>
          <!-- Time row with timezone data for JS conversion -->
          <tr>
            <?php foreach ($slots as $s): ?>
              <th class="vg-time-h" data-iso="<?= slotIso($s['slot_dt'], $pollTz) ?>">
                <?= date('H:i', strtotime($s['slot_dt'])) ?>
              </th>
            <?php endforeach; ?>
          </tr>
          <?php endif; ?>
        </thead>
        <tbody>
          <!-- Participant rows (hidden in anonymous polls until voted) -->
          <?php if ($showNames): ?>
            <?php foreach ($allPeople as $p): ?>
            <tr>
              <td class="vg-name"><?= h($p) ?></td>
              <?php foreach ($slots as $s): ?>
                <?php $st = $voteMap[$s['id']][$p] ?? 'no'; ?>
                <td class="vg-rc rc-<?= $st ?>"><?= $st === 'yes' ? '✓' : ($st === 'maybe' ? '~' : '') ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          <?php elseif ($anon && $allPeople): ?>
            <tr>
              <td class="vg-name hint" style="white-space:nowrap;font-style:italic">
                <?= count($allPeople) ?> responded
              </td>
              <?php foreach ($slots as $s): ?>
                <?php $yes = count(array_filter($voteMap[$s['id']] ?? [], fn($v)=>$v==='yes')); ?>
                <td class="vg-rc rc-<?= $yes ? 'yes' : 'no' ?>"><?= $yes ?: '' ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endif; ?>

          <?php if ($allPeople && (!$final && !$expired)): ?>
            <tr class="vg-sep"><td colspan="<?= count($slots) + 1 ?>"></td></tr>
          <?php endif; ?>

          <!-- Vote row -->
          <?php if (!$final && !$expired): ?>
          <tr>
            <td class="vg-your-label">You</td>
            <?php foreach ($slots as $s): ?>
              <?php $yes = count(array_filter($voteMap[$s['id']] ?? [], fn($v) => $v === 'yes')); ?>
              <td class="vg-vcell" data-dt="<?= h(substr($s['slot_dt'],0,16)) ?>">
                <button type="button" class="vg-btn" data-sid="<?= $s['id'] ?>" data-state="no">
                  <?php if ($yes): ?><span class="vg-n"><?= $yes ?></span><?php endif; ?>
                </button>
                <input type="hidden" name="vote[<?= $s['id'] ?>]" value="no">
              </td>
            <?php endforeach; ?>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (!$final && !$expired): ?>
      <div class="vg-legend">
        <span class="vg-l-yes">✓ yes</span>
        <span class="vg-l-maybe">~ maybe</span>
        <span class="vg-l-no">✗ no</span>
        <span class="vg-l-hint">tap to cycle</span>
        <?php if ($viewerCals): ?>
          <span id="calOverlayStatus" class="hint" style="margin-left:.5rem"></span>
        <?php endif; ?>
      </div>
      <button type="submit" form="vf" class="btn btn-primary" style="margin-top:.6rem">Save availability</button>
    </form>

    <?php if (!$viewer): ?>
      <p class="hint" style="margin-top:.75rem">
        <a href="auth.php?next=<?= urlencode(APP_URL.'/'.$pid) ?>">Sign in</a>
        to connect your calendar and see your availability here.
      </p>
    <?php elseif (!$viewerCals): ?>
      <p class="hint" style="margin-top:.75rem">
        <a href="calendar.php">Connect your calendar</a> to overlay your availability on this poll.
      </p>
    <?php endif; ?>
    <?php endif; ?>
  </div>

</div>
<script>
const STATES = ['no','yes','maybe'];
const ICONS  = {no:'', yes:'✓', maybe:'~'};
let dragState = null;

function applyVote(btn, state) {
  if (!btn || !btn.classList.contains('vg-btn')) return;
  const inp = btn.parentElement.querySelector('input[type=hidden]');
  if (!inp) return;
  btn.dataset.state = state;
  inp.value = state;
  const n = btn.querySelector('.vg-n');
  btn.className = 'vg-btn vg-' + state;
  btn.textContent = ICONS[state];
  if (n) btn.prepend(n);
}

document.addEventListener('mousedown', e => {
  const btn = e.target.closest('.vg-btn');
  if (!btn) return;
  e.preventDefault();
  const i = (STATES.indexOf(btn.dataset.state) + 1) % 3;
  dragState = STATES[i];
  applyVote(btn, dragState);
});
document.addEventListener('mouseover', e => {
  if (!dragState) return;
  applyVote(e.target.closest('.vg-btn'), dragState);
});
document.addEventListener('mouseup', () => { dragState = null; });

document.addEventListener('touchstart', e => {
  const btn = e.target.closest('.vg-btn');
  if (!btn) return;
  const i = (STATES.indexOf(btn.dataset.state) + 1) % 3;
  dragState = STATES[i];
  applyVote(btn, dragState);
}, { passive: true });
document.addEventListener('touchmove', e => {
  if (!dragState) return;
  const t = e.touches[0];
  applyVote(document.elementFromPoint(t.clientX, t.clientY)?.closest('.vg-btn'), dragState);
}, { passive: true });
document.addEventListener('touchend', () => { dragState = null; });

// Convert slot times to viewer's local timezone
document.querySelectorAll('.vg-time-h[data-iso]').forEach(th => {
  try {
    const d = new Date(th.dataset.iso);
    th.textContent = d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
  } catch(e) {}
});

<?php if ($viewerCals && $calFrom): ?>
// Auto-overlay viewer's own calendar busy slots
(async () => {
  const status = document.getElementById('calOverlayStatus');
  if (status) status.textContent = 'Loading your calendar…';
  try {
    const r = await fetch('api.php?action=free_slots&from=<?= $calFrom ?>&to=<?= $calTo ?>&work_start=00:00&work_end=23:59&duration=<?= $minDur ?>');
    const data = await r.json();
    if (!data.busy?.length) { if (status) status.textContent = ''; return; }
    const busySet = new Set(data.busy.map(dt => dt.slice(0,16)));
    document.querySelectorAll('.vg-vcell[data-dt]').forEach(td => {
      if (!busySet.has(td.dataset.dt)) return;
      td.classList.add('vg-me-busy');
      const btn = td.querySelector('.vg-btn');
      if (btn && btn.dataset.state === 'no') {
        // pre-set to no visually (already default), just tint the cell
      }
    });
    const nb = data.busy.length;
    if (status) status.textContent = `· ${nb} busy in your calendar`;
  } catch(e) { if (status) status.textContent = ''; }
})();
<?php endif; ?>
</script>
</body>
</html>
