<?php

declare(strict_types=1);

namespace App\Http\Controllers\Card;

use App\Card\CardManager;
use App\Card\Contracts\CardProviderInterface;
use App\Card\Enums\WebhookEventType;
use App\Card\Support\ProviderLogger;
use App\Domain\Card\AuthorizeCardAction;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCardWebhookJob;
use App\Models\CardWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Provider-agnostic inbound endpoints. Webhooks are verified, deduped and queued;
 * JIT funding is answered synchronously from our ledger (approve 200 / decline 402).
 */
class CardInboundController extends Controller
{
    public function __construct(
        private readonly CardManager $manager,
        private readonly ProviderLogger $logger,
    ) {}

    public function webhook(string $provider, Request $request, AuthorizeCardAction $authorize): JsonResponse
    {
        $adapter = $this->resolve($provider);
        $raw = $request->getContent();
        $headers = $request->headers->all();

        if (! $adapter->verifyWebhook($raw, $headers)) {
            $this->log($provider, 'webhook', false, 'invalid_signature', 401);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $events = $adapter->processWebhook($raw, $headers);

        // Real-time (JIT) authorisation requests must be answered synchronously so the
        // provider (e.g. Stripe issuing_authorization.request) approves/declines from
        // our ledger within its ~2s window. Everything else is deduped + queued.
        foreach ($events as $event) {
            if ($event->type === WebhookEventType::AuthorizationRequest && $adapter->supportsJitFunding()) {
                $result = $authorize->authorize($adapter->parseFundingRequest($raw, $headers));
                $response = $adapter->formatFundingResponse($result);
                $this->log($provider, 'jit', $result->approved, $result->reason, $response['status']);

                return response()->json($response['body'], $response['status']);
            }
        }

        $received = 0;
        foreach ($events as $event) {
            $webhook = CardWebhook::firstOrCreate(
                ['driver' => $provider, 'provider_event_id' => $event->providerEventId],
                [
                    'event_type' => $event->type->value,
                    'provider_card_ref' => $event->providerCardRef,
                    'provider_tx_ref' => $event->providerTxRef,
                    'payload' => [
                        'amount_minor' => $event->amountMinor,
                        'currency' => $event->currency,
                        'occurred_at' => $event->occurredAt?->toIso8601String(),
                        'raw' => $event->payload,
                    ],
                    'signature_valid' => true,
                    'status' => 'pending',
                    'received_at' => now(),
                ],
            );

            if ($webhook->wasRecentlyCreated) {
                ProcessCardWebhookJob::dispatch($webhook->id);
                $received++;
            }
        }

        $this->log($provider, 'webhook', true, null, 200);

        return response()->json(['received' => $received], 200);
    }

    public function jit(string $provider, Request $request, AuthorizeCardAction $authorize): JsonResponse
    {
        $adapter = $this->resolve($provider);
        abort_unless($adapter->supportsJitFunding(), 404);

        $raw = $request->getContent();
        $headers = $request->headers->all();

        if (! $adapter->verifyWebhook($raw, $headers)) {
            $this->log($provider, 'jit', false, 'invalid_signature', 401);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $result = $authorize->authorize($adapter->parseFundingRequest($raw, $headers));
        $response = $adapter->formatFundingResponse($result);

        $this->log($provider, 'jit', $result->approved, $result->reason, $response['status']);

        return response()->json($response['body'], $response['status']);
    }

    private function resolve(string $provider): CardProviderInterface
    {
        try {
            return $this->manager->driver($provider);
        } catch (Throwable) {
            abort(404);
        }
    }

    private function log(string $driver, string $operation, bool $success, ?string $error, int $status): void
    {
        $this->logger->record([
            'driver' => $driver,
            'direction' => 'inbound',
            'operation' => $operation,
            'status_code' => $status,
            'success' => $success,
            'error' => $error,
        ]);
    }
}
