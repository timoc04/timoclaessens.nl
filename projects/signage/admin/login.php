<?php
session_start();
require_once __DIR__ . '/../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if ($password === ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        header('Location: ' . app_url('admin/'));
        exit;
    }

    $error = 'Onjuist wachtwoord.';
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen | Signage beheer</title>
    <link rel="icon" type="image/svg+xml" href="../../../assets/icons/favicon.svg">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<main class="login-layout">
    <div class="login-shell">
        <a class="brand login-brand" href="../../../index.html" aria-label="Timo Claessens homepage">
            <img class="monogram" src="../../../assets/icons/favicon.svg" alt="tc. logo" width="38" height="38">
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