<?php
declare(strict_types=1);
$directory = sys_get_temp_dir() . '/cms-draft-test-' . bin2hex(random_bytes(6));
define('CMS_STORAGE_DIR', $directory);
require_once dirname(__DIR__) . '/includes/drafts.php';

function check_draft(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
try {
    $live = cms_read_content();
    $liveRevision = cms_content_revision($live);
    check_draft(cms_read_content_draft() === null, 'Draft should initially be absent.');

    $draft = $live;
    $draft['hero_intro'] = 'Unpublished first draft';
    cms_content_draft_action('save_draft', $draft, 'none', $liveRevision);
    check_draft(cms_read_content_draft() === $draft, 'Draft not saved.');
    check_draft(cms_read_content()['hero_intro'] === $live['hero_intro'], 'Draft changed public content.');

    $oldDraftRevision = cms_content_revision($draft);
    $draft['hero_intro'] = 'Unpublished second draft';
    cms_content_draft_action('save_draft', $draft, $oldDraftRevision, $liveRevision);
    try {
        cms_content_draft_action('save_draft', $live, $oldDraftRevision, $liveRevision);
        throw new RuntimeException('Stale draft overwrite accepted.');
    } catch (DomainException $expected) {}

    $currentDraftRevision = cms_content_revision($draft);
    $newLive = $live;
    $newLive['contact_intro'] = 'A separate live update';
    cms_save_content($newLive);
    try {
        cms_content_draft_action('publish', null, $currentDraftRevision, $liveRevision);
        throw new RuntimeException('Stale publication accepted.');
    } catch (DomainException $expected) {}
    check_draft(cms_read_content_draft() !== null, 'Conflict discarded the draft.');
    check_draft(cms_read_content()['contact_intro'] === 'A separate live update', 'Conflict changed published content.');

    cms_content_draft_action('publish', null, $currentDraftRevision, cms_content_revision($newLive));
    check_draft(cms_read_content_draft() === null, 'Published draft was not cleared.');
    check_draft(cms_read_content()['hero_intro'] === 'Unpublished second draft', 'Draft not published.');

    cms_content_draft_action('save_draft', $live, 'none', cms_content_revision(cms_read_content()));
    cms_content_draft_action('discard', null, cms_content_revision($live), cms_content_revision(cms_read_content()));
    check_draft(cms_read_content_draft() === null, 'Discard failed.');
    echo "CMS content draft tests passed.\n";
} finally {
    if (is_dir($directory)) {
        foreach (scandir($directory) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') unlink($directory . '/' . $file);
        }
        rmdir($directory);
    }
}
