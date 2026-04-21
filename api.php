<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
$user = currentUser();
if (!$user) { echo json_encode(['error' => 'unauthenticated']); exit; }

$action = $_GET['action'] ?? '';

if ($action === 'free_slots') {
    $from      = $_GET['from']       ?? '';
    $to        = $_GET['to']         ?? '';
    $workStart = $_GET['work_start'] ?? '09:00';
    $workEnd   = $_GET['work_end']   ?? '18:00';
    $duration  = max(30, (int)($_GET['duration'] ?? 60));

    if (!$from || !$to) { echo json_encode(['error' => 'missing params']); exit; }

    $db  = getDB();
    $uid = $user['id'];
    $cals = [];
    $res  = $db->query("SELECT * FROM calendars WHERE user_id=$uid");
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $cals[] = $r;

    // Collect all busy intervals from all connected calendars
    $busy = [];

    foreach ($cals as $cal) {
        if ($cal['provider'] === 'google') {
            $busy = array_merge($busy, fetchGoogleBusy($cal, $from, $to));
        } elseif ($cal['provider'] === 'microsoft') {
            $busy = array_merge($busy, fetchMicrosoftBusy($cal, $from, $to));
        } elseif ($cal['provider'] === 'caldav') {
            $busy = array_merge($busy, fetchCalDAVBusy($cal, $from, $to));
        }
    }

    $freeSlots = computeFreeSlots($from, $to, $workStart, $workEnd, $duration, $busy);
    $busySlots = computeBusySlots($from, $to, $workStart, $workEnd, $duration, $busy);
    echo json_encode(['slots' => $freeSlots, 'busy' => $busySlots]);
    exit;
}

echo json_encode(['error' => 'unknown action']);

// ── Token refresh helpers ─────────────────────────────────────────────────────

function refreshGoogleToken(array &$cal): bool {
    if (!$cal['refresh_token']) return false;
    $tok = httpPost('https://oauth2.googleapis.com/token', [
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'refresh_token' => $cal['refresh_token'],
        'grant_type'    => 'refresh_token',
    ]);
    if (empty($tok['access_token'])) return false;
    $expiry = time() + ($tok['expires_in'] ?? 3600);
    $db     = getDB();
    $id     = (int)$cal['id'];
    $at     = $db->escapeString($tok['access_token']);
    $db->exec("UPDATE calendars SET access_token='$at', token_expiry=$expiry WHERE id=$id");
    $cal['access_token'] = $tok['access_token'];
    return true;
}

function refreshMicrosoftToken(array &$cal): bool {
    if (!$cal['refresh_token']) return false;
    $tenant = MS_TENANT;
    $tok = httpPost("https://login.microsoftonline.com/$tenant/oauth2/v2.0/token", [
        'client_id'     => MS_CLIENT_ID,
        'client_secret' => MS_CLIENT_SECRET,
        'refresh_token' => $cal['refresh_token'],
        'grant_type'    => 'refresh_token',
        'scope'         => 'offline_access Calendars.Read',
    ]);
    if (empty($tok['access_token'])) return false;
    $expiry = time() + ($tok['expires_in'] ?? 3600);
    $db     = getDB();
    $id     = (int)$cal['id'];
    $at     = $db->escapeString($tok['access_token']);
    $db->exec("UPDATE calendars SET access_token='$at', token_expiry=$expiry WHERE id=$id");
    $cal['access_token'] = $tok['access_token'];
    return true;
}

// ── Google busy fetch ─────────────────────────────────────────────────────────
function fetchGoogleBusy(array $cal, string $from, string $to): array {
    if ($cal['token_expiry'] && $cal['token_expiry'] < time() + 60) refreshGoogleToken($cal);
    $body = json_encode([
        'timeMin'  => $from . 'T00:00:00Z',
        'timeMax'  => $to   . 'T23:59:59Z',
        'items'    => [['id' => 'primary']],
    ]);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nAuthorization: Bearer " . $cal['access_token'],
        'content' => $body,
        'ignore_errors' => true,
    ]]);
    $resp = file_get_contents('https://www.googleapis.com/calendar/v3/freeBusy', false, $ctx);
    $data = json_decode($resp ?: '{}', true);
    $out  = [];
    foreach (($data['calendars']['primary']['busy'] ?? []) as $b) {
        $out[] = [strtotime($b['start']), strtotime($b['end'])];
    }
    return $out;
}

// ── Microsoft busy fetch ──────────────────────────────────────────────────────
function fetchMicrosoftBusy(array $cal, string $from, string $to): array {
    if ($cal['token_expiry'] && $cal['token_expiry'] < time() + 60) refreshMicrosoftToken($cal);
    $body = json_encode([
        'schedules'            => ['me'],
        'startTime'            => ['dateTime' => $from . 'T00:00:00', 'timeZone' => 'UTC'],
        'endTime'              => ['dateTime' => $to   . 'T23:59:59', 'timeZone' => 'UTC'],
        'availabilityViewInterval' => 15,
    ]);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nAuthorization: Bearer " . $cal['access_token'],
        'content' => $body,
        'ignore_errors' => true,
    ]]);
    $resp = file_get_contents('https://graph.microsoft.com/v1.0/me/calendar/getSchedule', false, $ctx);
    $data = json_decode($resp ?: '{}', true);
    $out  = [];
    foreach (($data['value'][0]['scheduleItems'] ?? []) as $item) {
        // Only treat 'busy' status as busy; free/tentative/oof are ignored per requirements
        if (strtolower($item['status'] ?? '') !== 'busy') continue;
        $out[] = [strtotime($item['start']['dateTime']), strtotime($item['end']['dateTime'])];
    }
    return $out;
}

