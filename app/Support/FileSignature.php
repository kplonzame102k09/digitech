<?php

namespace App\Support;

/**
 * Detects a file's real extension from its magic bytes (content signature)
 * without relying on the PHP fileinfo extension or Symfony's MIME guessers.
 * This keeps upload validation working on shared hosts (e.g. InfinityFree)
 * where php_fileinfo may be disabled and would otherwise throw a
 * LogicException and produce a 500 during validation/store().
 */
class FileSignature
{
    /**
     * Map of supported signatures to the extension they identify.
     *
     * @var array<string, list<callable(string): bool>>
     */
    protected const CHECKERS = [
        'jpg' => [self::class.'::isJpeg'],
        'png' => [self::class.'::isPng'],
        'webp' => [self::class.'::isWebp'],
        'gif' => [self::class.'::isGif'],
        'pdf' => [self::class.'::isPdf'],
    ];

    /**
     * Detect the extension from a file's contents, or null when the content
     * does not match any known signature.
     */
    public static function detect(string $content): ?string
    {
        foreach (self::CHECKERS as $extension => $checkers) {
            foreach ($checkers as $checker) {
                if ($checker($content)) {
                    return $extension;
                }
            }
        }

        return null;
    }

    /**
     * Detect the extension from a filesystem path's contents.
     */
    public static function detectFile(string $path): ?string
    {
        $content = @file_get_contents($path, false, null, 0, 1024);

        if ($content === false) {
            return null;
        }

        return self::detect($content);
    }

    public static function isJpeg(string $content): bool
    {
        return str_starts_with($content, "\xFF\xD8\xFF");
    }

    public static function isPng(string $content): bool
    {
        return str_starts_with($content, "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A");
    }

    public static function isWebp(string $content): bool
    {
        return strlen($content) >= 12
            && str_starts_with($content, 'RIFF')
            && substr($content, 8, 4) === 'WEBP';
    }

    public static function isGif(string $content): bool
    {
        return str_starts_with($content, 'GIF87a') || str_starts_with($content, 'GIF89a');
    }

    public static function isPdf(string $content): bool
    {
        return str_starts_with($content, '%PDF-');
    }
}