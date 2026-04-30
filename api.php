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
    $tz        = $_GET['tz'] ?? 'UTC';
    if (!in_array($tz, timezone_identifiers_list())) $tz = 'UTC';

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

    $freeSlots = computeFreeSlots($from, $to, $workStart, $workEnd, $duration, $busy, $tz);
    $busySlots = computeBusySlots($from, $to, $workStart, $workEnd, $duration, $busy, $tz);
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
    $params = http_build_query([
        'timeMin'      => $from . 'T00:00:00Z',
        'timeMax'      => $to   . 'T23:59:59Z',
        'singleEvents' => 'true',
        'fields'       => 'items(summary,start,end,transparency,status)',
    ]);
    $resp = httpGet(
        "https://www.googleapis.com/calendar/v3/calendars/primary/events?$params",
        ["Authorization: Bearer " . $cal['access_token']]
    );
    $data = json_decode($resp ?: '{}', true);
    $out = [];
    foreach (($data['items'] ?? []) as $ev) {
        if (($ev['transparency'] ?? '') === 'transparent') continue;
        if (strtolower($ev['status'] ?? '') === 'cancelled') continue;
        $s = strtotime($ev['start']['dateTime'] ?? $ev['start']['date'] ?? '');
        $e = strtotime($ev['end']['dateTime']   ?? $ev['end']['date']   ?? '');
        if ($s && $e) $out[] = [$s, $e, $ev['summary'] ?? ''];
    }
    return $out;
}

// ── Microsoft busy fetch ──────────────────────────────────────────────────────
function fetchMicrosoftBusy(array $cal, string $from, string $to): array {
    if ($cal['token_expiry'] && $cal['token_expiry'] < time() + 60) refreshMicrosoftToken($cal);
    $params = http_build_query([
        'startDateTime' => $from . 'T00:00:00Z',
        'endDateTime'   => $to   . 'T23:59:59Z',
        '$select'       => 'subject,start,end,showAs',
    ]);
    $resp = httpGet(
        "https://graph.microsoft.com/v1.0/me/calendarView?$params",
        ["Authorization: Bearer " . $cal['access_token']]
    );
    $data = json_decode($resp ?: '{}', true);
    $out  = [];
    foreach (($data['value'] ?? []) as $ev) {
        $showAs = strtolower($ev['showAs'] ?? 'busy');
        if ($showAs === 'free' || $showAs === 'tentative' || $showAs === 'oof') continue;
        $s = strtotime($ev['start']['dateTime'] ?? '');
        $e = strtotime($ev['end']['dateTime']   ?? '');
        if ($s && $e) $out[] = [$s, $e, $ev['subject'] ?? ''];
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
        preg_match('/^SUMMARY:(.*)/m', $ical, $summ);
        $title = trim($summ[1] ?? '');
        if ($s && $e) $out[] = [$s, $e, $title];
    }
    return $out;
}

// ── Busy slot computation ─────────────────────────────────────────────────────
function computeBusySlots(
    string $from, string $to,
    string $workStart, string $workEnd,
    int $duration, array $busy, string $tz = 'UTC'
): array {
    $slots = [];
    $step  = $duration * 60;
    $tzObj = new DateTimeZone($tz);

    $day  = (new DateTime($from, $tzObj))->setTime(0,0)->getTimestamp();
    $last = (new DateTime($to,   $tzObj))->setTime(23,59)->getTimestamp();

    while ($day <= $last) {
        $d  = new DateTime('@'.$day); $d->setTimezone($tzObj);
        $ds = $d->format('Y-m-d');
        $start = (new DateTime("$ds $workStart", $tzObj))->getTimestamp();
        $end   = (new DateTime("$ds $workEnd",   $tzObj))->getTimestamp();
        $t = $start;
        while ($t + $step <= $end) {
            foreach ($busy as $b) {
                [$bs, $be] = $b;
                $title = $b[2] ?? '';
                if ($bs < ($t + $step) && $be > $t) {
                    $sd = new DateTime('@'.$t); $sd->setTimezone($tzObj);
                    $slots[] = ['dt' => $sd->format('Y-m-d\TH:i'), 'title' => $title];
                    break;
                }
            }
            $t += $step;
        }
        $day = (new DateTime($ds, $tzObj))->modify('+1 day')->getTimestamp();
    }
    return $slots;
}

// ── Free slot computation ─────────────────────────────────────────────────────
function computeFreeSlots(
    string $from, string $to,
    string $workStart, string $workEnd,
    int $duration, array $busy, string $tz = 'UTC'
): array {
    $slots = [];
    $step  = $duration * 60;
    $tzObj = new DateTimeZone($tz);

    $day  = (new DateTime($from, $tzObj))->setTime(0,0)->getTimestamp();
    $last = (new DateTime($to,   $tzObj))->setTime(23,59)->getTimestamp();

    while ($day <= $last) {
        $d  = new DateTime('@'.$day); $d->setTimezone($tzObj);
        $ds = $d->format('Y-m-d');
        $start = (new DateTime("$ds $workStart", $tzObj))->getTimestamp();
        $end   = (new DateTime("$ds $workEnd",   $tzObj))->getTimestamp();
        $t = $start;
        while ($t + $step <= $end) {
            $slotEnd = $t + $step;
            $isBusy  = false;
            foreach ($busy as $b) {
                [$bs, $be] = $b;
                if ($bs < $slotEnd && $be > $t) { $isBusy = true; break; }
            }
            if (!$isBusy) {
                $sd = new DateTime('@'.$t); $sd->setTimezone($tzObj);
                $slots[] = $sd->format('Y-m-d\TH:i');
            }
            $t += $step;
        }
        $day = (new DateTime($ds, $tzObj))->modify('+1 day')->getTimestamp();
    }
    return $slots;
}
