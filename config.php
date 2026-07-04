<?php
// ── TEMP DEBUG: shows real errors instead of a blank 500 page ──────────────
// Remove this whole block once the site is working — never leave error
// display on for real visitors on a live/production domain.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
// ── END TEMP DEBUG ───────────────────────────────────────────────────────

ob_start();
// ── Session ───────────────────────────────────────────────────────────────
ini_set('session.gc_maxlifetime', 2592000);
ini_set('session.cookie_lifetime', 2592000);
// Speed: remove unnecessary headers
@header_remove('X-Powered-By');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(2592000, '/', '', false, true);
    session_start();
}

// ── Discord OAuth ─────────────────────────────────────────────────────────
define('DISCORD_CLIENT_ID',     '1504623685762416750');
define('DISCORD_CLIENT_SECRET', 'zuys2i4n4FlBI1wqwxSeDX3DwSA8DLOo');
define('DISCORD_REDIRECT_URI',  'https://blockstards.com/bs-auth/discord.php');
define('DISCORD_GUILD_ID',      '1501007433328234576');
define('SITE_URL',              'https://blockstards.com');

// ── Supabase ──────────────────────────────────────────────────────────────
define('SUPABASE_URL',     'https://pnvdyywmsxqbxpssjbdm.supabase.co');
define('SUPABASE_KEY',     'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InBudmR5eXdtc3hxYnhwc3NqYmRtIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4MzA3MzE3MCwiZXhwIjoyMDk4NjQ5MTcwfQ.BSZgzWxRSUl2fvyF72NKVlUCF4nbXTxE0II7KY9tm_I');

// ── Sorsa API (Twitter banner fetch) ────────────────────────────────────────
define('SORSA_API_KEY', '2d725456-9686-4ecf-9cff-a9e0c8f74041');

// ── Staff IDs ─────────────────────────────────────────────────────────────
$STAFF_IDS = ['1215142688026923071', '1407491473175478296'];

// ── Guilds ────────────────────────────────────────────────────────────────
define('BOT_GUILDS', [
    ['id' => '1501007433328234576', 'name' => 'Blockstards',     'icon' => null],
    ['id' => '1518171963028275260', 'name' => 'Blockstard test', 'icon' => null],
]);
define('GUILD_RAFFLE_CHANNELS', [
    '1501007433328234576' => '1506038514968694896',
    '1518171963028275260' => '1518171965037215835',
]);

// ── Supabase HTTP helper ──────────────────────────────────────────────────
function sb(string $table): SB { return new SB($table); }

class SB {
    private string $table;
    private array  $filters  = [];
    private array  $orFilters = [];
    private string $select   = '*';
    private ?string $orderCol = null;
    private bool    $orderAsc = true;
    private ?int    $limitN   = null;

    public function __construct(string $table) { $this->table = $table; }

    public function select(string $cols): self { $this->select = $cols; return $this; }

    public function eq(string $col, $val): self {
        $this->filters[] = urlencode($col) . '=eq.' . urlencode($val);
        return $this;
    }
    public function neq(string $col, $val): self {
        $this->filters[] = urlencode($col) . '=neq.' . urlencode($val);
        return $this;
    }
    public function ilike(string $col, $val): self {
        $this->filters[] = urlencode($col) . '=ilike.' . urlencode($val);
        return $this;
    }
    public function is(string $col, $val): self {
        $this->filters[] = urlencode($col) . '=is.' . ($val === null ? 'null' : urlencode($val));
        return $this;
    }
    public function order(string $col, bool $asc = true): self {
        $this->orderCol = $col; $this->orderAsc = $asc; return $this;
    }
    public function limit(int $n): self { $this->limitN = $n; return $this; }

    private function buildUrl(): string {
        $url = SUPABASE_URL . '/rest/v1/' . $this->table;
        $qs  = array_merge($this->filters, $this->orFilters);
        $qs[] = 'select=' . urlencode($this->select);
        if ($this->orderCol) $qs[] = 'order=' . $this->orderCol . '.' . ($this->orderAsc ? 'asc' : 'desc');
        if ($this->limitN)   $qs[] = 'limit=' . $this->limitN;
        return $url . '?' . implode('&', $qs);
    }

