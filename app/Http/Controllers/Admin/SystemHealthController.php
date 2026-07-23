<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Server / infrastructure health for operators (DollarHub structure — controller +
 * Blade). Each check returns ok|warn|down + a short detail, so the page is a live
 * traffic-light view of the platform's dependencies. Read-only.
 */
class SystemHealthController extends Controller
{
    public function index(): View
    {
        $this->guardAccess();

        return view('admin.system.health', [
            'checks' => $this->checks(),
            'app' => $this->appInfo(),
        ]);
    }

    /** @return list<array{key:string,label:string,status:string,detail:string}> */
    private function checks(): array
    {
        return [
            $this->timed('database', 'Database', function () {
                DB::connection()->select('select 1');

                return ['ok', DB::connection()->getDriverName().' · '.DB::connection()->getDatabaseName()];
            }),
            $this->timed('redis', 'Redis', function () {
                $pong = Redis::connection()->ping();

                return [$pong ? 'ok' : 'down', 'PING '.(is_string($pong) ? $pong : 'OK')];
            }),
            $this->timed('cache', 'Cache', function () {
                $key = 'health:'.Str::random(8);
                Cache::put($key, '1', 5);
                $ok = Cache::get($key) === '1';
                Cache::forget($key);

                return [$ok ? 'ok' : 'down', config('cache.default').' store read/write'];
            }),
            $this->timed('queue', 'Queue', function () {
                $pending = Queue::size();
                $failed = DB::table('failed_jobs')->count();
                $status = $failed > 0 ? 'warn' : ($pending > 1000 ? 'warn' : 'ok');

                return [$status, "{$pending} pending · {$failed} failed"];
            }),
            $this->timed('storage', 'Storage', function () {
                $path = storage_path('logs');
                $writable = is_writable($path);
                $freeGb = @disk_free_space($path);
                $free = $freeGb !== false ? round($freeGb / 1073741824, 1).' GB free' : 'unknown';

                return [$writable ? 'ok' : 'down', ($writable ? 'writable · ' : 'NOT writable · ').$free];
            }),
            $this->timed('scheduler', 'Queue worker', function () {
                // A recent job heartbeat implies a worker is draining the queue.
                $recent = DB::table('jobs')->where('reserved_at', '>=', now()->subMinutes(5)->timestamp)->exists();
                $pending = Queue::size();

                return [$pending === 0 || $recent ? 'ok' : 'warn', $recent ? 'processing recently' : ($pending > 0 ? 'jobs waiting — is `horizon`/`queue:work` running?' : 'idle')];
            }),
        ];
    }

    /** @return array<string, string> */
    private function appInfo(): array
    {
        return [
            'Environment' => (string) app()->environment(),
            'Debug mode' => config('app.debug') ? 'on' : 'off',
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Queue connection' => (string) config('queue.default'),
            'Cache store' => (string) config('cache.default'),
        ];
    }

    /**
     * Run a check, capturing failures as a "down" status and timing it.
     *
     * @param  callable():array{0:string,1:string}  $fn
     * @return array{key:string,label:string,status:string,detail:string,ms:int}
     */
    private function timed(string $key, string $label, callable $fn): array
    {
        $start = microtime(true);
        try {
            [$status, $detail] = $fn();
        } catch (Throwable $e) {
            $status = 'down';
            $detail = mb_substr($e->getMessage(), 0, 120);
        }

        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
            'ms' => (int) ((microtime(true) - $start) * 1000),
        ];
    }

    private function guardAccess(): void
    {
        abort_unless(
            auth('admin')->user()?->can('view-system-health') || auth('admin')->user()?->hasRole('super-admin'),
            403,
        );
    }
}
