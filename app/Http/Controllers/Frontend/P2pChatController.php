<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\P2p\MarkMessagesReadAction;
use App\Domain\P2p\SendMessageAction;
use App\Enums\P2pMessageType;
use App\Http\Controllers\Controller;
use App\Models\P2pOrder;
use App\Models\P2pOrderMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Order chat: list (JSON, for initial load + polling fallback), send (form POST),
 * mark-read, and authorised attachment download. Live delivery is over the
 * `p2p.order.{id}` broadcast channel; this controller is the durable path.
 */
class P2pChatController extends Controller
{
    public function index(Request $request, P2pOrder $order): JsonResponse
    {
        $this->assertParty($request, $order);

        $messages = $order->messages()
            ->orderBy('created_at')
            ->limit(500)
            ->get()
            ->map(fn (P2pOrderMessage $m) => [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'sender_id' => $m->sender_id,
                'type' => $m->type->value,
                'body' => $m->body,
                'has_attachment' => $m->attachment_path !== null,
                'read_at' => $m->read_at?->toIso8601String(),
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request, P2pOrder $order, SendMessageAction $action): RedirectResponse
    {
        $this->assertParty($request, $order);

        $validated = $request->validate([
            'type' => ['nullable', 'in:text,image,receipt'],
            'body' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        try {
            $action->execute(
                $order,
                $request->user(),
                P2pMessageType::from($validated['type'] ?? 'text'),
                $validated['body'] ?? null,
                $request->file('attachment'),
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    public function read(Request $request, P2pOrder $order, MarkMessagesReadAction $action): RedirectResponse
    {
        $this->assertParty($request, $order);
        $action->execute($order, $request->user());

        return back();
    }

    public function attachment(Request $request, P2pOrderMessage $message): StreamedResponse
    {
        $this->assertParty($request, $message->order);

        abort_if(
            $message->attachment_path === null || ! Storage::disk('local')->exists($message->attachment_path),
            404,
        );

        return Storage::disk('local')->download($message->attachment_path);
    }

    private function assertParty(Request $request, ?P2pOrder $order): void
    {
        abort_unless(
            $order && in_array($request->user()->getKey(), [$order->buyer_id, $order->seller_id], true),
            403,
        );
    }
}
