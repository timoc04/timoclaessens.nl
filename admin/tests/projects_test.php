<?php
declare(strict_types=1);

$directory = sys_get_temp_dir() . '/cms-projects-test-' . bin2hex(random_bytes(6));
define('CMS_STORAGE_DIR', $directory);
require_once dirname(__DIR__) . '/includes/projects.php';

function assert_project(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

try {
    $defaults = cms_read_projects();
    assert_project(count($defaults) === 4, 'Legacy four-card fallback missing.');
    assert_project($defaults[0]['style'] === 'cloud', 'Artwork style mismatch.');

    $copy = cms_defaults();
    $copy['project_1_title'] = 'Edited in phase 2';
    cms_save_content($copy);
    assert_project(cms_read_projects()[0]['title'] === 'Edited in phase 2', 'Legacy CMS edits not imported.');

    $projects = cms_read_projects();
    $projects[0]['title'] = 'Updated title';
    $projects[] = [
        'id' => 'new-project', 'title' => 'New project', 'description' => 'Description',
        'url' => 'https://example.com/work', 'tags' => ['PHP', 'CSS'],
        'category' => 'WEB', 'art_label' => 'NEW WORK', 'style' => 'signage',
    ];
    cms_save_projects($projects);
    assert_project(cms_read_projects() === $projects, 'Saved project list does not match.');

    $projects[0]['title'] = 'Second version';
    cms_save_projects($projects);
    $backup = json_decode(file_get_contents($directory . '/projects.previous.json'), true, 512, JSON_THROW_ON_ERROR);
    assert_project($backup[0]['title'] === 'Updated title', 'Previous version backup failed.');

    $invalid = $projects;
    $invalid[0]['url'] = 'javascript:alert(1)';
    try {
        cms_save_projects($invalid);
        throw new RuntimeException('Unsafe URL accepted.');
    } catch (InvalidArgumentException $expected) {}

    $invalid = $projects;
    $invalid[0]['id'] = $invalid[1]['id'];
    try {
        cms_save_projects($invalid);
        throw new RuntimeException('Duplicate ID accepted.');
    } catch (InvalidArgumentException $expected) {}

    $invalid = $projects;
    $invalid[0]['tags'] = [''];
    try {
        cms_save_projects($invalid);
        throw new RuntimeException('Empty tag accepted.');
    } catch (InvalidArgumentException $expected) {}

    assert_project(cms_read_projects() === $projects, 'Invalid project overwrote stored data.');
    file_put_contents($directory . '/projects.json', '{invalid');
    try {
        cms_read_projects();
        throw new RuntimeException('Corrupt project JSON accepted.');
    } catch (RuntimeException $expected) {
        assert_project(str_contains($expected->getMessage(), 'Invalid projects JSON'), 'Wrong corrupt JSON error.');
    }
    echo "CMS project tests passed.\n";
} finally {
    if (is_dir($directory)) {
        foreach (scandir($directory) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') unlink($directory . '/' . $file);
        }
        rmdir($directory);
    }
}
