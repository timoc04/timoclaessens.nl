<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
signage_start_session();

$timeout = SESSION_TIMEOUT;

if (isset($_SESSION['last_activity'])) {
    $inactiveTime = time() - $_SESSION['last_activity'];

    if ($inactiveTime > $timeout) {
        signage_end_session();

        header('Location: ' . app_url('admin/login.php?timeout=1'));
        exit;
    }
}

$_SESSION['last_activity'] = time();

if (!signage_session_valid()) {
    signage_end_session();
    header('Location: ' . app_url('admin/login.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') signage_verify_csrf();

$message = '';

function sanitizeScreenName(string $screen): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $screen);
}

function getScreens(): array
{
    if (!is_dir(SCREENS_DIR)) {
        mkdir(SCREENS_DIR, 0755, true);
    }

    $screens = [];

    foreach (scandir(SCREENS_DIR) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        if (is_dir(SCREENS_DIR . '/' . $item)) {
            $screens[] = $item;
        }
    }

    sort($screens);

    return $screens;
}

function deleteDirectory(string $dir): void
{
    if (is_link($dir)) {
        throw new RuntimeException('Symbolische links mogen niet worden verwijderd.');
    }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_link($path)) {
            throw new RuntimeException('Symbolische link aangetroffen.');
        }
        if (is_dir($path)) {
            deleteDirectory($path);
        } elseif (!unlink($path)) {
            throw new RuntimeException('Bestand verwijderen mislukt.');
        }
    }
    if (!rmdir($dir)) throw new RuntimeException('Map verwijderen mislukt.');
}

function loadSettings(string $settingsFile): array
{
    if (!file_exists($settingsFile)) {
        return [];
    }

    $json = file_get_contents($settingsFile);
    $settings = json_decode($json, true);

    return is_array($settings) ? $settings : [];
}

function saveSettings(string $settingsFile, array $settings): void
{
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (file_put_contents($settingsFile, $json, LOCK_EX) === false) {
        throw new RuntimeException('Instellingen opslaan mislukt.');
    }
}

function isImageFile(string $file): bool
{
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
}

