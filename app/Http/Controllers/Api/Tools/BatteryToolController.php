<?php

namespace App\Http\Controllers\Api\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BatteryToolController extends Controller
{
    public function chargingStarted(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        return $this->queue('B1', $data, 'charging_started', 'Charging started.');
    }

    public function chargingComplete(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        return $this->queue('B2', $data, 'charging_complete', 'Charging complete.');
    }

    public function lowBattery(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'level' => ['required', 'integer', 'between:0,15'],
        ]);

        return $this->queue('B3', $data, 'low_battery', 'Battery level is low.');
    }

    private function queue($group, array $data, $action, $message)
    {
        return response()->json([
            'success' => true,
            'tool' => 'battery',
            'group' => $group,
            'command' => [
                'id' => (string) Str::uuid(),
                'device_id' => $data['device_id'],
                'action' => $action,
                'parameters' => array_filter([
                    'level' => $data['level'] ?? null,
                    'message' => $message,
                ], function ($value) {
                    return $value !== null;
                }),
                'requires_device_permission' => true,
            ],
        ], 202);
    }
}
