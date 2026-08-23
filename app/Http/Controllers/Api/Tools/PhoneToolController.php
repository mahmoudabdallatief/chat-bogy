<?php

namespace App\Http\Controllers\Api\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PhoneToolController extends Controller
{
    private const COMMANDS = [
        'open_app', 'dial_contact', 'send_sms', 'set_volume', 'flashlight',
    ];

    public function execute(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'action' => ['required', 'string', 'in:' . implode(',', self::COMMANDS)],
            'parameters' => ['nullable', 'array'],
        ]);

        return response()->json([
            'success' => true,
            'tool' => 'phone',
            'command' => [
                'id' => (string) Str::uuid(),
                'device_id' => $data['device_id'],
                'action' => $data['action'],
                'parameters' => $data['parameters'] ?? [],
                'requires_device_permission' => true,
            ],
        ], 202);
    }
}
