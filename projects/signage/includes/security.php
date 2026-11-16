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
    header('X-Frame-Options: DENY');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\'; img-src \'self\' https://timoclaessens.nl data:; media-src \'self\'; font-src \'self\'; object-src \'none\'; base-uri \'self\'; form-action \'self\'; frame-ancestors \'none\'');
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
/**
 * Validate one of the named accounts in the private config.php.
 * SIGNAGE_USERS = ['username' => 'password_hash', ...].
 */
function signage_authenticate(string $username, string $password): ?string
{
    if (!defined('SIGNAGE_USERS') || !is_array(SIGNAGE_USERS)) return null;
    if ($username === '' || strlen($username) > 100) return null;
    $hash = SIGNAGE_USERS[$username] ?? null;
    if (!is_string($hash) || $hash === '' || !password_verify($password, $hash)) return null;
    return $username;
}

/** Delete all session state and the matching browser cookie. */
function signage_end_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

/** Bind a session to the current account hash, invalidating removed/changed accounts. */
function signage_session_valid(): bool
{
    if (($_SESSION['logged_in'] ?? false) !== true) return false;
    $username = $_SESSION['username'] ?? null;
    if (!is_string($username) || !defined('SIGNAGE_USERS') || !is_array(SIGNAGE_USERS)) return false;
    $hash = SIGNAGE_USERS[$username] ?? null;
    return is_string($hash) && $hash !== '' &&
        hash_equals(hash('sha256', $hash), (string)($_SESSION['account_fingerprint'] ?? ''));
}

/**
 * Cross-session login throttling. Store state outside the public web root.
 * The directory must be writable and inaccessible over HTTP.
 */
function signage_rate_limit_path(): string
{
    $directory = defined('SIGNAGE_SECURITY_DIR') ? SIGNAGE_SECURITY_DIR : sys_get_temp_dir() . '/signage-security';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Login beveiliging niet beschikbaar.');
    }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return rtrim($directory, '/\\') . '/login-' . hash('sha256', $ip) . '.json';
}
function signage_rate_limit(bool $failed): bool
{
    $path = signage_rate_limit_path();
    $handle = fopen($path, 'c+');
    if ($handle === false) throw new RuntimeException('Login beveiliging niet beschikbaar.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Login beveiliging niet beschikbaar.');
        $data = json_decode(stream_get_contents($handle) ?: '{}', true);
        if (!is_array($data)) $data = [];
        $now = time();
        if (($data['start'] ?? 0) + 900 <= $now) $data = ['start' => $now, 'failures' => 0, 'locked_until' => 0];
        if ($failed && ($data['locked_until'] ?? 0) <= $now) {
            $data['failures'] = (int)($data['failures'] ?? 0) + 1;
            if ($data['failures'] >= 5) $data['locked_until'] = $now + 900;
        }
        $allowed = ($data['locked_until'] ?? 0) <= $now;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
        return $allowed;
    } finally {
        fclose($handle);
    }
}
