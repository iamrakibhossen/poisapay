<?php

declare(strict_types=1);

/**
 * Global Spending Priority Engine (App\Domain\Spending). The single source of
 * truth for how a user's balances are selected — and auto-converted — to fund
 * ANY spend (card, merchant, shop, payment link, subscription, bill, …).
 *
 * The default priority below is used only when a user has NOT configured a
 * custom order (user_spending_priority). A user's own order always wins.
 */
return [

    // Default-OFF flag that gates business modules routing their spend through
    // the engine. The engine itself is always callable; consumers check this.
    'engine_flag' => 'spending_engine_enabled',

    // System default spend order (symbols, canonical per coin). Used only when a
    // user has no custom spending priority. Editable from this single source.
    'default_priority' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SPENDING_PRIORITY',
        'USD,USDT,USDC,FDUSD,BTC,ETH,BNB,SOL,TRX,XRP',
    ))))),

    // After the ranked list, allow any remaining active asset the user holds to
    // be drawn on as a last resort, so a spend never fails while funds exist.
    'include_remaining' => (bool) env('SPENDING_INCLUDE_REMAINING', true),
];
