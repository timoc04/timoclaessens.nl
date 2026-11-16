<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

function check(bool $condition, string $description): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$description}\n");
        exit(1);
    }
    echo "PASS: {$description}\n";
}

define('SIGNAGE_USERS', ['test' => password_hash('correct-password', PASSWORD_DEFAULT)]);
check(signage_authenticate('test', 'correct-password') === 'test', 'valid password');
check(signage_authenticate('test', 'incorrect') === null, 'invalid password');
check(signage_authenticate('missing', 'correct-password') === null, 'unknown user');

$_SESSION = [
    'logged_in' => true,
    'username' => 'test',
    'account_fingerprint' => hash('sha256', SIGNAGE_USERS['test']),
];
check(signage_session_valid(), 'valid account session');
$_SESSION['account_fingerprint'] = 'revoked';
check(!signage_session_valid(), 'changed password hash invalidates session');
$_SESSION['username'] = 'removed';
check(!signage_session_valid(), 'removed account invalidates session');

$directory = sys_get_temp_dir() . '/signage-tests-' . bin2hex(random_bytes(8));
define('SIGNAGE_SECURITY_DIR', $directory);
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
try {
    check(signage_rate_limit(false), 'new IP is allowed');
    for ($attempt = 1; $attempt <= 4; $attempt++) {
        check(signage_rate_limit(true), "failed attempt {$attempt} still allowed");
    }
    check(!signage_rate_limit(true), 'fifth failure locks IP');
    check(!signage_rate_limit(false), 'lock persists across checks');
    $_SERVER['REMOTE_ADDR'] = '192.0.2.11';
    check(signage_rate_limit(false), 'different IP is not locked');
} finally {
    foreach (glob($directory . '/*.json') ?: [] as $file) unlink($file);
    if (is_dir($directory)) rmdir($directory);
}
echo "All signage security tests passed.\n";
