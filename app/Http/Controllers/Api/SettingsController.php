<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    private SettingsService $settings;

    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
    }

    public function index(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Device-Id');
        $group = $request->query('group');

        if ($group) {
            $settings = $this->settings->getByGroup($group, $deviceId);
        } else {
            $settings = $this->settings->getAllGrouped($deviceId);
        }

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function show(string $key, Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Device-Id');
        $value = $this->settings->get($key, null, $deviceId);

        if ($value === null) {
            return response()->json([
                'success' => false,
                'message' => "Setting '{$key}' not found",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'key' => $key,
                'value' => $value,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'required',
            'group' => 'sometimes|string|max:100',
            'type' => ['sometimes', 'string', Rule::in(['string', 'boolean', 'integer', 'float', 'json'])],
            'device_id' => 'sometimes|nullable|string|max:255',
        ]);

        $type = $validated['type'] ?? $this->inferType($validated['value']);
        $deviceId = $validated['device_id'] ?? $request->header('X-Device-Id');

        $setting = $this->settings->set(
            $validated['key'],
            $validated['value'],
            $validated['group'] ?? 'general',
            $deviceId,
            $type
        );

        return response()->json([
            'success' => true,
            'message' => 'Setting created successfully',
            'data' => [
                'key' => $setting->key,
                'value' => $setting->getCastedValue(),
                'group' => $setting->group,
                'type' => $setting->type,
            ],
        ], 201);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required',
            'type' => ['sometimes', 'string', Rule::in(['string', 'boolean', 'integer', 'float', 'json'])],
        ]);

        $deviceId = $request->header('X-Device-Id');
        $type = $validated['type'] ?? $this->inferType($validated['value']);

        $setting = $this->settings->set(
            $key,
            $validated['value'],
            $request->input('group', 'general'),
            $deviceId,
            $type
        );

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully',
            'data' => [
                'key' => $setting->key,
                'value' => $setting->getCastedValue(),
                'group' => $setting->group,
                'type' => $setting->type,
            ],
        ]);
    }

    public function destroy(string $key, Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Device-Id');
        $deleted = $this->settings->delete($key, $deviceId);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => "Setting '{$key}' not found",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting deleted successfully',
        ]);
    }

    public function robot(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Device-Id');
        $settings = $this->settings->getByGroup('robot', $deviceId);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function updateRobot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'robot_name' => 'sometimes|string|max:100',
            'robot_personality' => 'sometimes|string|max:100',
            'robot_avatar' => 'sometimes|nullable|string|max:255',
        ]);

        $deviceId = $request->header('X-Device-Id');

        foreach ($validated as $key => $value) {
            $this->settings->set($key, $value, 'robot', $deviceId, 'string');
        }

        return response()->json([
            'success' => true,
            'message' => 'Robot settings updated',
            'data' => $this->settings->getByGroup('robot', $deviceId),
        ]);
    }

    public function voice(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Device-Id');
        $settings = $this->settings->getByGroup('voice', $deviceId);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function updateVoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wake_word' => 'sometimes|string|max:100',
            'voice_language' => 'sometimes|string|max:20',
            'voice_speed' => 'sometimes|numeric|min:0.5|max:2.0',
            'voice_pitch' => 'sometimes|numeric|min:0.5|max:2.0',
            'voice_provider' => 'sometimes|string|max:50',
            'transcription_model' => 'sometimes|string|max:100',
            'speech_model' => 'sometimes|string|max:100',
            'voice_gender' => 'sometimes|string|in:male,female,neutral',
        ]);

        $deviceId = $request->header('X-Device-Id');

        $typeMap = [
            'voice_speed' => 'float',
            'voice_pitch' => 'float',
        ];

        foreach ($validated as $key => $value) {
            $type = $typeMap[$key] ?? 'string';
            $this->settings->set($key, $value, 'voice', $deviceId, $type);
        }

        return response()->json([
            'success' => true,
            'message' => 'Voice settings updated',
            'data' => $this->settings->getByGroup('voice', $deviceId),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Device-Id');
        $settings = $this->settings->getByGroup('notifications', $deviceId);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notifications_enabled' => 'sometimes|boolean',
            'notification_sound' => 'sometimes|string|max:100',
            'notification_vibration' => 'sometimes|boolean',
            'do_not_disturb' => 'sometimes|boolean',
            'dnd_start_time' => 'sometimes|string|max:10',
            'dnd_end_time' => 'sometimes|string|max:10',
        ]);

        $deviceId = $request->header('X-Device-Id');

        $typeMap = [
            'notifications_enabled' => 'boolean',
            'notification_vibration' => 'boolean',
            'do_not_disturb' => 'boolean',
        ];

        foreach ($validated as $key => $value) {
            $type = $typeMap[$key] ?? 'string';
            $this->settings->set($key, $value, 'notifications', $deviceId, $type);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated',
            'data' => $this->settings->getByGroup('notifications', $deviceId),
        ]);
    }

    public function overlay(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Device-Id');
        $settings = $this->settings->getByGroup('overlay', $deviceId);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function updateOverlay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'overlay_enabled' => 'sometimes|boolean',
            'overlay_position' => 'sometimes|string|in:top,bottom,left,right',
            'overlay_theme' => 'sometimes|string|in:light,dark,auto',
            'overlay_opacity' => 'sometimes|numeric|min:0.1|max:1.0',
            'overlay_animation' => 'sometimes|string|in:slide,fade,none',
        ]);

        $deviceId = $request->header('X-Device-Id');

        $typeMap = [
            'overlay_enabled' => 'boolean',
            'overlay_opacity' => 'float',
        ];

        foreach ($validated as $key => $value) {
            $type = $typeMap[$key] ?? 'string';
            $this->settings->set($key, $value, 'overlay', $deviceId, $type);
        }

        return response()->json([
            'success' => true,
            'message' => 'Overlay settings updated',
            'data' => $this->settings->getByGroup('overlay', $deviceId),
        ]);
    }

    public function ai(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Device-Id');
        $settings = $this->settings->getByGroup('ai', $deviceId);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function updateAi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_model' => 'sometimes|string|max:100',
            'ai_temperature' => 'sometimes|numeric|min:0|max:2',
            'ai_max_tokens' => 'sometimes|integer|min:1|max:100000',
            'ai_system_prompt' => 'sometimes|string|max:5000',
            'ai_response_style' => 'sometimes|string|in:conversational,formal,concise,creative',
            'ai_language' => 'sometimes|string|max:20',
            'ai_memory_enabled' => 'sometimes|boolean',
            'ai_tools_enabled' => 'sometimes|boolean',
        ]);

        $deviceId = $request->header('X-Device-Id');

        $typeMap = [
            'ai_temperature' => 'float',
            'ai_max_tokens' => 'integer',
            'ai_memory_enabled' => 'boolean',
            'ai_tools_enabled' => 'boolean',
        ];

        foreach ($validated as $key => $value) {
            $type = $typeMap[$key] ?? 'string';
            $this->settings->set($key, $value, 'ai', $deviceId, $type);
        }

        return response()->json([
            'success' => true,
            'message' => 'AI settings updated',
            'data' => $this->settings->getByGroup('ai', $deviceId),
        ]);
    }

    public function seed(): JsonResponse
    {
        $this->settings->seedDefaults();

        return response()->json([
            'success' => true,
            'message' => 'Default settings seeded successfully',
        ]);
    }

    private function inferType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_array($value) || is_object($value)) {
            return 'json';
        }
        return 'string';
    }
}
