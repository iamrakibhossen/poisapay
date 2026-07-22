<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/** Default English notification templates (§F4) — admin-editable afterwards. */
class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['deposit.credited', 'Deposit credited', 'money', 'Deposit credited', '{{amount}} has landed in your {{asset}} wallet.'],
            ['withdrawal.completed', 'Withdrawal sent', 'money', 'Withdrawal sent', 'Your withdrawal of {{amount}} was sent to {{address}}.'],
            ['withdrawal.review', 'Withdrawal under review', 'money', 'Withdrawal under review', 'Your withdrawal of {{amount}} is being reviewed and will complete shortly.'],
            ['kyc.approved', 'Verification approved', 'security', 'You are verified', 'Your {{tier}} verification was approved. Enjoy your upgraded limits.'],
            ['kyc.rejected', 'Verification rejected', 'security', 'Verification needs attention', 'Your verification could not be approved: {{reason}}.'],
            ['card.settled', 'Card purchase', 'money', 'Card purchase', 'A purchase of {{amount}} at {{merchant}} was settled on your card.'],
            ['reward.granted', 'Reward earned', 'product', 'You earned a reward', 'You received {{amount}} — {{reason}}.'],
            ['security.login', 'New sign-in', 'security', 'New sign-in detected', 'A new sign-in to your account was detected from {{location}}.'],
        ];

        foreach ($templates as [$key, $name, $category, $subject, $body]) {
            NotificationTemplate::firstOrCreate(
                ['key' => $key, 'locale' => 'en'],
                [
                    'name' => $name,
                    'category' => $category,
                    'channels' => ['in_app', 'email'],
                    'subject' => $subject,
                    'body' => $body,
                    'is_active' => true,
                ],
            );
        }
    }
}
