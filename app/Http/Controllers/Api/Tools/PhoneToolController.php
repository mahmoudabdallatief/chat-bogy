<?php

namespace App\Http\Controllers\Api\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PhoneToolController extends Controller
{
    public function openApp(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'app' => ['required', 'string', 'max:191'],
        ]);

        return $this->queue('P1', $data['device_id'], 'open_app', ['app' => $data['app']]);
    }

    public function phoneCall(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'action' => ['required', 'in:make_call,end_call'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'call_id' => ['nullable', 'string', 'max:191'],
        ]);
        Validator::make($data, [
            'phone_number' => [$data['action'] === 'make_call' ? 'required' : 'nullable'],
        ])->validate();

        return $this->queue('P2', $data['device_id'], $data['action'], $this->only($data, ['phone_number', 'call_id']));
    }

    public function contacts(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'action' => ['required', 'in:list,search,create,update,delete'],
            'contact_id' => ['nullable', 'string', 'max:191'],
            'query' => ['nullable', 'string', 'max:191'],
            'name' => ['nullable', 'string', 'max:191'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:191'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $required = [
            'search' => ['query' => ['required']],
            'create' => ['name' => ['required'], 'phone_number' => ['required']],
            'update' => ['contact_id' => ['required']],
            'delete' => ['contact_id' => ['required']],
        ];
        Validator::make($data, $required[$data['action']] ?? [])->validate();

        return $this->queue('P3', $data['device_id'], 'contacts_' . $data['action'], $this->only($data, [
            'contact_id', 'query', 'name', 'phone_number', 'email', 'limit',
        ]));
    }

    public function deviceAction(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'action' => ['required', 'in:set_volume,flashlight,set_brightness,set_wifi,set_bluetooth,lock_device'],
            'level' => ['nullable', 'integer', 'between:0,100'],
            'enabled' => ['nullable', 'boolean'],
        ]);
        $needsLevel = in_array($data['action'], ['set_volume', 'set_brightness'], true);
        $needsEnabled = in_array($data['action'], ['flashlight', 'set_wifi', 'set_bluetooth'], true);
        Validator::make($data, [
            'level' => [$needsLevel ? 'required' : 'nullable'],
            'enabled' => [$needsEnabled ? 'required' : 'nullable'],
        ])->validate();

        return $this->queue('P4', $data['device_id'], $data['action'], $this->only($data, ['level', 'enabled']));
    }

    public function notifications(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'action' => ['required', 'in:list,clear,clear_all'],
            'notification_id' => ['nullable', 'string', 'max:191'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);
        Validator::make($data, [
            'notification_id' => [$data['action'] === 'clear' ? 'required' : 'nullable'],
        ])->validate();

        return $this->queue('P5', $data['device_id'], 'notifications_' . $data['action'], $this->only($data, ['notification_id', 'limit']));
    }

    private function queue($group, $deviceId, $action, array $parameters)
    {
        return response()->json([
            'success' => true,
            'tool' => 'phone',
            'group' => $group,
            'command' => [
                'id' => (string) Str::uuid(),
                'device_id' => $deviceId,
                'action' => $action,
                'parameters' => $parameters,
                'requires_device_permission' => true,
            ],
        ], 202);
    }

    private function only(array $data, array $keys)
    {
        return array_filter(array_intersect_key($data, array_flip($keys)), function ($value) {
            return $value !== null;
        });
    }
}
