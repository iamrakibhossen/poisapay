<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CardAuthorization;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted after a card authorisation settles — notifies the cardholder (Reverb push). */
class CardTransactionSettled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $authorizationId) {}

    public function broadcastOn(): array
    {
        $auth = CardAuthorization::with('card')->find($this->authorizationId);

        return $auth && $auth->card ? [new PrivateChannel("user.{$auth->card->user_id}")] : [new Channel('cards')];
    }

    public function broadcastAs(): string
    {
        return 'card.settled';
    }
}
