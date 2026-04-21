<?php
require_once __DIR__ . '/db.php';
$user = currentUser();
$db   = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title']          ?? '');
    $desc         = trim($_POST['description']    ?? '');
    $deadline     = trim($_POST['deadline']       ?? '');
    $dur          = (int)($_POST['duration']      ?? 60);
    $slots        = $_POST['slots']               ?? [];
    $tz           = trim($_POST['tz']             ?? 'UTC');
    $anon         = (int)(!empty($_POST['anonymous']));
    $allDay       = (int)(!empty($_POST['all_day']));
    $creatorEmail = trim($_POST['creator_email']  ?? '');

    if (!in_array($tz, timezone_identifiers_list())) $tz = 'UTC';

    if (!$title)  $error = 'Title is required.';
    elseif (!$slots) $error = 'Select at least one ' . ($allDay ? 'date' : 'time slot') . '.';
    else {
        // Resolve user_id — logged-in user, or find/create from email, or null
        $uid = $user ? $user['id'] : null;
        if (!$uid && $creatorEmail && filter_var($creatorEmail, FILTER_VALIDATE_EMAIL)) {
            $eesc    = $db->escapeString($creatorEmail);
            $existing = $db->querySingle("SELECT id FROM users WHERE email='$eesc'", true);
            if ($existing) {
                $uid = $existing['id'];
            } else {
                $uname = explode('@', $creatorEmail)[0];
                $ustmt = $db->prepare("INSERT INTO users (name,email,password) VALUES (?,?,'')");
                $ustmt->bindValue(1, $uname); $ustmt->bindValue(2, $creatorEmail);
                $ustmt->execute();
                $uid = $db->lastInsertRowID();
            }
        }

        $pid   = uniqueSlug($title);
        $token = genId(12);
        $stmt  = $db->prepare("INSERT INTO polls (public_id,user_id,title,description,admin_token,deadline,timezone,anonymous,all_day) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bindValue(1, $pid);   $stmt->bindValue(2, $uid, SQLITE3_INTEGER);
        $stmt->bindValue(3, $title); $stmt->bindValue(4, $desc);
        $stmt->bindValue(5, $token); $stmt->bindValue(6, $deadline ?: null);
        $stmt->bindValue(7, $tz);    $stmt->bindValue(8, $anon);
        $stmt->bindValue(9, $allDay);
        $stmt->execute();
        $pollId = $db->lastInsertRowID();

        $sstmt = $db->prepare("INSERT INTO slots (poll_id,slot_dt,duration) VALUES (?,?,?)");
        foreach ($slots as $s) {
            $s = trim($s);
            if (!$s) continue;
            $sstmt->bindValue(1, $pollId);
            $sstmt->bindValue(2, $s);
            $sstmt->bindValue(3, $allDay ? 0 : $dur);
            $sstmt->execute();
            $sstmt->reset();
        }

        // Email admin link if creator provided email but is not logged in
        if ($creatorEmail && !$user) {
            $adminUrl = APP_URL . "/results.php?id=$pid&token=$token";
            $voteUrl  = APP_URL . "/vote.php?id=$pid";
            $body = "Hi,\n\nYour WhenPoll \"$title\" has been created!\n\n"
                  . "Your admin link (bookmark this — it's your only way back):\n$adminUrl\n\n"
                  . "Share this voting link with participants:\n$voteUrl\n\n— WhenPoll";
            $headers = implode("\r\n", [
                'From: WhenPoll <info@whenpoll.com>',
                'Content-Type: text/plain; charset=UTF-8',
            ]);
            mail($creatorEmail, "Your WhenPoll: $title", $body, $headers);
        }

        flash('success', 'Poll created! Share the voting link with participants.');
        redirect("results.php?id=$pid&token=$token");
    }
}

