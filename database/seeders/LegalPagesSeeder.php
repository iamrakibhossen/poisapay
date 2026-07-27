<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Legal & policy centre — the full set of PaishaPay legal documents, served on
 * the public site at /pages/{slug}. Idempotent (updateOrCreate by slug) and
 * faker-free, so it is safe to run on staging/prod. Content lives in modular
 * fragment files under content/ so it stays reviewable.
 */
class LegalPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = array_merge(
            require __DIR__.'/content/legal_agreements.php',
            require __DIR__.'/content/legal_compliance.php',
            require __DIR__.'/content/legal_general.php',
        );

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'meta_description' => $page['meta_description'],
                    'status' => 'published',
                ],
            );
        }
    }
}
