<?php

namespace App\Http\Controllers;

use App\Services\OfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfflineSyncController extends Controller
{
    public const MAX_OPERATIONS_PER_REQUEST = 50;

    public function __construct(private OfflineSyncService $offlineSyncService) {}

    /**
     * Lightweight heartbeat used by the device to confirm real connectivity,
     * an active session and a fresh CSRF token before sending its queue.
     */
    public function status(Request $request): JsonResponse
    {
        return response()
            ->json([
                'online' => true,
                'user_id' => $request->user()->id,
                'csrf_token' => csrf_token(),
                'server_time' => now()->toIso8601String(),
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    public function sync(Request $request): JsonResponse
    {
        $operations = $request->validate([
            'operations' => ['required', 'array', 'max:'.self::MAX_OPERATIONS_PER_REQUEST],
            'operations.*.id' => ['required', 'uuid', 'distinct'],
            'operations.*.type' => ['required', Rule::in(OfflineSyncService::TYPES)],
            'operations.*.animal_id' => ['required', 'integer'],
            'operations.*.payload' => ['required', 'array'],
        ])['operations'];

        $results = array_map(
            fn (array $operation): array => $this->offlineSyncService->process($request->user(), $operation),
            $operations,
        );

        return response()->json(['results' => $results]);
    }
}
