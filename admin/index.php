<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/drafts.php';
cms_start_session();
cms_require_login();

$notice = '';
$error = '';
try {
    $publishedContent = cms_read_content();
    $draft = cms_read_content_draft();
    $content = $draft ?? $publishedContent;
} catch (Throwable $exception) {
    error_log('Portfolio CMS read error: ' . $exception->getMessage());
    http_response_code(503);
    exit('The content could not be loaded. Check CMS storage and backups before retrying.');
}

$draftRevision = $draft === null ? 'none' : cms_content_revision($draft);
$liveRevision = cms_content_revision($publishedContent);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cms_check_csrf();
    try {
        $action = $_POST['action'] ?? '';
        $expectedDraft = $_POST['draft_revision'] ?? null;
        $expectedLive = $_POST['live_revision'] ?? null;
        if (!is_string($action) || !is_string($expectedDraft) || !is_string($expectedLive)) {
            throw new InvalidArgumentException('Invalid request.');
        }
        if ($action === 'save_draft') {
            $posted = $_POST['content'] ?? null;
            if (!is_array($posted)) throw new InvalidArgumentException('Missing content.');
            $editable = [];
            foreach (cms_fields() as $key => $field) {
                if ($field['section'] !== 'Projects') $editable[$key] = $posted[$key] ?? null;
            }
            $content = cms_validate_content(array_replace($content, $editable));
            cms_content_draft_action($action, $content, $expectedDraft, $expectedLive);
        } elseif ($action === 'publish' || $action === 'discard') {
            cms_content_draft_action($action, null, $expectedDraft, $expectedLive);
        } else {
            throw new InvalidArgumentException('Invalid action.');
        }
        $status = $action === 'save_draft' ? 'draft' : ($action === 'publish' ? 'published' : 'discarded');
        header('Location: /admin/?status=' . $status, true, 303);
        exit;
    } catch (DomainException $exception) {
        http_response_code(409);
        $error = $exception->getMessage();
    } catch (InvalidArgumentException $exception) {
        $error = 'Please check all fields and character limits.';
    } catch (Throwable $exception) {
        error_log('Portfolio CMS draft error: ' . $exception->getMessage());
        http_response_code(500);
        $error = 'Draft operation failed. Check the public site before retrying.';
    }
}
$status = $_GET['status'] ?? '';
if ($status === 'draft') $notice = 'Draft saved privately. Your public website has not changed.';
if ($status === 'published') $notice = 'Draft published successfully.';
if ($status === 'discarded') $notice = 'Draft discarded; published content is unchanged.';

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
<link rel="icon" href="/assets/icons/favicon.svg?v=f4139c4" type="image/svg+xml">
<link rel="stylesheet" href="/admin/admin.css">
<script defer src="/admin/drafts.js"></script>
</head>
<body>
<header class="admin-header">
<div class="container topbar">
<a class="brand" href="/"><img src="/assets/icons/favicon.svg?v=f4139c4" width="38" height="38" alt="tc. logo"> Timo Claessens <span>/ Content Manager</span></a>
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
<p class="lead muted">Edit your homepage copy privately and publish when ready. Drafts are stored outside GitHub.</p>
<?php if ($notice !== ''): ?><div class="notice success" role="status"><?= cms_escape($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= cms_escape($error) ?></div><?php endif; ?>
<p class="notice"><?= $draft !== null ? "Editing saved draft — the public site is unchanged." : "No draft saved — displaying published copy." ?></p>
<form method="post" action="/admin/" class="editor">
<input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>">
<input type="hidden" name="draft_revision" value="<?= cms_escape($draftRevision) ?>">
<input type="hidden" name="live_revision" value="<?= cms_escape($liveRevision) ?>">
<?php foreach ($sections as $section => $fields): ?>
<section class="panel editor-section">
<div class="section-heading"><h2><?= cms_escape($section) ?></h2><p>Changes appear publicly only after publishing.</p></div>
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
<div class="save-bar"><p>Saving a draft does not change your public website.</p><button class="button primary" type="submit" name="action" value="save_draft">Save draft</button></div>
</form>
<?php if ($draft !== null): ?>
<div class="save-bar" style="margin-top:18px">
<p>Publish the saved draft or discard it. Unsaved edits above are not included when publishing.</p>
<div class="header-actions">
<form method="post" action="/admin/"><input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>"><input type="hidden" name="draft_revision" value="<?= cms_escape($draftRevision) ?>"><input type="hidden" name="live_revision" value="<?= cms_escape($liveRevision) ?>"><button class="button primary" type="submit" name="action" value="publish">Publish saved draft</button></form>
<form method="post" action="/admin/" data-confirm-discard><input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>"><input type="hidden" name="draft_revision" value="<?= cms_escape($draftRevision) ?>"><input type="hidden" name="live_revision" value="<?= cms_escape($liveRevision) ?>"><button class="button secondary" type="submit" name="action" value="discard">Discard draft</button></form>
</div></div>
<?php endif; ?>
</main>
<footer class="container admin-footer"><p>© <?= date('Y') ?> Timo Claessens. All rights reserved.</p><a href="/privacy/">Privacy</a></footer>
</body>
</html>
