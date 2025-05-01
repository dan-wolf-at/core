<?php

declare(strict_types=1);

if (!function_exists('uncamelize')) {
    /**
     * @return string|string[]|null
     */
    function uncamelize(string $word, string $splitter = ' ', bool $uppercase = true): array|string|null
    {
        $word = preg_replace(
            '/(?!^)[[:upper:]][[:lower:]]/',
            '$0',
            (string) preg_replace('/(?!^)[[:upper:]]+/', $splitter . '$0', (string) $word),
        );

        return $uppercase ? ucwords((string) $word) : $word;
    }
}
