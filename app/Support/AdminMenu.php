<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for the admin navigation tree.
 *
 * Both the sidebar {@see resources/views/components/partials/admin-sidebar.blade.php}
 * and the command-palette search {@see resources/views/components/partials/admin-search.blade.php}
 * read from here so the two never drift. Entries carry route names (not URLs) and
 * optional `perm`; callers resolve routes and filter by permission at request time.
 */
class AdminMenu
{
    /**
     * Workflow-based grouped nav: section headings + links, a few with `children`
     * submenus. Items may carry an optional `badge` key naming an
     * {@see AdminAttention} count; the sidebar renders it as a pill and skips any
     * item whose route does not (yet) exist, so the tree can list pages that are
     * still being built without breaking navigation.
     */
    public static function groups(): array
    {
        return [
            ['heading' => null, 'items' => [
                ['label' => __('Dashboard'), 'icon' => 'heroicon-o-home', 'route' => 'admin.dashboard'],
                ['label' => __('Analytics'), 'icon' => 'heroicon-o-chart-bar-square', 'route' => 'admin.analytics', 'perm' => 'view-reports'],
            ]],

            ['heading' => __('Customers'), 'items' => [
                ['label' => __('Users'), 'icon' => 'heroicon-o-users', 'route' => 'admin.users', 'perm' => 'manage-users'],
                ['label' => __('KYC Queue'), 'icon' => 'heroicon-o-identification', 'route' => 'admin.kyc', 'perm' => 'review-kyc', 'badge' => 'kyc_pending'],
                ['label' => __('Risk'), 'icon' => 'heroicon-o-exclamation-triangle', 'route' => 'admin.risk', 'perm' => 'view-compliance'],
                ['label' => __('Support'), 'icon' => 'heroicon-o-lifebuoy', 'route' => 'admin.support', 'perm' => 'view-support', 'badge' => 'support_open'],
            ]],

            ['heading' => __('Money Movement'), 'items' => [
                ['label' => __('Deposits'), 'icon' => 'heroicon-o-arrow-down-tray', 'route' => 'admin.deposits', 'perm' => 'view-deposits', 'badge' => 'deposits_pending'],
                ['label' => __('Withdrawals'), 'icon' => 'heroicon-o-arrow-up-tray', 'route' => 'admin.withdrawals', 'perm' => 'view-withdrawals', 'badge' => 'withdrawals_review'],
                ['label' => __('Internal Transfers'), 'icon' => 'heroicon-o-arrows-right-left', 'route' => 'admin.transfers'],
                ['label' => __('Ledger'), 'icon' => 'heroicon-o-book-open', 'route' => 'admin.ledger', 'perm' => 'view-ledger'],
            ]],

            ['heading' => __('Blockchain'), 'items' => [
                ['label' => __('Chain Health'), 'icon' => 'heroicon-o-signal', 'route' => 'admin.blockchain-health', 'perm' => 'view-treasury'],
                ['label' => __('Deposit Monitor'), 'icon' => 'heroicon-o-magnifying-glass-circle', 'route' => 'admin.deposit-monitor', 'perm' => 'view-treasury'],
                ['label' => __('Sweep Queue'), 'icon' => 'heroicon-o-arrows-pointing-in', 'route' => 'admin.sweeps', 'perm' => 'view-treasury', 'badge' => 'sweeps_pending'],
                ['label' => __('Hot Wallet'), 'icon' => 'heroicon-o-fire', 'route' => 'admin.hot-wallet', 'perm' => 'view-treasury'],
                ['label' => __('Cold Wallet'), 'icon' => 'heroicon-o-lock-closed', 'route' => 'admin.cold-wallet', 'perm' => 'view-treasury'],
                ['label' => __('Reconciliation'), 'icon' => 'heroicon-o-scale', 'route' => 'admin.reconciliation', 'perm' => 'view-treasury'],
            ]],

            ['heading' => __('Exchange'), 'items' => [
                ['label' => __('Swap Orders'), 'icon' => 'heroicon-o-arrow-path-rounded-square', 'route' => 'admin.swap-orders', 'perm' => 'view-exchange'],
                ['label' => __('Rates'), 'icon' => 'heroicon-o-arrow-trending-up', 'route' => 'admin.rates', 'perm' => 'view-exchange'],
                ['label' => __('Liquidity'), 'icon' => 'heroicon-o-beaker', 'route' => 'admin.liquidity', 'perm' => 'view-treasury'],
            ]],

            ['heading' => __('Cards'), 'items' => [
                ['label' => __('Virtual Cards'), 'icon' => 'heroicon-o-credit-card', 'route' => 'admin.cards', 'perm' => 'view-cards'],
                ['label' => __('Transactions'), 'icon' => 'heroicon-o-list-bullet', 'route' => 'admin.card-transactions', 'perm' => 'view-cards'],
                ['label' => __('Disputes'), 'icon' => 'heroicon-o-scale', 'route' => 'admin.card-disputes', 'perm' => 'manage-card-disputes', 'badge' => 'card_disputes_open'],
                ['label' => __('Providers'), 'icon' => 'heroicon-o-rectangle-stack', 'children' => [
                    ['label' => __('Provider Health'), 'route' => 'admin.card-health', 'perm' => 'view-cards'],
                    ['label' => __('Webhooks'), 'route' => 'admin.card-webhooks', 'perm' => 'view-cards'],
                    ['label' => __('Provider Logs'), 'route' => 'admin.card-logs', 'perm' => 'view-cards'],
                ]],
            ]],

            ['heading' => __('Commerce'), 'items' => [
                ['label' => __('Merchants'), 'icon' => 'heroicon-o-building-storefront', 'route' => 'admin.merchants', 'perm' => 'view-merchants'],
                ['label' => __('Sellers'), 'icon' => 'heroicon-o-rocket-launch', 'route' => 'admin.sellers', 'perm' => 'view-sellers'],
                ['label' => __('Refunds'), 'icon' => 'heroicon-o-arrow-uturn-left', 'route' => 'admin.sell-refunds', 'perm' => 'view-sellers', 'badge' => 'sell_refunds_escalated'],
                ['label' => __('P2P'), 'icon' => 'heroicon-o-user-group', 'children' => [
                    ['label' => __('Orders'), 'route' => 'admin.p2p', 'perm' => 'view-p2p'],
                    ['label' => __('Disputes'), 'route' => 'admin.p2p-disputes', 'perm' => 'view-p2p', 'badge' => 'p2p_disputes_open'],
                ]],
                ['label' => __('Rewards'), 'icon' => 'heroicon-o-gift', 'route' => 'admin.rewards', 'perm' => 'view-rewards'],
            ]],

            ['heading' => __('Treasury'), 'items' => [
                ['label' => __('Company Funds'), 'icon' => 'heroicon-o-building-library', 'route' => 'admin.treasury', 'perm' => 'view-treasury'],
                ['label' => __('Revenue'), 'icon' => 'heroicon-o-banknotes', 'route' => 'admin.revenue', 'perm' => 'view-revenue'],
                ['label' => __('Settlements'), 'icon' => 'heroicon-o-check-badge', 'route' => 'admin.settlements', 'perm' => 'view-revenue', 'badge' => 'settlements_pending'],
                ['label' => __('Treasury Transfers'), 'icon' => 'heroicon-o-arrows-up-down', 'route' => 'admin.treasury-transfers', 'perm' => 'view-treasury'],
                ['label' => __('Financial Reports'), 'icon' => 'heroicon-o-chart-pie', 'route' => 'admin.reports', 'perm' => 'view-reports'],
            ]],

            ['heading' => __('Compliance'), 'items' => [
                ['label' => __('Cases & Alerts'), 'icon' => 'heroicon-o-shield-exclamation', 'route' => 'admin.compliance', 'perm' => 'view-compliance', 'badge' => 'compliance_open'],
                ['label' => __('Sanctions Lists'), 'icon' => 'heroicon-o-no-symbol', 'route' => 'admin.compliance-lists', 'perm' => 'view-compliance'],
                ['label' => __('Security'), 'icon' => 'heroicon-o-lock-closed', 'route' => 'admin.security', 'perm' => 'view-compliance'],
                ['label' => __('Audit Logs'), 'icon' => 'heroicon-o-clipboard-document-list', 'route' => 'admin.activity-logs', 'perm' => 'view-activity-logs'],
            ]],

            ['heading' => __('Operations'), 'items' => [
                ['label' => __('Queue'), 'icon' => 'heroicon-o-queue-list', 'route' => 'admin.queue', 'perm' => 'view-system-health'],
                ['label' => __('Webhooks'), 'icon' => 'heroicon-o-inbox-stack', 'route' => 'admin.webhook-logs', 'perm' => 'view-system-health', 'badge' => 'webhooks_failed'],
                ['label' => __('Notifications'), 'icon' => 'heroicon-o-bell', 'route' => 'admin.notifications'],
                ['label' => __('Server Health'), 'icon' => 'heroicon-o-heart', 'route' => 'admin.system-health', 'perm' => 'view-system-health'],
                ['label' => __('Server Logs'), 'icon' => 'heroicon-o-document-magnifying-glass', 'route' => 'admin.logs', 'perm' => 'view-system-health'],
                ['label' => __('Simulation'), 'icon' => 'heroicon-o-beaker', 'route' => 'admin.simulation'],
            ]],

            ['heading' => __('Content'), 'items' => [
                ['label' => __('CMS Pages'), 'icon' => 'heroicon-o-document-text', 'route' => 'admin.pages', 'perm' => 'manage-pages'],
                ['label' => __('FAQs'), 'icon' => 'heroicon-o-question-mark-circle', 'route' => 'admin.faqs', 'perm' => 'manage-faqs'],
                ['label' => __('Messaging'), 'icon' => 'heroicon-o-megaphone', 'route' => 'admin.messaging', 'perm' => 'manage-settings'],
            ]],

            ['heading' => __('Settings'), 'items' => [
                ['label' => __('Platform Settings'), 'icon' => 'heroicon-o-cog-6-tooth', 'route' => 'admin.settings', 'perm' => 'manage-settings'],
                ['label' => __('Currencies & Assets'), 'icon' => 'heroicon-o-cube', 'route' => 'admin.assets', 'perm' => 'manage-assets'],
                ['label' => __('Payment Methods'), 'icon' => 'heroicon-o-wallet', 'children' => [
                    ['label' => __('Deposit Methods'), 'route' => 'admin.deposit-methods', 'perm' => 'manage-assets'],
                    ['label' => __('Withdrawal Methods'), 'route' => 'admin.withdrawal-methods', 'perm' => 'manage-assets'],
                ]],
                ['label' => __('Networks & RPC'), 'icon' => 'heroicon-o-server-stack', 'route' => 'admin.rpc-endpoints', 'perm' => 'manage-assets'],
                ['label' => __('Custody Xpubs'), 'icon' => 'heroicon-o-key', 'route' => 'admin.custody', 'perm' => 'manage-assets'],
                ['label' => __('Card Providers'), 'icon' => 'heroicon-o-rectangle-stack', 'route' => 'admin.card-providers', 'perm' => 'manage-assets'],
                ['label' => __('P2P Payment Methods'), 'icon' => 'heroicon-o-banknotes', 'route' => 'admin.p2p-payment-methods', 'perm' => 'manage-p2p'],
                ['label' => __('Feature Flags'), 'icon' => 'heroicon-o-flag', 'route' => 'admin.feature-flags', 'perm' => 'manage-feature-flags'],
                ['label' => __('Roles & Permissions'), 'icon' => 'heroicon-o-shield-check', 'route' => 'admin.roles', 'perm' => 'manage-roles'],
                ['label' => __('Administrators'), 'icon' => 'heroicon-o-user-circle', 'route' => 'admin.administrators', 'perm' => 'manage-admins'],
            ]],
        ];
    }

    /**
     * Flatten the tree into individual searchable destinations. Submenu children
     * become their own entries labelled "Parent · Child". Each item keeps `perm`
     * so the caller can filter to what the operator may reach.
     *
     * @return list<array{label:string,group:?string,route:?string,url:?string,target:?string,perm:?string,icon:string}>
     */
    public static function searchItems(): array
    {
        $items = [];

        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                if (! empty($item['children'])) {
                    foreach ($item['children'] as $child) {
                        $items[] = [
                            'label' => $item['label'].' · '.$child['label'],
                            'group' => $group['heading'],
                            'route' => $child['route'] ?? null,
                            'url' => $child['url'] ?? null,
                            'target' => $child['target'] ?? null,
                            'perm' => $child['perm'] ?? null,
                            'icon' => $item['icon'],
                        ];
                    }

                    continue;
                }

                $items[] = [
                    'label' => $item['label'],
                    'group' => $group['heading'],
                    'route' => $item['route'] ?? null,
                    'url' => $item['url'] ?? null,
                    'target' => $item['target'] ?? null,
                    'perm' => $item['perm'] ?? null,
                    'icon' => $item['icon'],
                ];
            }
        }

        return $items;
    }
}
