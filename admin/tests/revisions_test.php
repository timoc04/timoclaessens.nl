<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/revisions.php';

function check_revision(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$valid = str_repeat('a', 64);
check_revision(cms_valid_revision($valid), 'SHA-256 token rejected.');
check_revision(cms_valid_revision('none', true), 'Unsaved draft token rejected.');
check_revision(!cms_valid_revision('none'), 'Live revision accepted none.');
foreach ([null, 1, [], '', 'ABC', str_repeat('A', 64), str_repeat('a', 63), str_repeat('a', 65)] as $invalid) {
    check_revision(!cms_valid_revision($invalid, true), 'Invalid revision accepted.');
}
cms_require_revisions('none', $valid);
cms_require_revisions($valid, $valid);
foreach ([['none', 'none'], ['invalid', $valid], [$valid, null]] as [$draft, $live]) {
    try {
        cms_require_revisions($draft, $live);
        throw new RuntimeException('Invalid revision pair accepted.');
    } catch (InvalidArgumentException $expected) {}
}
echo "CMS revision validation tests passed.\n";
