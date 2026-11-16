<?php
declare(strict_types=1);

/**
 * Editable plain-text fields. The original HTML remains the default if the
 * JSON store is unavailable, and JS applies overrides without changing markup.
 */
function cms_fields(): array
{
    return [
        'hero_intro' => ['label' => 'Introduction', 'section' => 'Hero', 'limit' => 650,
            'default' => "Hey, I'm Timo — an ICT student in Eindhoven with a passion for building reliable infrastructure, useful software and smart solutions to real-world problems."],
        'about_lead' => ['label' => 'Opening sentence', 'section' => 'About', 'limit' => 350,
            'default' => 'I enjoy understanding how technology works — and making it work better.'],
        'about_body' => ['label' => 'About paragraph', 'section' => 'About', 'limit' => 1500,
            'default' => "I'm studying HBO-ICT at Fontys University of Applied Sciences in Eindhoven. My interests span cloud platforms, networking, automation and software development. I love turning ideas into practical projects, whether that's a cloud environment, a useful tool or something in my homelab."],
        'about_closing' => ['label' => 'Closing sentence', 'section' => 'About', 'limit' => 500,
            'default' => 'Outside my studies, I keep learning by experimenting, building and refining my own projects.'],
        'project_1_title' => ['label' => 'AWS project title', 'section' => 'Projects', 'limit' => 120,
            'default' => 'AWS Infrastructure & Automation'],
        'project_1_description' => ['label' => 'AWS project description', 'section' => 'Projects', 'limit' => 500,
            'default' => 'Designing and deploying scalable infrastructure on AWS using Terraform, Ansible and CI/CD workflows.'],
        'project_1_url' => ['label' => 'AWS project URL', 'section' => 'Projects', 'type' => 'url', 'limit' => 2048,
            'default' => 'https://github.com/timoc04'],
        'project_1_tags' => ['label' => 'AWS project tags (comma-separated)', 'section' => 'Projects', 'type' => 'tags', 'limit' => 240,
            'default' => 'AWS, Terraform, Ansible, GitHub Actions'],
        'project_2_title' => ['label' => 'Signage project title', 'section' => 'Projects', 'limit' => 120,
            'default' => 'Digital Signage Platform'],
        'project_2_description' => ['label' => 'Signage project description', 'section' => 'Projects', 'limit' => 500,
            'default' => 'A browser-based digital signage solution for managing and displaying content across screens.'],
        'project_2_url' => ['label' => 'Signage project URL', 'section' => 'Projects', 'type' => 'url', 'limit' => 2048,
            'default' => 'https://github.com/timoc04/timoclaessens.nl/tree/main/projects/signage'],
        'project_2_tags' => ['label' => 'Signage project tags (comma-separated)', 'section' => 'Projects', 'type' => 'tags', 'limit' => 240,
            'default' => 'PHP, JavaScript, HTML & CSS'],
        'project_3_title' => ['label' => 'Monitoring API title', 'section' => 'Projects', 'limit' => 120,
            'default' => 'Cloud Monitoring API'],
        'project_3_description' => ['label' => 'Monitoring API description', 'section' => 'Projects', 'limit' => 500,
            'default' => 'A Python Flask API integrated with Azure services, containerised and deployed through automated workflows.'],
        'project_3_url' => ['label' => 'Monitoring API project URL', 'section' => 'Projects', 'type' => 'url', 'limit' => 2048,
            'default' => 'https://github.com/timoc04/monitoring-api'],
        'project_3_tags' => ['label' => 'Monitoring API project tags (comma-separated)', 'section' => 'Projects', 'type' => 'tags', 'limit' => 240,
            'default' => 'Python, Flask, Azure, Docker'],
        'project_4_title' => ['label' => 'Homelab project title', 'section' => 'Projects', 'limit' => 120,
            'default' => 'Homelab & Self-Hosting'],
        'project_4_description' => ['label' => 'Homelab project description', 'section' => 'Projects', 'limit' => 500,
            'default' => 'Hands-on exploration of virtualisation, Linux, networking and self-hosted services with Proxmox.'],
        'project_4_url' => ['label' => 'Homelab project URL', 'section' => 'Projects', 'type' => 'url', 'limit' => 2048,
            'default' => 'https://github.com/timoc04'],
        'project_4_tags' => ['label' => 'Homelab project tags (comma-separated)', 'section' => 'Projects', 'type' => 'tags', 'limit' => 240,
            'default' => 'Proxmox, Linux, Networking'],
        'expertise_intro' => ['label' => 'Expertise introduction', 'section' => 'Expertise', 'limit' => 350,
            'default' => 'A few technologies and disciplines I enjoy working with.'],
        'contact_intro' => ['label' => 'Contact introduction', 'section' => 'Contact', 'limit' => 500,
            'default' => 'Have a question, an idea or just want to connect? Feel free to reach out.'],
    ];
}

