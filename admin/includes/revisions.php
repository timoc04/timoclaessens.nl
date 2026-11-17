<?php
declare(strict_types=1);

/**
 * Validate revision tokens submitted by the two CMS editors.
 * Drafts use "none" until saved; published content always has a SHA-256 revision.
 */
function cms_valid_revision(mixed $value, bool $allowNone = false): bool
{
    return is_string($value) && (
        ($allowNone && $value === 'none') ||
        preg_match('/^[a-f0-9]{64}$/D', $value) === 1
    );
}

function cms_require_revisions(mixed $draft, mixed $live): void
{
    if (!cms_valid_revision($draft, true) || !cms_valid_revision($live)) {
        throw new InvalidArgumentException('Invalid revision. Reload the page and try again.');
    }
}
