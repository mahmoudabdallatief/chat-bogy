<?php

namespace App\Http\Controllers\Api\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RobotToolController extends Controller
{
    /** R1: Sets the robot to its idle behavior (resting animation loop). */
    public function idle(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        return $this->queue('R1', $data['device_id'], 'robot_idle', $this->withoutNulls([
            'type' => 'idle',
            'level' => $data['level'] ?? null,
        ]));
    }

    /** R2: Plays the talking animation while the robot speaks. */
    public function talking(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'while_speaking' => ['sometimes', 'boolean'],
            'level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        return $this->queue('R2', $data['device_id'], 'robot_talking', $this->withoutNulls([
            'type' => 'talking',
            'while_speaking' => $data['while_speaking'] ?? true,
            'level' => $data['level'] ?? null,
        ]));
    }

    /** R3: Plays the wake animation when the robot is awakened. */
    public function wake(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'source' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        return $this->queue('R3', $data['device_id'], 'robot_wake', $this->withoutNulls([
            'type' => 'wake',
            'source' => $data['source'] ?? null,
            'level' => $data['level'] ?? null,
        ]));
    }

    private function queue(string $group, string $deviceId, string $action, array $parameters)
    {
        return response()->json([
            'success' => true,
            'tool' => 'robot',
            'group' => $group,
            'command' => [
                'id' => (string) Str::uuid(),
                'device_id' => $deviceId,
                'action' => $action,
                'parameters' => $parameters,
                'requires_device_permission' => false,
            ],
        ], 202);
    }

    private function withoutNulls(array $data): array
    {
        return array_filter($data, function ($value) {
            return $value !== null;
        });
    }
}