<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Contracts\View\View;

/**
 * Public FAQ listing (help center) — server-rendered Blade. Published FAQs
 * grouped by their section.
 */
final class FaqController extends Controller
{
    public function __invoke(): View
    {
        $groups = Faq::query()
            ->published()
            ->ordered()
            ->get()
            ->groupBy(fn (Faq $faq) => $faq->group ?: 'General');

        return view('landing::faqs', ['groups' => $groups]);
    }
}
