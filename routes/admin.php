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
    // Public operator auth.
    Route::controller(AuthController::class)->group(function () {
        Route::get('/login', 'showLogin')->name('login');
        Route::post('/login', 'login')->name('login.attempt');
        Route::get('/forgot-password', 'forgotForm')->name('password.request');
        Route::post('/forgot-password', 'sendResetLink')->name('password.email');
        Route::get('/reset-password/{token}', 'resetForm')->name('password.reset');
        Route::post('/reset-password', 'resetPassword')->name('password.update');
        Route::post('/logout', 'logout')->name('logout');
    });

    // Guarded console.
    Route::middleware('operator')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');
        Route::get('/transfers', [TransfersController::class, 'index'])->name('transfers');

        // ── Treasury / reports / wallets ──
        Route::controller(TreasuryController::class)->group(function () {
            Route::get('/treasury', 'index')->name('treasury');
            Route::post('/treasury/reconcile', 'reconcile')->name('treasury.reconcile');
        });
        Route::controller(FinancialReportController::class)->group(function () {
            Route::get('/reports', 'index')->name('reports');
            Route::get('/reports/export', 'export')->name('reports.export');
        });
        Route::get('/wallets', [WalletsController::class, 'index'])->name('wallets');

        // ── Ops: blockchain health + simulation ──
        Route::controller(BlockchainHealthController::class)->group(function () {
            Route::get('/blockchain-health', 'index')->name('blockchain-health');
            Route::post('/blockchain-health/check', 'runHealthCheck')->name('blockchain-health.check');
            Route::post('/blockchain-health/tick', 'runMonitorTick')->name('blockchain-health.tick');
            Route::post('/blockchain-health/reconcile', 'runReconciliation')->name('blockchain-health.reconcile');
        });
        Route::controller(SimulationController::class)->group(function () {
            Route::get('/simulation', 'index')->name('simulation');
            Route::post('/simulation/tick', 'runChainTick')->name('simulation.tick');
            Route::post('/simulation/deposit', 'simulateDeposit')->name('simulation.deposit');
        });

        // ── Notifications ──
        Route::controller(AdminNotificationController::class)->group(function () {
            Route::get('/notifications', 'index')->name('notifications');
            Route::post('/notifications/{id}/read', 'markRead')->name('notifications.read');
            Route::post('/notifications/read-all', 'markAllRead')->name('notifications.read-all');
        });

        // ── KYC queue ──
        Route::controller(KycQueueController::class)->group(function () {
            Route::get('/kyc', 'index')->name('kyc');
            Route::get('/kyc/{id}', 'show')->name('kyc.show');
            Route::get('/kyc/{id}/file/{slot}', 'file')->name('kyc.file');
            Route::post('/kyc/{id}/approve', 'approve')->name('kyc.approve');
            Route::post('/kyc/{id}/reject', 'reject')->name('kyc.reject');
        });

        // ── Withdrawals / ledger ──
        Route::controller(WithdrawalsController::class)->group(function () {
            Route::get('/withdrawals', 'index')->name('withdrawals');
            Route::post('/withdrawals/{id}/approve', 'approve')->name('withdrawals.approve');
            Route::post('/withdrawals/{id}/cancel', 'cancel')->name('withdrawals.cancel');
        });
        Route::controller(LedgerController::class)->group(function () {
            Route::get('/ledger', 'index')->name('ledger');
            Route::post('/ledger/{id}/reverse', 'reverse')->name('ledger.reverse');
        });

        // ── Compliance lists + exports (Wave 5) ──
        Route::controller(ComplianceExportController::class)->group(function () {
            Route::get('/compliance/export/cases', 'cases')->name('compliance.export.cases');
            Route::get('/compliance/export/alerts', 'alerts')->name('compliance.export.alerts');
        });
        Route::controller(ComplianceListController::class)->group(function () {
            Route::get('/compliance-lists', 'index')->name('compliance-lists');
            Route::post('/compliance-lists', 'store')->name('compliance-lists.store');
            Route::delete('/compliance-lists/{id}', 'destroy')->name('compliance-lists.destroy');
        });

        // ── Feature flags (Wave 6) ──
        Route::controller(FeatureFlagController::class)->group(function () {
            Route::get('/feature-flags', 'index')->name('feature-flags');
            Route::post('/feature-flags/toggle', 'toggle')->name('feature-flags.toggle');
        });

        // ── Support tickets (Wave 6) ──
        Route::controller(SupportController::class)->group(function () {
            Route::get('/support', 'index')->name('support');
            Route::get('/support/{id}', 'show')->name('support.show');
            Route::post('/support/{id}/reply', 'reply')->name('support.reply');
            Route::post('/support/{id}/status', 'updateStatus')->name('support.status');
            Route::post('/support/{id}/assign', 'assign')->name('support.assign');
        });

        // ── Security monitoring (Wave 4) ──
        Route::controller(SecurityController::class)->group(function () {
            Route::get('/security', 'index')->name('security');
            Route::post('/security/flag', 'toggleFlag')->name('security.flag');
            Route::post('/security/ip-denylist', 'saveIpDenylist')->name('security.ip-denylist');
            Route::post('/security/verify-chain', 'verifyChain')->name('security.verify-chain');
        });

        // ── Config CRUD: assets / currencies ──
        Route::controller(AssetsController::class)->group(function () {
            Route::get('/assets', 'index')->name('assets');
            Route::post('/currencies', 'saveCurrency')->name('currencies.save');
            Route::post('/currencies/{id}/toggle', 'toggleCurrency')->name('currencies.toggle');
            Route::post('/assets', 'saveNetwork')->name('assets.save');
            Route::post('/assets/{id}/toggle', 'toggleActive')->name('assets.toggle');
        });

        Route::controller(DepositMethodsController::class)->group(function () {
            Route::get('/deposit-methods', 'index')->name('deposit-methods');
            Route::post('/deposit-methods', 'save')->name('deposit-methods.save');
            Route::post('/deposit-methods/{id}/toggle', 'toggleActive')->name('deposit-methods.toggle');
            Route::post('/deposit-methods/{id}/deposit-enabled', 'toggleDepositEnabled')->name('deposit-methods.deposit-enabled');
        });

        Route::controller(AdminWithdrawalMethodsController::class)->group(function () {
            Route::get('/withdrawal-methods', 'index')->name('withdrawal-methods');
            Route::post('/withdrawal-methods', 'save')->name('withdrawal-methods.save');
            Route::post('/withdrawal-methods/{id}/toggle', 'toggleActive')->name('withdrawal-methods.toggle');
        });

        Route::controller(CardProvidersController::class)->group(function () {
            Route::get('/card-providers', 'index')->name('card-providers');
            Route::post('/card-providers', 'save')->name('card-providers.save');
            Route::post('/card-providers/{id}/toggle', 'toggleActive')->name('card-providers.toggle');
        });

        Route::controller(RpcEndpointsController::class)->group(function () {
            Route::get('/rpc-endpoints', 'index')->name('rpc-endpoints');
            Route::post('/rpc-endpoints', 'save')->name('rpc-endpoints.save');
            Route::post('/rpc-endpoints/{id}/toggle', 'toggleActive')->name('rpc-endpoints.toggle');
            Route::delete('/rpc-endpoints/{id}', 'destroy')->name('rpc-endpoints.delete');
        });

        Route::controller(CustodyController::class)->group(function () {
            Route::get('/custody', 'index')->name('custody');
            Route::post('/custody', 'save')->name('custody.save');
            Route::post('/custody/{id}/toggle', 'toggleActive')->name('custody.toggle');
            Route::delete('/custody/{id}', 'destroy')->name('custody.delete');
        });

        Route::controller(AdminExchangeController::class)->group(function () {
            Route::get('/exchange', 'index')->name('exchange');
            Route::post('/exchange', 'save')->name('exchange.save');
            Route::post('/exchange/{id}/toggle', 'toggleActive')->name('exchange.toggle');
            Route::delete('/exchange/{id}', 'destroy')->name('exchange.delete');
        });

        Route::controller(FaqsController::class)->group(function () {
            Route::get('/faqs', 'index')->name('faqs');
            Route::post('/faqs', 'save')->name('faqs.save');
            Route::delete('/faqs/{id}', 'destroy')->name('faqs.delete');
        });

        Route::controller(PagesController::class)->group(function () {
            Route::get('/pages', 'index')->name('pages');
            Route::post('/pages', 'save')->name('pages.save');
            Route::delete('/pages/{id}', 'destroy')->name('pages.delete');
        });

        // ── Deposits queue ──
        Route::controller(DepositsController::class)->group(function () {
            Route::get('/deposits', 'index')->name('deposits');
            Route::post('/deposits/{id}/approve', 'approve')->name('deposits.approve');
            Route::post('/deposits/{id}/reject', 'reject')->name('deposits.reject');
        });

        // ── Revenue (unified page + legacy redirects to it) ──
        Route::controller(RevenueController::class)->group(function () {
            Route::get('/revenue', 'index')->name('revenue');
            Route::post('/revenue/withdraw', 'withdraw')->name('revenue.withdraw');
        });
        Route::redirect('/finance/revenue-wallet', '/admin/revenue')->name('revenue-wallet');
        Route::post('/finance/revenue-wallet/withdraw', [RevenueWalletController::class, 'withdraw'])->name('revenue-wallet.withdraw');
        Route::redirect('/finance/revenue-withdrawals', '/admin/revenue')->name('revenue-withdrawals');
        Route::post('/finance/revenue-withdrawals/{id}/approve', [RevenueWithdrawalsController::class, 'approve'])->name('revenue-withdrawals.approve');
        Route::redirect('/finance/revenue-transactions', '/admin/revenue')->name('revenue-transactions');
        Route::get('/finance/revenue-transactions/export', [RevenueTransactionsController::class, 'export'])->name('revenue-transactions.export');

        // ── Users + impersonation ──
        Route::controller(UsersController::class)->group(function () {
            Route::get('/users', 'index')->name('users');
            Route::get('/users/{user}', 'show')->name('users.show');
            Route::get('/users/{user}/edit', 'edit')->name('users.edit');
            Route::put('/users/{user}', 'update')->name('users.update');
            Route::post('/users/{user}/balance', 'adjustBalance')->name('users.balance');
            Route::post('/users/{id}/freeze', 'toggleFreeze')->name('users.freeze');
        });
        Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate');

        // ── Cards ──
        Route::controller(CardsController::class)->group(function () {
            Route::get('/cards', 'index')->name('cards');
            Route::post('/cards/{id}/freeze', 'toggleFreeze')->name('cards.freeze');
            Route::post('/cards/{id}/refund', 'refund')->name('cards.refund');
        });
        Route::controller(CardDisputesController::class)->group(function () {
            Route::get('/card-disputes', 'index')->name('card-disputes');
            Route::post('/card-disputes/{id}/resolve', 'resolve')->name('card-disputes.resolve');
        });
        Route::controller(CardMonitorController::class)->group(function () {
            Route::get('/card-logs', 'logs')->name('card-logs');
            Route::get('/card-webhooks', 'webhooks')->name('card-webhooks');
            Route::post('/card-webhooks/{id}/retry', 'retryWebhook')->name('card-webhooks.retry');
            Route::get('/card-health', 'health')->name('card-health');
        });

        // ── Merchants ──
        Route::controller(MerchantsController::class)->group(function () {
            Route::get('/merchants', 'index')->name('merchants');
            Route::post('/merchants/{id}/approve', 'approve')->name('merchants.approve');
            Route::post('/merchants/{id}/reactivate', 'reactivate')->name('merchants.reactivate');
            Route::post('/merchants/{id}/suspend', 'suspend')->name('merchants.suspend');
            Route::post('/merchants/{id}/fee', 'saveFee')->name('merchants.fee');
        });

        // ── Compliance cases + alerts (Wave 5, RBAC) ──
        Route::controller(ComplianceController::class)->group(function () {
            Route::get('/compliance', 'index')->name('compliance');
            Route::post('/compliance/alerts/{id}/clear', 'clearAlert')->name('compliance.alert.clear');
            Route::post('/compliance/alerts/{id}/escalate', 'escalateAlert')->name('compliance.alert.escalate');
            Route::post('/compliance/alerts/{id}/assign', 'assignAlert')->name('compliance.alert.assign');
            Route::post('/compliance/cases/{id}/sar', 'fileSar')->name('compliance.case.sar');
            Route::post('/compliance/cases/{id}/close', 'closeCase')->name('compliance.case.close');
        });

        // ── Messaging ──
        Route::controller(MessagingController::class)->group(function () {
            Route::get('/messaging', 'index')->name('messaging');
            Route::post('/messaging/templates', 'saveTemplate')->name('messaging.template.save');
            Route::post('/messaging/templates/{id}/toggle', 'toggleTemplate')->name('messaging.template.toggle');
            Route::post('/messaging/announcement', 'sendAnnouncement')->name('messaging.announcement.send');
        });

        // ── Rewards ──
        Route::controller(AdminRewardsController::class)->group(function () {
            Route::get('/rewards', 'index')->name('rewards');
            Route::post('/rewards/campaigns', 'saveCampaign')->name('rewards.campaign.save');
            Route::post('/rewards/campaigns/{id}/toggle', 'toggleCampaign')->name('rewards.campaign.toggle');
            Route::post('/rewards/grant', 'grant')->name('rewards.grant');
        });

        // ── Roles + administrators (RBAC) ──
        Route::controller(RolesController::class)->group(function () {
            Route::get('/roles', 'index')->name('roles');
            Route::post('/roles', 'save')->name('roles.save');
            Route::delete('/roles/{id}', 'destroy')->name('roles.delete');
        });
        Route::controller(AdministratorsController::class)->group(function () {
            Route::get('/administrators', 'index')->name('administrators');
            Route::post('/administrators', 'save')->name('administrators.save');
            Route::post('/administrators/{id}/toggle', 'toggleActive')->name('administrators.toggle');
            Route::delete('/administrators/{id}', 'destroy')->name('administrators.delete');
        });

        // ── P2P marketplace: order monitoring + dispute adjudication ──
        Route::controller(P2pController::class)->group(function () {
            Route::get('/p2p/orders', 'orders')->name('p2p');
            Route::get('/p2p/disputes', 'disputes')->name('p2p-disputes');
            Route::get('/p2p/disputes/{dispute}', 'dispute')->name('p2p-disputes.show');
            Route::post('/p2p/disputes/{dispute}/assign', 'assign')->name('p2p-disputes.assign');
            Route::post('/p2p/disputes/{dispute}/resolve', 'resolve')->name('p2p-disputes.resolve');
            Route::get('/p2p/dispute-evidence/{evidence}', 'disputeEvidence')->name('p2p-disputes.evidence');
        });
        // P2P payment-method catalog + per-method field schemas.
        Route::controller(P2pPaymentMethodController::class)->group(function () {
            Route::get('/p2p/payment-methods', 'index')->name('p2p-payment-methods');
            Route::get('/p2p/payment-methods/{method}', 'show')->name('p2p-payment-methods.show');
            Route::post('/p2p/payment-methods', 'store')->name('p2p-payment-methods.store');
            Route::put('/p2p/payment-methods/{method}', 'update')->name('p2p-payment-methods.update');
            Route::delete('/p2p/payment-methods/{method}', 'destroy')->name('p2p-payment-methods.delete');
        });

        // ── Platform settings (controller + Blade forms, not Livewire) ──
        Route::controller(SettingController::class)->group(function () {
            $sections = 'general|branding|auth|deposit|withdrawal|transfer|exchange|cards|merchant|p2p|credit|rewards|compliance|localization|announcement';
            Route::get('/settings/{section?}', 'index')->where('section', $sections)->name('settings');
            Route::put('/settings/{section}', 'update')->where('section', $sections)->name('settings.update');
        });
    });
});
