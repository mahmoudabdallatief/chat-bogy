<?php

namespace Tests\Feature\AiCore;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolFlowchartTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_resolves_intent_to_command(): void
    {
        $this->postJson('/api/ai-core/tools/execute', [
            'intent' => 'reminder.create',
            'entities' => [
                'title' => 'take medicine',
                'scheduled_at' => '2026-08-25T09:00:00Z',
            ],
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('tool', 'ai-core')
            ->assertJsonPath('group', 'F1')
            ->assertJsonPath('data.tool', 'time')
            ->assertJsonPath('data.group', 'T1')
            ->assertJsonPath('data.action', 'set_reminder')
            ->assertJsonPath('data.endpoint', '/api/tools/time/t1/reminders')
            ->assertJsonPath('data.parameters.device_id', 'device-01')
            ->assertJsonPath('data.parameters.title', 'take medicine')
            ->assertJsonPath('data.requires_device_permission', true);
    }

    public function test_execute_returns_422_for_unregistered_intent(): void
    {
        $this->postJson('/api/ai-core/tools/execute', [
            'intent' => 'unknown.intent',
            'entities' => [],
            'device_id' => 'device-01',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "Intent 'unknown.intent' is not registered in the flowchart.");
    }

    public function test_execute_returns_422_for_missing_required_entity(): void
    {
        $this->postJson('/api/ai-core/tools/execute', [
            'intent' => 'reminder.create',
            'entities' => [],
            'device_id' => 'device-01',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['required_entities']);
    }

    public function test_resolve_handles_multiple_intents(): void
    {
        $this->postJson('/api/ai-core/tools/resolve', [
            'intents' => ['reminder.create', 'app.open'],
            'entities' => [
                'title' => 'take medicine',
                'scheduled_at' => '2026-08-25T09:00:00Z',
                'app' => 'com.android.chrome',
            ],
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('group', 'F3')
            ->assertJsonPath('data.resolved_count', 2)
            ->assertJsonPath('data.total_intents', 2);
    }

    public function test_flowchart_endpoint_returns_mapping(): void
    {
        $this->getJson('/api/ai-core/tools/flowchart')
            ->assertOk()
            ->assertJsonPath('tool', 'ai-core')
            ->assertJsonPath('group', 'F2')
            ->assertJsonStructure(['data' => ['flowchart', 'total_intents']])
            ->assertJsonPath('data.total_intents', 21);
    }

    public function test_resolve_skips_unresolvable_intents(): void
    {
        $this->postJson('/api/ai-core/tools/resolve', [
            'intents' => ['reminder.create', 'unknown.intent'],
            'entities' => [],
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.total_intents', 2);
    }

    public function test_rejects_missing_device_id(): void
    {
        $this->postJson('/api/ai-core/tools/execute', [
            'intent' => 'reminder.create',
            'entities' => ['title' => 'test', 'scheduled_at' => '2026-08-25'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('device_id');
    }
}
