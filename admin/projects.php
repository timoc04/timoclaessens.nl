<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/project_drafts.php';
cms_start_session();
cms_require_login();

$error = '';
$notice = '';
try {
    $liveProjects = cms_read_projects();
    $draft = cms_read_project_draft();
    $projects = $draft ?? $liveProjects;
} catch (Throwable $exception) {
    error_log('CMS projects read failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Projects could not be loaded. Check private storage and backups.');
}

$revision = $draft === null ? 'none' : cms_projects_revision($draft);
$liveRevision = cms_projects_revision($liveProjects);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cms_check_csrf();
    try {
        $expectedRevision = $_POST['revision'] ?? null;
        $expectedLive = $_POST['live_revision'] ?? null;
        if (!is_string($expectedRevision) || !is_string($expectedLive) ||
            ($expectedRevision !== 'none' && !preg_match('/^[a-f0-9]{64}$/D', $expectedRevision)) ||
            !preg_match('/^[a-f0-9]{64}$/D', $expectedLive)) {
            throw new InvalidArgumentException('Invalid project revision. Reload the page.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'publish' || $action === 'discard') {
            cms_project_draft_action($action, null, $expectedRevision, $expectedLive);
            header('Location: /admin/projects.php?status=' . ($action === 'publish' ? 'published' : 'discarded'), true, 303);
            exit;
        }
        $id = $_POST['id'] ?? '';
        if (!is_string($action) || !is_string($id)) throw new InvalidArgumentException('Invalid request.');
        $index = null;
        foreach ($projects as $position => $project) {
            if ($project['id'] === $id) $index = $position;
        }
        if ($action === 'save' || $action === 'add') {
            $fields = $_POST['project'] ?? null;
            if (!is_array($fields)) throw new InvalidArgumentException('Missing project data.');
            $fields['id'] = $action === 'add' ? bin2hex(random_bytes(8)) : $id;
            $tags = $fields['tags'] ?? '';
            if (!is_string($tags)) throw new InvalidArgumentException('Invalid tags.');
            $fields['tags'] = array_map('trim', explode(',', $tags));
            $record = cms_validate_project($fields);
            if ($action === 'add') {
                $projects[] = $record;
            } elseif ($index !== null) {
                $projects[$index] = $record;
            } else {
                throw new InvalidArgumentException('Project not found.');
            }
        } elseif ($action === 'delete' && $index !== null) {
            array_splice($projects, $index, 1);
        } elseif ($action === 'up' && $index !== null && $index > 0) {
            [$projects[$index - 1], $projects[$index]] = [$projects[$index], $projects[$index - 1]];
        } elseif ($action === 'down' && $index !== null && $index < count($projects) - 1) {
            [$projects[$index + 1], $projects[$index]] = [$projects[$index], $projects[$index + 1]];
        } else {
            throw new InvalidArgumentException('Invalid project action.');
        }
        cms_project_draft_action('save_draft', $projects, $expectedRevision, $expectedLive);
        header('Location: /admin/projects.php?status=draft', true, 303);
        exit;
    } catch (DomainException $exception) {
        http_response_code(409);
        $error = $exception->getMessage();
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('CMS project operation failed: ' . $exception->getMessage());
        http_response_code(500);
        $error = 'Project changes could not be saved. Please try again.';
    }
}
$status = $_GET['status'] ?? '';
if ($status === 'draft') $notice = 'Project draft saved privately. The public website is unchanged.';
if ($status === 'published') $notice = 'Project draft published successfully.';
if ($status === 'discarded') $notice = 'Project draft discarded.';

function cms_project_field(string $name, string $label, string $value, int $max, bool $multiline = false): void
{
    $key = cms_escape($name);
    $safe = cms_escape($value);
    echo '<div class="field"><label>' . cms_escape($label);
    if ($multiline) {
        echo '<textarea name="project[' . $key . ']" maxlength="' . $max . '" required rows="3">' . $safe . '</textarea>';
    } else {
        $type = $name === 'url' ? 'url' : 'text';
        echo '<input type="' . $type . '" name="project[' . $key . ']" maxlength="' . $max . '" required value="' . $safe . '">';
    }
    echo '</label></div>';
}

