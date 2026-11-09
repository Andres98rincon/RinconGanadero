<?php

namespace App\Http\Controllers;

use App\Services\HerdAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __construct(private HerdAssistantService $assistant) {}

    /**
     * Answers a question about the user's own herd. Messages are not stored.
     */
    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:300'],
        ], [
            'message.required' => 'Escribe una pregunta.',
            'message.max' => 'La pregunta es muy larga. Usa máximo 300 caracteres.',
        ]);

        return response()
            ->json($this->assistant->answer($request->user(), $validated['message']))
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
