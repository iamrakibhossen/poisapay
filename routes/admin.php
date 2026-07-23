<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminExchangeController;
use App\Http\Controllers\Admin\AdministratorsController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminRewardsController;
use App\Http\Controllers\Admin\AdminWithdrawalMethodsController;
use App\Http\Controllers\Admin\AssetsController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BlockchainHealthController;
use App\Http\Controllers\Admin\CardDisputesController;
use App\Http\Controllers\Admin\CardMonitorController;
use App\Http\Controllers\Admin\CardProvidersController;
use App\Http\Controllers\Admin\CardsController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\ComplianceExportController;
use App\Http\Controllers\Admin\ComplianceListController;
use App\Http\Controllers\Admin\CustodyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepositMethodsController;
use App\Http\Controllers\Admin\DepositsController;
use App\Http\Controllers\Admin\FaqsController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\KycQueueController;
use App\Http\Controllers\Admin\LedgerController;
use App\Http\Controllers\Admin\MerchantsController;
use App\Http\Controllers\Admin\MessagingController;
use App\Http\Controllers\Admin\P2pController;
use App\Http\Controllers\Admin\P2pPaymentMethodController;
use App\Http\Controllers\Admin\PagesController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\RevenueTransactionsController;
use App\Http\Controllers\Admin\RevenueWalletController;
use App\Http\Controllers\Admin\RevenueWithdrawalsController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\RpcEndpointsController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SimulationController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\TransfersController;
use App\Http\Controllers\Admin\TreasuryController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\WalletsController;
use App\Http\Controllers\Admin\WithdrawalsController;
use App\Http\Controllers\ImpersonationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin (operator) routes
|--------------------------------------------------------------------------
| A self-contained route file for the operator console, kept separate from
| the consumer app (DollarHub-style). All routes are prefixed with `admin`,
| named `admin.*`, and — apart from auth — guarded by the `admin` session
| guard via the `operator` middleware.
*/

