<?php

header('Cache-Control: public, max-age=86400');

require_once __DIR__ . '/config.php';

function sanitizeScreenName(string $screen): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $screen);
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

$requestedScreen = $_GET['screen'] ?? 'main';
$screen = sanitizeScreenName(is_string($requestedScreen) ? $requestedScreen : 'main');
if ($screen === '') $screen = 'main';

$mediaDir = SCREENS_DIR . '/' . $screen . '/media';
$settingsFile = SCREENS_DIR . '/' . $screen . '/settings.json';
$mediaUrl = app_url('screens/' . $screen . '/media');

$files = [];

if (is_dir($mediaDir)) {
    $settings = loadSettings($settingsFile);

    foreach (scandir($mediaDir) ?: [] as $file) {
        $path = $mediaDir . '/' . $file;
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!is_file($path) || is_link($path) || !in_array($extension, ALLOWED_EXTENSIONS, true)) continue;

        $configuredDuration = $settings[$file]['duration'] ?? IMAGE_DEFAULT_DURATION;
        $duration = is_numeric($configuredDuration) ? (int)$configuredDuration : IMAGE_DEFAULT_DURATION;
        $files[] = [
            'src' => $mediaUrl . '/' . rawurlencode($file),
            'name' => $file,
            'duration' => max(1, min(86400, $duration)),
        ];
    }

    usort($files, static fn($a, $b) => strcmp($a['name'], $b['name']));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Signage Player</title>
    <link rel="apple-touch-icon" sizes="180x180" href="https://timoclaessens.nl/assets/icons/apple-touch-icon.png?v=ed1fe7e">
    <link rel="manifest" href="/signage.webmanifest">
    <link rel="icon" type="image/svg+xml" href="https://timoclaessens.nl/assets/icons/favicon.svg?v=f4139c4">

    <style>
        html, body {
            margin: 0;
            width: 100%;
            height: 100%;
            background: black;
            overflow: hidden;
        }

        img,
        video {
            width: 100vw;
            height: 100vh;
            object-fit: contain;
            background: black;
        }

        #message {
            color: white;
            font-family: Arial, sans-serif;
            font-size: 32px;
            text-align: center;
            margin-top: 40vh;
        }
    </style>
</head>

<body>

<div id="player"></div>

<script>
const media = <?php echo json_encode($files, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
const player = document.getElementById("player");
const EMPTY_RETRY_MS = 30000;
const VIDEO_START_TIMEOUT_MS = 15000;
const VIDEO_MAX_RUNTIME_MS = 4 * 60 * 60 * 1000;
let index = 0;
let playbackTimer = null;

function clearPlaybackTimer() {
    if (playbackTimer !== null) {
        clearTimeout(playbackTimer);
        playbackTimer = null;
    }
}

function showMessage(message) {
    player.replaceChildren();
    const element = document.createElement("div");
    element.id = "message";
    element.textContent = message;
    player.appendChild(element);
}

function reloadAfterPlaylist() {
    clearPlaybackTimer();
    playbackTimer = setTimeout(() => location.reload(), 1000);
}

function preloadNext(nextIndex) {
    const item = media[nextIndex];
    if (!item) return;
    if (item.src.toLowerCase().endsWith(".mp4")) {
        // Preloading an entire video can waste bandwidth on always-on screens.
        return;
    }
    const image = new Image();
    image.src = item.src;
}

function advance() {
    clearPlaybackTimer();
    index = (index + 1) % media.length;
    if (index === 0) {
        reloadAfterPlaylist();
    } else {
        // Schedule instead of recursing when several files are broken.
        playbackTimer = setTimeout(showNext, 0);
    }
}

function showNext() {
    clearPlaybackTimer();
    if (media.length === 0) {
        showMessage("Geen media beschikbaar");
        playbackTimer = setTimeout(() => location.reload(), EMPTY_RETRY_MS);
        return;
    }

    const item = media[index];
    preloadNext((index + 1) % media.length);

    if (item.src.toLowerCase().endsWith(".mp4")) {
        const video = document.createElement("video");
        let finished = false;
        let startTimer = null;
        function finish() {
            if (finished) return;
            finished = true;
            clearTimeout(startTimer);
            video.pause();
            video.removeAttribute("src");
            video.load();
            advance();
        }

        video.autoplay = true;
        video.muted = true;
        video.playsInline = true;
        video.preload = "auto";
        video.onended = finish;
        video.onerror = finish;
        video.onplaying = () => {
            clearTimeout(startTimer);
            clearPlaybackTimer();
            const seconds = Number.isFinite(video.duration) && video.duration > 0
                ? video.duration + 5 : VIDEO_MAX_RUNTIME_MS / 1000;
            playbackTimer = setTimeout(finish, Math.min(seconds * 1000, VIDEO_MAX_RUNTIME_MS));
        };
        video.src = item.src;
        player.replaceChildren(video);
        startTimer = setTimeout(finish, VIDEO_START_TIMEOUT_MS);
        video.play().catch(finish);
    } else {
        const img = new Image();
        let finished = false;
        const loadTimer = setTimeout(() => {
            if (finished) return;
            finished = true;
            advance();
        }, VIDEO_START_TIMEOUT_MS);
        img.onload = () => {
            clearTimeout(loadTimer);
            if (finished) return;
            player.replaceChildren(img);
            playbackTimer = setTimeout(() => {
                finished = true;
                advance();
            }, item.duration * 1000);
        };
        img.onerror = () => {
            clearTimeout(loadTimer);
            if (finished) return;
            finished = true;
            advance();
        };
        img.src = item.src;
    }
}

showNext();
</script>

</body>
</html>