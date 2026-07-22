<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin CMS pages CRUD (DollarHub structure — controller + Blade, not Livewire).
 * Marketing and legal content served on the public site.
 */
class PagesController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();

        return view('admin.pages', [
            'pages' => Page::orderBy('title')->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $id = $request->input('id') ?: null;

        // Auto-slug from the title when left blank (mirrors the Livewire updatedTitle hook).
        if (! $request->filled('slug') && $request->filled('title')) {
            $request->merge(['slug' => Str::slug((string) $request->input('title'))]);
        }

        $data = $request->validate([
            'id' => 'nullable|exists:pages,id',
            'title' => 'required|string|max:160',
            'slug' => 'required|string|max:160|regex:/^[a-z0-9-]+$/|unique:pages,slug'.($id ? ','.$id : ''),
            'status' => 'required|in:published,draft',
            'meta_description' => 'nullable|string|max:255',
            'content' => 'nullable|string',
        ]);

        $attributes = [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'meta_description' => ($data['meta_description'] ?? '') ?: null,
            'content' => ($data['content'] ?? '') ?: null,
        ];

        $page = $id
            ? tap(Page::findOrFail($id), fn ($p) => $p->update($attributes))
            : Page::create($attributes);

        ActivityLogger::log('page.saved', $page, [], "Saved page {$page->slug}");

        return redirect()->route('admin.pages')->with('success', $id ? 'Page updated.' : 'Page created.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeAccess();

        $page = Page::findOrFail($id);
        $slug = $page->slug;
        $page->delete();

        ActivityLogger::log('page.deleted', $page, [], "Deleted page {$slug}");

        return redirect()->route('admin.pages')->with('success', 'Page deleted.');
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth('admin')->user()->can('manage-pages'), 403);
    }
}
