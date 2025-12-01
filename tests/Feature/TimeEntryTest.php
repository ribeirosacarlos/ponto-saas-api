<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_clock_in()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/employee/clock', [
            'type' => 'in',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('time_entries', [
            'user_id' => $user->id,
            'type' => 'in',
        ]);
    }

    public function test_cannot_clock_in_twice_within_one_minute()
    {
        $user = User::factory()->create();

        // First clock in
        $this->actingAs($user)->postJson('/api/employee/clock', [
            'type' => 'in',
        ]);

        // Second clock in immediately
        $response = $this->actingAs($user)->postJson('/api/employee/clock', [
            'type' => 'out',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Aguarde 1 minuto entre os registros.',
            ]);
    }

    public function test_can_clock_in_after_one_minute()
    {
        $user = User::factory()->create();

        // First clock in 61 seconds ago
        TimeEntry::create([
            'user_id' => $user->id,
            'clocked_at' => now()->subSeconds(61),
            'type' => 'in',
            'source' => 'web',
        ]);

        // Second clock in now
        $response = $this->actingAs($user)->postJson('/api/employee/clock', [
            'type' => 'out',
        ]);

        $response->assertStatus(201);
    }
}
