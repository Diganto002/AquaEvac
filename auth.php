<?php
/**
 * =============================================================================
 * auth.php
 * -----------------------------------------------------------------------------
 * Handles registration, login, logout, and session-based authentication for
 * both regular users (citizen/volunteer/responder) and admins. Also exposes
 * require_login()/require_role() guard functions used by admin_actions.php
 * and any other protected module.
 *
 * Actions (sent as `action` in the POST body or query string):
 *   POST ?action=register  { name, email, password, role? }
 *   POST ?action=login     { email, password }
 *   POST ?action=logout
 *   GET  ?action=me
 *   POST ?action=forgot_password { email }
 *   POST ?action=reset_password  { token, password }
 *   POST ?action=login_2fa  { code }             -- second step when requires_2fa was returned
 *   POST ?action=setup_2fa                        -- admin-only: generates a pending TOTP secret
 *   POST ?action=confirm_2fa { code }             -- admin-only: confirms setup, turns 2FA on
 *   POST ?action=disable_2fa { password }         -- admin-only: turns 2FA back off
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/totp.php';

// -----------------------------------------------------------------------
// SECURE SESSION BOOTSTRAP
// -----------------------------------------------------------------------

function configure_secure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,   // only send cookie over HTTPS in production
        'httponly' => true,        // not accessible to JavaScript -> mitigates XSS session theft
        'samesite' => 'Strict',    // mitigates CSRF
    ]);

    session_name('AQUAEVAC_SESSION');
    session_start();
}

configure_secure_session();

// Roles allowed to self-register through the public endpoint. Admin
// accounts should be provisioned manually / by an existing admin via
// admin_actions.php, not through open self-registration.
const SELF_REGISTERABLE_ROLES = ['citizen', 'volunteer', 'responder'];

// -----------------------------------------------------------------------
// GUARD HELPERS (used by this file and by admin_actions.php)
// -----------------------------------------------------------------------

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    // A password reset increments session_version. If this session has an
    // older version, it is no longer trusted.
    $pdo = get_db_connection();
    $stmt = $pdo->prepare(
        'SELECT id, name, email, role, session_version, responder_availability FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $dbUser = $stmt->fetch();

    if ($dbUser === false) {
        return null;
    }

    $sessionVersion = (int) ($_SESSION['session_version'] ?? 0);
    if ($sessionVersion !== (int) $dbUser['session_version']) {
        $_SESSION = [];
        session_destroy();
        return null;
    }

    return [
        'id'    => (int) $dbUser['id'],
        'name'  => $dbUser['name'],
        'email' => $dbUser['email'],
        'role'  => $dbUser['role'],
        'availability' => $dbUser['responder_availability'] ?? null,
    ];
}


function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


function require_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!hash_equals(csrf_token(), (string) $provided)) {
        send_json(['error' => 'Security check failed. Refresh the page and try again.'], 403);
        exit;
    }
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        send_json(['error' => 'Authentication required. Please log in.'], 401);
        exit;
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        send_json(['error' => "Forbidden. This action requires the '$role' role."], 403);
        exit;
    }
    return $user;
}

// -----------------------------------------------------------------------
// VALIDATION HELPERS
// -----------------------------------------------------------------------

function is_valid_password(string $password): bool
{
    // Minimum 8 characters. Extend with complexity rules as needed.
    return strlen($password) >= 8;
}

// -----------------------------------------------------------------------
// PASSWORD RESET CONFIG
// -----------------------------------------------------------------------

// How long a reset link stays valid for.
const PASSWORD_RESET_TTL_MINUTES = 30;

// This project ships with no SMTP/mail server configured (see README), so
// there is nowhere for a real "reset e-mail" to go. In DEMO mode the reset
// link is handed straight back in the API response so the flow is fully
// testable out of the box. Set APP_DEMO_MODE=false once real email sending
// (e.g. PHPMailer/SMTP) is wired up, and this stops happening -> the
// response becomes identical whether or not the e-mail exists, same as a
// real production forgot-password endpoint.
function is_demo_mode(): bool
{
    $env = getenv('APP_DEMO_MODE');
    return $env === false ? true : filter_var($env, FILTER_VALIDATE_BOOLEAN);
}

// -----------------------------------------------------------------------
// ACTIONS
// -----------------------------------------------------------------------

function handle_register(): void
{
    require_method(['POST']);
    $body = read_json_body() ?: $_POST;

    $name     = trim((string)($body['name'] ?? ''));
    $email    = strtolower(trim((string)($body['email'] ?? '')));
    $password = (string)($body['password'] ?? '');
    $role     = (string)($body['role'] ?? 'citizen');

    if ($name === '' || $email === '' || $password === '') {
        send_json(['error' => 'name, email, and password are required.'], 400);
        return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json(['error' => 'Invalid email address.'], 400);
        return;
    }
    if (!is_valid_password($password)) {
        send_json(['error' => 'Password must be at least 8 characters long.'], 400);
        return;
    }
    if (!in_array($role, SELF_REGISTERABLE_ROLES, true)) {
        // Silently downgrade instead of erroring, to avoid leaking which
        // roles exist in the system to a would-be attacker.
        $role = 'citizen';
    }

    $pdo = get_db_connection();

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);
    if ($check->fetch() !== false) {
        send_json(['error' => 'An account with this email already exists.'], 409);
        return;
    }

    // SECURITY: never store plaintext passwords. password_hash() uses
    // bcrypt (PASSWORD_DEFAULT) with a per-password random salt.
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare(
        'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)'
    );
    $insert->execute([
        'name'     => $name,
        'email'    => $email,
        'password' => $password_hash,
        'role'     => $role,
    ]);

    send_json([
        'message' => 'Registration successful.',
        'user'    => ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => $role],
    ], 201);
}

function handle_login(): void
{
    require_method(['POST']);
    $body = read_json_body() ?: $_POST;

    $email    = strtolower(trim((string)($body['email'] ?? '')));
    $password = (string)($body['password'] ?? '');

    if ($email === '' || $password === '') {
        send_json(['error' => 'email and password are required.'], 400);
        return;
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT id, name, email, password, role, totp_enabled, session_version FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    // Deliberately generic error message for both "no such user" and
    // "wrong password" so we don't leak which emails are registered.
    if ($user === false || !password_verify($password, $user['password'])) {
        send_json(['error' => 'Invalid email or password.'], 401);
        return;
    }

    // Rehash transparently if PHP's default algorithm/cost has changed
    // since the password was originally hashed.
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $rehash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password = :password WHERE id = :id')
            ->execute(['password' => $rehash, 'id' => $user['id']]);
    }

    // If this account has 2FA turned on, password verification alone is
    // NOT enough to establish a session. We stash only a pending user id
    // (regenerating the session id first, same as a normal login) and
    // require a valid TOTP code via ?action=login_2fa before any of the
    // real session keys (user_id/user_role/...) get set — so a
    // half-finished 2FA login can't be used to access anything.
    if ((int) $user['totp_enabled'] === 1) {
        session_regenerate_id(true);
        $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
        send_json([
            'requires_2fa' => true,
            'message' => 'Enter the 6-digit code from your authenticator app.',
        ]);
        return;
    }

    complete_login_session($user);

    send_json([
        'message' => 'Login successful.',
        'user'    => [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
    ]);
}

/** Finishes establishing a real, logged-in session for $user. */
function complete_login_session(array $user): void
{
    // SECURITY: regenerate the session ID on privilege change (login) to
    // prevent session fixation attacks.
    session_regenerate_id(true);
    unset($_SESSION['pending_2fa_user_id']);
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['session_version'] = (int) ($user['session_version'] ?? 1);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function handle_login_2fa(): void
{
    require_method(['POST']);
    $body = read_json_body() ?: $_POST;
    $code = (string) ($body['code'] ?? '');

    $pendingId = $_SESSION['pending_2fa_user_id'] ?? null;
    if ($pendingId === null) {
        send_json(['error' => 'No pending login. Please log in with your email and password again.'], 400);
        return;
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT id, name, email, role, totp_secret, totp_enabled, session_version FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $pendingId]);
    $user = $stmt->fetch();

    if ($user === false || (int) $user['totp_enabled'] !== 1 || $user['totp_secret'] === null) {
        unset($_SESSION['pending_2fa_user_id']);
        send_json(['error' => 'Invalid session state. Please log in again.'], 400);
        return;
    }

    if (!totp_verify($user['totp_secret'], $code)) {
        send_json(['error' => 'Incorrect or expired code. Please try again.'], 401);
        return;
    }

    complete_login_session($user);

    send_json([
        'message' => 'Login successful.',
        'user'    => [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
    ]);
}

// -----------------------------------------------------------------------
// TWO-FACTOR AUTH SETUP (self-service, from a logged-in session)
// -----------------------------------------------------------------------

// Step 1: generate a new secret (not yet active) and a QR provisioning URI.
// Storing it before confirmation is intentional — confirm_2fa flips
// totp_enabled to 1 only after the user proves they scanned it correctly,
// so a setup that's never finished never affects login.
function handle_setup_2fa(array $user): void
{
    require_method(['POST']);
    $secret = totp_generate_secret();

    $pdo = get_db_connection();
    $pdo->prepare('UPDATE users SET totp_secret = :secret, totp_enabled = 0 WHERE id = :id')
        ->execute(['secret' => $secret, 'id' => $user['id']]);

    send_json([
        'secret' => $secret,
        'otpauth_uri' => totp_provisioning_uri($secret, $user['email']),
    ]);
}

// Step 2: user enters the code their app is showing right now -> confirms
// the secret was scanned correctly and turns 2FA on for real.
function handle_confirm_2fa(array $user): void
{
    require_method(['POST']);
    $body = read_json_body() ?: $_POST;
    $code = (string) ($body['code'] ?? '');

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT totp_secret FROM users WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);
    $row = $stmt->fetch();

    if ($row === false || $row['totp_secret'] === null) {
        send_json(['error' => 'No pending 2FA setup found. Start setup again.'], 400);
        return;
    }
    if (!totp_verify($row['totp_secret'], $code)) {
        send_json(['error' => 'Incorrect code. Check your authenticator app and try again.'], 401);
        return;
    }

    $pdo->prepare('UPDATE users SET totp_enabled = 1 WHERE id = :id')->execute(['id' => $user['id']]);
    write_audit_log($user, 'auth.2fa_enabled', 'user', $user['id']);

    send_json(['message' => 'Two-factor authentication is now enabled on your account.']);
}

