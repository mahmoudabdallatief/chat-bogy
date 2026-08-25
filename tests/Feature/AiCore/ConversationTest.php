<?php

namespace Tests\Feature\AiCore;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_creates_new_conversation(): void
    {
        $this->postJson('/api/ai-core/conversation/start', [
            'device_id' => 'device-01',
            'title' => 'My Chat',
        ])
            ->assertCreated()
            ->assertJsonPath('tool', 'ai-core')
            ->assertJsonPath('group', 'C1')
            ->assertJsonPath('data.device_id', 'device-01')
            ->assertJsonPath('data.title', 'My Chat')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_start_returns_active_conversation_if_exists(): void
    {
        $conversation = Conversation::create([
            'device_id' => 'device-01',
            'title' => 'Existing Chat',
            'is_active' => true,
        ]);

        $this->postJson('/api/ai-core/conversation/start', [
            'device_id' => 'device-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', $conversation->uuid);
    }

    public function test_show_returns_conversation_with_messages(): void
    {
        $conversation = Conversation::create([
            'device_id' => 'device-01',
            'title' => 'Test Chat',
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Hello',
        ]);

        $this->getJson('/api/ai-core/conversation/' . $conversation->uuid . '/messages?device_id=device-01')
            ->assertOk()
            ->assertJsonPath('group', 'C2')
            ->assertJsonPath('data.id', $conversation->uuid)
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.role', 'user')
            ->assertJsonPath('messages.0.content', 'Hello');
    }

    public function test_show_returns_404_for_unknown_conversation(): void
    {
        $this->getJson('/api/ai-core/conversation/unknown-id/messages?device_id=device-01')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_destroy_deletes_conversation(): void
    {
        $conversation = Conversation::create([
            'device_id' => 'device-01',
            'title' => 'To Delete',
        ]);

        $this->deleteJson('/api/ai-core/conversation/' . $conversation->uuid, [
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Conversation deleted.');

        $this->assertDatabaseMissing('conversations', [
            'id' => $conversation->id,
        ]);
    }

    public function test_list_returns_conversations(): void
    {
        Conversation::create([
            'device_id' => 'device-01',
            'title' => 'Chat 1',
        ]);

        Conversation::create([
            'device_id' => 'device-02',
            'title' => 'Chat 2',
        ]);

        $this->getJson('/api/ai-core/conversation/list?device_id=device-01')
            ->assertOk()
            ->assertJsonPath('group', 'C4')
            ->assertJsonCount(1, 'data');
    }

    public function test_list_filters_by_active(): void
    {
        Conversation::create([
            'device_id' => 'device-01',
            'title' => 'Active Chat',
            'is_active' => true,
        ]);

        Conversation::create([
            'device_id' => 'device-01',
            'title' => 'Archived Chat',
            'is_active' => false,
        ]);

        $this->getJson('/api/ai-core/conversation/list?device_id=device-01&is_active=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Active Chat');
    }
}
