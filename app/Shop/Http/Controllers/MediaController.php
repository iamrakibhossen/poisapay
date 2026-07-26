<?php

declare(strict_types=1);

namespace App\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shop\Models\Seller;
use App\Shop\Models\ShopMedia;
use App\Shop\Services\Media\MediaDeleteService;
use App\Shop\Services\Media\MediaUploadService;
use App\Shop\Services\SellerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Shop Media Library's JSON surface for the page-builder picker + the
 * standalone /shop/media manager. Consistent with the builder's existing JSON
 * endpoints (it's a builder tool, not a public consumer flow). Every action is
 * scoped to the current merchant's own assets.
 */
class MediaController extends Controller
{
    public function __construct(
        private readonly SellerService $sellers,
        private readonly MediaUploadService $uploads,
        private readonly MediaDeleteService $deletes,
    ) {}

    /** Standalone Media Library management page. */
    public function index(Request $request): View
    {
        $seller = $this->seller($request);

        return view('frontend.seller.media.index', [
            'config' => $this->clientConfig(),
            'stats' => [
                'count' => $seller->media()->count(),
                'size' => $this->humanSize((int) $seller->media()->sum('size_bytes')),
            ],
        ]);
    }

    /** Paginated listing: search by name, sort by upload date, optional trash view. */
    public function items(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $query = $seller->media()
            ->search((string) $request->query('q', ''))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed());

        $request->query('sort') === 'oldest' ? $query->oldest() : $query->latest();

        $page = $query->paginate((int) config('media.per_page', 30));

        return response()->json([
            'items' => array_map(fn (ShopMedia $m) => $this->present($m), $page->items()),
            'nextPage' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'total' => $page->total(),
        ]);
    }

    /** Upload one or many images (deduped per seller). */
    public function store(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $request->validate([
            'files' => ['required', 'array', 'max:30'],
            'files.*' => ['required', 'file', 'mimes:'.implode(',', (array) config('media.accept', [])), 'max:'.(int) config('media.max_upload_kb', 12288)],
        ]);

        $created = [];
        foreach ($request->file('files', []) as $file) {
            $created[] = $this->present($this->uploads->upload($seller, $file));
        }

        return response()->json(['items' => $created], 201);
    }

    /** Rename / set alt text. */
    public function update(Request $request, ShopMedia $media): JsonResponse
    {
        $this->authorizeOwner($request, $media);

        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);

        $this->uploads->updateMeta($media, $validated['name'] ?? $media->name, $validated['alt'] ?? null);

        return response()->json(['item' => $this->present($media->fresh())]);
    }

    /** Replace an asset's bytes in place (URL preserved → updates everywhere). */
    public function replace(Request $request, ShopMedia $media): JsonResponse
    {
        $this->authorizeOwner($request, $media);

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', (array) config('media.accept', [])), 'max:'.(int) config('media.max_upload_kb', 12288)],
        ]);

        $this->uploads->replace($media, $request->file('file'));

        return response()->json(['item' => $this->present($media->fresh())]);
    }

    /** Soft delete (recoverable), or permanently purge with ?force=1. */
    public function destroy(Request $request, ShopMedia $media): JsonResponse
    {
        $this->authorizeOwner($request, $media);

        $request->boolean('force') ? $this->deletes->purge($media) : $this->deletes->delete($media);

        return response()->json(['ok' => true]);
    }

    /** Restore a soft-deleted asset. */
    public function restore(Request $request, string $media): JsonResponse
    {
        $seller = $this->seller($request);
        $item = $seller->media()->onlyTrashed()->whereKey($media)->firstOrFail();

        $this->deletes->restore($item);

        return response()->json(['item' => $this->present($item->fresh())]);
    }

    /** @return array<string, mixed> */
    private function present(ShopMedia $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'url' => $media->url(),
            'thumb' => $media->previewUrl(),
            'alt' => $media->alt,
            'mime' => $media->mime,
            'ext' => $media->extension,
            'width' => $media->width,
            'height' => $media->height,
            'size' => $media->size_bytes,
            'sizeHuman' => $this->humanSize($media->size_bytes),
            'status' => $media->status->value,
            'trashed' => $media->trashed(),
            'createdAt' => $media->created_at?->toIso8601String(),
            'createdHuman' => $media->created_at?->diffForHumans(),
        ];
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        $units = ['KB', 'MB', 'GB'];
        $n = $bytes / 1024;
        $i = 0;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return round($n, $n >= 10 ? 0 : 1).' '.$units[$i];
    }

    /** @return array<string, mixed> */
    private function clientConfig(): array
    {
        return [
            'endpoints' => [
                'items' => route('shop.media.items'),
                'upload' => route('shop.media.store'),
            ],
            'accept' => (array) config('media.accept', []),
            'maxKb' => (int) config('media.max_upload_kb', 12288),
            'csrf' => csrf_token(),
        ];
    }

    private function seller(Request $request): Seller
    {
        $seller = $this->sellers->forUser($request->user());
        abort_unless($seller instanceof Seller && $seller->canSell(), 403);

        return $seller;
    }

    private function authorizeOwner(Request $request, ShopMedia $media): void
    {
        abort_unless($media->seller_id === $this->seller($request)->id, 403);
    }
}
