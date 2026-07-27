<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Help center Q&A — the full public FAQ catalogue grouped by topic. Idempotent
 * (updateOrCreate by question) and faker-free, so it is safe on staging/prod.
 * Content lives in content/help_faqs.php.
 */
class HelpFaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = require __DIR__.'/content/help_faqs.php';

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'group' => $faq['group'],
                    'sort_order' => $faq['sort_order'],
                    'show_on_homepage' => $faq['show_on_homepage'] ?? false,
                    'status' => 'published',
                ],
            );
        }
    }
}
