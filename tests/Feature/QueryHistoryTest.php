<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\QueryRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class QueryHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::factory()->create(['key' => 'queries.execute', 'name' => 'queries execute']);
        $role = Role::factory()->create(['key' => 'query_user', 'name' => 'query user']);
        $role->permissions()->sync([$permission->id]);

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->roles()->sync([$role->id]);
    }

    public function test_history_index_requires_authentication(): void
    {
        $response = $this->get(route('queries.history.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_history_index_shows_user_queries(): void
    {
        QueryRun::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'success',
        ]);

        QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
        ]);

        $anotherUser = User::factory()->create();
        QueryRun::factory()->create([
            'user_id' => $anotherUser->id,
            'status' => 'success',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('queries.history.index'));

        $response->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('queries/History')
                ->has('runs.data', 4)
                ->has('filters')
                ->has('categories')
                ->has('statuses')
        );
    }

    public function test_history_index_filters_by_status(): void
    {
        QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'success',
        ]);
        QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('queries.history.index', ['status' => 'failed']));

        $response->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('runs.data', 1)
                ->where('runs.data.0.status', 'failed')
        );
    }

    public function test_history_index_filters_by_category(): void
    {
        QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'category' => 'report',
        ]);
        QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'category' => 'audit',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('queries.history.index', ['category' => 'report']));

        $response->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('runs.data', 1)
        );
    }

    public function test_history_index_filters_by_favorites(): void
    {
        QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'is_favorite' => true,
        ]);
        QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'is_favorite' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('queries.history.index', ['is_favorite' => '1']));

        $response->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('runs.data', 1)
        );
    }

    public function test_history_index_filters_by_search(): void
    {
        QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'sql' => 'SELECT * FROM users',
        ]);
        QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'sql' => 'SELECT COUNT(*) FROM orders',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('queries.history.index', ['search' => 'users']));

        $response->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('runs.data', 1)
        );
    }

    public function test_history_show_displays_single_query(): void
    {
        $run = QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'sql' => 'SELECT * FROM products',
            'status' => 'success',
            'category' => 'report',
            'note' => 'Test note',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('queries.history.show', $run->id));

        $response->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('queries/Show')
                ->where('run.id', $run->id)
                ->where('run.sql', 'SELECT * FROM products')
        );
    }

    public function test_history_show_denies_other_users_query(): void
    {
        $anotherUser = User::factory()->create();
        $run = QueryRun::factory()->create([
            'user_id' => $anotherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('queries.history.show', $run->id));

        $response->assertNotFound();
    }

    public function test_history_update_category(): void
    {
        $run = QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'category' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('queries.history.update', $run->id), [
                'category' => 'audit',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.toast.type', 'success');
        $this->assertDatabaseHas('query_runs', [
            'id' => $run->id,
            'category' => 'audit',
        ]);
    }

    public function test_history_update_validates_category(): void
    {
        $run = QueryRun::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('queries.history.update', $run->id), [
                'category' => 'invalid_category',
            ]);

        $response->assertSessionHasErrors('category');
    }

    public function test_history_update_tags(): void
    {
        $run = QueryRun::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('queries.history.update', $run->id), [
                'tags' => ['ventas', 'reporte_mensual'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.toast.type', 'success');
        $this->assertDatabaseHas('query_runs', [
            'id' => $run->id,
            'tags' => json_encode(['ventas', 'reporte_mensual']),
        ]);
    }

    public function test_history_update_favorite(): void
    {
        $run = QueryRun::factory()->create([
            'user_id' => $this->user->id,
            'is_favorite' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('queries.history.update', $run->id), [
                'is_favorite' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.toast.type', 'success');
        $this->assertDatabaseHas('query_runs', [
            'id' => $run->id,
            'is_favorite' => 1,
        ]);
    }

    public function test_history_destroy_deletes_query(): void
    {
        $run = QueryRun::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('queries.history.destroy', $run->id));

        $response->assertRedirect(route('queries.history.index'));
        $response->assertSessionHas('flash.toast.type', 'info');
        $this->assertDatabaseMissing('query_runs', ['id' => $run->id]);
    }

    public function test_history_destroy_denies_other_users_query(): void
    {
        $anotherUser = User::factory()->create();
        $run = QueryRun::factory()->create([
            'user_id' => $anotherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('queries.history.destroy', $run->id));

        $response->assertNotFound();
        $this->assertDatabaseHas('query_runs', ['id' => $run->id]);
    }
}
