<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            AdminSeeder::class,
            RegistrySeeder::class,
            TradingPairSeeder::class,
            DealerInventorySeeder::class,
            BlockchainInfraSeeder::class,
            CmsSeeder::class,
            CardProviderSeeder::class,
            RewardCampaignSeeder::class,
            NotificationTemplateSeeder::class,
            ShopNotificationTemplateSeeder::class,
            DepositMethodSeeder::class,
            WithdrawalMethodSeeder::class,
            P2pSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
