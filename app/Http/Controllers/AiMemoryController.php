<?php

namespace App\Http\Controllers;

use App\Models\AiMemoryProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AiMemoryController extends Controller
{
    public function index(Request $request): Response
    {
        $profiles = AiMemoryProfile::query()
            ->with(['connection:id,name'])
            ->where('user_id', $request->user()?->id)
            ->latest('last_used_at')
            ->get()
            ->map(function (AiMemoryProfile $profile): array {
                return [
                    'id' => $profile->id,
                    'preferred_tables' => $profile->preferred_tables,
                    'term_aliases' => $profile->term_aliases,
                    'successful_query_patterns' => $profile->successful_query_patterns,
                    'last_used_at' => $profile->last_used_at?->toDateTimeString(),
                    'created_at' => $profile->created_at?->toDateTimeString(),
                    'connection' => $profile->connection?->only(['id', 'name']),
                ];
            });

        return Inertia::render('ai-memory/Index', [
            'profiles' => $profiles,
        ]);
    }

    public function destroy(int $id, Request $request): RedirectResponse
    {
        $profile = AiMemoryProfile::query()
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        $profile->delete();

        return Redirect::route('ai-memory.index')->with('flash', [
            'toast' => [
                'type' => 'info',
                'message' => 'Perfil de memoria IA eliminado.',
            ],
        ]);
    }

    public function clearAll(Request $request): RedirectResponse
    {
        AiMemoryProfile::query()
            ->where('user_id', $request->user()?->id)
            ->delete();

        return Redirect::route('ai-memory.index')->with('flash', [
            'toast' => [
                'type' => 'success',
                'message' => 'Toda la memoria IA ha sido limpiada.',
            ],
        ]);
    }
}
