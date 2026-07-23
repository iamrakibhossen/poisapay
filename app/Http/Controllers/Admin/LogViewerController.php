<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Application log viewer for operators — parses the daily/single Laravel log into
 * structured rows (level, time, message) with a level filter, plus download + clear.
 * Read from the newest entries; never exposes anything outside storage/logs.
 */
class LogViewerController extends Controller
{
    private const MAX_ENTRIES = 300;

    public function index(Request $request): View
    {
        $this->guardAccess();

        $level = strtolower((string) $request->query('level', 'all'));
        $entries = $this->parse($this->logPath(), $level);

        return view('admin.system.logs', [
            'entries' => $entries,
            'level' => $level,
            'levels' => ['all', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            'sizeKb' => is_file($this->logPath()) ? (int) round(filesize($this->logPath()) / 1024) : 0,
            'exists' => is_file($this->logPath()),
        ]);
    }

    public function download(): StreamedResponse
    {
        $this->guardAccess();
        abort_unless(is_file($this->logPath()), 404);

        return response()->streamDownload(function () {
            readfile($this->logPath());
        }, 'laravel-'.now()->format('Ymd-His').'.log', ['Content-Type' => 'text/plain']);
    }

    public function clear(): RedirectResponse
    {
        $this->guardManage();

        if (is_file($this->logPath())) {
            file_put_contents($this->logPath(), '');
        }
        ActivityLogger::log('system.logs.cleared', null, ['file' => basename($this->logPath())]);

        return back()->with('success', 'Log file cleared.');
    }

    /**
     * Parse the most recent entries, newest first, optionally filtered by level.
     *
     * @return list<array{time:string,level:string,message:string}>
     */
    private function parse(string $path, string $level): array
    {
        if (! is_file($path)) {
            return [];
        }

        // Read a bounded tail so a huge log never blows memory.
        $lines = $this->tail($path, 4000);
        $pattern = '/^\[(?<time>\d{4}-\d{2}-\d{2}[ T][\d:]+)[^\]]*\]\s+\w+\.(?<level>[A-Z]+):\s?(?<msg>.*)$/';

        $entries = [];
        $current = null;
        foreach ($lines as $line) {
            if (preg_match($pattern, $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                }
                $current = [
                    'time' => $m['time'],
                    'level' => strtolower($m['level']),
                    'message' => trim($m['msg']),
                ];
            } elseif ($current !== null && trim($line) !== '') {
                // Continuation (stack trace) — keep the first couple of lines only.
                if (mb_strlen($current['message']) < 500) {
                    $current['message'] .= "\n".rtrim($line);
                }
            }
        }
        if ($current) {
            $entries[] = $current;
        }

        $entries = array_reverse($entries);
        if ($level !== 'all') {
            $entries = array_values(array_filter($entries, fn ($e) => $e['level'] === $level));
        }

        return array_slice($entries, 0, self::MAX_ENTRIES);
    }

    /** Read approximately the last $maxLines lines of a file. @return list<string> */
    private function tail(string $path, int $maxLines): array
    {
        $size = (int) filesize($path);
        if ($size === 0) {
            return [];   // empty log — nothing to read (fread requires length > 0)
        }

        $chunk = (int) min($size, 2_000_000); // cap at ~2MB tail
        $fh = fopen($path, 'rb');
        fseek($fh, -$chunk, SEEK_END);
        $data = (string) fread($fh, $chunk);
        fclose($fh);

        $lines = explode("\n", $data);

        return array_slice($lines, -$maxLines);
    }

    private function logPath(): string
    {
        // Prefer the single-file log; fall back to today's daily file.
        $single = storage_path('logs/laravel.log');
        if (is_file($single)) {
            return $single;
        }

        return storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
    }

    private function guardAccess(): void
    {
        abort_unless(
            auth('admin')->user()?->can('view-system-health') || auth('admin')->user()?->hasRole('super-admin'),
            403,
        );
    }

    private function guardManage(): void
    {
        abort_unless(
            auth('admin')->user()?->can('manage-developer') || auth('admin')->user()?->hasRole('super-admin'),
            403,
        );
    }
}
