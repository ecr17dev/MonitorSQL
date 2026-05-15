<?php

namespace Tests\Feature;

use App\Models\DatabaseConnection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SavedQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SavedQueryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DatabaseConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::factory()->create(['key' => 'queries.execute', 'name' => 'queries execute']);
        $role = Role::factory()->create(['key' => 'query_user', 'name' => 'query user']);
        $role->permissions()->sync([$permission->id]);

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->roles()->sync([$role->id]);

        $this->connection = DatabaseConnection::factory()->create();
    }

    public function test_saved_index_requires_authentication(): void
    {
        $response = $this->get(route('queries.saved.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_saved_index_shows_user_queries(): void
    {
        SavedQuery::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'connection_id' => $this->connection->id,
        ]);

        $anotherUser = User::factory()->create();
        SavedQuery::factory()->create([
            'user_id' => $anotherUser->id,
            'connection_id' => $this->connection->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('queries.saved.index'));

        $response->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('queries/Saved')
                ->has('saved.data', 2)
        );
    }

    public function test_saved_index_filters_by_category(): void
    {
        SavedQuery::factory()->create([
            'user_id' => $this->user->id,
            'connection_id' => $this->connection->id,
            'category' => 'report',
        ]);
        SavedQuery::factory()->create([
            'user_id' => $this->user->id,
            'connection_id' => $this->connection->id,
            'category' => 'audit',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('queries.saved.index', ['category' => 'report']));

        $response->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('saved.data', 1)
        );
    }

    public function test_saved_store_creates_query(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('queries.saved.store'), [
                'connection_id' => $this->connection->id,
                'name' => 'Test Query',
                'sql' => 'SELECT * FROM users',
            ]);

        $response->assertRedirect(route('queries.saved.index'));
        $response->assertSessionHas('flash.toast.type', 'success');
        $this->assertDatabaseHas('saved_queries', [
            'name' => 'Test Query',
            'sql' => 'SELECT * FROM users',
        ]);
    }

    public function test_saved_update_name_and_sql(): void
    {
        $saved = SavedQuery::factory()->create([
            'user_id' => $this->user->id,
            'connection_id' => $this->connection->id,
            'name' => 'Old Name',
            'sql' => 'SELECT 1',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('queries.saved.update', $saved->id), [
                'name' => 'New Name',
                'sql' => 'SELECT 2',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.toast.type', 'success');
        $this->assertDatabaseHas('saved_queries', [
            'id' => $saved->id,
            'name' => 'New Name',
            'sql' => 'SELECT 2',
        ]);
    }

    public function test_saved_update_toggle_favorite(): void
    {
        $saved = SavedQuery::factory()->create([
            'user_id' => $this->user->id,
            'connection_id' => $this->connection->id,
            'is_favorite' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('queries.saved.update', $saved->id), [
                'is_favorite' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.toast.type', 'success');
        $this->assertDatabaseHas('saved_queries', [
            'id' => $saved->id,
            'is_favorite' => 1,
        ]);
    }

    public function test_saved_destroy_soft_deletes(): void
    {
        $saved = SavedQuery::factory()->create([
            'user_id' => $this->user->id,
            'connection_id' => $this->connection->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('queries.saved.destroy', $saved->id));

        $response->assertRedirect();
        $response->assertSessionHas('flash.toast.type', 'info');
        $this->assertSoftDeleted('saved_queries', ['id' => $saved->id]);
    }

    public function test_saved_destroy_denies_other_users_query(): void
    {
        $anotherUser = User::factory()->create();
        $saved = SavedQuery::factory()->create([
            'user_id' => $anotherUser->id,
            'connection_id' => $this->connection->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('queries.saved.destroy', $saved->id));

        $response->assertNotFound();
        $this->assertNotSoftDeleted('saved_queries', ['id' => $saved->id]);
    }
}
