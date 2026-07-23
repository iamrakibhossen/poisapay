<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Switch the UI locale, persisting to the session and the authenticated user. */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = in_array($request->input('locale'), ['en', 'bn'], true)
            ? (string) $request->input('locale')
            : 'en';

        session(['locale' => $locale]);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
