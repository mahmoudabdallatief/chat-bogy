<?php

namespace Tests\Feature;

use Tests\TestCase;

class BatteryToolTest extends TestCase
{
    /** @dataProvider notificationProvider */
    public function test_battery_tool_queues_each_notification($uri, $payload, $group, $action)
    {
        $this->postJson($uri, array_merge(['device_id' => 'android-01'], $payload))
            ->assertStatus(202)
            ->assertJsonPath('tool', 'battery')
            ->assertJsonPath('group', $group)
            ->assertJsonPath('command.action', $action)
            ->assertJsonPath('command.device_id', 'android-01')
            ->assertJsonPath('command.requires_device_permission', true);
    }

    public function notificationProvider()
    {
        return [
            ['/api/tools/battery/b1/charging-started', ['level' => 42], 'B1', 'charging_started'],
            ['/api/tools/battery/b2/charging-complete', ['level' => 100], 'B2', 'charging_complete'],
            ['/api/tools/battery/b3/low-battery', ['level' => 15], 'B3', 'low_battery'],
            ['/api/tools/battery/b4/charger-removed', ['level' => 50], 'B4', 'charger_removed'],
        ];
    }

    public function test_low_battery_notification_rejects_a_level_above_the_low_threshold()
    {
        $this->postJson('/api/tools/battery/b3/low-battery', [
            'device_id' => 'android-01',
            'level' => 16,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('level');
    }
}
