<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve files from the "public" disk at /storage/{path} through PHP so the
 * deployment does not depend on a web-server rewrite / symlink (InfinityFree
 * Apache loops on a naive ^storage/(.+)$ rewrite and has no symlinks).
 */
class StorageFileController extends Controller
{
    public function show(Request $request, string $path): BinaryFileResponse
    {
        $relative = $this->normalizePath($path);

        if ($relative === null) {
            abort(404);
        }

        $root = realpath(storage_path('app/public'));

        if ($root === false) {
            abort(404);
        }

        $absolute = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if ($absolute === false
            || ! Str::startsWith($absolute, $root.DIRECTORY_SEPARATOR)
            || ! is_file($absolute)) {
            abort(404);
        }

        return response()->file($absolute, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function normalizePath(string $path): ?string
    {
        $path = urldecode($path);
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        $segments = explode('/', str_replace('\\', '/', $path));

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || Str::startsWith($segment, '.')) {
                return null;
            }
        }

        return implode('/', $segments);
    }
}