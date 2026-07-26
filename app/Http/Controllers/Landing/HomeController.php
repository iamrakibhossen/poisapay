<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The public landing home (`/`). Static, conversion-first marketing page rendered
 * entirely from the isolated Landing view tree.
 */
final class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('landing::home');
    }
}
