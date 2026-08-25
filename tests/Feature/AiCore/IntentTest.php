<?php

namespace Tests\Feature\AiCore;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_reminder_intent(): void
    {
        $this->postJson('/api/ai-core/intent/detect', [
            'message' => 'Remind me to take medicine at 9am',
        ])
            ->assertOk()
            ->assertJsonPath('tool', 'ai-core')
            ->assertJsonPath('group', 'I1')
            ->assertJsonPath('data.intent', 'reminder.create')
            ->assertJsonStructure(['data' => ['entities' => ['title', 'time']]]);
    }

    public function test_detects_alarm_intent(): void
    {
        $this->postJson('/api/ai-core/intent/detect', [
            'message' => 'Set an alarm for 7am',
        ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'alarm.create')
            ->assertJsonPath('data.entities.time', '07:00');
    }

    public function test_detects_call_intent(): void
    {
        $this->postJson('/api/ai-core/intent/detect', [
            'message' => 'Call +201000000000',
        ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'call.make')
            ->assertJsonPath('data.entities.phone_number', '+201000000000');
    }

    public function test_detects_open_app_intent(): void
    {
        $this->postJson('/api/ai-core/intent/detect', [
            'message' => 'Open Chrome on my phone',
        ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'app.open')
            ->assertJsonPath('data.entities.app', 'Chrome on my phone');
    }

    public function test_detects_wifi_intent(): void
    {
        $this->postJson('/api/ai-core/intent/detect', [
            'message' => 'Turn on wifi',
        ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'device.wifi');
    }

    public function test_returns_unknown_intent_with_zero_confidence(): void
    {
        $this->postJson('/api/ai-core/intent/detect', [
            'message' => 'The weather is nice today',
        ])
            ->assertOk()
            ->assertJsonPath('data.intent', null)
            ->assertJsonPath('data.confidence', 0);
    }

    public function test_lists_all_intents(): void
    {
        $this->getJson('/api/ai-core/intent/list')
            ->assertOk()
            ->assertJsonPath('tool', 'ai-core')
            ->assertJsonPath('group', 'I2')
            ->assertJsonStructure(['data' => ['intents', 'total']])
            ->assertJsonPath('data.total', 22);
    }

    public function test_rejects_missing_message(): void
    {
        $this->postJson('/api/ai-core/intent/detect', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }
}
