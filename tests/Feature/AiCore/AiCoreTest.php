<?php

namespace Tests\Feature\AiCore;

use App\Models\Memory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_processes_message_and_returns_command(): void
    {
        $this->postJson('/api/ai-core/chat', [
            'message' => 'Remind me to take medicine at 9am',
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('tool', 'ai-core')
            ->assertJsonPath('group', 'A1')
            ->assertJsonPath('data.intent.name', 'reminder.create')
            ->assertJsonCount(1, 'data.commands')
            ->assertJsonStructure(['data' => ['reply']])
            ->assertJsonPath('data.commands.0.action', 'set_reminder')
            ->assertJsonPath('data.commands.0.tool', 'time');
    }

    public function test_chat_stores_user_and_assistant_messages(): void
    {
        $response = $this->postJson('/api/ai-core/chat', [
            'message' => 'Set an alarm for 7am',
            'device_id' => 'device-01',
        ])->assertOk();

        $conversationId = $response->json('data.conversation_id');

        $this->getJson('/api/ai-core/conversation/' . $conversationId . '/messages?device_id=device-01')
            ->assertOk()
            ->assertJsonCount(2, 'messages');
    }

    public function test_chat_clears_conversation_on_clear_intent(): void
    {
        $response = $this->postJson('/api/ai-core/chat', [
            'message' => 'Start over',
            'device_id' => 'device-01',
        ])->assertOk();

        $conversationId = $response->json('data.conversation_id');

        $this->assertNotEquals('Start over', $response->json('data.reply'));
    }

    public function test_chat_stores_and_retrieves_memory(): void
    {
        $this->postJson('/api/ai-core/chat', [
            'message' => 'Remember that my favorite color is blue',
            'device_id' => 'device-01',
            'title' => 'Color Chat',
        ])->assertOk();

        $this->assertDatabaseHas('memories', [
            'device_id' => 'device-01',
            'key' => 'favorite_color',
        ]);
    }

    public function test_chat_handles_fallback_for_unknown_message(): void
    {
        $this->postJson('/api/ai-core/chat', [
            'message' => 'The sun is shining',
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.intent.name', null)
            ->assertJsonPath('data.commands', [])
            ->assertJsonStructure(['data' => ['reply']]);
    }

    public function test_chat_creates_conversation_if_none_provided(): void
    {
        $response = $this->postJson('/api/ai-core/chat', [
            'message' => 'Open Chrome',
            'device_id' => 'device-01',
        ])->assertOk();

        $conversationId = $response->json('data.conversation_id');
        $this->assertNotNull($conversationId);
    }

    public function test_chat_uses_existing_conversation(): void
    {
        $response1 = $this->postJson('/api/ai-core/chat', [
            'message' => 'Remind me to take medicine at 9am',
            'device_id' => 'device-01',
            'title' => 'Med Reminder',
        ])->assertOk();

        $conversationId = $response1->json('data.conversation_id');

        $response2 = $this->postJson('/api/ai-core/chat', [
            'message' => 'Set an alarm for 7am',
            'device_id' => 'device-01',
            'conversation_id' => $conversationId,
        ])->assertOk();

        $this->assertEquals($conversationId, $response2->json('data.conversation_id'));
    }

    public function test_rejects_missing_message(): void
    {
        $this->postJson('/api/ai-core/chat', [
            'device_id' => 'device-01',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_rejects_missing_device_id(): void
    {
        $this->postJson('/api/ai-core/chat', [
            'message' => 'Hello',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('device_id');
    }
}
