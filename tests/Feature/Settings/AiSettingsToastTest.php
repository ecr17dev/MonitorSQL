<?php

namespace Tests\Feature\Settings;

use App\Models\AiProviderConfig;
use App\Models\SystemPrompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSettingsToastTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_provider_settings_update_sets_flash_toast(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->from(route('ai-providers.edit'))
            ->post(route('ai-providers.update'), [
                'providers' => [
                    [
                        'provider' => 'openai',
                        'api_key' => 'sk-test-key',
                        'is_enabled' => true,
                        'default_model' => 'gpt-5-mini',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('ai-providers.edit'))
            ->assertSessionHas('flash.toast.type', 'success');

        $this->assertDatabaseHas('ai_provider_configs', [
            'provider' => 'openai',
            'is_enabled' => 1,
            'default_model' => 'gpt-5-mini',
        ]);

        $this->assertNotNull(
            AiProviderConfig::query()->where('provider', 'openai')->first()?->api_key
        );
    }

    public function test_system_prompt_update_sets_flash_toast(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->from(route('system-prompt.edit'))
            ->post(route('system-prompt.update'), [
                'content' => 'Prompt de prueba para SQL seguro.',
            ]);

        $response
            ->assertRedirect(route('system-prompt.edit'))
            ->assertSessionHas('flash.toast.type', 'success');

        $this->assertDatabaseHas('system_prompts', [
            'key' => 'sql_assistant',
            'content' => 'Prompt de prueba para SQL seguro.',
        ]);

        $this->assertNotNull(SystemPrompt::query()->where('key', 'sql_assistant')->first());
    }
}
