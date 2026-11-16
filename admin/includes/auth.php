<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content.php';

function cms_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('portfolio_cms');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/admin', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' https://timoclaessens.nl; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

function cms_csrf_token(): string
{
    if (!isset($_SESSION['cms_csrf'])) {
        $_SESSION['cms_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cms_csrf'];
}

function cms_check_csrf(): void
{
    $posted = $_POST['csrf_token'] ?? null;
    if (!is_string($posted) || !hash_equals(cms_csrf_token(), $posted)) {
        http_response_code(403);
        exit('Invalid form token. Reload the page and try again.');
    }
}

function cms_authenticated(): bool
{
    $username = $_SESSION['cms_username'] ?? null;
    if (!is_string($username) || !isset(CMS_USERS[$username])) return false;
    if ((int)($_SESSION['cms_last_activity'] ?? 0) + 1800 < time()) return false;
    $hash = CMS_USERS[$username];
    return is_string($hash) && hash_equals(
        hash('sha256', $hash),
        (string)($_SESSION['cms_account_fingerprint'] ?? '')
    );
}

function cms_require_login(): void
{
    if (!cms_authenticated()) {
        header('Location: /admin/login.php', true, 302);
        exit;
    }
    $_SESSION['cms_last_activity'] = time();
}

function cms_authenticate(string $username, string $password): bool
{
    $hash = CMS_USERS[$username] ?? null;
    return $username !== '' && strlen($username) <= 100
        && is_string($hash) && password_verify($password, $hash);
}

/** IP-based limit independent of session cookies; fail closed when unavailable. */
function cms_login_allowed(bool $failed): bool
{
    $directory = cms_storage_directory();
    $path = $directory . '/login-' . hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')) . '.json';
    $handle = fopen($path, 'c+');
    if ($handle === false) throw new RuntimeException('CMS login limit is unavailable.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('CMS login limit is unavailable.');
        $raw = stream_get_contents($handle);
        $state = json_decode($raw ?: '{}', true);
        if (!is_array($state)) $state = [];
        $now = time();
        if ((int)($state['start'] ?? 0) + 900 <= $now) {
            $state = ['start' => $now, 'failures' => 0, 'locked_until' => 0];
        }
        if ($failed && (int)($state['locked_until'] ?? 0) <= $now) {
            $state['failures'] = (int)($state['failures'] ?? 0) + 1;
            if ($state['failures'] >= 5) $state['locked_until'] = $now + 900;
        }
        $allowed = (int)($state['locked_until'] ?? 0) <= $now;
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR)) === false ||
            !fflush($handle)) {
            throw new RuntimeException('CMS login limit could not be stored.');
        }
        return $allowed;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function cms_logout(): void
{
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600, 'path' => $cookie['path'],
        'domain' => $cookie['domain'], 'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'], 'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);
    session_destroy();
}

function cms_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
