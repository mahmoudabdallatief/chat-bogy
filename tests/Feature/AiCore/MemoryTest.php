<?php

namespace Tests\Feature\AiCore;

use App\Models\Memory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_memory(): void
    {
        $this->postJson('/api/ai-core/memory/store', [
            'device_id' => 'device-01',
            'key' => 'user_name',
            'value' => 'Ahmed',
            'type' => 'fact',
            'tags' => ['identity', 'user'],
        ])
            ->assertCreated()
            ->assertJsonPath('tool', 'ai-core')
            ->assertJsonPath('group', 'M1')
            ->assertJsonPath('data.key', 'user_name')
            ->assertJsonPath('data.value', 'Ahmed')
            ->assertJsonPath('data.type', 'fact');
    }

    public function test_store_memory_updates_existing_key(): void
    {
        Memory::create([
            'device_id' => 'device-01',
            'key' => 'user_name',
            'value' => 'Ahmed',
            'type' => 'fact',
        ]);

        $this->postJson('/api/ai-core/memory/store', [
            'device_id' => 'device-01',
            'key' => 'user_name',
            'value' => 'Mohamed',
            'type' => 'fact',
        ])
            ->assertCreated()
            ->assertJsonPath('data.value', 'Mohamed');

        $this->assertDatabaseHas('memories', [
            'device_id' => 'device-01',
            'key' => 'user_name',
            'value' => 'Mohamed',
        ]);
        $this->assertDatabaseMissing('memories', [
            'value' => 'Ahmed',
        ]);
    }

    public function test_retrieve_relevant_memories(): void
    {
        Memory::create([
            'device_id' => 'device-01',
            'key' => 'user_name',
            'value' => 'Ahmed',
            'type' => 'fact',
            'tags' => ['identity', 'reminder.create'],
        ]);

        Memory::create([
            'device_id' => 'device-01',
            'key' => 'preferred_language',
            'value' => 'ar',
            'type' => 'preference',
            'tags' => ['language'],
        ]);

        $this->getJson('/api/ai-core/memory/retrieve?device_id=device-01&intent=reminder.create')
            ->assertOk()
            ->assertJsonPath('group', 'M2')
            ->assertJsonCount(1, 'data');
    }

    public function test_search_memories(): void
    {
        Memory::create([
            'device_id' => 'device-01',
            'key' => 'user_name',
            'value' => 'Ahmed',
            'type' => 'fact',
        ]);

        $this->getJson('/api/ai-core/memory/search?device_id=device-01&query=Ahmed')
            ->assertOk()
            ->assertJsonPath('group', 'M3')
            ->assertJsonCount(1, 'data');
    }

    public function test_list_memories(): void
    {
        Memory::create([
            'device_id' => 'device-01',
            'key' => 'user_name',
            'value' => 'Ahmed',
            'type' => 'fact',
        ]);

        $this->getJson('/api/ai-core/memory/list?device_id=device-01')
            ->assertOk()
            ->assertJsonPath('group', 'M4')
            ->assertJsonCount(1, 'data');
    }

    public function test_delete_memory(): void
    {
        $memory = Memory::create([
            'device_id' => 'device-01',
            'key' => 'user_name',
            'value' => 'Ahmed',
            'type' => 'fact',
        ]);

        $this->deleteJson('/api/ai-core/memory/' . $memory->uuid, [
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('group', 'M5')
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('memories', [
            'id' => $memory->id,
        ]);
    }

    public function test_delete_memory_returns_404(): void
    {
        $this->deleteJson('/api/ai-core/memory/nonexistent-id', [
            'device_id' => 'device-01',
        ])
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_rejects_missing_device_id(): void
    {
        $this->postJson('/api/ai-core/memory/store', [
            'key' => 'user_name',
            'value' => 'Ahmed',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('device_id');
    }
}
