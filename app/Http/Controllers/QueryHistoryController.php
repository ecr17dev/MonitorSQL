<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateQueryRunRequest;
use App\Models\QueryRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class QueryHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = QueryRun::query()
            ->with(['connection:id,name', 'user:id,name,email'])
            ->where('user_id', $request->user()?->id);

        $query
            ->when($request->filled('status'), function ($builder) use ($request): void {
                $builder->byStatus($request->string('status')->toString());
            })
            ->when($request->filled('category'), function ($builder) use ($request): void {
                $builder->byCategory($request->string('category')->toString());
            })
            ->when($request->filled('connection_id'), function ($builder) use ($request): void {
                $builder->byConnection($request->integer('connection_id'));
            })
            ->when($request->filled('search'), function ($builder) use ($request): void {
                $builder->search($request->string('search')->toString());
            })
            ->when($request->filled('is_favorite'), function ($builder): void {
                $builder->favorites();
            })
            ->when(
                $request->filled('date_from') || $request->filled('date_to'),
                function ($builder) use ($request): void {
                    $builder->dateRange(
                        $request->string('date_from')->toString(),
                        $request->string('date_to')->toString(),
                    );
                }
            );

        $runs = $query->latest()->paginate(20)->withQueryString()
            ->through(function (QueryRun $run): array {
                return [
                    'id' => $run->id,
                    'status' => $run->status,
                    'category' => $run->category,
                    'tags' => $run->tags,
                    'is_favorite' => $run->is_favorite,
                    'duration_ms' => $run->duration_ms,
                    'rows_returned' => $run->rows_returned,
                    'is_ai_generated' => $run->is_ai_generated,
                    'sql' => mb_strlen($run->sql) > 120 ? mb_substr($run->sql, 0, 120).'...' : $run->sql,
                    'created_at' => $run->created_at?->toDateTimeString(),
                    'connection' => $run->connection?->only(['id', 'name']),
                    'user' => $run->user?->only(['id', 'name', 'email']),
                ];
            });

        return Inertia::render('queries/History', [
            'runs' => $runs,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'category' => $request->string('category')->toString(),
                'connection_id' => $request->string('connection_id')->toString(),
                'search' => $request->string('search')->toString(),
                'is_favorite' => $request->string('is_favorite')->toString(),
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
            ],
            'categories' => QueryRun::categories(),
            'statuses' => QueryRun::statuses(),
        ]);
    }

    public function show(int $id, Request $request): Response
    {
        $run = QueryRun::query()
            ->with(['connection:id,name,driver', 'user:id,name,email'])
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        return Inertia::render('queries/Show', [
            'run' => [
                'id' => $run->id,
                'sql' => $run->sql,
                'normalized_sql' => $run->normalized_sql,
                'status' => $run->status,
                'category' => $run->category,
                'tags' => $run->tags,
                'note' => $run->note,
                'is_favorite' => $run->is_favorite,
                'duration_ms' => $run->duration_ms,
                'rows_returned' => $run->rows_returned,
                'is_ai_generated' => $run->is_ai_generated,
                'error_message' => $run->error_message,
                'meta' => $run->meta,
                'created_at' => $run->created_at?->toDateTimeString(),
                'connection' => $run->connection?->only(['id', 'name', 'driver']),
                'user' => $run->user?->only(['id', 'name', 'email']),
            ],
            'categories' => QueryRun::categories(),
            'statuses' => QueryRun::statuses(),
        ]);
    }

    public function update(int $id, UpdateQueryRunRequest $request): RedirectResponse
    {
        $run = QueryRun::query()
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        $data = [];
        if ($request->has('category')) {
            $data['category'] = $request->input('category');
        }
        if ($request->has('tags')) {
            $data['tags'] = $request->input('tags');
        }
        if ($request->has('note')) {
            $data['note'] = $request->input('note');
        }
        if ($request->has('is_favorite')) {
            $data['is_favorite'] = $request->boolean('is_favorite');
        }

        $run->update($data);

        return Redirect::back()->with('flash', [
            'toast' => [
                'type' => 'success',
                'message' => 'Consulta actualizada.',
            ],
        ]);
    }

    public function destroy(int $id, Request $request): RedirectResponse
    {
        $run = QueryRun::query()
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        $run->delete();

        return Redirect::route('queries.history.index')->with('flash', [
            'toast' => [
                'type' => 'info',
                'message' => 'Consulta eliminada del historial.',
            ],
        ]);
    }
}
