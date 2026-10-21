<?php

namespace App\Support;

use Dotenv\Dotenv;
use RuntimeException;

final class EnvironmentFile
{
    public static function update(string $path, array $changes): void
    {
        $settings = Dotenv::parse(file_get_contents($path));
        foreach ($changes as $key => $value) {
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new RuntimeException('Invalid environment setting.');
            }
            if ($value === null) { unset($settings[$key]); continue; }
            if (!is_string($value) || strpos($value, "\0") !== false) { throw new RuntimeException('Invalid environment setting.'); }
            $settings[$key] = $value;
        }
        $contents = "# Environment settings. Keep this file private and out of source control.\n";
        foreach ($settings as $key => $value) {
            $contents .= $key . '="' . str_replace(["\\", '"', '$', "\r", "\n", "\t"],
                ["\\\\", '\\"', '\\$', '\\r', '\\n', '\\t'], $value) . '"' . "\n";
        }
        if (Dotenv::parse($contents) !== $settings) {
            throw new RuntimeException('Environment settings failed round-trip validation.');
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            // Preserve the original Windows file ACL rather than replacing it
            // with a temporary file inheriting broader directory permissions.
            if (file_put_contents($path, $contents, LOCK_EX) === false) { throw new RuntimeException('Unable to save environment settings.'); }
            return;
        }
        $temporary = tempnam(dirname($path), '.env-write-');
        try {
            if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false
                || !chmod($temporary, fileperms($path) & 0777)
                || !rename($temporary, $path)) {
                throw new RuntimeException('Unable to save environment settings.');
            }
        } finally {
            if ($temporary !== false && is_file($temporary)) { unlink($temporary); }
        }
    }
}
