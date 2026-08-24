<?php

namespace App\Http\Controllers\Api\Tools;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TimeToolController extends Controller
{
    public function setReminder(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'payload' => ['nullable', 'array'],
        ]);

        $timezone = $data['timezone'] ?? config('app.timezone', 'UTC');

        return $this->queue('T1', $data['device_id'], 'set_reminder', [
            'title' => $data['title'],
            'scheduled_at' => Carbon::parse($data['scheduled_at'], $timezone)->toIso8601String(),
            'timezone' => $timezone,
            'payload' => $data['payload'] ?? null,
        ]);
    }

    public function setAlarm(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'time' => ['required', 'date_format:H:i'],
            'label' => ['nullable', 'string', 'max:255'],
            'days' => ['nullable', 'array'],
            'days.*' => ['string', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'enabled' => ['sometimes', 'boolean'],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ]);

        return $this->queue('T2', $data['device_id'], 'set_alarm', $this->only($data, [
            'time', 'label', 'days', 'enabled', 'timezone',
        ]));
    }

    public function dateTime(Request $request)
    {
        $data = $request->validate([
            'timezone' => ['sometimes', 'string', 'timezone'],
        ]);
        $timezone = $data['timezone'] ?? config('app.timezone', 'UTC');
        $now = Carbon::now($timezone);

        return response()->json([
            'success' => true,
            'tool' => 'time',
            'group' => 'T3',
            'data' => [
                'timezone' => $timezone,
                'date_time' => $now->toIso8601String(),
                'date' => $now->toDateString(),
                'time' => $now->format('H:i:s'),
                'day_of_week' => strtolower($now->format('l')),
                'calendar' => [
                    'year' => $now->year,
                    'month' => $now->month,
                    'day' => $now->day,
                ],
            ],
        ]);
    }

    private function queue(string $group, string $deviceId, string $action, array $parameters)
    {
        return response()->json([
            'success' => true,
            'tool' => 'time',
            'group' => $group,
            'command' => [
                'id' => (string) Str::uuid(),
                'device_id' => $deviceId,
                'action' => $action,
                'parameters' => $this->withoutNulls($parameters),
                'requires_device_permission' => true,
            ],
        ], 202);
    }

    private function only(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }

    private function withoutNulls(array $data): array
    {
        return array_filter($data, function ($value) {
            return $value !== null;
        });
    }
}