$cals = [];
if ($user) {
    $res = $db->query("SELECT id,provider,label FROM calendars WHERE user_id=" . (int)$user['id']);
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) $cals[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_NAME ?> · New poll</title>
<link rel="stylesheet" href="css/app.css">
</head>
<body>
<?php $_navUser = currentUser(); ?>
<nav>
  <a class="brand" href="index.php">
    <svg class="brand-icon" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
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
  <?php if ($_navUser): ?>
    <span class="nav-divider">|</span>
    <span class="nav-user"><?= h($_navUser['name']) ?></span>
    <a href="calendar.php" class="nav-link">Calendars</a>
    <a href="index.php?action=logout" class="nav-link nav-logout">Sign out</a>
  <?php endif; ?>
  <span class="nav-eco" title="Hosted in EU · 100% renewable energy">🌿 EU · renewable</span>
</nav>

<div class="page-wrap">
  <h1>New poll</h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST" id="createForm">
    <input type="hidden" name="tz"      id="pollTz"     value="UTC">
    <input type="hidden" name="all_day" id="pollAllDay" value="0">

    <!-- ── Details ───────────────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:.9rem">
      <div class="field">
        <label>Title *</label>
        <input type="text" name="title" required placeholder="e.g. Q3 kick-off" autofocus>
      </div>
      <?php if (!$user): ?>
      <div class="field">
        <label>Your email <span class="muted">(optional)</span></label>
        <input type="email" name="creator_email" placeholder="you@example.com">
        <p class="hint" style="margin-top:.3rem">
          If you skip this, <strong>save your admin link</strong> after creating — it's the only way to manage your poll.
        </p>
      </div>
      <?php endif; ?>
      <div class="row-3">
        <div class="field" style="margin:0">
          <label>Duration</label>
          <select name="duration" id="durSelect">
            <option value="30">30 min</option>
            <option value="60" selected>1 hour</option>
            <option value="90">1.5 h</option>
            <option value="120">2 h</option>
          </select>
        </div>
        <div class="field" style="margin:0">
          <label>Deadline <span class="muted">(opt.)</span></label>
          <input type="date" name="deadline">
        </div>
        <div class="field" style="margin:0;display:flex;flex-direction:column;justify-content:flex-end">
          <label class="toggle-check">
            <input type="checkbox" name="anonymous" value="1">
            <span>Anonymous voting</span>
          </label>
        </div>
      </div>
      <div class="mode-toggle" style="margin-top:.75rem">
        <button type="button" class="mode-btn active" id="modeTime">⏱ Time slots</button>
        <button type="button" class="mode-btn" id="modeDay">📅 Full days</button>
      </div>
      <details style="margin-top:.75rem">
        <summary class="hint" style="cursor:pointer;list-style:none">+ Description</summary>
        <div class="field" style="margin-top:.5rem;margin-bottom:0">
          <textarea name="description" placeholder="Any extra info for participants…"></textarea>
        </div>
      </details>
    </div>

    <!-- ── Slot / date picker ─────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:.9rem">
      <div class="grid-toolbar">
        <div class="grid-controls">
          <div>
            <label class="ctrl-label">Start</label>
            <input type="date" id="gridFrom" class="ctrl-input">
          </div>
          <div id="hoursWrap">
            <label class="ctrl-label">Hours</label>
            <div style="display:flex;gap:.25rem;align-items:center">
              <select id="hourFrom" class="ctrl-select"></select>
              <span class="muted" style="font-size:.8rem">–</span>
              <select id="hourTo" class="ctrl-select"></select>
            </div>
          </div>
          <label class="toggle-check" style="padding-bottom:.35rem">
            <input type="checkbox" id="skipWknd" checked>
            <span>Weekdays only</span>
          </label>
        </div>
        <div class="grid-quick">
          <button type="button" class="btn btn-ghost btn-sm" onclick="quickAll()">All</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="quickClear()">Clear</button>
          <?php if ($cals): ?>
            <button type="button" class="btn btn-ghost btn-sm" id="calBtn" style="display:none">✨ Busy</button>
          <?php else: ?>
            <a href="calendar.php" class="btn btn-ghost btn-sm" style="white-space:nowrap">🗓 Calendar</a>
          <?php endif; ?>
        </div>
      </div>

      <div id="slotCount" class="hint" style="margin:.25rem 0 .6rem"></div>

      <!-- Time-slot grid -->
      <div class="slot-grid-scroll" id="timeGridWrap">
        <table id="slotGrid" class="slot-grid">
          <thead><tr id="gridHead"></tr></thead>
          <tbody id="gridBody"></tbody>
        </table>
      </div>

      <!-- Full-day date picker -->
      <div id="dateGrid" class="date-grid" style="display:none"></div>

      <div id="slotHiddens"></div>
    </div>

    <!-- Calendar busy overlay panel -->
    <?php if ($cals): ?>
    <div id="calPanel" class="suggest-panel" style="display:none;margin-bottom:.9rem">
      <button type="button" class="btn btn-secondary btn-sm" id="fetchFreeBtn">Show busy slots from calendar</button>
      <span id="calStatus" class="hint" style="margin-left:.5rem"></span>
    </div>
    <?php endif; ?>

    <div class="form-footer-row">
      <button type="submit" class="btn btn-primary">Create poll →</button>
      <div class="template-tools">
        <button type="button" class="btn btn-ghost btn-sm" onclick="saveTemplate()">Save template</button>
        <select id="templSel" class="ctrl-select" onchange="loadTemplate(this)" style="display:none">
          <option value="">Load template…</option>
        </select>
      </div>
    </div>
  </form>
</div>

<script>
// ── State ─────────────────────────────────────────────────────────────────────
const selected  = new Set();
const busyCells = new Set();
let isAllDay    = false;

// ── Timezone ──────────────────────────────────────────────────────────────────
document.getElementById('pollTz').value =
  Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

// ── Controls ──────────────────────────────────────────────────────────────────
const $from  = document.getElementById('gridFrom');
const $hFrom = document.getElementById('hourFrom');
const $hTo   = document.getElementById('hourTo');
const $skip  = document.getElementById('skipWknd');

for (let h = 6; h <= 22; h++) {
  const t = h.toString().padStart(2,'0') + ':00';
  $hFrom.add(new Option(t, h));
  $hTo.add(new Option(t, h));
}
$hFrom.value = 9; $hTo.value = 18;

(function () {
  const d = new Date(), dow = d.getDay();
  d.setDate(d.getDate() + (dow === 0 ? 1 : (8 - dow) % 7 || 7));
  $from.value = d.toISOString().slice(0,10);
})();

// ── Mode toggle ───────────────────────────────────────────────────────────────
document.getElementById('modeTime').addEventListener('click', () => setMode(false));
document.getElementById('modeDay' ).addEventListener('click', () => setMode(true));

function setMode(allDay) {
  isAllDay = allDay;
  document.getElementById('pollAllDay').value = allDay ? '1' : '0';
  document.getElementById('modeTime').classList.toggle('active', !allDay);
  document.getElementById('modeDay' ).classList.toggle('active',  allDay);
  document.getElementById('hoursWrap').style.display   = allDay ? 'none' : '';
  document.getElementById('timeGridWrap').style.display = allDay ? 'none' : '';
  document.getElementById('dateGrid').style.display     = allDay ? 'flex' : 'none';
  document.getElementById('durSelect').closest('.field').style.opacity = allDay ? '.4' : '';
  const calBtn = document.getElementById('calBtn');
  if (calBtn) calBtn.style.display = allDay ? 'none' : '';
  selected.clear(); busyCells.clear();
  renderGrid();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const DAY = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MON = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function fmtDate(d) { return d.toISOString().slice(0,10); }

function getDates() {
  const start = new Date($from.value + 'T00:00:00');
  const skip  = $skip.checked;
  const dates = [], d = new Date(start);
  const target = skip ? 10 : 14;
  while (dates.length < target) {
    if (!skip || (d.getDay() !== 0 && d.getDay() !== 6)) dates.push(new Date(d));
    d.setDate(d.getDate() + 1);
  }
  return dates;
}

function getHours() {
  const h = [];
  for (let i = parseInt($hFrom.value); i < parseInt($hTo.value); i++) h.push(i);
  return h;
}

function slotKey(date, hour) {
  return fmtDate(date) + 'T' + hour.toString().padStart(2,'0') + ':00';
}

// ── Render ────────────────────────────────────────────────────────────────────
function renderGrid() {
  if (isAllDay) { renderDateGrid(); syncHiddens(); return; }

  const dates = getDates(), hours = getHours();

  document.getElementById('gridHead').innerHTML =
    '<th class="time-label-th"></th>' +
    dates.map(d => {
      const wknd = d.getDay() === 0 || d.getDay() === 6;
      return `<th class="grid-head-cell${wknd?' wknd':''}">
        <span class="gh-day">${DAY[d.getDay()]}</span>
        <span class="gh-date">${d.getDate()} ${MON[d.getMonth()]}</span>
      </th>`;
    }).join('');

  document.getElementById('gridBody').innerHTML = hours.map(h => {
    const t = h.toString().padStart(2,'0') + ':00';
    const cells = dates.map(d => {
      const key = slotKey(d, h), wknd = d.getDay() === 0 || d.getDay() === 6;
      const sel = selected.has(key), busy = busyCells.has(key);
      return `<td><button type="button" class="grid-cell${sel?' sel':''}${wknd?' wknd':''}${busy&&!sel?' busy':''}" data-key="${key}"></button></td>`;
    }).join('');
    return `<tr><td class="time-label">${t}</td>${cells}</tr>`;
  }).join('');

  syncHiddens();
}

function renderDateGrid() {
  const dates = getDates();
  document.getElementById('dateGrid').innerHTML = dates.map(d => {
    const key = fmtDate(d) + 'T00:00';
    const sel = selected.has(key), wknd = d.getDay() === 0 || d.getDay() === 6;
    return `<button type="button" class="date-cell${sel?' sel':''}${wknd?' wknd':''}" data-key="${key}">
      <span class="dc-day">${DAY[d.getDay()]}</span>
      <span class="dc-num">${d.getDate()}</span>
      <span class="dc-mon">${MON[d.getMonth()]}</span>
    </button>`;
  }).join('');
}

// ── Drag-select (works for both .grid-cell and .date-cell) ────────────────────
let dragAction = null;

function applyCell(btn) {
  if (!btn || !btn.dataset.key) return;
  const key = btn.dataset.key;
  if (dragAction === 'add') {
    selected.add(key); btn.classList.add('sel'); btn.classList.remove('busy');
  } else {
    selected.delete(key); btn.classList.remove('sel');
    if (busyCells.has(key)) btn.classList.add('busy');
  }
  syncHiddens();
}

document.addEventListener('mousedown', e => {
  const btn = e.target.closest('.grid-cell,.date-cell');
  if (!btn) return;
  e.preventDefault();
  dragAction = selected.has(btn.dataset.key) ? 'remove' : 'add';
  applyCell(btn);
});
document.addEventListener('mouseover', e => {
  if (!dragAction) return;
  applyCell(e.target.closest('.grid-cell,.date-cell'));
});
document.addEventListener('mouseup', () => { dragAction = null; });
document.addEventListener('touchstart', e => {
  const btn = e.target.closest('.grid-cell,.date-cell');
  if (!btn) return;
  dragAction = selected.has(btn.dataset.key) ? 'remove' : 'add';
  applyCell(btn);
}, { passive: true });
document.addEventListener('touchmove', e => {
  if (!dragAction) return;
  const t = e.touches[0];
  applyCell(document.elementFromPoint(t.clientX, t.clientY)?.closest('.grid-cell,.date-cell'));
}, { passive: true });
document.addEventListener('touchend', () => { dragAction = null; });

function syncHiddens() {
  const c = document.getElementById('slotHiddens');
  c.innerHTML = '';
  selected.forEach(k => {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'slots[]'; inp.value = k;
    c.appendChild(inp);
  });
  const n = selected.size;
  document.getElementById('slotCount').textContent =
    n ? `${n} ${isAllDay ? (n>1?'dates':'date') : (n>1?'slots':'slot')} selected`
      : isAllDay ? 'Click dates to select.' : 'Click or drag to select slots.';
}

function quickAll() {
  document.querySelectorAll('.grid-cell,.date-cell').forEach(b => {
    selected.add(b.dataset.key); b.classList.add('sel'); b.classList.remove('busy');
  });
  syncHiddens();
}
function quickClear() {
  selected.clear();
  document.querySelectorAll('.grid-cell,.date-cell').forEach(b => {
    b.classList.remove('sel');
    if (busyCells.has(b.dataset.key)) b.classList.add('busy');
  });
  syncHiddens();
}

[$from, $hFrom, $hTo, $skip].forEach(el => el.addEventListener('change', renderGrid));

// ── Calendar busy overlay ─────────────────────────────────────────────────────
const calBtn = document.getElementById('calBtn');
if (calBtn) {
  const calPanel = document.getElementById('calPanel');
  calBtn.addEventListener('click', () => {
    calPanel.style.display = calPanel.style.display === 'none' ? 'block' : 'none';
  });
  calBtn.style.display = '';

  document.getElementById('fetchFreeBtn').addEventListener('click', async () => {
    const status = document.getElementById('calStatus');
    const dates  = getDates();
    const from   = fmtDate(dates[0]), to = fmtDate(dates[dates.length - 1]);
    const dur    = document.querySelector('[name=duration]').value;
    const hf     = parseInt($hFrom.value).toString().padStart(2,'0') + ':00';
    const ht     = parseInt($hTo.value).toString().padStart(2,'00') + ':00';
    status.textContent = 'Checking…';
    try {
      const resp = await fetch(`api.php?action=free_slots&from=${from}&to=${to}&work_start=${hf}&work_end=${ht}&duration=${dur}`);
      const data = await resp.json();
      busyCells.clear();
      (data.busy || []).forEach(dt => busyCells.add(dt.slice(0,13) + ':00'));
      renderGrid();
      const nb = data.busy ? data.busy.length : 0;
      status.textContent = `${nb} busy slot${nb !== 1 ? 's' : ''} marked`;
    } catch (e) { status.textContent = 'Error.'; }
  });
}

// ── Templates (localStorage) ──────────────────────────────────────────────────
function saveTemplate() {
  const name = prompt('Template name:');
  if (!name?.trim()) return;
  const templates = JSON.parse(localStorage.getItem('wp_templates') || '[]');
  templates.push({
    name: name.trim(),
    duration: document.querySelector('[name=duration]').value,
    hourFrom: $hFrom.value, hourTo: $hTo.value,
    skipWknd: $skip.checked, allDay: isAllDay,
  });
  localStorage.setItem('wp_templates', JSON.stringify(templates));
  updateTemplateUI();
}

function loadTemplate(sel) {
  const idx = parseInt(sel.value);
  if (isNaN(idx)) return;
  const t = JSON.parse(localStorage.getItem('wp_templates') || '[]')[idx];
  if (!t) return;
  document.querySelector('[name=duration]').value = t.duration ?? 60;
  $hFrom.value = t.hourFrom ?? 9;
  $hTo.value   = t.hourTo ?? 18;
  $skip.checked = !!t.skipWknd;
  if (t.allDay !== isAllDay) setMode(!!t.allDay);
  else renderGrid();
  sel.value = '';
}

function updateTemplateUI() {
  const templates = JSON.parse(localStorage.getItem('wp_templates') || '[]');
  const sel = document.getElementById('templSel');
  sel.style.display = templates.length ? '' : 'none';
  sel.innerHTML = '<option value="">Load template…</option>' +
    templates.map((t, i) => `<option value="${i}">${h(t.name)}</option>`).join('');
}
function h(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

updateTemplateUI();
renderGrid();
</script>
</body>
</html>
