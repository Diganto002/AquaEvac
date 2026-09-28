<?php
/**
 * =============================================================================
 * db_connect.php
 * -----------------------------------------------------------------------------
 * Secure PDO connection to the MySQL database (aquaevac_db).
 * Also doubles as the shared "bootstrap" include for the other PHP modules:
 * every other script does `require_once __DIR__ . '/db_connect.php';` to get
 * both the DB connection helper AND the small JSON/response utilities below.
 * =============================================================================
 */

declare(strict_types=1);

// -----------------------------------------------------------------------
// CONFIG
// Defaults are intentionally compatible with a standard XAMPP installation:
// MySQL on 127.0.0.1:3306 with the default root account and blank password.
// Environment variables can still override these values for other setups.
// -----------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'aquaevac_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');

// Base URL of the Python AI engine (Flask), used by api_proxy.php.
define('AI_API_BASE_URL', getenv('AI_API_BASE_URL') ?: 'http://127.0.0.1:5000');

// -----------------------------------------------------------------------
// DATABASE CONNECTION
// -----------------------------------------------------------------------

/**
 * Returns a singleton PDO connection.
 * - Uses prepared statements everywhere (PDO::ATTR_EMULATE_PREPARES = false)
 *   so real, server-side prepared statements are used -> protects against
 *   SQL injection.
 * - Throws exceptions on error instead of failing silently.
 * - Never leaks DB credentials/errors to the client; logs them server-side
 *   and returns a generic JSON error instead.
 */
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log('[db_connect] Database connection failed: ' . $e->getMessage());
        send_json(['error' => 'Database connection failed.'], 500);
        exit;
    }
}

// -----------------------------------------------------------------------
// SHARED RESPONSE / REQUEST HELPERS
// (kept here so every module that includes db_connect.php gets them too)
// -----------------------------------------------------------------------

/**
 * Send a JSON response with the given HTTP status code and stop further
 * output. Centralizing this avoids inconsistent headers/encoding across
 * modules.
 */
function send_json(array $payload, int $status_code = 200): void
{
    if (!headers_sent()) {
        http_response_code($status_code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}

/**
 * Reads and JSON-decodes the raw request body (used for POST/PUT JSON
 * APIs). Returns an empty array on invalid/empty input rather than
 * throwing, so callers can validate required fields themselves.
 */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Rejects the request (405) unless it uses one of the allowed HTTP methods.
 */
function require_method(array $allowed_methods): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowed_methods, true)) {
        send_json(['error' => 'Method not allowed. Allowed: ' . implode(', ', $allowed_methods)], 405);
        exit;
    }
}

// -----------------------------------------------------------------------
// AUDIT LOG
// Append-only trail of sensitive admin/responder actions (road blocks,
// shelter edits, SOS status overrides, admin logins) for accountability.
// Deliberately swallows its own failures (logged, never thrown) — an audit
// log write should never be the reason a real action fails.
// -----------------------------------------------------------------------
function write_audit_log(
    ?array $actor,
    string $action,
    ?string $targetType = null,
    ?int $targetId = null,
    array $details = []
): void {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (actor_user_id, actor_name, actor_role, action, target_type, target_id, details, ip_address)
             VALUES (:actor_user_id, :actor_name, :actor_role, :action, :target_type, :target_id, :details, :ip_address)'
        );
        $stmt->execute([
            'actor_user_id' => $actor['id'] ?? null,
            'actor_name'    => $actor['name'] ?? 'system',
            'actor_role'    => $actor['role'] ?? 'system',
            'action'        => $action,
            'target_type'   => $targetType,
            'target_id'     => $targetId,
            'details'       => $details !== [] ? json_encode($details, JSON_UNESCAPED_SLASHES) : null,
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (\Throwable $e) {
        error_log('[audit_log] failed to write entry: ' . $e->getMessage());
    }
}