/**
 * Create screen.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['new_screen'])
) {
    $newScreen = sanitizeScreenName(trim($_POST['new_screen']));

    if ($newScreen === '') {
        $message = 'Schermnaam mag niet leeg zijn.';
    } else {
        $screenDir = SCREENS_DIR . '/' . $newScreen;
        $mediaDir = $screenDir . '/media';
        $settingsFile = $screenDir . '/settings.json';

        if (is_dir($screenDir)) {
            $message = 'Dit scherm bestaat al.';
        } else {
            mkdir($mediaDir, 0755, true);
            file_put_contents($settingsFile, '{}');

            $message = 'Scherm aangemaakt.';
        }
    }
}

/**
 * Delete screen.
 * The main screen may never be deleted.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_screen'])
) {
    $screenToDelete = sanitizeScreenName($_POST['delete_screen']);
    $availableScreens = getScreens();

    if ($screenToDelete === 'main') {
        $message = 'Het hoofdscherm main kan niet verwijderd worden.';
    } elseif (!in_array($screenToDelete, $availableScreens, true)) {
        $message = 'Scherm bestaat niet.';
    } elseif (count($availableScreens) <= 1) {
        $message = 'Je kunt het laatste scherm niet verwijderen.';
    } else {
        try {
            deleteDirectory(SCREENS_DIR . '/' . $screenToDelete);
            $message = 'Scherm verwijderd.';
        } catch (RuntimeException $exception) {
            $message = 'Scherm kon niet volledig worden verwijderd.';
        }
    }
}

$screens = getScreens();

if (empty($screens)) {
    $mainDir = SCREENS_DIR . '/main/media';

    mkdir($mainDir, 0755, true);
    file_put_contents(SCREENS_DIR . '/main/settings.json', '{}');

    $screens = ['main'];
}

$currentScreen = sanitizeScreenName($_GET['screen'] ?? $screens[0]);

if (!in_array($currentScreen, $screens, true)) {
    $currentScreen = 'main';

    if (!in_array('main', $screens, true)) {
        $currentScreen = $screens[0];
    }
}

$screenDir = SCREENS_DIR . '/' . $currentScreen;
$mediaDir = $screenDir . '/media';
$settingsFile = $screenDir . '/settings.json';
$mediaUrl = app_url('screens/' . $currentScreen . '/media');

if (!is_dir($mediaDir)) {
    mkdir($mediaDir, 0755, true);
}

if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, '{}');
}

$settings = loadSettings($settingsFile);

/**
 * Upload media file.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media'])) {
    $file = $_FILES['media'];
    if (!is_array($file) || !isset($file['name'], $file['tmp_name'], $file['size'], $file['error']) || !is_string($file['name'])) {
        http_response_code(400);
        exit('Ongeldige upload.');
    }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
        $message = 'Bestandstype niet toegestaan.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Upload mislukt.';
    } else {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        $allowedMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'mp4' => 'video/mp4'];
        $mime = is_uploaded_file($file['tmp_name'])
            ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : false;
        $maxBytes = 100 * 1024 * 1024;
        if (!is_uploaded_file($file['tmp_name']) || $safeName === '' || $safeName[0] === '.' ||
            $file['size'] > $maxBytes || $file['size'] <= 0 ||
            !isset($allowedMime[$extension]) || $mime !== $allowedMime[$extension]) {
            $message = 'Ongeldig bestand of bestand groter dan 100 MB.';
        } elseif (file_exists($mediaDir . '/' . $safeName)) {
            $message = 'Er bestaat al een bestand met deze naam.';
        } elseif (!move_uploaded_file($file['tmp_name'], $mediaDir . '/' . $safeName)) {
            $message = 'Opslaan van upload mislukt.';
        } else {
        if (isImageFile($safeName)) {
            $settings[$safeName]['duration'] = IMAGE_DEFAULT_DURATION;
            saveSettings($settingsFile, $settings);
        }

        $message = 'Bestand geüpload.';
        }
    }
}

/**
 * Update image duration.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['duration_file'])
    && isset($_POST['duration_seconds'])
) {
    $fileName = basename($_POST['duration_file']);
    $duration = (int) $_POST['duration_seconds'];

    if ($duration < 1 || $duration > 86400) {
        $message = 'Weergavetijd moet minimaal 1 seconde zijn.';
    } elseif (!is_file($mediaDir . '/' . $fileName) || is_link($mediaDir . '/' . $fileName)) {
        $message = 'Bestand bestaat niet.';
    } elseif (!isImageFile($fileName)) {
        $message = 'Weergavetijd kan alleen bij afbeeldingen worden aangepast.';
    } else {
        $settings[$fileName]['duration'] = $duration;
        saveSettings($settingsFile, $settings);

        $message = 'Weergavetijd opgeslagen.';
    }
}

/**
 * Rename media file.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['rename_old'])
    && isset($_POST['rename_new'])
) {
    $oldName = basename($_POST['rename_old']);
    $newName = trim(basename($_POST['rename_new']));

    $oldPath = $mediaDir . '/' . $oldName;
    $oldExtension = strtolower(pathinfo($oldName, PATHINFO_EXTENSION));

    if ($newName === '') {
        $message = 'Nieuwe naam mag niet leeg zijn.';
    } elseif (!is_file($oldPath) || is_link($oldPath)) {
        $message = 'Oorspronkelijk bestand bestaat niet.';
    } else {
        if (!str_contains($newName, '.')) {
            $newName .= '.' . $oldExtension;
        }

        $newExtension = strtolower(pathinfo($newName, PATHINFO_EXTENSION));

        if ($newExtension !== $oldExtension) {
            $message = 'Bestandsextensie mag niet worden gewijzigd.';
        } elseif (!in_array($newExtension, ALLOWED_EXTENSIONS, true)) {
            $message = 'Nieuwe bestandstype is niet toegestaan.';
        } else {
            $safeNewName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($newName));
            $newPath = $mediaDir . '/' . $safeNewName;

            if ($safeNewName === '' || $safeNewName[0] === '.' || file_exists($newPath)) {
                $message = 'Er bestaat al een bestand met deze naam.';
            } else {
                if (!rename($oldPath, $newPath)) {
                    $message = 'Hernoemen mislukt.';
                } else {
                if (isset($settings[$oldName])) {
                    $settings[$safeNewName] = $settings[$oldName];
                    unset($settings[$oldName]);
                    saveSettings($settingsFile, $settings);
                }

                $message = 'Bestand hernoemd.';
                }
            }
        }
    }
}

/**
 * Delete media file.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $fileToDelete = basename((string)$_POST['delete_file']);
    $path = $mediaDir . '/' . $fileToDelete;

    if (is_file($path) && !is_link($path)) {
        if (!unlink($path)) {
            $message = 'Verwijderen mislukt.';
        } else {
        unset($settings[$fileToDelete]);
        saveSettings($settingsFile, $settings);

        $message = 'Bestand verwijderd.';
        }
    }
}

/**
 * Get media files.
 */
