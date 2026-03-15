<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Support;

final class Format
{
    public const MARKDOWN = 'markdown';

    public const JSON = 'json';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::MARKDOWN, self::JSON];
    }

    public static function mimeType(string $format): ?string
    {
        return match ($format) {
            self::MARKDOWN => 'text/markdown; charset=UTF-8',
            self::JSON => 'application/json',
            default => null,
        };
    }

    public static function acceptValue(string $format): ?string
    {
        return match ($format) {
            self::MARKDOWN => 'text/markdown',
            self::JSON => 'application/json',
            default => null,
        };
    }
}
