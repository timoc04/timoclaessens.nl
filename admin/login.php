<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
cms_start_session();
if (cms_authenticated()) {
    header('Location: /admin/', true, 302);
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cms_check_csrf();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!is_string($username) || !is_string($password)) {
        http_response_code(400);
        $error = 'Invalid login details.';
    } else {
        try {
            if (!cms_login_allowed(false)) {
                http_response_code(429);
                $error = 'Too many attempts. Try again in 15 minutes.';
            } elseif (!cms_authenticate($username, $password)) {
                cms_login_allowed(true);
                $error = 'Incorrect username or password.';
            } else {
                session_regenerate_id(true);
                $_SESSION['cms_username'] = $username;
                $_SESSION['cms_account_fingerprint'] = hash('sha256', CMS_USERS[$username]);
                $_SESSION['cms_last_activity'] = time();
                header('Location: /admin/', true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Portfolio CMS authentication error: ' . $exception->getMessage());
            http_response_code(503);
            $error = 'The login service is temporarily unavailable.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Sign in | Portfolio CMS</title>
<link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
<main class="login-shell">
<section class="panel login-panel">
<div class="eyebrow">TIMO CLAESSENS / CMS</div>
<h1>Welcome <em>back.</em></h1>
<p class="muted">Sign in to manage content on your portfolio.</p>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= cms_escape($error) ?></div><?php endif; ?>
<form method="post" action="/admin/login.php">
<input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>">
<label for="username">Username</label>
<input id="username" name="username" type="text" required autocomplete="username" maxlength="100">
<label for="password">Password</label>
<input id="password" name="password" type="password" required autocomplete="current-password">
<button class="button primary" type="submit">Sign in ↗</button>
</form>
<a class="return-link" href="/">← Back to website</a>
</section>
</main>
</body>
</html>
