<?php

namespace Tests\Feature;

use Tests\TestCase;

class TimeToolTest extends TestCase
{
    public function test_reminder_and_alarm_are_queued_for_the_device()
    {
        $this->postJson('/api/tools/time/t1/reminders', [
            'device_id' => 'android-01',
            'title' => 'Take medicine',
            'scheduled_at' => '2026-08-25 09:30:00',
            'timezone' => 'Africa/Cairo',
        ])->assertStatus(202)
            ->assertJsonPath('tool', 'time')
            ->assertJsonPath('group', 'T1')
            ->assertJsonPath('command.action', 'set_reminder')
            ->assertJsonPath('command.parameters.timezone', 'Africa/Cairo');

        $this->postJson('/api/tools/time/t2/alarms', [
            'device_id' => 'android-01',
            'time' => '07:00',
            'days' => ['monday', 'wednesday'],
        ])->assertStatus(202)
            ->assertJsonPath('group', 'T2')
            ->assertJsonPath('command.action', 'set_alarm');
    }

    public function test_date_and_time_are_returned_for_requested_timezone()
    {
        $this->getJson('/api/tools/time/t3/date-time?timezone=Africa%2FCairo')
            ->assertOk()
            ->assertJsonPath('tool', 'time')
            ->assertJsonPath('group', 'T3')
            ->assertJsonPath('data.timezone', 'Africa/Cairo')
            ->assertJsonStructure(['data' => ['date_time', 'date', 'time', 'day_of_week', 'calendar']]);
    }

    public function test_time_tool_rejects_invalid_alarm_time()
    {
        $this->postJson('/api/tools/time/t2/alarms', [
            'device_id' => 'android-01',
            'time' => '25:00',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('time');
    }
}
