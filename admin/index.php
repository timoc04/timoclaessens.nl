<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
cms_start_session();
cms_require_login();

$notice = '';
$error = '';
try {
    $content = cms_read_content();
} catch (Throwable $exception) {
    error_log('Portfolio CMS read error: ' . $exception->getMessage());
    http_response_code(503);
    exit('The content could not be loaded. Check CMS storage and backups before retrying.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cms_check_csrf();
    try {
        $posted = $_POST['content'] ?? null;
        if (!is_array($posted)) {
            throw new InvalidArgumentException('Missing content.');
        }
        // Project fields are legacy data for initial migration only.
        // Keep their existing values without exposing or accepting edits here.
        $editable = [];
        foreach (cms_fields() as $key => $field) {
            if ($field['section'] === 'Projects') {
                continue;
            }
            $editable[$key] = $posted[$key] ?? null;
        }
        $content = cms_validate_content(array_replace($content, $editable));
        cms_save_content($content);
        $notice = 'Changes saved successfully. Refresh the website to see them.';
    } catch (InvalidArgumentException $exception) {
        $error = 'Please fill in every field and respect the character limits.';
    } catch (Throwable $exception) {
        error_log('Portfolio CMS save error: ' . $exception->getMessage());
        http_response_code(500);
        $error = 'Your changes could not be saved. Please try again.';
    }
}

$sections = [];
foreach (cms_fields() as $key => $field) {
    if ($field['section'] !== 'Projects') {
        $sections[$field['section']][$key] = $field;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Content manager | Timo Claessens</title>
<link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
<header class="admin-header">
<div class="container topbar">
<a class="brand" href="/"><img src="/assets/icons/favicon.svg" width="38" height="38" alt="tc. logo"> Timo Claessens <span>/ Content Manager</span></a>
<div class="header-actions">
<a class="button secondary" href="/admin/projects.php">Manage projects</a>
<a class="button secondary" href="/" target="_blank" rel="noopener noreferrer">View website ↗</a>
<form method="post" action="/admin/logout.php">
<input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>">
<button class="button secondary" type="submit">Sign out</button>
</form>
</div>
</div>
</header>
<main class="container admin-main">
<div class="eyebrow">PORTFOLIO / ADMINISTRATION</div>
<h1>Manage your <em>content.</em></h1>
<p class="lead muted">Edit the text shown on your website. Updates are saved outside GitHub so a normal deployment won't overwrite them.</p>
<?php if ($notice !== ''): ?><div class="notice success" role="status"><?= cms_escape($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= cms_escape($error) ?></div><?php endif; ?>
<form method="post" action="/admin/" class="editor">
<input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>">
<?php foreach ($sections as $section => $fields): ?>
<section class="panel editor-section">
<div class="section-heading"><h2><?= cms_escape($section) ?></h2><p>Changes appear after refreshing the homepage.</p></div>
<div class="field-grid">
<?php foreach ($fields as $key => $field):
    $value = $content[$key] ?? $field['default'];
    $isShort = str_ends_with($key, '_title') || in_array(($field['type'] ?? ''), ['url', 'tags'], true);
    $inputType = ($field['type'] ?? '') === 'url' ? 'url' : 'text';
?>
<div class="field">
<label for="<?= cms_escape($key) ?>"><?= cms_escape($field['label']) ?></label>
<?php if ($isShort): ?>
<input type="<?= cms_escape($inputType) ?>" id="<?= cms_escape($key) ?>" name="content[<?= cms_escape($key) ?>]" value="<?= cms_escape($value) ?>" maxlength="<?= (int)$field['limit'] ?>" required>
<?php else: ?>
<textarea id="<?= cms_escape($key) ?>" name="content[<?= cms_escape($key) ?>]" rows="3" maxlength="<?= (int)$field['limit'] ?>" required><?= cms_escape($value) ?></textarea>
<?php endif; ?>
<p class="field-hint">Maximum <?= (int)$field['limit'] ?> bytes. Plain text only.</p>
</div>
<?php endforeach; ?>
</div>
</section>
<?php endforeach; ?>
<div class="save-bar"><p>Save to publish your changes. Your HTML and signage files are not modified.</p><button class="button primary" type="submit">Save changes ↗</button></div>
</form>
</main>
<footer class="container admin-footer"><p>© <?= date('Y') ?> Timo Claessens. All rights reserved.</p><a href="/privacy/">Privacy</a></footer>
</body>
</html>
