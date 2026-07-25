<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer portal — "My Purchases" (funnel platform). Buyers re-download files,
 * track orders, view license keys and invoices.
 *
 * FRONTEND-FIRST: renders from sample purchases so the UX is reviewable.
 */
class PurchasesController extends Controller
{
    public function index(Request $request): View
    {
        return view('frontend.purchases', [
            'purchases' => [
                [
                    'name' => 'LaunchKit — Laravel SaaS Boilerplate', 'type' => 'digital', 'icon' => 'cube',
                    'seller' => 'Rahim Studios', 'date' => 'Jul 20, 2026', 'price' => '$49',
                    'file' => 'launchkit-v1.0.zip', 'action' => 'Download',
                ],
                [
                    'name' => 'PoisaHub Dev Tee (Black · M)', 'type' => 'physical', 'icon' => 'truck',
                    'seller' => 'Rahim Studios', 'date' => 'Jul 12, 2026', 'price' => '$25',
                    'shipStatus' => 'Shipped', 'tracking' => 'BD-7712-9920', 'carrier' => 'Pathao', 'action' => 'Track order',
                    'address' => 'House 14, Road 7, Dhanmondi, Dhaka 1209',
                    'timeline' => [
                        ['Order placed', 'Jul 12 · 10:22 AM', true],
                        ['Shipped', 'Jul 13 · 4:05 PM · Pathao', true],
                        ['Out for delivery', 'Expected Jul 15', false],
                        ['Delivered', 'Pending', false],
                    ],
                ],
                [
                    'name' => 'SaaS Pro License', 'type' => 'license', 'icon' => 'key',
                    'seller' => 'Rahim Studios', 'date' => 'Jun 28, 2026', 'price' => '$79',
                    'licenseKey' => 'LK-9F2A-7C4E-1B80-XZ21', 'action' => 'View key',
                ],
            ],
        ]);
    }
}
