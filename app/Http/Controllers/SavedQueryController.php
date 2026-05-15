<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedQueryRequest;
use App\Http\Requests\UpdateSavedQueryRequest;
use App\Models\SavedQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class SavedQueryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = SavedQuery::query()
            ->with(['connection:id,name'])
            ->where('user_id', $request->user()?->id);

        $query
            ->when($request->filled('category'), function ($builder) use ($request): void {
                $builder->byCategory($request->string('category')->toString());
            })
            ->when($request->filled('connection_id'), function ($builder) use ($request): void {
                $builder->byConnection($request->integer('connection_id'));
            })
            ->when($request->filled('is_favorite'), function ($builder): void {
                $builder->favorites();
            })
            ->when($request->filled('search'), function ($builder) use ($request): void {
                $builder->search($request->string('search')->toString());
            });

        $queries = $query->latest()->paginate(20)->withQueryString()
            ->through(function (SavedQuery $saved): array {
                return [
                    'id' => $saved->id,
                    'name' => $saved->name,
                    'sql' => mb_strlen($saved->sql) > 120 ? mb_substr($saved->sql, 0, 120).'...' : $saved->sql,
                    'full_sql' => $saved->sql,
                    'is_favorite' => $saved->is_favorite,
                    'category' => $saved->category,
                    'tags' => $saved->tags,
                    'note' => $saved->note,
                    'created_at' => $saved->created_at?->toDateTimeString(),
                    'connection' => $saved->connection?->only(['id', 'name']),
                ];
            });

        return Inertia::render('queries/Saved', [
            'saved' => $queries,
            'filters' => [
                'category' => $request->string('category')->toString(),
                'connection_id' => $request->string('connection_id')->toString(),
                'is_favorite' => $request->string('is_favorite')->toString(),
                'search' => $request->string('search')->toString(),
            ],
            'categories' => SavedQuery::categories(),
        ]);
    }

    public function store(StoreSavedQueryRequest $request): RedirectResponse
    {
        SavedQuery::create([
            ...$request->validated(),
            'user_id' => $request->user()?->id,
        ]);

        return Redirect::route('queries.saved.index')->with('flash', [
            'toast' => [
                'type' => 'success',
                'message' => 'Consulta guardada correctamente.',
            ],
        ]);
    }

    public function update(int $id, UpdateSavedQueryRequest $request): RedirectResponse
    {
        $saved = SavedQuery::query()
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        $saved->update($request->validated());

        return Redirect::back()->with('flash', [
            'toast' => [
                'type' => 'success',
                'message' => 'Consulta guardada actualizada.',
            ],
        ]);
    }

    public function destroy(int $id, Request $request): RedirectResponse
    {
        $saved = SavedQuery::query()
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        $saved->delete();

        return Redirect::back()->with('flash', [
            'toast' => [
                'type' => 'info',
                'message' => 'Consulta guardada eliminada.',
            ],
        ]);
    }
}
