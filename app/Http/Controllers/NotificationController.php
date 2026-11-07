<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Services\LivestockNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $preference = NotificationPreference::query()->firstOrCreate(['user_id' => $request->user()->id], ['enabled' => true, 'lead_days' => 1]);
        return view('notifications.index', [
            'preference' => $preference,
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
            'categories' => LivestockNotificationService::CATEGORIES,
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['nullable', 'boolean'], 'lead_days' => ['required', 'integer', 'min:0', 'max:30'], 'categories' => ['nullable', 'array'], 'categories.*' => [Rule::in(array_keys(LivestockNotificationService::CATEGORIES))]]);
        NotificationPreference::query()->updateOrCreate(['user_id' => $request->user()->id], ['enabled' => $request->boolean('enabled'), 'lead_days' => $data['lead_days'], 'categories' => $data['categories'] ?? null]);
        return back()->with('success', 'Preferencias de notificaciones actualizadas.');
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:2000'], 'keys' => ['nullable', 'array'], 'keys.p256dh' => ['nullable', 'string'], 'keys.auth' => ['nullable', 'string'], 'contentEncoding' => ['nullable', 'string', 'max:32']]);
        $subscription = PushSubscription::query()->updateOrCreate(['user_id' => $request->user()->id, 'endpoint' => $data['endpoint']], ['public_key' => data_get($data, 'keys.p256dh'), 'auth_token' => data_get($data, 'keys.auth'), 'content_encoding' => $data['contentEncoding'] ?? null, 'enabled' => true, 'last_used_at' => now()]);
        return response()->json(['ok' => true, 'id' => $subscription->id]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'url', 'max:2000']]);
        PushSubscription::query()->where('user_id', $request->user()->id)->where('endpoint', $request->string('endpoint'))->update(['enabled' => false]);
        return response()->json(['ok' => true]);
    }
}
