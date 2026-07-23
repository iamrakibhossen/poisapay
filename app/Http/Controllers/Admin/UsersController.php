<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\KycProfile;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin user management (DollarHub parity — controller + Blade, no Livewire).
 * List, detail (show), profile/KYC edit, ledger-backed balance adjustment,
 * freeze money-movement and impersonate. Every mutation is permission-gated and
 * written to the activity log; balance changes post a balanced double-entry so
 * the ledger's solvency invariant is preserved.
 */
class UsersController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    private function authorizeAny(string ...$permissions): void
    {
        $admin = auth('admin')->user();
        abort_unless(
            $admin->hasRole('super-admin') || collect($permissions)->contains(fn ($p) => $admin->can($p)),
            403,
        );
    }

    public function index(Request $request): View
    {
        $this->authorizeAny('view-users', 'manage-users');

        $search = (string) $request->query('search', '');
        $tier = (string) $request->query('tier', 'all');

        $users = User::query()
            ->when($tier !== 'all', fn ($q) => $q->where('kyc_tier', $tier))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')
                ->when(ctype_digit($search), fn ($u2) => $u2->orWhere('uid', $search))))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'search' => $search,
            'tier' => $tier,
            'tabs' => [
                'all' => User::count(),
                'unverified' => User::where('kyc_tier', KycTier::Unverified->value)->count(),
                'basic' => User::where('kyc_tier', KycTier::Basic->value)->count(),
                'full' => User::where('kyc_tier', KycTier::Full->value)->count(),
            ],
        ]);
    }

    public function show(User $user): View
    {
        $this->authorizeAny('view-users', 'manage-users');

        $user->load([
            'ledgerAccounts' => fn ($q) => $q
                ->whereIn('type', [LedgerAccountType::UserAvailable->value, LedgerAccountType::UserLocked->value])
                ->with(['asset', 'balance']),
        ]);

        $kyc = KycProfile::where('user_id', $user->id)->latest()->first();

        // Group available + locked balances per asset (only assets the user touches).
        $balances = $user->ledgerAccounts
            ->groupBy('asset_id')
            ->map(function ($accounts) {
                $asset = $accounts->first()->asset;
                $available = $accounts->firstWhere('type', LedgerAccountType::UserAvailable)?->money() ?? $asset->zero();
                $locked = $accounts->firstWhere('type', LedgerAccountType::UserLocked)?->money() ?? $asset->zero();

                return ['asset' => $asset, 'available' => $available, 'locked' => $locked];
            })
            ->filter(fn ($row) => ! $row['available']->isZero() || ! $row['locked']->isZero())
            ->sortBy(fn ($row) => $row['asset']->symbol)
            ->values();

        $recentActivity = AuditLog::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'balances' => $balances,
            'kyc' => $kyc,
            'recentActivity' => $recentActivity,
            'assets' => Asset::orderBy('symbol')->get(),
            'stats' => [
                'deposits' => $user->deposits()->count(),
                'withdrawals' => $user->withdrawals()->count(),
                'transfers' => $user->sentTransfers()->count(),
                'cards' => $user->cards()->count(),
            ],
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorizeAny('manage-users');

        return view('admin.users.edit', [
            'user' => $user,
            'tiers' => KycTier::cases(),
            'statuses' => KycStatus::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAny('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'base_currency' => ['required', 'string', 'size:3'],
            'kyc_tier' => ['required', Rule::enum(KycTier::class)],
            'kyc_status' => ['required', Rule::enum(KycStatus::class)],
            'email_verified' => ['nullable', 'boolean'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'base_currency' => strtoupper($data['base_currency']),
            'kyc_tier' => $data['kyc_tier'],
            'kyc_status' => $data['kyc_status'],
        ]);

        if ($request->boolean('email_verified')) {
            $user->email_verified_at ??= now();
        } else {
            $user->email_verified_at = null;
        }

        $user->save();

        ActivityLogger::log('user.updated', $user, ['email' => $user->email], "Admin updated user {$user->email}");

        return redirect()->route('admin.users.show', $user)->with('success', 'User updated successfully.');
    }

    /**
     * Credit or debit a user's available balance for a chosen asset. Posts a
     * balanced entry (treasury:pending ⇄ user:available) so double-entry and
     * solvency are preserved; debits cannot exceed the available balance.
     */
    public function adjustBalance(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAny('adjust-balance');

        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $asset = Asset::findOrFail($data['asset_id']);
        $amount = Money::ofDecimal($data['amount'], $asset->decimals, $asset->symbol);
        $isCredit = $data['type'] === 'credit';

        $available = $this->accounts->forUser($user, LedgerAccountType::UserAvailable, $asset->id);
        $treasury = $this->accounts->system(LedgerAccountType::TreasuryPending, $asset->id);

        if (! $isCredit) {
            $current = $this->ledger->availableBalance($user, $asset->id);
            if ($current->isLessThan($amount)) {
                throw ValidationException::withMessages([
                    'amount' => "Amount exceeds the available balance ({$current->format()}).",
                ]);
            }
        }

        $lines = $isCredit
            ? [PostingLine::debit($treasury->id, $asset->id, $amount), PostingLine::credit($available->id, $asset->id, $amount)]
            : [PostingLine::debit($available->id, $asset->id, $amount), PostingLine::credit($treasury->id, $asset->id, $amount)];

        $this->ledger->post(new EntryData(
            type: $isCredit ? 'admin.balance.credit' : 'admin.balance.debit',
            idempotencyKey: 'admin-adjust:'.$user->id.':'.$asset->id.':'.now()->timestamp.':'.bin2hex(random_bytes(4)),
            lines: $lines,
            memo: 'Manual '.($isCredit ? 'credit' : 'debit').' — '.$data['reason'],
            metadata: ['user_id' => $user->id, 'asset_id' => $asset->id, 'reason' => $data['reason']],
        ));

        ActivityLogger::log(
            $isCredit ? 'balance.credited' : 'balance.debited',
            $user,
            ['asset' => $asset->symbol, 'amount' => $amount->baseString(), 'reason' => $data['reason']],
            'Admin '.($isCredit ? 'credited' : 'debited').' '.$amount->format().($isCredit ? ' to ' : ' from ').$user->name,
        );

        return back()->with('success', 'Balance '.($isCredit ? 'credited' : 'debited').' successfully ('.$amount->format().').');
    }

    public function toggleFreeze(string $id): RedirectResponse
    {
        $this->authorizeAny('freeze-users');

        $user = User::findOrFail($id);
        $user->update(['is_frozen' => ! $user->is_frozen]);

        ActivityLogger::log(
            $user->is_frozen ? 'user.frozen' : 'user.unfrozen',
            $user,
            [],
            ($user->is_frozen ? 'Froze ' : 'Unfroze ').$user->name,
        );

        return back()->with('success', $user->is_frozen
            ? "{$user->name} has been frozen."
            : "{$user->name} has been unfrozen.");
    }
}
