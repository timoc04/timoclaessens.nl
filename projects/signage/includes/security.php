<?php
/** Shared security controls for the signage administration. */
function signage_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $secure = true;
    } else {
        $secure = (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('signage_admin');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
function signage_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function signage_verify_csrf(): void
{
    if (!is_string($_POST['csrf_token'] ?? null) ||
        !hash_equals(signage_csrf_token(), $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Ongeldige sessiecontrole. Vernieuw de pagina en probeer opnieuw.');
    }
}
function signage_login_allowed(): bool
{
    return (int)($_SESSION['login_locked_until'] ?? 0) <= time();
}
function signage_login_failed(): void
{
    $now = time();
    if ($now - (int)($_SESSION['login_window_start'] ?? 0) > 900) {
        $_SESSION['login_window_start'] = $now;
        $_SESSION['login_failures'] = 0;
    }
    $_SESSION['login_failures'] = (int)($_SESSION['login_failures'] ?? 0) + 1;
    if ($_SESSION['login_failures'] >= 5) $_SESSION['login_locked_until'] = $now + 900;
}
function signage_password_matches(string $password): bool
{
    if (defined('ADMIN_PASSWORD_HASH') && ADMIN_PASSWORD_HASH !== '') {
        return password_verify($password, ADMIN_PASSWORD_HASH);
    }
    // Backwards-compatible migration: remove ADMIN_PASSWORD after configuring a hash.
    return defined('ADMIN_PASSWORD') && is_string(ADMIN_PASSWORD) &&
        ADMIN_PASSWORD !== '' && hash_equals(ADMIN_PASSWORD, $password);
}
