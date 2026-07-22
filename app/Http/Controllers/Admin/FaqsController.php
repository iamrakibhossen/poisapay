<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin FAQ CRUD (DollarHub structure — controller + Blade, not Livewire).
 * Questions answered on the public help page.
 */
class FaqsController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();

        return view('admin.faqs', [
            'faqs' => Faq::ordered()->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $request->merge(['show_on_homepage' => $request->boolean('show_on_homepage')]);

        $data = $request->validate([
            'id' => 'nullable|exists:faqs,id',
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'group' => 'nullable|string|max:80',
            'show_on_homepage' => 'boolean',
            'sort_order' => 'integer|min:0|max:65535',
            'status' => 'required|in:published,draft',
        ]);

        $id = $data['id'] ?? null;

        $attributes = [
            'question' => $data['question'],
            'answer' => $data['answer'],
            'group' => ($data['group'] ?? '') ?: null,
            'show_on_homepage' => $data['show_on_homepage'],
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'],
        ];

        $faq = $id
            ? tap(Faq::findOrFail($id), fn ($f) => $f->update($attributes))
            : Faq::create($attributes);

        ActivityLogger::log('faq.saved', $faq, [], "Saved FAQ {$faq->id}");

        return redirect()->route('admin.faqs')->with('success', $id ? 'FAQ updated.' : 'FAQ created.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeAccess();

        $faq = Faq::findOrFail($id);
        $faq->delete();

        ActivityLogger::log('faq.deleted', $faq, [], "Deleted FAQ {$id}");

        return redirect()->route('admin.faqs')->with('success', 'FAQ deleted.');
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth('admin')->user()->can('manage-faqs'), 403);
    }
}