$files = [];

foreach (scandir($mediaDir) as $file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (is_file($mediaDir . '/' . $file) && !is_link($mediaDir . '/' . $file) && in_array($extension, ALLOWED_EXTENSIONS, true)) {
        $files[] = $file;
    }
}

sort($files);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Signage Admin</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="https://timoclaessens.nl/assets/icons/favicon.svg?v=f4139c4">
    <link rel="apple-touch-icon" sizes="180x180" href="https://timoclaessens.nl/assets/icons/apple-touch-icon.png?v=ed1fe7e">
    <link rel="manifest" href="/admin/admin.webmanifest">
    <link rel="stylesheet" href="admin.css?v=33ea597">

</head>

<body>

<header class="site-header">
    <div class="header-inner">
        <div class="topbar">
            <a class="brand" href="../../../index.html" aria-label="Timo Claessens homepage">
                <img class="monogram" src="https://timoclaessens.nl/assets/icons/favicon.svg?v=f4139c4" alt="tc. logo" width="38" height="38">
                <span>Timo Claessens <span aria-hidden="true">/</span> Signage</span>
            </a>
            <nav class="top-links" aria-label="Beheeracties">
                <a class="button" href="<?php echo htmlspecialchars(app_url($currentScreen)); ?>" target="_blank" rel="noopener noreferrer">Open huidig scherm ↗</a>
                <form method="POST" action="<?php echo htmlspecialchars(app_url('admin/logout.php'), ENT_QUOTES); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(signage_csrf_token(), ENT_QUOTES); ?>">
                    <button class="button secondary" type="submit">Uitloggen</button>
                </form>
            </nav>
        </div>
        <div class="intro">
            <p class="eyebrow">Signage / Administration</p>
            <h1>Signage <em>beheer.</em></h1>
            <p>Beheer meerdere signage schermen, media en weergavetijden.</p>
        </div>
    </div>
</header>

