<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_redirects_to_chat_for_authenticated_users()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('chat'));
    }

    public function test_chat_page_shows_correct_content()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('chat'));
        $response->assertOk();
        $response->assertSee('Chat SQL');
        $response->assertSee('Sin conexiones configuradas', false);
    }
}
