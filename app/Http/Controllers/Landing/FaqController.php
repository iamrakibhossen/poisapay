<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Support\Seo\JsonLd;
use App\Support\Seo\SeoData;
use Illuminate\Contracts\View\View;

/**
 * Public FAQ listing (help center) — server-rendered Blade. Published FAQs
 * grouped by their section.
 */
final class FaqController extends Controller
{
    public function __invoke(): View
    {
        $faqs = Faq::query()->published()->ordered()->get();
        $groups = $faqs->groupBy(fn (Faq $faq) => $faq->group ?: 'General');

        $seo = SeoData::make('Help Center', 'Answers to common questions about PoishaPay — wallet, cards, exchange, P2P, merchant payments, security and more.')
            ->withCanonical(route('help-center'))
            ->withBreadcrumbs([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Help Center', 'url' => route('help-center')],
            ]);

        if ($faqs->isNotEmpty()) {
            $seo = $seo->withSchema(JsonLd::faq($faqs->take(20)->map(
                fn (Faq $f) => ['question' => (string) $f->question, 'answer' => (string) $f->answer],
            )->all()));
        }

        return view('landing::faqs', ['groups' => $groups, 'seo' => $seo]);
    }
}