<main>
    <?php if ($message): ?>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-heading"><h2>Schermen</h2><p class="card-description">Selecteer een scherm of beheer de beschikbare schermen.</p></div>

        <div class="screen-actions">
            <form method="GET">
                <label>Scherm kiezen</label>

                <select name="screen" onchange="this.form.submit()">
                    <?php foreach ($screens as $screen): ?>
                        <option
                            value="<?php echo htmlspecialchars($screen); ?>"
                            <?php echo $screen === $currentScreen ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($screen); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(signage_csrf_token(), ENT_QUOTES); ?>">
                <label>Nieuw scherm aanmaken</label>

                <input
                    type="text"
                    name="new_screen"
                    placeholder="Bijvoorbeeld: scherm1, bar, entree"
                    required
                >

                <button type="submit">
                    Scherm aanmaken
                </button>
            </form>

            <form
                method="POST"
                onsubmit="return confirm('Weet je zeker dat je dit scherm inclusief alle media wilt verwijderen?');"
            >
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(signage_csrf_token(), ENT_QUOTES); ?>">
                <label>Huidig scherm verwijderen</label>

                <input
                    type="hidden"
                    name="delete_screen"
                    value="<?php echo htmlspecialchars($currentScreen); ?>"
                >

                <button
                    class="delete-screen-button"
                    type="submit"
                    <?php echo $currentScreen === 'main' ? 'disabled' : ''; ?>
                >
                    Scherm verwijderen
                </button>

                <?php if ($currentScreen === 'main'): ?>
                    <div class="small-text">
                        Het hoofdscherm main kan niet verwijderd worden.
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <p class="small-text player-url">
            Player URL huidig scherm:
            <strong>
                <?php echo htmlspecialchars(app_full_url($currentScreen)); ?>
            </strong>
        </p>
    </section>

    <section class="card">
        <h2>
            Media uploaden voor:
            <?php echo htmlspecialchars($currentScreen); ?>
        </h2>

        <form class="upload-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(signage_csrf_token(), ENT_QUOTES); ?>">
            <input
                type="file"
                name="media"
                accept=".jpg,.jpeg,.png,.webp,.mp4"
                required
            >

            <button type="submit">
                Uploaden
            </button>
        </form>
    </section>

    <section class="card">
        <div class="card-heading"><h2>Huidige media</h2><p class="card-description">Beheer bestanden en pas de weergavetijd van afbeeldingen aan.</p></div>

        <?php if (empty($files)): ?>
            <p class="empty">
                Er is nog geen media geüpload voor dit scherm.
            </p>
        <?php else: ?>
            <div class="media-grid">
                <?php foreach ($files as $file): ?>
                    <?php
                        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $url = $mediaUrl . '/' . rawurlencode($file);
                        $isImage = isImageFile($file);
                        $duration = $settings[$file]['duration'] ?? IMAGE_DEFAULT_DURATION;
                    ?>

                    <div class="media-item">
                        <div class="preview">
                            <?php if ($extension === 'mp4'): ?>
                                <video src="<?php echo htmlspecialchars($url); ?>" muted></video>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($url); ?>" alt="">
                            <?php endif; ?>
                        </div>

                        <div class="file-info">
                            <div class="filename">
                                <?php echo htmlspecialchars($file); ?>
                            </div>

                            <?php if ($isImage): ?>
                                <form class="rename-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(signage_csrf_token(), ENT_QUOTES); ?>">
                                    <input
                                        type="hidden"
                                        name="duration_file"
                                        value="<?php echo htmlspecialchars($file); ?>"
                                    >

                                    <input
                                        type="number"
                                        name="duration_seconds"
                                        min="1"
                                        value="<?php echo htmlspecialchars((string) $duration); ?>"
                                        required
                                    >

                                    <button class="rename-button" type="submit">
                                        Weergavetijd opslaan
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="small-text">
                                    Video wordt volledig afgespeeld.
                                </div>
                            <?php endif; ?>

                            <form class="rename-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(signage_csrf_token(), ENT_QUOTES); ?>">
                                <input
                                    type="hidden"
                                    name="rename_old"
                                    value="<?php echo htmlspecialchars($file); ?>"
                                >

                                <input
                                    type="text"
                                    name="rename_new"
                                    placeholder="Nieuwe naam"
                                    required
                                >

                                <button class="rename-button" type="submit">
                                    Hernoemen
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirm('Weet je zeker dat je dit bestand wilt verwijderen?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(signage_csrf_token(), ENT_QUOTES); ?>">
                                <input type="hidden" name="delete_file" value="<?php echo htmlspecialchars($file, ENT_QUOTES); ?>">
                                <button class="delete" type="submit">Verwijderen</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
const timeoutSeconds = <?php echo SESSION_TIMEOUT; ?>;

setTimeout(function () {
    window.location.href = "<?php echo htmlspecialchars(app_url('admin/')); ?>";
}, timeoutSeconds * 1000);
</script>

</body>
</html>
