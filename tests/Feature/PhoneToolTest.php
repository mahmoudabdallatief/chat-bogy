<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhoneToolTest extends TestCase
{
    /** @dataProvider commandProvider */
    public function test_phone_tool_queues_each_group_of_command($uri, $payload, $expectedAction, $group)
    {
        $this->postJson($uri, array_merge(['device_id' => 'android-01'], $payload))
            ->assertStatus(202)
            ->assertJsonPath('tool', 'phone')
            ->assertJsonPath('group', $group)
            ->assertJsonPath('command.action', $expectedAction);
    }

    public function commandProvider()
    {
        return [
            ['/api/tools/phone/p1/open-app', ['app' => 'com.android.chrome'], 'open_app', 'P1'],
            ['/api/tools/phone/p2/calls', ['action' => 'make_call', 'phone_number' => '+201000000000'], 'make_call', 'P2'],
            ['/api/tools/phone/p3/contacts', ['action' => 'create', 'name' => 'Ali', 'phone_number' => '+201000000000'], 'contacts_create', 'P3'],
            ['/api/tools/phone/p4/device-actions', ['action' => 'set_bluetooth', 'enabled' => true], 'set_bluetooth', 'P4'],
            ['/api/tools/phone/p5/notifications', ['action' => 'clear', 'notification_id' => 'notification-1'], 'notifications_clear', 'P5'],
        ];
    }

    public function test_phone_tool_rejects_missing_required_parameters()
    {
        $this->postJson('/api/tools/phone/p1/open-app', [
            'device_id' => 'android-01',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('app');
    }
}
