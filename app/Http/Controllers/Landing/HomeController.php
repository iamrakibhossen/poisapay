<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Support\Seo\JsonLd;
use App\Support\Seo\SeoData;
use Illuminate\Contracts\View\View;

/**
 * The public landing home (`/`). Static, conversion-first marketing page rendered
 * entirely from the isolated Landing view tree.
 */
final class HomeController extends Controller
{
    public function __invoke(): View
    {
        $seo = SeoData::make(
            'PaishaPay · Spend crypto like cash, with a premium virtual card',
            'A premium crypto wallet with a beautiful virtual card. Hold, send and spend crypto and Taka anywhere — '
                .'instant deposits, Apple & Google Pay, bank-grade custody. Built for Bangladesh.',
        )
            ->withCanonical(route('home'))
            ->withSchema(JsonLd::softwareApplication());

        return view('landing::home', ['seo' => $seo]);
    }
}
