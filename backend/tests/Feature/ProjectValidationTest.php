<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->token = $user->createToken('test')->plainTextToken;
    }

    public function test_client_name_is_required(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', ['project_name' => 'Test', 'status' => 'Planning', 'priority' => 'Low'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_name']);
    }

    public function test_project_name_is_required(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', ['client_name' => 'Acme', 'status' => 'Planning', 'priority' => 'Low'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_name']);
    }

    public function test_status_must_be_a_valid_value(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', [
                'client_name' => 'Acme',
                'project_name' => 'Test',
                'status' => 'Cancelled',
                'priority' => 'Low',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_priority_must_be_a_valid_value(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', [
                'client_name' => 'Acme',
                'project_name' => 'Test',
                'status' => 'Planning',
                'priority' => 'Critical',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_due_date_cannot_be_before_start_date(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', [
                'client_name' => 'Acme',
                'project_name' => 'Test',
                'status' => 'Planning',
                'priority' => 'Low',
                'start_date' => '2026-07-01',
                'due_date' => '2026-06-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    public function test_due_date_equal_to_start_date_is_valid(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', [
                'client_name' => 'Acme',
                'project_name' => 'Test',
                'status' => 'Planning',
                'priority' => 'Low',
                'start_date' => '2026-07-01',
                'due_date' => '2026-07-01',
            ])
            ->assertStatus(201);
    }
}
