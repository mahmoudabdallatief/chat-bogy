<?php

namespace Tests\Feature\AiCore;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_reminder_command(): void
    {
        $this->postJson('/api/ai-core/command/parse', [
            'message' => 'Remind me to take medicine at 9am',
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('tool', 'ai-core')
            ->assertJsonPath('group', 'A1')
            ->assertJsonPath('intent.name', 'reminder.create')
            ->assertJsonPath('resolvable', true)
            ->assertJsonPath('command.tool', 'time')
            ->assertJsonPath('command.action', 'set_reminder')
            ->assertJsonPath('command.parameters.title', 'take medicine')
            ->assertJsonPath('command.parameters.device_id', 'device-01');
    }

    public function test_parse_alarm_command(): void
    {
        $this->postJson('/api/ai-core/command/parse', [
            'message' => 'Wake me up at 7am',
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('intent.name', 'alarm.create')
            ->assertJsonPath('command.action', 'set_alarm')
            ->assertJsonPath('command.parameters.time', '07:00');
    }

    public function test_parse_call_command(): void
    {
        $this->postJson('/api/ai-core/command/parse', [
            'message' => 'Call 01234567890',
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('intent.name', 'call.make')
            ->assertJsonPath('command.action', 'make_call')
            ->assertJsonPath('command.parameters.phone_number', '01234567890');
    }

    public function test_parse_open_app_command(): void
    {
        $this->postJson('/api/ai-core/command/parse', [
            'message' => 'Open Chrome',
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('intent.name', 'app.open')
            ->assertJsonPath('command.action', 'open_app')
            ->assertJsonPath('command.parameters.app', 'Chrome');
    }

    public function test_parse_device_volume_command(): void
    {
        $this->postJson('/api/ai-core/command/parse', [
            'message' => 'Turn up volume to 80%',
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('intent.name', 'device.volume')
            ->assertJsonPath('command.action', 'set_volume')
            ->assertJsonPath('command.parameters.level', 80);
    }

    public function test_parse_unknown_message_returns_unresolvable(): void
    {
        $this->postJson('/api/ai-core/command/parse', [
            'message' => 'What is the meaning of life?',
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('intent.name', null)
            ->assertJsonPath('resolvable', false);
    }

    public function test_execute_returns_command_when_resolvable(): void
    {
        $this->postJson('/api/ai-core/command/execute', [
            'message' => 'Set an alarm for 7am',
            'device_id' => 'device-01',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('intent.name', 'alarm.create')
            ->assertJsonCount(1, 'commands');
    }

    public function test_rejects_missing_message(): void
    {
        $this->postJson('/api/ai-core/command/parse', [
            'device_id' => 'device-01',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_rejects_missing_device_id(): void
    {
        $this->postJson('/api/ai-core/command/parse', [
            'message' => 'Open Chrome',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('device_id');
    }
}
