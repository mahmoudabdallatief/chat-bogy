<?php

namespace App\Http\Controllers\Api\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BatteryToolController extends Controller
{
    public function report(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'level' => ['required', 'integer', 'between:0,100'],
            'is_charging' => ['required', 'boolean'],
            'temperature' => ['nullable', 'numeric', 'between:-50,100'],
        ]);

        return response()->json([
            'success' => true,
            'tool' => 'battery',
            'data' => $data,
            'warning' => $data['level'] <= 15 && !$data['is_charging']
                ? 'Battery level is low.'
                : null,
        ]);
    }
}
