<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFilterTest extends TestCase
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

    private function make(array $attrs): void
    {
        Project::factory()->create(array_merge(['user_id' => $this->user->id], $attrs));
    }

    public function test_search_matches_client_name(): void
    {
        $this->make(['client_name' => 'Acme Corp', 'project_name' => 'Alpha', 'status' => 'Planning', 'priority' => 'Low']);
        $this->make(['client_name' => 'Globex', 'project_name' => 'Beta', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?search=Acme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['clientName' => 'Acme Corp']);
    }

    public function test_search_matches_project_name(): void
    {
        $this->make(['client_name' => 'Corp A', 'project_name' => 'Portal Launch', 'status' => 'Planning', 'priority' => 'Low']);
        $this->make(['client_name' => 'Corp B', 'project_name' => 'App Redesign', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?search=Portal')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['projectName' => 'Portal Launch']);
    }

    public function test_filter_by_status(): void
    {
        $this->make(['client_name' => 'A', 'project_name' => 'P1', 'status' => 'Planning', 'priority' => 'Low']);
        $this->make(['client_name' => 'B', 'project_name' => 'P2', 'status' => 'In Progress', 'priority' => 'Low']);
        $this->make(['client_name' => 'C', 'project_name' => 'P3', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?status=Planning')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filter_by_priority(): void
    {
        $this->make(['client_name' => 'A', 'project_name' => 'P1', 'status' => 'Planning', 'priority' => 'High']);
        $this->make(['client_name' => 'B', 'project_name' => 'P2', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?priority=High')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['priority' => 'High']);
    }

    public function test_sort_by_client_name_ascending(): void
    {
        $this->make(['client_name' => 'Zebra Co', 'project_name' => 'Z', 'status' => 'Planning', 'priority' => 'Low']);
        $this->make(['client_name' => 'Acme Corp', 'project_name' => 'A', 'status' => 'Planning', 'priority' => 'Low']);

        $data = $this->withToken($this->token)
            ->getJson('/api/projects?sort_by=client_name&sort_dir=asc')
            ->assertOk()
            ->json('data');

        $this->assertEquals('Acme Corp', $data[0]['clientName']);
        $this->assertEquals('Zebra Co', $data[1]['clientName']);
    }

    public function test_invalid_sort_by_falls_back_to_created_at(): void
    {
        $this->make(['client_name' => 'A', 'project_name' => 'P', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?sort_by=evil_column; DROP TABLE projects--')
            ->assertOk();

        $this->assertDatabaseHas('projects', ['client_name' => 'A']);
    }

    public function test_combined_search_and_status_filter(): void
    {
        $this->make(['client_name' => 'Acme', 'project_name' => 'P1', 'status' => 'Planning', 'priority' => 'Low']);
        $this->make(['client_name' => 'Acme', 'project_name' => 'P2', 'status' => 'In Progress', 'priority' => 'Low']);
        $this->make(['client_name' => 'Other', 'project_name' => 'P3', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?search=Acme&status=Planning')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
