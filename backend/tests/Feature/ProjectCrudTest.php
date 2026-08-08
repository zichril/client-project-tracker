<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function projectPayload(array $overrides = []): array
    {
        return array_merge([
            'client_name' => 'Acme Corp',
            'project_name' => 'Website Redesign',
            'description' => 'A redesign project',
            'status' => 'Planning',
            'priority' => 'High',
            'start_date' => '2026-06-01',
            'due_date' => '2026-07-01',
        ], $overrides);
    }

    public function test_can_list_own_projects(): void
    {
        Project::factory()->count(3)->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_cannot_see_other_users_projects(): void
    {
        $other = User::factory()->create();
        Project::factory()->count(2)->create(['user_id' => $other->id]);

        $this->withToken($this->token)->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_can_create_project(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', $this->projectPayload())
            ->assertStatus(201)
            ->assertJsonFragment(['clientName' => 'Acme Corp'])
            ->assertJsonFragment(['projectName' => 'Website Redesign'])
            ->assertJsonFragment(['status' => 'Planning'])
            ->assertJsonFragment(['priority' => 'High']);

        $this->assertDatabaseHas('projects', [
            'client_name' => 'Acme Corp',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_show_own_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $project->id]);
    }

    public function test_cannot_show_other_users_project(): void
    {
        $other = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $other->id]);

        $this->withToken($this->token)->getJson("/api/projects/{$project->id}")
            ->assertNotFound();
    }

    public function test_can_update_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)
            ->putJson("/api/projects/{$project->id}", $this->projectPayload(['status' => 'In Progress']))
            ->assertOk()
            ->assertJsonFragment(['status' => 'In Progress']);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'In Progress']);
    }

    public function test_cannot_update_other_users_project(): void
    {
        $other = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $other->id]);

        $this->withToken($this->token)
            ->putJson("/api/projects/{$project->id}", $this->projectPayload())
            ->assertNotFound();
    }

    public function test_can_delete_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->deleteJson("/api/projects/{$project->id}")
            ->assertOk();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_cannot_delete_other_users_project(): void
    {
        $other = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $other->id]);

        $this->withToken($this->token)->deleteJson("/api/projects/{$project->id}")
            ->assertNotFound();
    }
}
