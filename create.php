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
    $allDaySpan   = max(1, min(5, (int)($_POST['all_day_span'] ?? 1)));
    if (!in_array($tz, timezone_identifiers_list())) $tz = 'UTC';

    if (!$title)  $error = 'Title is required.';
    elseif (!$slots) $error = 'Select at least one ' . ($allDay ? 'date' : 'time slot') . '.';
    else {
        $uid = $user ? $user['id'] : null;

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
            $sstmt->bindValue(3, $allDay ? $allDaySpan : $dur);
            $sstmt->execute();
            $sstmt->reset();
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
  <h1>New poll</h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST" id="createForm">
    <input type="hidden" name="tz"           id="pollTz"      value="UTC">
    <input type="hidden" name="all_day"      id="pollAllDay"  value="0">
    <input type="hidden" name="all_day_span" id="allDaySpan"  value="1">

    <!-- ── Details ───────────────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:.9rem">
      <div class="field">
        <label>Title *</label>
        <input type="text" name="title" required placeholder="e.g. Q3 kick-off" autofocus>
      </div>
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
          <div id="spanWrap" style="display:none">
            <label class="ctrl-label">Event spans</label>
            <select id="daySpan" class="ctrl-select">
              <option value="1">1 day</option>
              <option value="2">2 days</option>
              <option value="3">3 days</option>
              <option value="4">4 days</option>
              <option value="5">5 days</option>
            </select>
          </div>
          <label class="toggle-check" style="padding-bottom:.35rem">
            <input type="checkbox" id="skipWknd" checked>
            <span>Weekdays only</span>
          </label>
        </div>
        <div id="tzDisplay" class="hint" style="font-size:.72rem;align-self:flex-end;padding-bottom:.38rem;white-space:nowrap"></div>
        <div class="grid-quick">
          <button type="button" class="btn btn-ghost btn-sm" onclick="quickAll()">All</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="quickClear()">Clear</button>
          <?php if (!$cals): ?>
            <a href="calendar.php" class="btn btn-ghost btn-sm" style="white-space:nowrap">🗓 Calendar</a>
          <?php endif; ?>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addWeek()">+ week</button>
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

    <?php if ($cals): ?>
    <div style="min-height:1.1rem;margin-bottom:.2rem">
      <span id="calStatus" class="hint"></span>
    </div>
    <?php endif; ?>

    <div class="form-footer-row">
      <button type="submit" class="btn btn-primary">Create poll →</button>
    </div>
  </form>
</div>

<script>
// ── State ─────────────────────────────────────────────────────────────────────
const selected  = new Set();
const busyCells = new Set();
const busyTitles = {};
let isAllDay    = false;
let numWeeks    = 2;

// ── Timezone ──────────────────────────────────────────────────────────────────
const detectedTz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
document.getElementById('pollTz').value = detectedTz;
document.getElementById('tzDisplay').textContent = '🕐 ' + detectedTz;

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
  document.getElementById('hoursWrap').style.display    = allDay ? 'none' : '';
  document.getElementById('spanWrap').style.display     = allDay ? '' : 'none';
  document.getElementById('timeGridWrap').style.display = allDay ? 'none' : '';
  document.getElementById('dateGrid').style.display     = allDay ? 'flex' : 'none';
  document.getElementById('durSelect').closest('.field').style.opacity = allDay ? '.4' : '';
  document.getElementById('tzDisplay').style.display    = allDay ? 'none' : '';
  selected.clear(); busyCells.clear();
  renderGrid();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const DAY = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MON = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function fmtDate(d) {
  // Use LOCAL date parts — toISOString() converts to UTC which shifts the date
  // for positive-offset timezones (e.g. Italy UTC+2 → midnight appears as prev day)
  return d.getFullYear() + '-' +
    (d.getMonth() + 1).toString().padStart(2, '0') + '-' +
    d.getDate().toString().padStart(2, '0');
}

function getDates() {
  const start = new Date($from.value + 'T00:00:00');
  const skip  = $skip.checked;
  const dates = [], d = new Date(start);
  const target = skip ? numWeeks * 5 : numWeeks * 7;
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
      const title = busyTitles[key] || '';
      const truncTitle = title.slice(0, 80);
      const safeTitle = truncTitle.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      const titleHtml = (busy && !sel && safeTitle) ? `<span class="busy-title">${safeTitle}</span>` : '';
      return `<td><button type="button" class="grid-cell${sel?' sel':''}${wknd?' wknd':''}${busy&&!sel?' busy':''}" data-key="${key}" ${title ? `title="${title.replace(/"/g,'&quot;')}"` : ''}>${titleHtml}</button></td>`;
    }).join('');
    return `<tr><td class="time-label">${t}</td>${cells}</tr>`;
  }).join('');

  syncHiddens();
}

function getSpan() {
  return parseInt(document.getElementById('daySpan')?.value || '1') || 1;
}

