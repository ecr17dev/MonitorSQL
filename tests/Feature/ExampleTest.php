<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_home()
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_redirected_to_chat_from_home()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('home'));

        $response->assertRedirect(route('chat'));
    }
}