// ── CalDAV busy fetch (REPORT query) ─────────────────────────────────────────
function fetchCalDAVBusy(array $cal, string $from, string $to): array {
    $startDt = $from . 'T000000Z';
    $endDt   = $to   . 'T235959Z';
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
  <D:prop><D:getetag/><C:calendar-data/></D:prop>
  <C:filter>
    <C:comp-filter name="VCALENDAR">
      <C:comp-filter name="VEVENT">
        <C:time-range start="$startDt" end="$endDt"/>
      </C:comp-filter>
    </C:comp-filter>
  </C:filter>
</C:calendar-query>
XML;
    $auth = base64_encode($cal['caldav_user'] . ':' . $cal['caldav_pass']);
    $ctx  = stream_context_create(['http' => [
        'method'  => 'REPORT',
        'header'  => "Depth: 1\r\nContent-Type: application/xml; charset=utf-8\r\nAuthorization: Basic $auth",
        'content' => $xml,
        'ignore_errors' => true,
    ]]);
    $resp = file_get_contents($cal['caldav_url'], false, $ctx);
    if (!$resp) return [];

    $out = [];
    // Parse DTSTART/DTEND/TRANSP from iCal blobs in the XML response
    preg_match_all('/<C:calendar-data>(.*?)<\/C:calendar-data>/s', $resp, $matches);
    foreach ($matches[1] as $ical) {
        // Skip transparent/free events
        if (preg_match('/^TRANSP:TRANSPARENT/m', $ical)) continue;
        // Skip tentative/free via X-MICROSOFT-CDO-BUSYSTATUS
        if (preg_match('/^X-MICROSOFT-CDO-BUSYSTATUS:(FREE|TENTATIVE)/m', $ical)) continue;
        if (preg_match('/^STATUS:TENTATIVE/m', $ical)) continue;

        preg_match('/^DTSTART[^:]*:([\dTZ]+)/m', $ical, $ds);
        preg_match('/^DTEND[^:]*:([\dTZ]+)/m',   $ical, $de);
        if (!$ds || !$de) continue;
        $s = strtotime($ds[1]);
        $e = strtotime($de[1]);
        if ($s && $e) $out[] = [$s, $e];
    }
    return $out;
}

// ── Busy slot computation ─────────────────────────────────────────────────────
function computeBusySlots(
    string $from, string $to,
    string $workStart, string $workEnd,
    int $duration, array $busy
): array {
    $slots = [];
    $step  = $duration * 60;
    $day   = strtotime($from);
    $last  = strtotime($to);
    [$wh, $wm] = explode(':', $workStart);
    [$eh, $em] = explode(':', $workEnd);
    while ($day <= $last) {
        $dayStart = mktime((int)$wh, (int)$wm, 0, date('n',$day), date('j',$day), date('Y',$day));
        $dayEnd   = mktime((int)$eh, (int)$em, 0, date('n',$day), date('j',$day), date('Y',$day));
        $t = $dayStart;
        while ($t + $step <= $dayEnd) {
            foreach ($busy as [$bs, $be]) {
                if ($bs < ($t + $step) && $be > $t) { $slots[] = date('Y-m-d\TH:i', $t); break; }
            }
            $t += $step;
        }
        $day = strtotime('+1 day', $day);
    }
    return $slots;
}

// ── Free slot computation ─────────────────────────────────────────────────────
function computeFreeSlots(
    string $from, string $to,
    string $workStart, string $workEnd,
    int $duration, array $busy
): array {
    $slots = [];
    $step  = $duration * 60;
    $day   = strtotime($from);
    $last  = strtotime($to);

    [$wh, $wm] = explode(':', $workStart);
    [$eh, $em] = explode(':', $workEnd);

    while ($day <= $last) {
        $dayStart = mktime((int)$wh, (int)$wm, 0, date('n',$day), date('j',$day), date('Y',$day));
        $dayEnd   = mktime((int)$eh, (int)$em, 0, date('n',$day), date('j',$day), date('Y',$day));
        $t = $dayStart;
        while ($t + $step <= $dayEnd) {
            $slotEnd = $t + $step;
            $isBusy  = false;
            foreach ($busy as [$bs, $be]) {
                // Overlap check: busy interval overlaps slot
                if ($bs < $slotEnd && $be > $t) { $isBusy = true; break; }
            }
            if (!$isBusy) $slots[] = date('Y-m-d\TH:i', $t);
            $t += $step;
        }
        $day = strtotime('+1 day', $day);
    }
    return $slots;
}
