<?php

declare(strict_types=1);

namespace App\Sell\Services;

use App\Sell\Models\AnalyticsEvent;
use App\Sell\Models\SalesPage;
use Illuminate\Http\Request;

/**
 * Records funnel events for the seller dashboard. Writes are cheap (one insert)
 * and best-effort — a tracking failure must never break a page render or a sale,
 * so callers wrap this in a try/catch-free "fire and forget" via {@see track()}.
 */
class AnalyticsService
{
    /** Event types (mirror the sell_analytics_events.type column). */
    public const PAGE_VIEW = 'page_view';
    public const CHECKOUT_START = 'checkout_start';
    public const PURCHASE = 'purchase';

    /**
     * Record an event, swallowing any failure. `dedupeOncePerSession` avoids
     * counting the same visitor's repeated views/checkouts within one session.
     */
    public function track(
        SalesPage $page,
        string $type,
        Request $request,
        ?string $orderId = null,
        bool $dedupeOncePerSession = false,
    ): void {
        try {
            $sessionId = $request->hasSession() ? $request->session()->getId() : null;

            if ($dedupeOncePerSession && $sessionId !== null) {
                $flag = "sell:seen:{$type}:{$page->getKey()}";
                if ($request->session()->get($flag)) {
                    return;
                }
                $request->session()->put($flag, true);
            }

            AnalyticsEvent::create([
                'seller_id' => $page->seller_id,
                'sales_page_id' => $page->getKey(),
                'order_id' => $orderId,
                'type' => $type,
                'session_id' => $sessionId ? substr(hash('sha256', $sessionId), 0, 64) : null,
                'ip_hash' => $request->ip() ? substr(hash('sha256', $request->ip()), 0, 64) : null,
                'referrer' => substr((string) $request->headers->get('referer', ''), 0, 255) ?: null,
                'utm' => $this->utm($request),
                'occurred_at' => now(),
            ]);
        } catch (\Throwable) {
            // Analytics must never break the funnel — drop silently.
        }
    }

    /** @return array<string, string>|null */
    private function utm(Request $request): ?array
    {
        $utm = array_filter([
            'source' => $request->query('utm_source'),
            'medium' => $request->query('utm_medium'),
            'campaign' => $request->query('utm_campaign'),
        ]);

        return $utm !== [] ? $utm : null;
    }
}
