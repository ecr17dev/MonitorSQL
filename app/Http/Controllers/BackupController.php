<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BackupController extends Controller
{
    public function __invoke(): Response
    {
        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path): bool => ! str_ends_with($path, '/'))
            ->map(function (string $path): array {
                $size = null;
                $lastModified = null;

                try {
                    $size = Storage::disk('local')->size($path);
                    $lastModified = Storage::disk('local')->lastModified($path);
                } catch (Throwable) {
                    // Ignore metadata failures for partially available files.
                }

                return [
                    'name' => basename($path),
                    'path' => $path,
                    'size_bytes' => $size,
                    'last_modified_at' => $lastModified !== null ? now()->setTimestamp($lastModified)->toDateTimeString() : null,
                ];
            })
            ->sortByDesc('last_modified_at')
            ->values();

        return Inertia::render('backups/Index', [
            'files' => $files,
            'total_files' => $files->count(),
        ]);
    }
}
