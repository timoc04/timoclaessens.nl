<?php
declare(strict_types=1);

require_once __DIR__ . '/content.php';

/**
 * Project records live in a separate private JSON file. Legacy CMS fields
 * remain untouched so existing content.json files work without migration.
 */
function cms_project_styles(): array
{
    return ['cloud', 'signage', 'api', 'homelab'];
}

function cms_project_defaults(): array
{
    $copy = cms_read_content();
    $types = ['CLOUD & DEVOPS', 'WEB DEVELOPMENT', 'BACKEND DEVELOPMENT', 'PERSONAL LAB'];
    $labels = ['CLOUD INFRASTRUCTURE', 'WEB APPLICATION', 'SOFTWARE & MONITORING', 'SELF-HOSTED SYSTEMS'];
    $projects = [];
    for ($i = 1; $i <= 4; $i++) {
        $projects[] = [
            'id' => 'project-' . $i,
            'title' => $copy["project_{$i}_title"],
            'description' => $copy["project_{$i}_description"],
            'url' => $copy["project_{$i}_url"],
            'tags' => array_map('trim', explode(',', $copy["project_{$i}_tags"])),
            'category' => $types[$i - 1],
            'art_label' => $labels[$i - 1],
            'style' => cms_project_styles()[$i - 1],
        ];
    }
    return $projects;
}

function cms_project_path(): string
{
    return cms_storage_directory() . '/projects.json';
}

function cms_validate_project(array $project): array
{
    $result = [];
    foreach (['id' => 64, 'title' => 120, 'description' => 1000,
              'url' => 2048, 'category' => 100, 'art_label' => 100] as $field => $limit) {
        $value = $project[$field] ?? null;
        if (!is_string($value) || trim($value) === '' || strlen($value) > $limit) {
            throw new InvalidArgumentException('Invalid project field: ' . $field);
        }
        $result[$field] = trim($value);
    }
    if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $result['id'])) {
        throw new InvalidArgumentException('Invalid project ID.');
    }
    $url = parse_url($result['url']);
    if ($url === false || !in_array(strtolower((string)($url['scheme'] ?? '')), ['https', 'http'], true)
        || empty($url['host']) || isset($url['user']) || isset($url['pass'])
        || preg_match('/[\\x00-\\x20\\x7f]/', $result['url'])) {
        throw new InvalidArgumentException('Project URL must be HTTP or HTTPS.');
    }
    $style = $project['style'] ?? null;
    if (!is_string($style) || !in_array($style, cms_project_styles(), true)) {
        throw new InvalidArgumentException('Invalid project artwork.');
    }
    $result['style'] = $style;
    $tags = $project['tags'] ?? null;
    if (!is_array($tags) || count($tags) < 1 || count($tags) > 6) {
        throw new InvalidArgumentException('Each project requires 1–6 tags.');
    }
    $result['tags'] = [];
    foreach ($tags as $tag) {
        if (!is_string($tag) || trim($tag) === '' || strlen(trim($tag)) > 40) {
            throw new InvalidArgumentException('Invalid project tag.');
        }
        $result['tags'][] = trim($tag);
    }
    return $result;
}

function cms_validate_projects(array $projects): array
{
    if (count($projects) > 20 || !array_is_list($projects)) {
        throw new InvalidArgumentException('Maximum 20 projects.');
    }
    $result = [];
    $ids = [];
    foreach ($projects as $project) {
        if (!is_array($project)) throw new InvalidArgumentException('Invalid project record.');
        $item = cms_validate_project($project);
        if (isset($ids[$item['id']])) throw new InvalidArgumentException('Duplicate project ID.');
        $ids[$item['id']] = true;
        $result[] = $item;
    }
    return $result;
}

function cms_read_projects(): array
{
    $path = cms_project_path();
    if (!is_file($path)) return cms_project_defaults();
    $json = file_get_contents($path);
    if ($json === false) throw new RuntimeException('Could not read projects.');
    try {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Invalid projects JSON.', 0, $exception);
    }
    if (!is_array($data)) throw new RuntimeException('Invalid projects data.');
    return cms_validate_projects($data);
}

/** Stable revision of the validated project list for optimistic concurrency. */
function cms_projects_revision(array $projects): string
{
    return hash('sha256', json_encode(cms_validate_projects($projects), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/** Atomic write and a previous-version backup, both outside public_html.
 * If supplied, the expected revision is verified while holding the exclusive lock.
 */
function cms_save_projects(array $projects, ?string $expectedRevision = null): void
{
    $validated = cms_validate_projects($projects);
    $dir = cms_storage_directory();
    $path = $dir . '/projects.json';
    $lock = fopen($dir . '/projects.lock', 'c');
    if ($lock === false) throw new RuntimeException('Could not lock project storage.');
    $temp = false;
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Could not lock project storage.');
        if ($expectedRevision !== null && !hash_equals(cms_projects_revision(cms_read_projects()), $expectedRevision)) {
            throw new DomainException('Projects were updated in another tab or session. Refresh the page before editing.');
        }
        $json = json_encode($validated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        $temp = tempnam($dir, '.projects-');
        if ($temp === false || file_put_contents($temp, $json) !== strlen($json)) {
            throw new RuntimeException('Could not save projects.');
        }
        chmod($temp, 0600);
        if (is_file($path) && !copy($path, $dir . '/projects.previous.json')) {
            throw new RuntimeException('Could not back up previous projects.');
        }
        if (!rename($temp, $path)) throw new RuntimeException('Could not replace projects.');
        $temp = false;
    } finally {
        if ($temp !== false && is_file($temp)) unlink($temp);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
