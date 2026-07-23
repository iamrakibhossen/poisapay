<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\NotifyP2pOrderParticipants;
use App\Listeners\P2pChatSubscriber;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the self-contained P2P marketplace module. Keeps P2P registration in
 * one place so the rest of the app stays untouched.
 */
class P2pServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::subscribe(P2pChatSubscriber::class);
        Event::subscribe(NotifyP2pOrderParticipants::class);
    }
}