    private function curl(string $method, string $url, ?array $body = null) {
        $ch = curl_init($url);
        $headers = [
            'apikey: '        . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($raw, true);
        return ['data' => $data, 'code' => $code, 'error' => ($code >= 400 ? ($data['message'] ?? $raw) : null)];
    }

    // ── READ ──────────────────────────────────────────────────────────────
    public function get(): array {
        $r = $this->curl('GET', $this->buildUrl());
        return is_array($r['data']) ? $r['data'] : [];
    }
    public function first(): ?array {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    // ── WRITE ─────────────────────────────────────────────────────────────
    public function insert(array $row): array {
        unset($row['id']); // Let Supabase auto-generate primary key
        return $this->curl('POST', SUPABASE_URL . '/rest/v1/' . $this->table, $row);
    }
    public function upsert(array $row, string $onConflict = ''): array {
        $url = SUPABASE_URL . '/rest/v1/' . $this->table;
        if ($onConflict) $url .= '?on_conflict=' . urlencode($onConflict);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($row),
            CURLOPT_HTTPHEADER     => [
                'apikey: '        . SUPABASE_KEY,
                'Authorization: Bearer ' . SUPABASE_KEY,
                'Content-Type: application/json',
                'Prefer: resolution=merge-duplicates,return=representation',
            ],
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['data' => json_decode($raw, true), 'code' => $code];
    }
    public function update(array $data): array {
        return $this->curl('PATCH', $this->buildUrl(), $data);
    }
    public function delete(): array {
        return $this->curl('DELETE', $this->buildUrl());
    }
    public function rpc(string $fn, array $params = []): array {
        return $this->curl('POST', SUPABASE_URL . '/rest/v1/rpc/' . $fn, $params);
    }
}

// ── Twitter banner fetch ─────────────────────────────────────────────────────
function fetch_twitter_banner(string $twitter): string {
    if (!$twitter) return '';
    $handle = ltrim(preg_replace('/https?:\/\/(www\.)?(twitter|x)\.com\//i', '', $twitter), '@');
    $handle = explode('?', $handle)[0];
    if (!$handle) return '';
    $sorsa_key = defined('SORSA_API_KEY') ? SORSA_API_KEY : (getenv('SORSA_API_KEY') ?: '');
    $opts = ['http' => ['timeout' => 5, 'ignore_errors' => true,
        'header' => "ApiKey: $sorsa_key
Accept: application/json
"]];
    $raw = @file_get_contents("https://api.sorsa.io/v3/info?username=$handle", false, stream_context_create($opts));
    if ($raw) {
        $d = json_decode($raw, true) ?? [];
        foreach (['profile_banner_url','banner_url','banner','profile_background_image_url'] as $key) {
            $val = $d[$key] ?? '';
            if ($val && str_starts_with($val, 'http')) {
                if (str_contains($val, 'profile_banners') && !preg_match('/\/\d+x\d+$/', $val)) {
                    $val = rtrim($val, '/') . '/1500x500';
                }
                return $val;
            }
        }
        $img = $d['profile_image_url'] ?? '';
        if ($img) return str_replace('_normal', '_400x400', $img);
    }
    return "https://unavatar.io/twitter/$handle";
}

// ── Auth helpers ──────────────────────────────────────────────────────────
function get_user(): ?array {
    if (!isset($_SESSION['bs_user'])) return null;
    $cached = $_SESSION['bs_user'];
    // Refresh username/avatar from bs_users at most once every 60s per session,
    // so Discord profile changes (synced by the bot) show up without re-login.
    $lastCheck = $_SESSION['bs_user_refreshed_at'] ?? 0;
    if (time() - $lastCheck > 60) {
        try {
            $fresh = sb('bs_users')->eq('discord_id', $cached['discord_id'])->select('username,avatar,is_staff')->first();
            if ($fresh) {
                $cached['username'] = $fresh['username'] ?? $cached['username'];
                $cached['avatar']   = $fresh['avatar']   ?? $cached['avatar'];
                if (isset($fresh['is_staff'])) $cached['is_staff'] = $fresh['is_staff'];
                $_SESSION['bs_user'] = $cached;
            }
        } catch (Exception $e) {}
        $_SESSION['bs_user_refreshed_at'] = time();
    }
    return $cached;
}
function current_user(): ?array { return get_user(); }

function require_login(): void {
    if (!get_user()) { header('Location: /bs-auth/discord.php'); exit; }
}

function is_staff(): bool {
    global $STAFF_IDS;
    $u = get_user();
    if (!$u) return false;
    if (in_array($u['discord_id'], $STAFF_IDS)) return true;
    // Check bs_permissions in Supabase
    try {
        $row = sb('bs_permissions')
            ->eq('discord_id', $u['discord_id'])
            ->eq('guild_id', DISCORD_GUILD_ID)
            ->limit(1)->first();
        return $row !== null;
    } catch(Exception $e) { return false; }
}

function require_staff(): void {
    if (!is_staff()) { http_response_code(403); die('Access denied.'); }
}

function has_perm(string $perm_key): bool {
    global $STAFF_IDS;
    $u = get_user();
    if (!$u) return false;
    // Hardcoded staff always have all perms
    if (in_array($u['discord_id'], $STAFF_IDS)) return true;
    try {
        // Check for specific perm or 'owner' perm
        $row = sb('bs_permissions')
            ->eq('discord_id', $u['discord_id'])
            ->eq('guild_id', DISCORD_GUILD_ID)
            ->eq('perm_key', $perm_key)
            ->first();
        if ($row) return true;
        $owner = sb('bs_permissions')
            ->eq('discord_id', $u['discord_id'])
            ->eq('guild_id', DISCORD_GUILD_ID)
            ->eq('perm_key', 'owner')
            ->first();
        return $owner !== null;
    } catch(Exception $e) { return false; }
}

function require_perm(string $perm_key): void {
    if (!has_perm($perm_key)) {
        // Staff fallback
        if (is_staff()) return;
        header('Location: /'); exit;
    }
}

// ── Utility helpers ───────────────────────────────────────────────────────
function parseDuration(string $str): int {
    $str = strtolower(trim($str));
    if (preg_match('/^(\d+(?:\.\d+)?)\s*([mhd]?)$/', $str, $m)) {
        $val = (float)$m[1];
        switch ($m[2]) {
            case 'm': return (int)($val * 60);
            case 'd': return (int)($val * 86400);
            default:  return (int)($val * 3600);
        }
    }
    return (int)((float)$str * 3600) ?: 86400;
}

function time_left($end_date): string {
    if (!$end_date) return 'No end date';
    $diff = (is_numeric($end_date) ? (int)$end_date : strtotime($end_date)) - time();
    if ($diff <= 0) return 'Ended';
    $d = floor($diff/86400); $h = floor(($diff%86400)/3600); $m = floor(($diff%3600)/60);
    if ($d > 0) return "{$d}d {$h}h left";
    if ($h > 0) return "{$h}h {$m}m left";
    return "{$m}m left";
}
function time_until($d): string { return time_left($d); }

function get_username(string $discord_id): string {
    static $cache = [];
    if (!$discord_id) return '—';
    if (isset($cache[$discord_id])) return $cache[$discord_id];
    try {
        $r = sb('bs_users')->eq('discord_id', $discord_id)->select('username')->first();
        $cache[$discord_id] = $r ? $r['username'] : ('...' . substr($discord_id, -4));
    } catch(Exception $e) {
        $cache[$discord_id] = '...' . substr($discord_id, -4);
    }
    return $cache[$discord_id];
}

function get_avatar_url(string $discord_id, string $avatar = ''): string {
    if (!$avatar) {
        try {
            $r = sb('bs_users')->eq('discord_id', $discord_id)->select('avatar')->first();
            $avatar = $r['avatar'] ?? '';
        } catch(Exception $e) {}
    }
    if ($avatar) return "https://cdn.discordapp.com/avatars/{$discord_id}/{$avatar}.png";
    return "https://cdn.discordapp.com/embed/avatars/" . ((int)$discord_id % 5) . ".png";
}

function get_balance(string $discord_id, string $guild_id = DISCORD_GUILD_ID): float {
    try {
        $r = sb('bs_user_blox')
            ->eq('discord_id', $discord_id)
            ->eq('guild_id', $guild_id)
            ->select('balance')
            ->first();
        return $r ? (float)$r['balance'] : 0.0;
    } catch(Exception $e) { return 0.0; }
}

// ── Supabase PDO shim — db() function for legacy SQL calls ───────────────
class SupabasePDO {
    public function prepare(string $sql): SupabaseStmt { return new SupabaseStmt($sql); }
    public function query(string $sql): SupabaseResult { return (new SupabaseStmt($sql))->run(); }
    public function exec(string $sql): int { return 0; }
    public function lastInsertId(): string { return '0'; }
}

class SupabaseStmt {
    private string $sql;
    private array  $params = [];

    public function __construct(string $sql) { $this->sql = $sql; }
    public function execute(array $p = []): bool { $this->params = $p; return true; }
    public function fetchAll(int $m = 0): array { return $this->run()->rows; }
    public function fetch(int $m = 0): array|false { $r = $this->run()->rows; return $r[0] ?? false; }
    public function fetchColumn(int $c = 0): mixed {
        $rows = $this->run()->rows;
        if (empty($rows)) return 0;
        return array_values($rows[0])[$c] ?? 0;
    }

    public function run(): SupabaseResult {
        $sql = trim($this->sql);
        $up  = strtoupper($sql);
        try {
            if (str_starts_with($up, 'SELECT')) return new SupabaseResult($this->doSelect($sql));
            if (str_starts_with($up, 'INSERT')) { $this->doInsert($sql); }
            if (str_starts_with($up, 'UPDATE')) { $this->doUpdate($sql); }
            if (str_starts_with($up, 'DELETE')) { $this->doDelete($sql); }
        } catch (Exception $e) {
            error_log('SupabaseStmt error: ' . $e->getMessage() . ' SQL: ' . $sql);
        }
        return new SupabaseResult([]);
    }

    private function table(): string {
        if (preg_match('/\bFROM\s+`?(\w+)`?/i', $this->sql, $m)) return $m[1];
        if (preg_match('/\bINTO\s+`?(\w+)`?/i', $this->sql, $m)) return $m[1];
        if (preg_match('/\bUPDATE\s+`?(\w+)`?/i', $this->sql, $m)) return $m[1];
        if (preg_match('/\bDELETE\s+FROM\s+`?(\w+)`?/i', $this->sql, $m)) return $m[1];
        return '';
    }

    private function doSelect(string $sql): array {
        $t = $this->table();
        if (!$t) return [];
        $q = sb($t);
        // Parse WHERE col=? conditions
        $where = '';
        if (preg_match('/\bWHERE\b(.+?)(?:\bORDER\b|\bLIMIT\b|\bGROUP\b|$)/is', $sql, $wm)) {
            $where = $wm[1];
        }
        if ($where) {
            preg_match_all('/\b(\w+)\s+(LIKE|=)\s*\?/i', $where, $cm, PREG_SET_ORDER);
            foreach ($cm as $i => $match) {
                if (!isset($this->params[$i])) continue;
                $col = $match[1]; $op = strtoupper($match[2]); $val = $this->params[$i];
                if ($op === 'LIKE') { $val = str_replace('%', '*', $val); $q = $q->ilike($col, $val); }
                else                { $q = $q->eq($col, $val); }
            }
        }
        if (preg_match('/\bORDER\s+BY\s+(\w+)(?:\s+(ASC|DESC))?/i', $sql, $om)) {
            $q = $q->order($om[1], strtoupper($om[2] ?? 'ASC') === 'ASC');
        }
        if (preg_match('/\bLIMIT\s+(\d+)/i', $sql, $lm)) {
            $q = $q->limit((int)$lm[1]);
        }
        return $q->get() ?: [];
    }

    private function doInsert(string $sql): void {
        $t = $this->table();
        if (!$t) return;
        if (!preg_match('/\(([^)]+)\)\s*VALUES/i', $sql, $m)) return;
        $cols = array_map(fn($c) => trim($c, '` '), explode(',', $m[1]));
        if (count($cols) === count($this->params)) {
            sb($t)->insert(array_combine($cols, $this->params));
        }
    }

    private function doUpdate(string $sql): void {
        $t = $this->table();
        if (!$t) return;
        if (!preg_match('/\bSET\b(.+?)(?:\bWHERE\b|$)/is', $sql, $sm)) return;
        preg_match_all('/(\w+)\s*=\s*\?/i', $sm[1], $sc);
        $setCols = $sc[1];
        $wherePart = '';
        if (preg_match('/\bWHERE\b(.+)/is', $sql, $wm)) $wherePart = $wm[1];
        preg_match_all('/(\w+)\s*=\s*\?/i', $wherePart, $wc);
        $whereCols = $wc[1];
        $data = [];
        foreach ($setCols as $i => $c) $data[$c] = $this->params[$i] ?? null;
        $q = sb($t);
        foreach ($whereCols as $i => $c) {
            $q = $q->eq($c, $this->params[count($setCols) + $i] ?? null);
        }
        $q->update($data);
    }

    private function doDelete(string $sql): void {
        $t = $this->table();
        if (!$t) return;
        $q = sb($t);
        $wherePart = '';
        if (preg_match('/\bWHERE\b(.+)/is', $sql, $wm)) $wherePart = $wm[1];
        preg_match_all('/(\w+)\s*=\s*\?/i', $wherePart, $cm);
        foreach ($cm[1] as $i => $c) {
            if (isset($this->params[$i])) $q = $q->eq($c, $this->params[$i]);
        }
        $q->delete();
    }
}

class SupabaseResult {
    public array $rows;
    private int  $pos = 0;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetchAll(int $m = 0): array { return $this->rows; }
    public function fetch(int $m = 0): array|false { return $this->rows[$this->pos++] ?? false; }
    public function fetchColumn(int $c = 0): mixed {
        if (empty($this->rows)) return 0;
        return array_values($this->rows[0])[$c] ?? 0;
    }
    public function rowCount(): int { return count($this->rows); }
}

function db(): SupabasePDO { return new SupabasePDO(); }
function get_db(): SupabasePDO { return new SupabasePDO(); }