<?php

declare(strict_types=1);

/*
 * Operator (admin guard) permission catalog. `permissions` is the flat list
 * synced to the DB; `groups` maps name substrings to admin-UI sections
 * (see permissionGroup()). New modules add their permissions here — nothing
 * is hardcoded elsewhere.
 */
return [
    'permissions' => [
        // Overview
        'view-dashboard',

        // Compliance
        'review-kyc', 'approve-kyc', 'view-compliance', 'file-sar',

        // Money movement
        'view-deposits', 'view-withdrawals', 'approve-withdrawals', 'view-transfers',

        // Treasury / ledger
        'view-ledger', 'view-treasury', 'manage-treasury', 'view-reconciliation', 'withdraw-profit',

        // Finance / revenue wallet
        'view-revenue', 'withdraw-revenue', 'approve-revenue-withdrawal',

        // Exchange
        'view-exchange', 'manage-exchange',

        // Cards
        'view-cards', 'manage-cards', 'manage-card-providers', 'manage-card-disputes',

        // Merchants
        'view-merchants', 'manage-merchants',

        // Rewards
        'view-rewards', 'manage-rewards',

        // Users
        'view-users', 'manage-users', 'freeze-users', 'adjust-balance', 'impersonate-users',

        // Configuration
        'manage-assets', 'manage-settings', 'manage-feature-flags',

        // CMS
        'view-pages', 'manage-pages', 'view-faqs', 'manage-faqs', 'manage-announcements',

        // Reports
        'view-reports', 'export-reports',

        // System
        'view-activity-logs', 'view-audit-logs', 'manage-roles', 'manage-admins',
        'view-system-health', 'manage-developer',
    ],

    'groups' => [
        'Overview' => ['dashboard'],
        'Compliance' => ['kyc', 'compliance', 'sar'],
        'Money Movement' => ['deposit', 'withdrawal', 'transfer'],
        'Treasury' => ['ledger', 'treasury', 'reconciliation'],
        'Exchange' => ['exchange'],
        'Cards' => ['card'],
        'Merchants' => ['merchant'],
        'Rewards' => ['reward'],
        'Users' => ['user', 'balance'],
        'Configuration' => ['asset', 'setting', 'feature-flag'],
        'CMS' => ['page', 'faq', 'announcement'],
        'Reports' => ['report'],
        'System' => ['activity', 'audit', 'role', 'admin', 'health', 'developer'],
    ],

    /*
     * Role → permission grants. 'super-admin' implicitly gets everything via a
     * Gate::before check; the rest are explicit.
     */
    'roles' => [
        'admin' => [
            'view-dashboard', 'view-users', 'manage-users', 'freeze-users', 'review-kyc', 'approve-kyc',
            'view-deposits', 'view-withdrawals', 'approve-withdrawals', 'view-transfers', 'view-ledger',
            'view-treasury', 'manage-assets', 'manage-cards', 'manage-card-providers', 'manage-card-disputes', 'view-merchants',
            'view-rewards', 'view-reports', 'export-reports', 'manage-pages', 'manage-faqs',
            'manage-announcements', 'manage-settings', 'view-activity-logs',
        ],
        'compliance' => [
            'view-dashboard', 'review-kyc', 'approve-kyc', 'view-compliance', 'file-sar',
            'view-withdrawals', 'view-users', 'view-activity-logs',
        ],
        'treasury' => [
            'view-dashboard', 'view-treasury', 'manage-treasury', 'view-reconciliation',
            'view-withdrawals', 'approve-withdrawals', 'view-ledger', 'view-reports',
        ],
        'support' => [
            'view-dashboard', 'view-users', 'view-deposits', 'view-withdrawals', 'view-merchants', 'view-pages',
        ],
    ],
];
