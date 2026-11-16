<?php
declare(strict_types=1);

/** Read screen settings; malformed JSON must not be silently overwritten. */
function signage_load_settings(string $file): array
{
    if (!is_file($file)) return [];
    $contents = file_get_contents($file);
    if ($contents === false) throw new RuntimeException('Instellingen lezen mislukt.');
    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Instellingenbestand bevat ongeldige JSON.', 0, $exception);
    }
    if (!is_array($decoded)) throw new RuntimeException('Instellingenbestand bevat geen geldig object.');
    return $decoded;
}

/** Write settings atomically: readers never see a partially written JSON file. */
function signage_save_settings(string $file, array $settings): void
{
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $directory = dirname($file);
    $temporary = tempnam($directory, '.settings-');
    if ($temporary === false) throw new RuntimeException('Tijdelijk instellingenbestand maken mislukt.');
    try {
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException('Instellingen opslaan mislukt.');
        }
        if (!rename($temporary, $file)) {
            throw new RuntimeException('Instellingen vervangen mislukt.');
        }
    } finally {
        if (is_file($temporary)) unlink($temporary);
    }
}
