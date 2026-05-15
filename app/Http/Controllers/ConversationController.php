<?php

namespace App\Http\Controllers;

use App\Models\AgentConversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AgentConversation::query()
            ->where('user_id', $request->user()?->id);

        $query
            ->when($request->filled('connection_id'), function ($builder) use ($request): void {
                $builder->byConnection($request->integer('connection_id'));
            })
            ->when($request->filled('search'), function ($builder) use ($request): void {
                $builder->search($request->string('search')->toString());
            })
            ->when($request->boolean('show_archived', false), function ($builder): void {
                // defaults to not archived
            }, function ($builder): void {
                $builder->notArchived();
            });

        $conversations = $query
            ->orderByDesc('is_pinned')
            ->latest('last_message_at')
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (AgentConversation $conversation): array {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'is_archived' => $conversation->is_archived,
                    'is_pinned' => $conversation->is_pinned,
                    'message_count' => $conversation->message_count,
                    'last_message_at' => $conversation->last_message_at?->toDateTimeString(),
                    'connection_id' => $conversation->connection_id,
                    'created_at' => $conversation->created_at?->toDateTimeString(),
                ];
            });

        return Inertia::render('conversations/Index', [
            'conversations' => $conversations,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'connection_id' => $request->string('connection_id')->toString(),
                'show_archived' => $request->string('show_archived')->toString(),
            ],
        ]);
    }

    public function show(string $id, Request $request): Response
    {
        $conversation = AgentConversation::query()
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        $messages = $conversation->messages()
            ->oldest()
            ->get()
            ->map(function ($message): array {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'agent' => $message->agent,
                    'usage' => $message->usage,
                    'tool_calls' => $message->tool_calls,
                    'created_at' => $message->created_at?->toDateTimeString(),
                ];
            });

        return Inertia::render('conversations/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'is_archived' => $conversation->is_archived,
                'is_pinned' => $conversation->is_pinned,
                'message_count' => $conversation->message_count,
                'connection_id' => $conversation->connection_id,
                'last_message_at' => $conversation->last_message_at?->toDateTimeString(),
                'created_at' => $conversation->created_at?->toDateTimeString(),
            ],
            'messages' => $messages,
        ]);
    }

    public function update(string $id, Request $request): RedirectResponse
    {
        $conversation = AgentConversation::query()
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'is_archived' => ['sometimes', 'boolean'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        $conversation->update($validated);

        return Redirect::back()->with('flash', [
            'toast' => [
                'type' => 'success',
                'message' => 'Conversación actualizada.',
            ],
        ]);
    }

    public function destroy(string $id, Request $request): RedirectResponse
    {
        $conversation = AgentConversation::query()
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        $conversation->messages()->delete();
        $conversation->delete();

        return Redirect::route('conversations.index')->with('flash', [
            'toast' => [
                'type' => 'info',
                'message' => 'Conversación eliminada.',
            ],
        ]);
    }
}
