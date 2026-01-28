<?php
// Load environment variables from .env file
// This file is committed to git but the .env file itself is not (added to .gitignore)

// Try multiple possible locations for .env file
$possible_paths = [
    __DIR__ . '/../../.env',           // /var/www/brokeant/.env
    dirname(__DIR__, 2) . '/.env',     // Same as above, alternative path
    '/var/www/brokeant/.env',          // Absolute path for VPS
];

$env_file = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $env_file = $path;
        break;
    }
}

if ($env_file && is_readable($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Remove surrounding quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }
                if (!empty($key) && !empty($value)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }
    }
}
?>
