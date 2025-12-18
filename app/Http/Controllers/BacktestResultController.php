<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BacktestResultController extends Controller
{
    private function disk(): string
    {
        return (string) config('backtest_results.disk', 'local');
    }

    private function directory(): string
    {
        return trim((string) config('backtest_results.directory', 'backtest-results'), '/');
    }

    private function ensureDummyFiles(): void
    {
        $disk = Storage::disk($this->disk());
        $directory = $this->directory();

        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        if (count($disk->files($directory)) > 0) {
            return;
        }

        $timestamp = now()->toDateTimeString();

        for ($i = 1; $i <= 10; $i++) {
            $filename = "file{$i}.txt";
            $content = implode("\n", [
                "Dummy Backtest Result #{$i}",
                "Generated at: {$timestamp}",
                "",
                "This is placeholder content.",
                "Replace these files with real backtest outputs anytime.",
            ]);

            $disk->put("{$directory}/{$filename}", $content);
        }
    }

    public function index()
    {
        $this->ensureDummyFiles();

        $disk = Storage::disk($this->disk());
        $directory = $this->directory();

        $files = collect($disk->files($directory))
            ->map(fn (string $path) => basename($path))
            ->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))
            ->values();

        return view('backtest-result.dashboard', [
            'directory' => $directory,
            'files' => $files,
        ]);
    }

    public function show(string $file)
    {
        $this->ensureDummyFiles();

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk($this->disk());
        $directory = $this->directory();
        $path = "{$directory}/{$file}";

        if (!$disk->exists($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        try {
            $content = $disk->get($path);
        } catch (FileNotFoundException) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $size = $disk->size($path);
        $lastModified = $disk->lastModified($path);

        return view('backtest-result.show', [
            'directory' => $directory,
            'file' => $file,
            'content' => Str::limit($content, 2_000_000, "\n\n…(truncated)"),
            'size' => $size,
            'lastModified' => $lastModified,
        ]);
    }
}

