<?php
require_once __DIR__ . '/config.php';

// ── PDO-backed DB with a SQLite3-compatible method surface ───────────────────
// Keeps method names the rest of the codebase already uses:
//   getDB()->querySingle / escapeString / lastInsertRowID / query / exec / prepare
//   result->fetchArray(SQLITE3_ASSOC)
//   stmt->bindValue(pos, val) / execute() / reset()

if (!defined('SQLITE3_ASSOC')) define('SQLITE3_ASSOC', 2); // symbol used in call sites

class DB {
    public PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function exec(string $sql): int|false {
        return $this->pdo->exec($sql);
    }

    public function query(string $sql): DBResult {
        return new DBResult($this->pdo->query($sql));
    }

    public function prepare(string $sql): DBStmt {
        return new DBStmt($this->pdo->prepare($sql));
    }

    // SQLite3::querySingle($sql, $entireRow=false). entireRow=true → assoc row;
    // false → first column value (scalar).
    public function querySingle(string $sql, bool $entireRow = false): mixed {
        $stmt = $this->pdo->query($sql);
        if (!$stmt) return false;
        if ($entireRow) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: false;
        }
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : false;
    }

    // SQLite3::escapeString escapes internal single quotes (doubles them).
    // Callers then wrap the result in 'single quotes' themselves.
    public function escapeString(string $s): string {
        return str_replace("'", "''", $s);
    }

    public function lastInsertRowID(): int {
        return (int)$this->pdo->lastInsertId();
    }
}

class DBResult {
    private ?PDOStatement $stmt;
    public function __construct(?PDOStatement $stmt) { $this->stmt = $stmt; }
    public function fetchArray(int $_mode = SQLITE3_ASSOC): array|false {
        if (!$this->stmt) return false;
        $row = $this->stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }
}

class DBStmt {
    private PDOStatement $stmt;
    private array $bound = [];
    public function __construct(PDOStatement $stmt) { $this->stmt = $stmt; }
    public function bindValue(int|string $pos, mixed $val): void {
        $this->bound[$pos] = $val;
    }
    public function execute(): bool {
        ksort($this->bound);
        $ok = $this->stmt->execute(array_values($this->bound));
        return $ok;
    }
    public function reset(): bool { $this->bound = []; return true; }
}

function getDB(): DB {
    static $db = null;
    if ($db) return $db;
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_NAME
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db = new DB($pdo);
    return $db;
}

function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $db  = getDB();
    $uid = (int)$_SESSION['user_id'];
    $row = $db->querySingle("SELECT id,name,email FROM users WHERE id=$uid", true);
    return $row ?: null;
}

function requireLogin(): array {
    $u = currentUser();
    if (!$u) { header('Location: auth.php'); exit; }
    return $u;
}

function h(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $u): never { header("Location: $u"); exit; }
function genId(int $bytes = 8): string { return bin2hex(random_bytes($bytes)); }

function slugify(string $title): string {
    $s = mb_strtolower(trim($title));
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    $s = trim($s, '_');
    $s = substr($s, 0, 60);
    return $s ?: 'poll';
}

function uniqueSlug(string $title): string {
    $db   = getDB();
    $base = slugify($title);
    $slug = $base;
    $i    = 2;
    while ($db->querySingle("SELECT id FROM polls WHERE public_id='" . $db->escapeString($slug) . "'")) {
        $slug = $base . '_' . $i++;
    }
    return $slug;
}

function slotLabel(string $dt, int $dur): string {
    $ts = strtotime($dt);
    return date('D d M', $ts) . ' · ' . date('H:i', $ts) . '–' . date('H:i', $ts + $dur * 60);
}

function flash(string $key, string $msg = ''): string {
    if ($msg !== '') { $_SESSION['flash'][$key] = $msg; return ''; }
    $v = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $v;
}

function httpPost(string $url, array $data, array $headers = []): array {
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers)),
        'content' => http_build_query($data),
        'ignore_errors' => true,
    ]]);
    $body = file_get_contents($url, false, $ctx);
    return json_decode($body ?: '{}', true) ?? [];
}

function httpGet(string $url, array $headers = []): string {
    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'header'  => implode("\r\n", $headers),
        'ignore_errors' => true,
    ]]);
    return file_get_contents($url, false, $ctx) ?: '';
}