Route::prefix('admin')->name('admin.')->group(function () {
    // Public operator auth
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

    Route::post('/logout', function () {
        Auth::guard('admin')->logout();
        request()->session()->regenerate();

        return redirect()->route('admin.login');
    })->name('logout');

    // Guarded console
    Route::middleware('operator')->group(function () {
        // ── Converted to controllers (Batch A: read/simple) ──
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');
        Route::get('/treasury', [TreasuryController::class, 'index'])->name('treasury');
        Route::post('/treasury/reconcile', [TreasuryController::class, 'reconcile'])->name('treasury.reconcile');
        Route::get('/reports', [FinancialReportController::class, 'index'])->name('reports');
        Route::get('/reports/export', [FinancialReportController::class, 'export'])->name('reports.export');
        Route::get('/wallets', [WalletsController::class, 'index'])->name('wallets');
        Route::get('/blockchain-health', [BlockchainHealthController::class, 'index'])->name('blockchain-health');
        Route::post('/blockchain-health/check', [BlockchainHealthController::class, 'runHealthCheck'])->name('blockchain-health.check');
        Route::post('/blockchain-health/tick', [BlockchainHealthController::class, 'runMonitorTick'])->name('blockchain-health.tick');
        Route::post('/blockchain-health/reconcile', [BlockchainHealthController::class, 'runReconciliation'])->name('blockchain-health.reconcile');
        Route::get('/simulation', [SimulationController::class, 'index'])->name('simulation');
        Route::post('/simulation/tick', [SimulationController::class, 'runChainTick'])->name('simulation.tick');
        Route::post('/simulation/deposit', [SimulationController::class, 'simulateDeposit'])->name('simulation.deposit');
        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/{id}/read', [AdminNotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [AdminNotificationController::class, 'markAllRead'])->name('notifications.read-all');

        // ── Converted to controllers (Batch B: queues) ──
        Route::get('/kyc', [KycQueueController::class, 'index'])->name('kyc');
        Route::get('/kyc/{id}', [KycQueueController::class, 'show'])->name('kyc.show');
        Route::get('/kyc/{id}/file/{slot}', [KycQueueController::class, 'file'])->name('kyc.file');
        Route::post('/kyc/{id}/approve', [KycQueueController::class, 'approve'])->name('kyc.approve');
        Route::post('/kyc/{id}/reject', [KycQueueController::class, 'reject'])->name('kyc.reject');
        Route::get('/withdrawals', [WithdrawalsController::class, 'index'])->name('withdrawals');
        Route::post('/withdrawals/{id}/approve', [WithdrawalsController::class, 'approve'])->name('withdrawals.approve');
        Route::post('/withdrawals/{id}/cancel', [WithdrawalsController::class, 'cancel'])->name('withdrawals.cancel');
        Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger');
        Route::post('/ledger/{id}/reverse', [LedgerController::class, 'reverse'])->name('ledger.reverse');
        Route::get('/transfers', [TransfersController::class, 'index'])->name('transfers');

        // ── Compliance lists + exports (Wave 5) ──
        Route::get('/compliance/export/cases', [ComplianceExportController::class, 'cases'])->name('compliance.export.cases');
        Route::get('/compliance/export/alerts', [ComplianceExportController::class, 'alerts'])->name('compliance.export.alerts');
        Route::get('/compliance-lists', [ComplianceListController::class, 'index'])->name('compliance-lists');
        Route::post('/compliance-lists', [ComplianceListController::class, 'store'])->name('compliance-lists.store');
        Route::delete('/compliance-lists/{id}', [ComplianceListController::class, 'destroy'])->name('compliance-lists.destroy');

        // ── Feature flags (Wave 6) ──
        Route::get('/feature-flags', [FeatureFlagController::class, 'index'])->name('feature-flags');
        Route::post('/feature-flags/toggle', [FeatureFlagController::class, 'toggle'])->name('feature-flags.toggle');

        // ── Support tickets (Wave 6) ──
        Route::get('/support', [SupportController::class, 'index'])->name('support');
        Route::get('/support/{id}', [SupportController::class, 'show'])->name('support.show');
        Route::post('/support/{id}/reply', [SupportController::class, 'reply'])->name('support.reply');
        Route::post('/support/{id}/status', [SupportController::class, 'updateStatus'])->name('support.status');
        Route::post('/support/{id}/assign', [SupportController::class, 'assign'])->name('support.assign');

        // ── Security monitoring (Wave 4) ──
        Route::get('/security', [SecurityController::class, 'index'])->name('security');
        Route::post('/security/flag', [SecurityController::class, 'toggleFlag'])->name('security.flag');
        Route::post('/security/ip-denylist', [SecurityController::class, 'saveIpDenylist'])->name('security.ip-denylist');
        Route::post('/security/verify-chain', [SecurityController::class, 'verifyChain'])->name('security.verify-chain');

        // ── Converted to controllers (Batch C: config CRUD) ──
        Route::get('/assets', [AssetsController::class, 'index'])->name('assets');
        Route::post('/currencies', [AssetsController::class, 'saveCurrency'])->name('currencies.save');
        Route::post('/currencies/{id}/toggle', [AssetsController::class, 'toggleCurrency'])->name('currencies.toggle');
        Route::post('/assets', [AssetsController::class, 'saveNetwork'])->name('assets.save');
        Route::post('/assets/{id}/toggle', [AssetsController::class, 'toggleActive'])->name('assets.toggle');

        Route::get('/deposit-methods', [DepositMethodsController::class, 'index'])->name('deposit-methods');
        Route::post('/deposit-methods', [DepositMethodsController::class, 'save'])->name('deposit-methods.save');
        Route::post('/deposit-methods/{id}/toggle', [DepositMethodsController::class, 'toggleActive'])->name('deposit-methods.toggle');
        Route::post('/deposit-methods/{id}/deposit-enabled', [DepositMethodsController::class, 'toggleDepositEnabled'])->name('deposit-methods.deposit-enabled');

        Route::get('/withdrawal-methods', [AdminWithdrawalMethodsController::class, 'index'])->name('withdrawal-methods');
        Route::post('/withdrawal-methods', [AdminWithdrawalMethodsController::class, 'save'])->name('withdrawal-methods.save');
        Route::post('/withdrawal-methods/{id}/toggle', [AdminWithdrawalMethodsController::class, 'toggleActive'])->name('withdrawal-methods.toggle');

        Route::get('/card-providers', [CardProvidersController::class, 'index'])->name('card-providers');
        Route::post('/card-providers', [CardProvidersController::class, 'save'])->name('card-providers.save');
        Route::post('/card-providers/{id}/toggle', [CardProvidersController::class, 'toggleActive'])->name('card-providers.toggle');

        Route::get('/rpc-endpoints', [RpcEndpointsController::class, 'index'])->name('rpc-endpoints');
        Route::post('/rpc-endpoints', [RpcEndpointsController::class, 'save'])->name('rpc-endpoints.save');
        Route::post('/rpc-endpoints/{id}/toggle', [RpcEndpointsController::class, 'toggleActive'])->name('rpc-endpoints.toggle');
        Route::delete('/rpc-endpoints/{id}', [RpcEndpointsController::class, 'destroy'])->name('rpc-endpoints.delete');

        Route::get('/custody', [CustodyController::class, 'index'])->name('custody');
        Route::post('/custody', [CustodyController::class, 'save'])->name('custody.save');
        Route::post('/custody/{id}/toggle', [CustodyController::class, 'toggleActive'])->name('custody.toggle');
        Route::delete('/custody/{id}', [CustodyController::class, 'destroy'])->name('custody.delete');

        Route::get('/exchange', [AdminExchangeController::class, 'index'])->name('exchange');
        Route::post('/exchange', [AdminExchangeController::class, 'save'])->name('exchange.save');
        Route::post('/exchange/{id}/toggle', [AdminExchangeController::class, 'toggleActive'])->name('exchange.toggle');
        Route::delete('/exchange/{id}', [AdminExchangeController::class, 'destroy'])->name('exchange.delete');

        Route::get('/faqs', [FaqsController::class, 'index'])->name('faqs');
        Route::post('/faqs', [FaqsController::class, 'save'])->name('faqs.save');
        Route::delete('/faqs/{id}', [FaqsController::class, 'destroy'])->name('faqs.delete');

        Route::get('/pages', [PagesController::class, 'index'])->name('pages');
        Route::post('/pages', [PagesController::class, 'save'])->name('pages.save');
        Route::delete('/pages/{id}', [PagesController::class, 'destroy'])->name('pages.delete');

        // ── Converted to controllers (Batch D: revenue / cards / merchants / users) ──
        Route::get('/deposits', [DepositsController::class, 'index'])->name('deposits');
        Route::post('/deposits/{id}/approve', [DepositsController::class, 'approve'])->name('deposits.approve');
        Route::post('/deposits/{id}/reject', [DepositsController::class, 'reject'])->name('deposits.reject');

        // Unified Revenue page (dashboard + profit-by-coin + payouts/transactions/approvals tabs).
        Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue');
        Route::post('/revenue/withdraw', [RevenueController::class, 'withdraw'])->name('revenue.withdraw');

        // The old standalone finance pages are merged into /revenue — redirect for any bookmarks.
        Route::redirect('/finance/revenue-wallet', '/admin/revenue')->name('revenue-wallet');
        Route::post('/finance/revenue-wallet/withdraw', [RevenueWalletController::class, 'withdraw'])->name('revenue-wallet.withdraw');

        Route::redirect('/finance/revenue-withdrawals', '/admin/revenue')->name('revenue-withdrawals');
        Route::post('/finance/revenue-withdrawals/{id}/approve', [RevenueWithdrawalsController::class, 'approve'])->name('revenue-withdrawals.approve');

        Route::redirect('/finance/revenue-transactions', '/admin/revenue')->name('revenue-transactions');
        Route::get('/finance/revenue-transactions/export', [RevenueTransactionsController::class, 'export'])->name('revenue-transactions.export');

        Route::get('/users', [UsersController::class, 'index'])->name('users');
        Route::get('/users/{user}', [UsersController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UsersController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/balance', [UsersController::class, 'adjustBalance'])->name('users.balance');
        Route::post('/users/{id}/freeze', [UsersController::class, 'toggleFreeze'])->name('users.freeze');
        Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate');

        Route::get('/cards', [CardsController::class, 'index'])->name('cards');
        Route::post('/cards/{id}/freeze', [CardsController::class, 'toggleFreeze'])->name('cards.freeze');
        Route::post('/cards/{id}/refund', [CardsController::class, 'refund'])->name('cards.refund');

        Route::get('/card-disputes', [CardDisputesController::class, 'index'])->name('card-disputes');
        Route::post('/card-disputes/{id}/resolve', [CardDisputesController::class, 'resolve'])->name('card-disputes.resolve');

        Route::get('/card-logs', [CardMonitorController::class, 'logs'])->name('card-logs');
        Route::get('/card-webhooks', [CardMonitorController::class, 'webhooks'])->name('card-webhooks');
        Route::post('/card-webhooks/{id}/retry', [CardMonitorController::class, 'retryWebhook'])->name('card-webhooks.retry');
        Route::get('/card-health', [CardMonitorController::class, 'health'])->name('card-health');

        Route::get('/merchants', [MerchantsController::class, 'index'])->name('merchants');
        Route::post('/merchants/{id}/approve', [MerchantsController::class, 'approve'])->name('merchants.approve');
        Route::post('/merchants/{id}/reactivate', [MerchantsController::class, 'reactivate'])->name('merchants.reactivate');
        Route::post('/merchants/{id}/suspend', [MerchantsController::class, 'suspend'])->name('merchants.suspend');
        Route::post('/merchants/{id}/fee', [MerchantsController::class, 'saveFee'])->name('merchants.fee');

        // ── Converted to controllers (Batch E — complex + RBAC) ──
        Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance');
        Route::post('/compliance/alerts/{id}/clear', [ComplianceController::class, 'clearAlert'])->name('compliance.alert.clear');
        Route::post('/compliance/alerts/{id}/escalate', [ComplianceController::class, 'escalateAlert'])->name('compliance.alert.escalate');
        Route::post('/compliance/alerts/{id}/assign', [ComplianceController::class, 'assignAlert'])->name('compliance.alert.assign');
        Route::post('/compliance/cases/{id}/sar', [ComplianceController::class, 'fileSar'])->name('compliance.case.sar');
        Route::post('/compliance/cases/{id}/close', [ComplianceController::class, 'closeCase'])->name('compliance.case.close');

        Route::get('/messaging', [MessagingController::class, 'index'])->name('messaging');
        Route::post('/messaging/templates', [MessagingController::class, 'saveTemplate'])->name('messaging.template.save');
        Route::post('/messaging/templates/{id}/toggle', [MessagingController::class, 'toggleTemplate'])->name('messaging.template.toggle');
        Route::post('/messaging/announcement', [MessagingController::class, 'sendAnnouncement'])->name('messaging.announcement.send');

        Route::get('/rewards', [AdminRewardsController::class, 'index'])->name('rewards');
        Route::post('/rewards/campaigns', [AdminRewardsController::class, 'saveCampaign'])->name('rewards.campaign.save');
        Route::post('/rewards/campaigns/{id}/toggle', [AdminRewardsController::class, 'toggleCampaign'])->name('rewards.campaign.toggle');
        Route::post('/rewards/grant', [AdminRewardsController::class, 'grant'])->name('rewards.grant');

        Route::get('/roles', [RolesController::class, 'index'])->name('roles');
        Route::post('/roles', [RolesController::class, 'save'])->name('roles.save');
        Route::delete('/roles/{id}', [RolesController::class, 'destroy'])->name('roles.delete');

        Route::get('/administrators', [AdministratorsController::class, 'index'])->name('administrators');
        Route::post('/administrators', [AdministratorsController::class, 'save'])->name('administrators.save');
        Route::post('/administrators/{id}/toggle', [AdministratorsController::class, 'toggleActive'])->name('administrators.toggle');
        Route::delete('/administrators/{id}', [AdministratorsController::class, 'destroy'])->name('administrators.delete');
        // P2P marketplace — order monitoring + dispute adjudication.
        Route::get('/p2p/orders', [P2pController::class, 'orders'])->name('p2p');
        Route::get('/p2p/disputes', [P2pController::class, 'disputes'])->name('p2p-disputes');
        Route::get('/p2p/disputes/{dispute}', [P2pController::class, 'dispute'])->name('p2p-disputes.show');
        Route::post('/p2p/disputes/{dispute}/assign', [P2pController::class, 'assign'])->name('p2p-disputes.assign');
        Route::post('/p2p/disputes/{dispute}/resolve', [P2pController::class, 'resolve'])->name('p2p-disputes.resolve');
        Route::get('/p2p/dispute-evidence/{evidence}', [P2pController::class, 'disputeEvidence'])->name('p2p-disputes.evidence');
        // P2P payment-method catalog + per-method field schemas.
        Route::get('/p2p/payment-methods', [P2pPaymentMethodController::class, 'index'])->name('p2p-payment-methods');
        Route::get('/p2p/payment-methods/{method}', [P2pPaymentMethodController::class, 'show'])->name('p2p-payment-methods.show');
        Route::post('/p2p/payment-methods', [P2pPaymentMethodController::class, 'store'])->name('p2p-payment-methods.store');
        Route::put('/p2p/payment-methods/{method}', [P2pPaymentMethodController::class, 'update'])->name('p2p-payment-methods.update');
        Route::delete('/p2p/payment-methods/{method}', [P2pPaymentMethodController::class, 'destroy'])->name('p2p-payment-methods.delete');

        // Platform settings — DollarHub-style controller + Blade forms (not Livewire).
        Route::get('/settings/{section?}', [SettingController::class, 'index'])
            ->where('section', 'general|branding|auth|deposit|withdrawal|transfer|exchange|cards|merchant|p2p|credit|rewards|compliance|localization|announcement')->name('settings');
        Route::put('/settings/{section}', [SettingController::class, 'update'])
            ->where('section', 'general|branding|auth|deposit|withdrawal|transfer|exchange|cards|merchant|p2p|credit|rewards|compliance|localization|announcement')->name('settings.update');
    });
});
