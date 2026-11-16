<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
signage_start_session();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    signage_verify_csrf();
    $password = (string)($_POST['password'] ?? '');

    if (signage_login_allowed() && signage_password_matches($password)) {
        session_regenerate_id(true);
        unset($_SESSION['login_failures'], $_SESSION['login_window_start'], $_SESSION['login_locked_until']);
        $_SESSION['logged_in'] = true;
        header('Location: ' . app_url('admin/'));
        exit;
    }

    if (signage_login_allowed()) signage_login_failed();
    $error = signage_login_allowed() ? 'Onjuist wachtwoord.' : 'Te veel pogingen. Probeer het over 15 minuten opnieuw.';
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen | Signage beheer</title>
    <link rel="icon" type="image/svg+xml" href="https://timoclaessens.nl/assets/icons/favicon.svg?v=f4139c4">
    <link rel="apple-touch-icon" sizes="180x180" href="https://timoclaessens.nl/assets/icons/apple-touch-icon.png?v=ed1fe7e">
    <link rel="manifest" href="/admin/admin.webmanifest">
    <link rel="stylesheet" href="admin.css?v=33ea597">
</head>
<body>
<main class="login-layout">
    <div class="login-shell">
        <a class="brand login-brand" href="../../../index.html" aria-label="Timo Claessens homepage">
            <img class="monogram" src="https://timoclaessens.nl/assets/icons/favicon.svg?v=f4139c4" alt="tc. logo" width="38" height="38">
            <span>Timo Claessens <span aria-hidden="true">/</span> Signage</span>
        </a>
        <section class="login-card" aria-labelledby="login-title">
            <p class="eyebrow">Signage / Administration</p>
            <h1 id="login-title">Welkom <em>terug.</em></h1>
            <p>Log in om schermen, media en weergavetijden te beheren.</p>
            <?php if ($error): ?>
                <div class="error" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(signage_csrf_token(), ENT_QUOTES); ?>">
                <label for="password">Wachtwoord</label>
                <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Voer je wachtwoord in" required>
                <button type="submit">Inloggen</button>
            </form>
        </section>
        <p class="login-footer">TimoClaessens.nl Signage</p>
    </div>
</main>
</body>
</html>