function cms_project_editor(array $project, string $action): void
{
    cms_project_field('title', 'Project title', $project['title'], 120);
    cms_project_field('description', 'Description', $project['description'], 1000, true);
    cms_project_field('url', 'Destination URL', $project['url'], 2048);
    cms_project_field('tags', 'Technology tags (1–6, comma-separated)', implode(', ', $project['tags']), 240);
    cms_project_field('category', 'Category', $project['category'], 100);
    cms_project_field('art_label', 'Artwork caption', $project['art_label'], 100);
    echo '<div class="field"><label>Artwork style<select name="project[style]" required>';
    foreach (cms_project_styles() as $style) {
        echo '<option value="' . cms_escape($style) . '"' . ($project['style'] === $style ? ' selected' : '') . '>' . cms_escape(ucfirst($style)) . '</option>';
    }
    echo '</select></label></div>';
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Projects | Portfolio CMS</title>
<link rel="icon" href="/assets/icons/favicon.svg?v=f4139c4" type="image/svg+xml">
<link rel="stylesheet" href="/admin/admin.css?v=cf16eb5f"><script defer src="/admin/projects.js?v=project-drafts-1"></script></head>
<body>
<header class="admin-header"><div class="container topbar">
<a class="brand" href="/admin/"><img src="/assets/icons/favicon.svg?v=f4139c4" width="38" height="38" alt="tc. logo"> Timo Claessens <span>/ Project Manager</span></a>
<div class="header-actions"><a class="button secondary" href="/admin/">Edit page copy</a><a class="button secondary" href="/admin/preview.php" target="_blank" rel="noopener noreferrer">Preview drafts ↗</a><a class="button secondary" href="/" target="_blank" rel="noopener noreferrer">View website ↗</a>
<form method="post" action="/admin/logout.php"><input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>"><button class="button secondary" type="submit">Sign out</button></form></div>
</div></header>
<main class="container admin-main"><div class="eyebrow">PORTFOLIO / PROJECTS</div>
<h1>Manage your <em>projects.</em></h1>
<p class="lead muted">Add, edit, reorder or remove project cards in a private draft. Publish the saved draft when ready.</p>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= cms_escape($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="notice success" role="status"><?= cms_escape($notice) ?></div><?php endif; ?>
<p class="notice"><?= $draft !== null ? "Editing saved project draft — the public website is unchanged." : "No saved project draft — displaying published projects." ?></p>
<div class="editor">
<?php foreach ($projects as $position => $project): ?>
<section class="panel editor-section">
<div class="section-heading"><h2><?= cms_escape(sprintf('%02d / %s', $position + 1, $project['title'])) ?></h2><p>Card position <?= $position + 1 ?> of <?= count($projects) ?></p></div>
<form method="post" action="/admin/projects.php">
<input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>">
<input type="hidden" name="revision" value="<?= cms_escape($revision) ?>">
<input type="hidden" name="live_revision" value="<?= cms_escape($liveRevision) ?>">
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= cms_escape($project['id']) ?>">
<div class="field-grid"><?php cms_project_editor($project, 'save'); ?></div>
<div class="project-actions"><button class="button primary" type="submit">Save project draft</button></div>
</form>
<div class="project-actions">
<?php foreach (['up' => 'Move up ↑', 'down' => 'Move down ↓', 'delete' => 'Delete project'] as $action => $label): ?>
<?php if (($action === 'up' && $position === 0) || ($action === 'down' && $position === count($projects) - 1)) continue; ?>
<form method="post" action="/admin/projects.php">
<input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>">
<input type="hidden" name="revision" value="<?= cms_escape($revision) ?>">
<input type="hidden" name="live_revision" value="<?= cms_escape($liveRevision) ?>">
<input type="hidden" name="id" value="<?= cms_escape($project['id']) ?>">
<input type="hidden" name="action" value="<?= cms_escape($action) ?>">
<button class="button secondary<?= $action === 'delete' ? ' danger' : '' ?>" type="submit"<?= $action === 'delete' ? ' data-confirm-delete="' . cms_escape($project['title']) . '"' : '' ?>><?= cms_escape($label) ?></button>
</form>
<?php endforeach; ?>
</div></section>
<?php endforeach; ?>
<?php if (count($projects) < 20): ?>
<section class="panel editor-section"><div class="section-heading"><h2>Add a project</h2><p>Choose an existing artwork style. Up to 20 projects.</p></div>
<form method="post" action="/admin/projects.php">
<input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>">
<input type="hidden" name="revision" value="<?= cms_escape($revision) ?>">
<input type="hidden" name="live_revision" value="<?= cms_escape($liveRevision) ?>">
<input type="hidden" name="action" value="add"><input type="hidden" name="id" value="">
<div class="field-grid"><?php cms_project_editor(['title'=>'','description'=>'','url'=>'','tags'=>[''], 'category'=>'', 'art_label'=>'', 'style'=>'cloud'], 'add'); ?></div>
<div class="project-actions"><button class="button primary" type="submit">Add to draft ↗</button></div>
</form></section>
<?php endif; ?>
</div>
<?php if ($draft !== null): ?>
<div class="save-bar" style="margin-top:18px">
<p>Publish the saved project draft or discard it. Unsaved edits in the forms above are not included.</p>
<div class="header-actions">
<form method="post" action="/admin/projects.php"><input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>"><input type="hidden" name="revision" value="<?= cms_escape($revision) ?>"><input type="hidden" name="live_revision" value="<?= cms_escape($liveRevision) ?>"><button class="button primary" type="submit" name="action" value="publish">Publish project draft</button></form>
<form method="post" action="/admin/projects.php" data-confirm-discard><input type="hidden" name="csrf_token" value="<?= cms_escape(cms_csrf_token()) ?>"><input type="hidden" name="revision" value="<?= cms_escape($revision) ?>"><input type="hidden" name="live_revision" value="<?= cms_escape($liveRevision) ?>"><button class="button secondary" type="submit" name="action" value="discard">Discard project draft</button></form>
</div></div>
<?php endif; ?>
</main>
<footer class="container admin-footer"><p>© <?= date('Y') ?> Timo Claessens. All rights reserved.</p><a href="/privacy/">Privacy</a></footer>
</body></html>
