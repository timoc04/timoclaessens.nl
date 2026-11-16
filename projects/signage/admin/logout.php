<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
signage_start_session();

// Only authenticated, CSRF-protected POST requests may log out.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Gebruik de uitlogknop in het beheerpaneel.');
}
if (!signage_session_valid()) {
    signage_end_session();
    header('Location: ' . app_url('admin/login.php'));
    exit;
}
signage_verify_csrf();
signage_end_session();
$timedOut = false;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $timedOut ? 'Sessie verlopen' : 'Uitgelogd'; ?> | Signage beheer</title>
    <link rel="icon" type="image/svg+xml" href="https://timoclaessens.nl/assets/icons/favicon.svg?v=f4139c4">
    <link rel="apple-touch-icon" sizes="180x180" href="https://timoclaessens.nl/assets/icons/apple-touch-icon.png?v=ed1fe7e">
    <link rel="manifest" href="/admin/admin.webmanifest">
    <link rel="stylesheet" href="admin.css?v=33ea597">
</head>
<body>
<main class="login-layout">
    <div class="login-shell">
        <a class="brand login-brand" href="https://timoclaessens.nl/" aria-label="Timo Claessens homepage">
            <img class="monogram" src="https://timoclaessens.nl/assets/icons/favicon.svg?v=f4139c4" alt="tc. logo" width="38" height="38">
            <span>Timo Claessens <span aria-hidden="true">/</span> Signage</span>
        </a>
        <section class="login-card" aria-labelledby="logout-title">
            <p class="eyebrow">Signage / Administration</p>
            <?php if ($timedOut): ?>
                <h1 id="logout-title">Sessie <em>verlopen.</em></h1>
                <p>Je bent automatisch uitgelogd vanwege inactiviteit. Log opnieuw in om verder te gaan.</p>
            <?php else: ?>
                <h1 id="logout-title">Tot <em>ziens.</em></h1>
                <p>Je bent succesvol uitgelogd uit het signage beheerpaneel.</p>
            <?php endif; ?>
            <a class="button" href="<?php echo htmlspecialchars(app_url('admin/login.php'), ENT_QUOTES, 'UTF-8'); ?>">Opnieuw inloggen</a>
        </section>
        <p class="login-footer">TimoClaessens.nl Signage</p>
    </div>
</main>
</body>
</html>
