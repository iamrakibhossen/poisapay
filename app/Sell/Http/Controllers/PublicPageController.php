<?php

declare(strict_types=1);

namespace App\Sell\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sell\Services\SalesPageService;
use Illuminate\Http\JsonResponse;

/**
 * Public sales-page read — cache-first, no auth. On a warm cache this returns the
 * fully-assembled page with zero DB queries (TTFB target < 100ms).
 */
class PublicPageController extends Controller
{
    public function show(string $slug, SalesPageService $pages): JsonResponse
    {
        $view = $pages->publicView($slug);

        abort_if($view === null, 404);

        return response()->json(['data' => $view]);
    }
}
