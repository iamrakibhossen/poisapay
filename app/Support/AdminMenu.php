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
    /** DollarHub grouped nav: section headings + links, a few with `children` submenus. */
    public static function groups(): array
    {
        return [
            ['heading' => null, 'items' => [
                ['label' => __('Dashboard'), 'icon' => 'heroicon-o-home', 'route' => 'admin.dashboard'],
            ]],

            ['heading' => __('Compliance'), 'items' => [
                ['label' => __('KYC Queue'), 'icon' => 'heroicon-o-identification', 'route' => 'admin.kyc', 'perm' => 'review-kyc'],
                ['label' => __('Cases & Alerts'), 'icon' => 'heroicon-o-shield-exclamation', 'route' => 'admin.compliance', 'perm' => 'view-compliance'],
                ['label' => __('Security'), 'icon' => 'heroicon-o-lock-closed', 'route' => 'admin.security', 'perm' => 'view-compliance'],
                ['label' => __('Sanctions Lists'), 'icon' => 'heroicon-o-no-symbol', 'route' => 'admin.compliance-lists', 'perm' => 'view-compliance'],
            ]],

            ['heading' => __('Money Movement'), 'items' => [
                ['label' => __('Deposits'), 'icon' => 'heroicon-o-arrow-down-tray', 'children' => [
                    ['label' => __('All Deposits'), 'route' => 'admin.deposits', 'perm' => 'view-deposits'],
                    ['label' => __('Deposit Methods'), 'route' => 'admin.deposit-methods', 'perm' => 'manage-assets'],
                ]],
                ['label' => __('Withdrawals'), 'icon' => 'heroicon-o-arrow-up-tray', 'children' => [
                    ['label' => __('All Withdrawals'), 'route' => 'admin.withdrawals', 'perm' => 'view-withdrawals'],
                    ['label' => __('Withdrawal Methods'), 'route' => 'admin.withdrawal-methods', 'perm' => 'manage-assets'],
                ]],
                ['label' => __('Transfers'), 'icon' => 'heroicon-o-arrows-right-left', 'route' => 'admin.transfers'],
                ['label' => __('Exchange / Swaps'), 'icon' => 'heroicon-o-arrow-path-rounded-square', 'route' => 'admin.exchange', 'perm' => 'view-exchange'],
            ]],

            ['heading' => __('Treasury & Revenue'), 'items' => [
                ['label' => __('Ledger'), 'icon' => 'heroicon-o-book-open', 'route' => 'admin.ledger', 'perm' => 'view-ledger'],
                ['label' => __('Treasury & Solvency'), 'icon' => 'heroicon-o-building-library', 'route' => 'admin.treasury', 'perm' => 'view-treasury'],
                ['label' => __('Financial Reports'), 'icon' => 'heroicon-o-chart-pie', 'route' => 'admin.reports', 'perm' => 'view-reports'],
                ['label' => __('Revenue'), 'icon' => 'heroicon-o-banknotes', 'route' => 'admin.revenue', 'perm' => 'view-revenue'],
            ]],

            ['heading' => __('Cards'), 'items' => [
                ['label' => __('Issued Cards'), 'icon' => 'heroicon-o-credit-card', 'route' => 'admin.cards', 'perm' => 'view-cards'],
                ['label' => __('Disputes'), 'icon' => 'heroicon-o-scale', 'route' => 'admin.card-disputes', 'perm' => 'manage-card-disputes'],
                ['label' => __('Providers'), 'icon' => 'heroicon-o-rectangle-stack', 'children' => [
                    ['label' => __('Card Providers'), 'route' => 'admin.card-providers', 'perm' => 'manage-assets'],
                    ['label' => __('Provider Health'), 'route' => 'admin.card-health', 'perm' => 'view-cards'],
                    ['label' => __('Webhooks'), 'route' => 'admin.card-webhooks', 'perm' => 'view-cards'],
                    ['label' => __('Provider Logs'), 'route' => 'admin.card-logs', 'perm' => 'view-cards'],
                ]],
            ]],

            ['heading' => __('Commerce'), 'items' => [
                ['label' => __('Merchants'), 'icon' => 'heroicon-o-building-storefront', 'route' => 'admin.merchants', 'perm' => 'view-merchants'],
                ['label' => __('P2P'), 'icon' => 'heroicon-o-user-group', 'children' => [
                    ['label' => __('Orders'), 'route' => 'admin.p2p', 'perm' => 'view-p2p'],
                    ['label' => __('Disputes'), 'route' => 'admin.p2p-disputes', 'perm' => 'view-p2p'],
                    ['label' => __('Payment Methods'), 'route' => 'admin.p2p-payment-methods', 'perm' => 'manage-p2p'],
                ]],
                ['label' => __('Rewards'), 'icon' => 'heroicon-o-gift', 'route' => 'admin.rewards', 'perm' => 'view-rewards'],
                ['label' => __('Support'), 'icon' => 'heroicon-o-lifebuoy', 'route' => 'admin.support', 'perm' => 'view-support'],
            ]],

            ['heading' => __('Blockchain'), 'items' => [
                ['label' => __('Custody Wallets'), 'icon' => 'heroicon-o-wallet', 'route' => 'admin.wallets', 'perm' => 'view-treasury'],
                ['label' => __('Chain Health'), 'icon' => 'heroicon-o-signal', 'route' => 'admin.blockchain-health', 'perm' => 'view-treasury'],
                ['label' => __('RPC Endpoints'), 'icon' => 'heroicon-o-server-stack', 'route' => 'admin.rpc-endpoints', 'perm' => 'manage-assets'],
                ['label' => __('Custody & Xpubs'), 'icon' => 'heroicon-o-key', 'route' => 'admin.custody', 'perm' => 'manage-assets'],
            ]],

            ['heading' => __('Users & Access'), 'items' => [
                ['label' => __('Users'), 'icon' => 'heroicon-o-users', 'route' => 'admin.users', 'perm' => 'manage-users'],
                ['label' => __('Roles & Permissions'), 'icon' => 'heroicon-o-shield-check', 'route' => 'admin.roles', 'perm' => 'manage-roles'],
                ['label' => __('Administrators'), 'icon' => 'heroicon-o-user-circle', 'route' => 'admin.administrators', 'perm' => 'manage-admins'],
            ]],

            ['heading' => __('Content'), 'items' => [
                ['label' => __('CMS Pages'), 'icon' => 'heroicon-o-document-text', 'route' => 'admin.pages', 'perm' => 'manage-pages'],
                ['label' => __('FAQs'), 'icon' => 'heroicon-o-question-mark-circle', 'route' => 'admin.faqs', 'perm' => 'manage-faqs'],
                ['label' => __('Notifications'), 'icon' => 'heroicon-o-bell', 'route' => 'admin.notifications'],
                ['label' => __('Messaging'), 'icon' => 'heroicon-o-megaphone', 'route' => 'admin.messaging', 'perm' => 'manage-settings'],
            ]],

            ['heading' => __('Monitoring'), 'items' => [
                ['label' => __('Server Health'), 'icon' => 'heroicon-o-heart', 'route' => 'admin.system-health', 'perm' => 'view-system-health'],
                ['label' => __('Logs'), 'icon' => 'heroicon-o-document-magnifying-glass', 'route' => 'admin.logs', 'perm' => 'view-system-health'],
                ['label' => __('Webhook Logs'), 'icon' => 'heroicon-o-inbox-stack', 'route' => 'admin.webhook-logs', 'perm' => 'view-system-health'],
                ['label' => __('Queue (Horizon)'), 'icon' => 'heroicon-o-queue-list', 'url' => url('/horizon'), 'target' => '_blank', 'perm' => 'view-system-health'],
            ]],

            ['heading' => __('System'), 'items' => [
                ['label' => __('Assets & Chains'), 'icon' => 'heroicon-o-cube', 'route' => 'admin.assets', 'perm' => 'manage-assets'],
                ['label' => __('Activity Logs'), 'icon' => 'heroicon-o-clipboard-document-list', 'route' => 'admin.activity-logs', 'perm' => 'view-activity-logs'],
                ['label' => __('Simulation'), 'icon' => 'heroicon-o-beaker', 'route' => 'admin.simulation'],
                ['label' => __('Settings'), 'icon' => 'heroicon-o-cog-6-tooth', 'route' => 'admin.settings', 'perm' => 'manage-settings'],
                ['label' => __('Feature Flags'), 'icon' => 'heroicon-o-flag', 'route' => 'admin.feature-flags', 'perm' => 'manage-feature-flags'],
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
