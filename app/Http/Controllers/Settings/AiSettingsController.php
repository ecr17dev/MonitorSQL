<?php

namespace App\Http\Controllers\Settings;

use App\Ai\Agents\SqlQueryAssistant;
use App\Http\Controllers\Controller;
use App\Models\AiProviderConfig;
use App\Models\SystemPrompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AiSettingsController extends Controller
{
    public function editProviders(): Response
    {
        $providers = collect([
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic (Claude)',
            'cerebras' => 'Cerebras',
            'openrouter' => 'OpenRouter',
            'deepseek' => 'DeepSeek',
            'groq' => 'Groq',
            'gemini' => 'Google Gemini',
            'mistral' => 'Mistral',
            'xai' => 'xAI (Grok)',
        ])->map(fn (string $displayName, string $provider): array => [
            'provider' => $provider,
            'display_name' => $displayName,
            'api_key' => '',
            'is_enabled' => false,
            'default_model' => '',
        ])->values();

        $configured = AiProviderConfig::query()
            ->get(['provider', 'api_key', 'is_enabled', 'default_model'])
            ->keyBy('provider');

        $merged = $providers->map(function (array $default) use ($configured): array {
            $conf = $configured->get($default['provider']);

            return [
                'provider' => $default['provider'],
                'display_name' => $default['display_name'],
                'api_key' => $conf ? '••••••••' : '',
                'is_enabled' => $conf ? (bool) $conf->is_enabled : false,
                'default_model' => $conf ? ($conf->default_model ?? '') : '',
            ];
        });

        return Inertia::render('settings/AiProviders', [
            'providers' => $merged->values(),
        ]);
    }

    public function testProvider(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
        ]);

        $provider = $data['provider'];

        $config = AiProviderConfig::query()->where('provider', $provider)->first();

        if (! $config || empty($config->api_key)) {
            return response()->json([
                'success' => false,
                'message' => 'No API key configured for this provider. Save a key first.',
            ], 422);
        }

        try {
            $start = microtime(true);

            $model = $config->default_model ?: config('monitorsql.ai.model', 'gpt-4.1-mini');

            $response = SqlQueryAssistant::make()->prompt(
                prompt: 'Reply with exactly one word: OK',
                provider: [$provider => $model],
                timeout: 15,
            );

            $duration = round((microtime(true) - $start) * 1000);

            return response()->json([
                'success' => true,
                'message' => "Connected successfully in {$duration}ms using {$model}.",
                'duration_ms' => $duration,
            ]);
        } catch (Throwable $throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: '.$throwable->getMessage(),
            ]);
        }
    }

    public function updateProviders(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'providers' => ['required', 'array'],
            'providers.*.provider' => ['required', 'string'],
            'providers.*.api_key' => ['nullable', 'string'],
            'providers.*.is_enabled' => ['boolean'],
            'providers.*.default_model' => ['nullable', 'string'],
        ]);

        foreach ($data['providers'] as $providerData) {
            $model = AiProviderConfig::query()->firstOrNew([
                'provider' => $providerData['provider'],
            ]);

            $providerKey = $providerData['provider'];
            $displayName = collect([
                'openai' => 'OpenAI',
                'anthropic' => 'Anthropic (Claude)',
                'cerebras' => 'Cerebras',
                'openrouter' => 'OpenRouter',
                'deepseek' => 'DeepSeek',
                'groq' => 'Groq',
                'gemini' => 'Google Gemini',
                'mistral' => 'Mistral',
                'xai' => 'xAI (Grok)',
            ])->get($providerKey, $providerKey);

            $model->display_name = $displayName;
            $model->is_enabled = (bool) ($providerData['is_enabled'] ?? false);

            if (! empty($providerData['api_key']) && $providerData['api_key'] !== '••••••••') {
                $model->api_key = $providerData['api_key'];
            }

            $model->default_model = $providerData['default_model'] ?? '';
            $model->save();
        }

        Cache::forget('ai_provider_keys');

        return back()->with('toast', ['type' => 'success', 'message' => __('AI provider configs updated.')]);
    }

    public function editPrompt(): Response
    {
        $prompt = SystemPrompt::query()->where('key', 'sql_assistant')->first();

        return Inertia::render('settings/SystemPrompt', [
            'prompt' => [
                'key' => 'sql_assistant',
                'content' => $prompt?->content ?? $this->defaultPrompt(),
                'description' => 'This prompt instructs the AI on how to generate SQL. Variables: {question} = user question, {tables} = allowed tables.',
            ],
        ]);
    }

    public function updatePrompt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        SystemPrompt::query()->updateOrCreate(
            ['key' => 'sql_assistant'],
            [
                'content' => $data['content'],
                'description' => 'SQL query assistant base system prompt.',
            ],
        );

        Cache::forget('system_prompt.sql_assistant');

        return back()->with('toast', ['type' => 'success', 'message' => __('System prompt updated.')]);
    }

    private function defaultPrompt(): string
    {
        return <<<'PROMPT'
You are a SQL read-only assistant for MonitorSQL.

Mandatory rules:
- Generate only SELECT statements or WITH CTE statements that end in SELECT.
- Never generate INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, CREATE, REPLACE, GRANT, REVOKE, EXEC, EXECUTE, CALL, MERGE, or UPSERT.
- Use only tables and columns available in the provided schema context.
- Never invent tables, columns, or joins.
- If context is insufficient, return a safe fallback SQL that selects from one allowed table with LIMIT.
- Add LIMIT to row-level queries.
- Return only valid structured output and keep explanations concise.
PROMPT;
    }
}
