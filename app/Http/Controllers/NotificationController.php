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
        $notifications = $request->user()->notifications()->latest()->paginate(20);

        // Se cargan antes de marcarlas: en esta visita las nuevas siguen resaltadas como "Nueva".
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return view('notifications.index', [
            'preference' => $preference,
            'notifications' => $notifications,
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
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! self::isTrustedPushEndpoint((string) $value)) {
                    $fail('El servicio de notificaciones del navegador no es válido.');
                }
            }],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', Rule::in(['aesgcm', 'aes128gcm'])],
        ]);
        $subscription = PushSubscription::query()->updateOrCreate(['user_id' => $request->user()->id, 'endpoint' => $data['endpoint']], ['public_key' => data_get($data, 'keys.p256dh'), 'auth_token' => data_get($data, 'keys.auth'), 'content_encoding' => $data['contentEncoding'] ?? null, 'enabled' => true, 'last_used_at' => now()]);
        return response()->json(['ok' => true, 'id' => $subscription->id]);
    }

    /**
     * Push endpoints must belong to a browser push service. Any other URL would let a user make
     * the server send requests to arbitrary hosts (SSRF), including internal network addresses.
     */
    public static function isTrustedPushEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        $host = strtolower($parts['host'] ?? '');

        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }

        foreach (['fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com', 'push.services.mozilla.com', 'notify.windows.com', 'push.apple.com'] as $trustedHost) {
            if ($host === $trustedHost || str_ends_with($host, '.'.$trustedHost)) {
                return true;
            }
        }

        return false;
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'url', 'max:2000']]);
        PushSubscription::query()->where('user_id', $request->user()->id)->where('endpoint', $request->string('endpoint'))->update(['enabled' => false]);
        return response()->json(['ok' => true]);
    }
}
