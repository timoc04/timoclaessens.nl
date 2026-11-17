<?php
declare(strict_types=1);

require_once __DIR__ . '/content.php';

/**
 * Private draft for homepage copy. Never read by public CMS endpoints.
 * Project fields are retained solely for backwards compatibility.
 */
function cms_content_draft_path(): string
{
    return cms_storage_directory() . '/content.draft.json';
}

function cms_read_content_draft(): ?array
{
    $path = cms_content_draft_path();
    if (!is_file($path)) return null;
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Could not read content draft.');
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Invalid content draft JSON.', 0, $exception);
    }
    if (!is_array($decoded)) throw new RuntimeException('Invalid content draft.');
    return cms_validate_content($decoded);
}

function cms_content_revision(array $content): string
{
    return hash('sha256', json_encode(cms_validate_content($content), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/** The caller must already hold the content draft lock. */
function cms_write_content_draft_locked(array $content): void
{
    $path = cms_content_draft_path();
    $directory = cms_storage_directory();
    $json = json_encode(cms_validate_content($content), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    $temporary = tempnam($directory, '.draft-');
    if ($temporary === false) throw new RuntimeException('Could not create draft file.');
    try {
        if (file_put_contents($temporary, $json) !== strlen($json)) {
            throw new RuntimeException('Could not write draft.');
        }
        chmod($temporary, 0600);
        if (!rename($temporary, $path)) throw new RuntimeException('Could not replace draft.');
    } finally {
        if (is_file($temporary)) unlink($temporary);
    }
}

/**
 * Lock all draft operations; check a revision to avoid stale editor tabs.
 * Publish also checks that live content has not changed since the draft was opened.
 */
function cms_content_draft_action(string $action, ?array $content, string $expectedDraft, string $expectedLive): void
{
    if (!in_array($action, ['save_draft', 'publish', 'discard'], true)) {
        throw new InvalidArgumentException('Invalid draft action.');
    }
    $directory = cms_storage_directory();
    $handle = fopen($directory . '/content.draft.lock', 'c');
    if ($handle === false) throw new RuntimeException('Could not lock draft.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Could not lock draft.');
        $draft = cms_read_content_draft();
        $currentDraftRevision = $draft === null ? 'none' : cms_content_revision($draft);
        if (!hash_equals($currentDraftRevision, $expectedDraft)) {
            throw new DomainException('Draft changed in another tab. Reload before editing.');
        }
        if ($action === 'save_draft') {
            if ($content === null) throw new InvalidArgumentException('Missing draft content.');
            cms_write_content_draft_locked($content);
            return;
        }
        if ($action === 'discard') {
            if ($draft !== null && !unlink(cms_content_draft_path())) {
                throw new RuntimeException('Could not discard draft.');
            }
            return;
        }
        if ($draft === null) throw new InvalidArgumentException('No draft to publish.');
        if (!hash_equals(cms_content_revision(cms_read_content()), $expectedLive)) {
            throw new DomainException('Published content changed since this page was opened. Reload before publishing.');
        }
        // A failed publish preserves the draft.
        cms_save_content($draft);
        if (!unlink(cms_content_draft_path())) {
            throw new RuntimeException('Content was published but the draft could not be cleared. Contact the administrator.');
        }
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