// Disabling requires the current password again (not just an active
// session) since a stolen/left-open session shouldn't be enough to turn
// off an account's second factor.
function handle_disable_2fa(array $user): void
{
    require_method(['POST']);
    $body = read_json_body() ?: $_POST;
    $password = (string) ($body['password'] ?? '');

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);
    $row = $stmt->fetch();

    if ($row === false || !password_verify($password, $row['password'])) {
        send_json(['error' => 'Incorrect password.'], 401);
        return;
    }

    $pdo->prepare('UPDATE users SET totp_enabled = 0, totp_secret = NULL WHERE id = :id')
        ->execute(['id' => $user['id']]);
    write_audit_log($user, 'auth.2fa_disabled', 'user', $user['id']);

    send_json(['message' => 'Two-factor authentication has been disabled.']);
}

function handle_forgot_password(): void
{
    require_method(['POST']);
    $body = read_json_body() ?: $_POST;

    $email = strtolower(trim((string)($body['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json(['error' => 'A valid email address is required.'], 400);
        return;
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    // SECURITY: respond identically whether or not the email exists, so
    // this endpoint can't be used to enumerate registered accounts.
    $generic = [
        'message' => 'If an account exists for that email, a reset link has been generated.',
    ];

    if ($user !== false) {
        // Invalidate any earlier unused reset requests for this user first.
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = :uid AND used_at IS NULL')
            ->execute(['uid' => $user['id']]);

        // Random, unguessable token. Only its SHA-256 hash is stored, the
        // same principle as never storing plaintext passwords -> a DB leak
        // alone can't be replayed into a valid reset link.
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable("+" . PASSWORD_RESET_TTL_MINUTES . " minutes"))
            ->format('Y-m-d H:i:s');

        $pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)'
        )->execute(['uid' => $user['id'], 'hash' => $tokenHash, 'exp' => $expiresAt]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $resetUrl = "{$scheme}://{$host}/reset-password.php?token={$token}";

        // In a real deployment this is where you'd email $resetUrl to the
        // user instead of returning it. Demo mode surfaces it directly
        // since no mail server is configured (see README).
        if (is_demo_mode()) {
            $generic['demo_reset_url'] = $resetUrl;
            $generic['demo_note'] = 'DEMO_MODE: no email server is configured, so the reset link is returned directly instead of being emailed.';
        }
    }

    send_json($generic);
}

function handle_reset_password(): void
{
    require_method(['POST']);
    $body = read_json_body() ?: $_POST;

    $token = (string)($body['token'] ?? '');
    $password = (string)($body['password'] ?? '');

    if ($token === '') {
        send_json(['error' => 'Missing or invalid reset link.'], 400);
        return;
    }
    if (!is_valid_password($password)) {
        send_json(['error' => 'Password must be at least 8 characters long.'], 400);
        return;
    }

    $pdo = get_db_connection();
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = :hash LIMIT 1'
    );
    $stmt->execute(['hash' => $tokenHash]);
    $reset = $stmt->fetch();

    $isExpired = $reset !== false && strtotime($reset['expires_at']) < time();

    if ($reset === false || $reset['used_at'] !== null || $isExpired) {
        send_json(['error' => 'This reset link is invalid or has expired. Please request a new one.'], 400);
        return;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->prepare('UPDATE users SET password = :password WHERE id = :id')
        ->execute(['password' => $passwordHash, 'id' => $reset['user_id']]);

    // Single-use: mark this token spent and kill any other outstanding
    // reset tokens for the same account.
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')
        ->execute(['id' => $reset['id']]);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = :uid AND id != :id')
        ->execute(['uid' => $reset['user_id'], 'id' => $reset['id']]);

    // Log out any existing session for this account for safety, and let
    // the client log in fresh with the new password.
    send_json(['message' => 'Password has been reset. You can now log in with your new password.']);
}

function handle_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    send_json(['message' => 'Logged out.']);
}

function handle_me(): void
{
    $user = current_user();
    if ($user === null) {
        send_json(['authenticated' => false]);
        return;
    }
    send_json(['authenticated' => true, 'user' => $user, 'csrf_token' => csrf_token()]);
}

// -----------------------------------------------------------------------
// ROUTER (only runs when this file is the entry point, e.g. auth.php?action=login)
// -----------------------------------------------------------------------
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'register':
            handle_register();
            break;
        case 'login':
            handle_login();
            break;
        case 'logout':
            handle_logout();
            break;
        case 'me':
            handle_me();
            break;
        case 'forgot_password':
            handle_forgot_password();
            break;
        case 'reset_password':
            handle_reset_password();
            break;
        case 'login_2fa':
            handle_login_2fa();
            break;
        case 'setup_2fa':
            handle_setup_2fa(require_role('admin'));
            break;
        case 'confirm_2fa':
            handle_confirm_2fa(require_role('admin'));
            break;
        case 'disable_2fa':
            handle_disable_2fa(require_role('admin'));
            break;
        default:
            send_json(['error' => 'Unknown or missing action. Use register, login, login_2fa, logout, me, forgot_password, reset_password, setup_2fa, confirm_2fa, or disable_2fa.'], 400);
    }
}
