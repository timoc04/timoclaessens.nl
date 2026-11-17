<?php
declare(strict_types=1);
require_once __DIR__ . '/projects.php';

/** Drafts remain private and are never served by the public projects API. */
function cms_project_draft_path(): string
{
    return cms_storage_directory() . '/projects.draft.json';
}

function cms_read_project_draft(): ?array
{
    $path = cms_project_draft_path();
    if (!is_file($path)) return null;
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Could not read project draft.');
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Invalid project draft JSON.', 0, $exception);
    }
    if (!is_array($data)) throw new RuntimeException('Invalid project draft.');
    return cms_validate_projects($data);
}

function cms_write_project_draft(array $projects): void
{
    $directory = cms_storage_directory();
    $json = json_encode(cms_validate_projects($projects), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    $temp = tempnam($directory, '.project-draft-');
    if ($temp === false) throw new RuntimeException('Could not create project draft.');
    try {
        if (file_put_contents($temp, $json) !== strlen($json)) throw new RuntimeException('Could not write project draft.');
        chmod($temp, 0600);
        if (!rename($temp, cms_project_draft_path())) throw new RuntimeException('Could not replace project draft.');
    } finally {
        if (is_file($temp)) unlink($temp);
    }
}

/** Apply a change against the current draft under an exclusive draft lock. */
function cms_project_draft_action(string $action, ?array $projects, string $expectedDraft, string $expectedLive): void
{
    if (!in_array($action, ['save_draft', 'publish', 'discard'], true)) {
        throw new InvalidArgumentException('Invalid project draft action.');
    }
    $directory = cms_storage_directory();
    $lock = fopen($directory . '/projects.draft.lock', 'c');
    if ($lock === false) throw new RuntimeException('Could not lock project draft.');
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Could not lock project draft.');
        $draft = cms_read_project_draft();
        $revision = $draft === null ? 'none' : cms_projects_revision($draft);
        if (!hash_equals($revision, $expectedDraft)) {
            throw new DomainException('Project draft changed in another tab. Reload before editing.');
        }
        if ($action === 'save_draft') {
            if ($projects === null) throw new InvalidArgumentException('Missing project draft.');
            cms_write_project_draft($projects);
            return;
        }
        if ($action === 'discard') {
            if ($draft !== null && !unlink(cms_project_draft_path())) {
                throw new RuntimeException('Could not discard project draft.');
            }
            return;
        }
        if ($draft === null) throw new InvalidArgumentException('No project draft to publish.');
        // cms_save_projects checks the live revision while holding its own lock.
        cms_save_projects($draft, $expectedLive);
        if (!unlink(cms_project_draft_path())) {
            throw new RuntimeException('Projects were published but the draft could not be cleared.');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
