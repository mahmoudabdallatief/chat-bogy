<?php

namespace Tests\Feature;

use Tests\TestCase;

class RobotToolTest extends TestCase
{
    /** @dataProvider animationProvider */
    public function test_robot_tool_queues_each_animation($uri, $payload, $group, $type)
    {
        $this->postJson($uri, array_merge(['device_id' => 'robot-01'], $payload))
            ->assertStatus(202)
            ->assertJsonPath('tool', 'robot')
            ->assertJsonPath('group', $group)
            ->assertJsonPath('command.action', 'robot_' . $type)
            ->assertJsonPath('command.parameters.type', $type)
            ->assertJsonPath('command.device_id', 'robot-01')
            ->assertJsonPath('command.requires_device_permission', false);
    }

    public function animationProvider()
    {
        return [
            ['/api/tools/robot/r1/idle', ['level' => 20], 'R1', 'idle'],
            ['/api/tools/robot/r2/talking', ['while_speaking' => true], 'R2', 'talking'],
            ['/api/tools/robot/r3/wake', ['source' => 'wake_word'], 'R3', 'wake'],
        ];
    }

    public function test_robot_tool_rejects_invalid_animation_level()
    {
        $this->postJson('/api/tools/robot/r1/idle', [
            'device_id' => 'robot-01',
            'level' => 101,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('level');
    }
}