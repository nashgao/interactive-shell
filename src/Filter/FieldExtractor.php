<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Filter;

/**
 * Extract values from context using dot notation paths.
 *
 * Protocol-specific implementations should extend this class
 * and implement buildContext() to extract fields from their message format.
 */
class FieldExtractor
{
    /**
     * Extract value from context using dot notation path.
     *
     * @param array<string, mixed> $context Context with extracted fields
     * @param string $path Dot notation path (e.g., 'payload.temperature')
     * @return mixed Extracted value or null if path doesn't exist
     */
    public static function extract(array $context, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $context;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }
}
