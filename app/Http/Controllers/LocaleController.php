<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = $request->validate(['locale' => ['required', 'in:es,en']])['locale'];
        $request->session()->put('locale', $locale);

        return back()->with('status', __('ui.messages.saved'));
    }
}