function cms_defaults(): array
{
    $defaults = [];
    foreach (cms_fields() as $name => $field) {
        $defaults[$name] = $field['default'];
    }
    return $defaults;
}

/** Ensure storage is not inside the website's publicly served directory. */
function cms_storage_directory(): string
{
    $directory = CMS_STORAGE_DIR;
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('CMS data directory could not be created.');
    }
    $resolved = realpath($directory);
    if ($resolved === false) {
        throw new RuntimeException('CMS data directory is inaccessible.');
    }
    $webRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($webRoot !== false && ($resolved === $webRoot ||
        str_starts_with($resolved, rtrim($webRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('CMS data directory must be outside the web root.');
    }
    return $resolved;
}

function cms_content_path(): string
{
    return cms_storage_directory() . '/content.json';
}

function cms_validate_content(array $data): array
{
    $validated = [];
    foreach (cms_fields() as $key => $field) {
        if (!array_key_exists($key, $data) || !is_string($data[$key])) {
            throw new InvalidArgumentException('Missing or invalid field: ' . $key);
        }
        $value = trim($data[$key]);
        if ($value === '' || strlen($value) > $field['limit']) {
            throw new InvalidArgumentException('Field length is invalid: ' . $key);
        }
        if (($field['type'] ?? '') === 'url') {
            $parts = parse_url($value);
            if ($parts === false || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['https', 'http'], true)
                || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                || preg_match('/[\\x00-\\x20\\x7f]/', $value)) {
                throw new InvalidArgumentException('Invalid HTTP(S) project URL: ' . $key);
            }
        }
        if (($field['type'] ?? '') === 'tags') {
            $tags = array_map('trim', explode(',', $value));
            if (count($tags) > 6 || in_array('', $tags, true)) {
                throw new InvalidArgumentException('Use one to six nonempty comma-separated tags: ' . $key);
            }
            foreach ($tags as $tag) {
                if (strlen($tag) > 40) {
                    throw new InvalidArgumentException('Tag exceeds 40 bytes: ' . $key);
                }
            }
            $value = implode(', ', $tags);
        }
        $validated[$key] = $value;
    }
    return $validated;
}

function cms_read_content(): array
{
    $path = cms_content_path();
    if (!file_exists($path)) {
        return cms_defaults();
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Could not read CMS content.');
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Invalid CMS content JSON.', 0, $exception);
    }
    if (!is_array($data)) {
        throw new RuntimeException('CMS content must be a JSON object.');
    }
    return cms_validate_content(array_replace(cms_defaults(), $data));
}

/** Atomically replace JSON so site visitors never read a half-written file. */
function cms_save_content(array $data): void
{
    $validated = cms_validate_content($data);
    $directory = cms_storage_directory();
    $path = $directory . '/content.json';
    $json = json_encode($validated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $lock = fopen($directory . '/content.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('Could not acquire CMS storage lock.');
    }
    $temporary = false;
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Could not lock CMS content.');
        }
        $temporary = tempnam($directory, '.cms-');
        if ($temporary === false || file_put_contents($temporary, $json . "\n") === false) {
            throw new RuntimeException('Could not write CMS content.');
        }
        chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Could not replace CMS content.');
        }
        $temporary = false;
    } finally {
        if ($temporary !== false && is_file($temporary)) {
            unlink($temporary);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
