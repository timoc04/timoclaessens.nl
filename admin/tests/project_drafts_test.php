<?php
declare(strict_types=1);
$directory = sys_get_temp_dir() . '/cms-project-draft-test-' . bin2hex(random_bytes(6));
define('CMS_STORAGE_DIR', $directory);
require_once dirname(__DIR__) . '/includes/project_drafts.php';

function check_project_draft(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
try {
    $live = cms_read_projects();
    $liveRevision = cms_projects_revision($live);
    check_project_draft(cms_read_project_draft() === null, 'Unexpected draft.');
    $draft = $live;
    $draft[0]['title'] = 'Unpublished project';
    cms_project_draft_action('save_draft', $draft, 'none', $liveRevision);
    check_project_draft(cms_read_project_draft() === cms_validate_projects($draft), 'Draft not saved.');
    check_project_draft(cms_read_projects() === $live, 'Saving draft changed live projects.');
    $revision = cms_projects_revision($draft);
    $updated = $draft;
    $updated[0]['title'] = 'Updated unpublished project';
    cms_project_draft_action('save_draft', $updated, $revision, $liveRevision);
    try {
        cms_project_draft_action('save_draft', $draft, $revision, $liveRevision);
        throw new RuntimeException('Stale draft accepted.');
    } catch (DomainException $expected) {}
    $newLive = $live;
    $newLive[1]['title'] = 'Published elsewhere';
    cms_save_projects($newLive, $liveRevision);
    try {
        cms_project_draft_action('publish', null, cms_projects_revision($updated), $liveRevision);
        throw new RuntimeException('Stale publish accepted.');
    } catch (DomainException $expected) {}
    check_project_draft(cms_read_project_draft() === cms_validate_projects($updated), 'Conflict lost draft.');
    check_project_draft(cms_read_projects() === cms_validate_projects($newLive), 'Conflict changed published projects.');
    cms_project_draft_action('publish', null, cms_projects_revision($updated), cms_projects_revision($newLive));
    check_project_draft(cms_read_project_draft() === null, 'Published draft not removed.');
    check_project_draft(cms_read_projects() === cms_validate_projects($updated), 'Draft not published.');
    cms_project_draft_action('save_draft', $live, 'none', cms_projects_revision($updated));
    cms_project_draft_action('discard', null, cms_projects_revision($live), cms_projects_revision($updated));
    check_project_draft(cms_read_project_draft() === null, 'Discard failed.');
    check_project_draft(cms_read_projects() === cms_validate_projects($updated), 'Discard changed published projects.');
    echo "CMS project draft tests passed.\n";
} finally {
    if (is_dir($directory)) {
        foreach (scandir($directory) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') unlink($directory . '/' . $file);
        }
        rmdir($directory);
    }
}
