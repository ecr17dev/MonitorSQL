<?php

namespace App\Providers;

use App\Models\AiProviderConfig;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->overrideAiProviderKeys();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        RateLimiter::for('monitor-sql-validate', function (Request $request): Limit {
            return Limit::perMinute((int) config('monitorsql.rate_limits.query_validate_per_minute', 30))
                ->by((string) optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('monitor-sql-execute', function (Request $request): Limit {
            return Limit::perMinute((int) config('monitorsql.rate_limits.query_execute_per_minute', 20))
                ->by((string) optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('monitor-sql-ai-generate', function (Request $request): Limit {
            return Limit::perMinute((int) config('monitorsql.rate_limits.query_ai_generate_per_minute', 15))
                ->by((string) optional($request->user())->id ?: $request->ip());
        });
    }

    protected function overrideAiProviderKeys(): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        try {
            $keys = Cache::remember('ai_provider_keys', 3600, function (): array {
                return AiProviderConfig::query()
                    ->where('is_enabled', true)
                    ->get()
                    ->mapWithKeys(fn ($config): array => [
                        $config->provider => $config->api_key,
                    ])
                    ->all();
            });

            foreach ($keys as $provider => $key) {
                if (! empty($key)) {
                    Config::set("ai.providers.{$provider}.key", $key);
                }
            }
        } catch (\Throwable) {
            // Database may not exist yet during migration
        }
    }
}
