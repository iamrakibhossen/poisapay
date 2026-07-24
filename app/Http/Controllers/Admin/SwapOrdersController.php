<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ConversionContext;
use App\Http\Controllers\Controller;
use App\Models\Conversion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Swap Orders — "What conversions have run and in what context?" Read-only list
 * of Conversions (swap / ramp / card-settle) with their quote detail. The
 * exchange engine is untouched; this only surfaces its output.
 */
class SwapOrdersController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-exchange') || $admin->hasRole('super-admin'), 403);

        $context = (string) $request->query('context', 'all');

        $orders = Conversion::with('user', 'quote.fromAsset', 'quote.toAsset')
            ->when($context !== 'all', fn ($q) => $q->where('context', $context))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.swap-orders', [
            'orders' => $orders,
            'context' => $context,
            'tabs' => [
                'all' => Conversion::count(),
                ConversionContext::Swap->value => Conversion::where('context', ConversionContext::Swap->value)->count(),
                ConversionContext::Ramp->value => Conversion::where('context', ConversionContext::Ramp->value)->count(),
                ConversionContext::CardSettle->value => Conversion::where('context', ConversionContext::CardSettle->value)->count(),
            ],
        ]);
    }
}