function renderDateGrid() {
  const dates = getDates();
  const span  = getSpan();

  // Build a map: dateString → start slot key (for highlighting covered days)
  const coveredBy = {};
  selected.forEach(key => {
    const start = new Date(key.slice(0, 10) + 'T00:00:00');
    for (let i = 0; i < span; i++) {
      const d = new Date(start);
      d.setDate(d.getDate() + i);
      coveredBy[fmtDate(d)] = key;
    }
  });

  document.getElementById('dateGrid').innerHTML = dates.map(d => {
    const ds      = fmtDate(d);
    const key     = ds + 'T00:00';
    const isStart = selected.has(key);
    const inSpan  = ds in coveredBy;
    const wknd    = d.getDay() === 0 || d.getDay() === 6;
    // mark tail cells (covered but not start) so we can style differently
    const isTail  = inSpan && !isStart;
    return `<button type="button" class="date-cell${inSpan?' sel':''}${wknd?' wknd':''}${isTail?' span-tail':''}" data-key="${key}">
      <span class="dc-day">${DAY[d.getDay()]}</span>
      <span class="dc-num">${d.getDate()}</span>
      <span class="dc-mon">${MON[d.getMonth()]}</span>
      ${isStart && span > 1 ? `<span class="dc-span-badge">${span}d</span>` : ''}
    </button>`;
  }).join('');
}

// ── Drag-select (works for both .grid-cell and .date-cell) ────────────────────
let dragAction = null;

function applyCell(btn) {
  if (!btn || !btn.dataset.key) return;
  const key = btn.dataset.key;

  if (isAllDay) {
    const span = getSpan();
    const ds   = key.slice(0, 10);
    // Find which start-slot (if any) covers this date
    let coverKey = null;
    if (span > 1) {
      selected.forEach(sk => {
        const start = new Date(sk.slice(0, 10) + 'T00:00:00');
        for (let i = 0; i < span; i++) {
          const d = new Date(start);
          d.setDate(d.getDate() + i);
          if (fmtDate(d) === ds) { coverKey = sk; break; }
        }
      });
    } else {
      coverKey = selected.has(key) ? key : null;
    }
    if (dragAction === 'add' && !coverKey) {
      selected.add(key);
    } else if (dragAction === 'remove' && coverKey) {
      selected.delete(coverKey);
    }
    syncHiddens();
    renderDateGrid();
    return;
  }

  // Time-slot grid
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
  const span = isAllDay ? getSpan() : 1;
  document.getElementById('allDaySpan').value = span;
  const c = document.getElementById('slotHiddens');
  c.innerHTML = '';
  selected.forEach(k => {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'slots[]'; inp.value = k;
    c.appendChild(inp);
  });
  const n = selected.size;
  const unit = isAllDay
    ? (span > 1 ? `${span}-day event` : 'date')
    : 'slot';
  const units = isAllDay
    ? (span > 1 ? `${span}-day events` : 'dates')
    : 'slots';
  document.getElementById('slotCount').textContent =
    n ? `${n} ${n > 1 ? units : unit} selected`
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

[$from, $hFrom, $hTo, $skip].forEach(el => el.addEventListener('change', () => {
  renderGrid();
  <?php if ($cals): ?>scheduleFetch();<?php endif; ?>
}));

document.getElementById('daySpan').addEventListener('change', () => {
  selected.clear(); // changing span invalidates existing selections
  syncHiddens();
  renderDateGrid();
});

// ── Calendar busy overlay (auto-load) ─────────────────────────────────────────
<?php if ($cals): ?>
let busyFetchTimer = null;

async function fetchBusySlots() {
  if (isAllDay) return;
  const status = document.getElementById('calStatus');
  const dates  = getDates();
  const from   = fmtDate(dates[0]), to = fmtDate(dates[dates.length - 1]);
  const dur    = document.querySelector('[name=duration]').value;
  const hf     = parseInt($hFrom.value).toString().padStart(2, '0') + ':00';
  const ht     = parseInt($hTo.value).toString().padStart(2, '0') + ':00';
  if (status) status.textContent = 'Loading calendar…';
  try {
    const tz   = encodeURIComponent(document.getElementById('pollTz').value || 'UTC');
    const resp = await fetch(`api.php?action=free_slots&from=${from}&to=${to}&work_start=${hf}&work_end=${ht}&duration=${dur}&tz=${tz}`);
    const data = await resp.json();
    busyCells.clear();
    Object.keys(busyTitles).forEach(k => delete busyTitles[k]);
    (data.busy || []).forEach(b => {
      const dt    = typeof b === 'string' ? b : b.dt;
      const title = typeof b === 'string' ? '' : (b.title || '');
      busyCells.add(dt.slice(0, 13) + ':00');
      if (title) busyTitles[dt.slice(0, 13) + ':00'] = title;
    });
    renderGrid();
    const nb = data.busy ? data.busy.length : 0;
    if (status) status.textContent = nb ? `${nb} busy slot${nb !== 1 ? 's' : ''} marked` : '';
  } catch (e) { if (status) status.textContent = ''; }
}

function scheduleFetch() {
  clearTimeout(busyFetchTimer);
  busyFetchTimer = setTimeout(fetchBusySlots, 500);
}
<?php endif; ?>

// ── Add a week ────────────────────────────────────────────────────────────────
function addWeek() {
  numWeeks++;
  renderGrid();
  <?php if ($cals): ?>scheduleFetch();<?php endif; ?>
}

function h(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

renderGrid();
<?php if ($cals): ?>fetchBusySlots();<?php endif; ?>
</script>
</body>
</html>
