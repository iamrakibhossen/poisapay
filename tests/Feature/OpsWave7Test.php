<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ops\BackupService;
use App\Domain\Reconciliation\ReconciliationService;
use App\Enums\LedgerAccountType;
use App\Models\LoginHistory;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

// ---------------------------------------------------------------------------
// 7A Backups — retention pruning
// ---------------------------------------------------------------------------
it('prunes database backups older than the retention window', function () {
    $svc = app(BackupService::class);
    $dir = $svc->directory();
    @mkdir($dir, 0755, true);

    $old = $dir.'/poisapay-20000101-000000.sql.gz';
    $new = $dir.'/poisapay-'.now()->format('Ymd-His').'.sql.gz';
    file_put_contents($old, 'x');
    file_put_contents($new, 'x');
    touch($old, now()->subDays(30)->getTimestamp());

    $removed = $svc->prune(14);

    expect($removed)->toBeGreaterThanOrEqual(1)
        ->and(file_exists($old))->toBeFalse()
        ->and(file_exists($new))->toBeTrue();

    @unlink($new);
});

// ---------------------------------------------------------------------------
// 7A Data retention command
// ---------------------------------------------------------------------------
it('prunes stale login history and acknowledged security events', function () {
    $user = User::factory()->create();

    $oldLogin = LoginHistory::create(['user_id' => $user->id, 'ip_address' => '1.1.1.1']);
    $oldLogin->forceFill(['created_at' => now()->subDays(200)])->save();
    $freshLogin = LoginHistory::create(['user_id' => $user->id, 'ip_address' => '2.2.2.2']);

    $oldEvent = SecurityEvent::create(['user_id' => $user->id, 'type' => 'new_device', 'severity' => 'info', 'acknowledged_at' => now()->subDays(200)]);
    $oldEvent->forceFill(['created_at' => now()->subDays(200)])->save();

    Artisan::call('poisapay:retention', ['--days' => 90]);

    expect(LoginHistory::whereKey($oldLogin->id)->exists())->toBeFalse()
        ->and(LoginHistory::whereKey($freshLogin->id)->exists())->toBeTrue()
        ->and(SecurityEvent::whereKey($oldEvent->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// 7B OpenAPI
// ---------------------------------------------------------------------------
it('serves the OpenAPI spec and Swagger UI', function () {
    $this->getJson('/api/openapi.json')->assertOk()->assertJsonFragment(['openapi' => '3.0.3'])
        ->assertJsonPath('info.title', 'PoisaPay REST API');

    $this->get('/api/docs')->assertOk()->assertSee('swagger-ui', false);
});

// ---------------------------------------------------------------------------
// 7D Insolvency alerting
// ---------------------------------------------------------------------------
it('raises a critical insolvency signal when treasury < liability', function () {
    $asset = testAsset('USDT', 6, 'tron');
    $user = User::factory()->create();

    // Credit a user liability with NO treasury backing (debit a non-treasury account),
    // forcing the solvency invariant to fail.
    $resolver = app(AccountResolver::class);
    app(LedgerService::class)->post(new EntryData(
        type: 'test.imbalance',
        idempotencyKey: 'insolv-1',
        lines: [
            PostingLine::debit($resolver->system(LedgerAccountType::FeeIncome, $asset->id)->id, $asset->id, '1000'),
            PostingLine::credit($resolver->forUser($user, LedgerAccountType::UserAvailable, $asset->id)->id, $asset->id, '1000'),
        ],
    ));

    $run = app(ReconciliationService::class)->runForAsset($asset->fresh());

    expect($run->is_solvent)->toBeFalse()
        ->and(SecurityEvent::where('type', 'insolvency')->where('severity', 'critical')->exists())->toBeTrue();
});
