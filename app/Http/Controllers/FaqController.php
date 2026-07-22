<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Contracts\View\View;

/**
 * Public FAQ listing — plain server-rendered Blade (no Livewire on the
 * user-facing frontend). Published FAQs grouped by their section.
 */
class FaqController
{
    public function __invoke(): View
    {
        $groups = Faq::query()
            ->published()
            ->ordered()
            ->get()
            ->groupBy(fn (Faq $faq) => $faq->group ?: 'General');

        return view('marketing.faqs', ['groups' => $groups]);
    }
}
