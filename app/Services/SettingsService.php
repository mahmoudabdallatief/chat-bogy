<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_PREFIX = 'setting:';
    private const CACHE_TTL = 3600;

    public function get(string $key, $default = null, ?string $deviceId = null)
    {
        $cacheKey = $this->cacheKey($key, $deviceId);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default, $deviceId) {
            $query = Setting::byKey($key);

            if ($deviceId) {
                $query->where(function ($q) use ($deviceId) {
                    $q->where('device_id', $deviceId)->orWhere('is_device_scoped', false)->whereNull('device_id');
                });
                $setting = $query->orderByDesc('device_id')->first();
            } else {
                $setting = $query->global()->first();
            }

            if (!$setting) {
                return $default;
            }

            return $setting->getCastedValue();
        });
    }

    public function set(string $key, $value, string $group = 'general', ?string $deviceId = null, string $type = 'string'): Setting
    {
        $castedValue = $this->castValue($value, $type);

        $setting = Setting::updateOrCreate(
            [
                'key' => $key,
                'device_id' => $deviceId,
            ],
            [
                'group' => $group,
                'value' => $castedValue,
                'type' => $type,
                'is_device_scoped' => $deviceId !== null,
            ]
        );

        $this->clearCache($key, $deviceId, $group);

        return $setting;
    }

    public function getByGroup(string $group, ?string $deviceId = null): array
    {
        $cacheKey = self::CACHE_PREFIX . "group:{$group}:" . ($deviceId ?? 'global');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $deviceId) {
            $query = Setting::byGroup($group);

            if ($deviceId) {
                $query->where(function ($q) use ($deviceId) {
                    $q->where('device_id', $deviceId)->orWhere('is_device_scoped', false)->whereNull('device_id');
                });
            } else {
                $query->global();
            }

            $settings = $query->get();
            $result = [];

            foreach ($settings as $setting) {
                $result[$setting->key] = $setting->getCastedValue();
            }

            return $result;
        });
    }

    public function getAllGrouped(?string $deviceId = null): array
    {
        $cacheKey = self::CACHE_PREFIX . "all:" . ($deviceId ?? 'global');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($deviceId) {
            $query = Setting::query();

            if ($deviceId) {
                $query->where(function ($q) use ($deviceId) {
                    $q->where('device_id', $deviceId)->orWhere('is_device_scoped', false)->whereNull('deviceId');
                });
            } else {
                $query->global();
            }

            $settings = $query->get();
            $result = [];

            foreach ($settings as $setting) {
                $result[$setting->group][$setting->key] = [
                    'value' => $setting->getCastedValue(),
                    'type' => $setting->type,
                    'is_device_scoped' => $setting->is_device_scoped,
                ];
            }

            return $result;
        });
    }

    public function delete(string $key, ?string $deviceId = null): bool
    {
        $query = Setting::byKey($key);

        if ($deviceId) {
            $query->byDevice($deviceId);
        } else {
            $query->global();
        }

        $setting = $query->first();
        $group = $setting ? $setting->group : null;

        $deleted = $query->delete();

        $this->clearCache($key, $deviceId, $group);

        return (bool) $deleted;
    }

    public function reset(string $key, ?string $deviceId = null): bool
    {
        return $this->delete($key, $deviceId);
    }

    public function seedDefaults(): void
    {
        $defaults = [
            'robot' => [
                'robot_name' => ['value' => 'Boogy', 'type' => 'string'],
                'robot_personality' => ['value' => 'friendly', 'type' => 'string'],
                'robot_avatar' => ['value' => null, 'type' => 'string'],
            ],
            'voice' => [
                'wake_word' => ['value' => 'hey boogy', 'type' => 'string'],
                'voice_language' => ['value' => 'en-US', 'type' => 'string'],
                'voice_speed' => ['value' => 1.0, 'type' => 'float'],
                'voice_pitch' => ['value' => 1.0, 'type' => 'float'],
                'voice_provider' => ['value' => 'openai', 'type' => 'string'],
                'transcription_model' => ['value' => 'gpt-4o-mini-transcribe', 'type' => 'string'],
                'speech_model' => ['value' => 'gpt-4o-mini-tts', 'type' => 'string'],
                'voice_gender' => ['value' => 'female', 'type' => 'string'],
            ],
            'notifications' => [
                'notifications_enabled' => ['value' => true, 'type' => 'boolean'],
                'notification_sound' => ['value' => 'default', 'type' => 'string'],
                'notification_vibration' => ['value' => true, 'type' => 'boolean'],
                'do_not_disturb' => ['value' => false, 'type' => 'boolean'],
                'dnd_start_time' => ['value' => '22:00', 'type' => 'string'],
                'dnd_end_time' => ['value' => '07:00', 'type' => 'string'],
            ],
            'overlay' => [
                'overlay_enabled' => ['value' => true, 'type' => 'boolean'],
                'overlay_position' => ['value' => 'bottom', 'type' => 'string'],
                'overlay_theme' => ['value' => 'dark', 'type' => 'string'],
                'overlay_opacity' => ['value' => 0.9, 'type' => 'float'],
                'overlay_animation' => ['value' => 'slide', 'type' => 'string'],
            ],
            'ai' => [
                'ai_model' => ['value' => 'google/flan-t5-small', 'type' => 'string'],
                'ai_temperature' => ['value' => 0.7, 'type' => 'float'],
                'ai_max_tokens' => ['value' => 1000, 'type' => 'integer'],
                'ai_system_prompt' => ['value' => 'You are a helpful robot assistant named Boogy.', 'type' => 'string'],
                'ai_response_style' => ['value' => 'conversational', 'type' => 'string'],
                'ai_language' => ['value' => 'auto', 'type' => 'string'],
                'ai_memory_enabled' => ['value' => true, 'type' => 'boolean'],
                'ai_tools_enabled' => ['value' => true, 'type' => 'boolean'],
            ],
        ];

        foreach ($defaults as $group => $settings) {
            foreach ($settings as $key => $config) {
                Setting::firstOrCreate(
                    ['key' => $key, 'device_id' => null],
                    [
                        'group' => $group,
                        'value' => $config['value'],
                        'type' => $config['type'],
                        'is_device_scoped' => false,
                    ]
                );
            }
        }
    }

    private function castValue($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'json':
                return json_encode($value);
            default:
                return (string) $value;
        }
    }

    private function cacheKey(string $key, ?string $deviceId): string
    {
        return self::CACHE_PREFIX . $key . ':' . ($deviceId ?? 'global');
    }

    private function clearCache(string $key, ?string $deviceId, ?string $group = null): void
    {
        Cache::forget($this->cacheKey($key, $deviceId));

        if ($group !== null) {
            Cache::forget(self::CACHE_PREFIX . "group:{$group}:" . ($deviceId ?? 'global'));
        }

        Cache::forget(self::CACHE_PREFIX . "all:" . ($deviceId ?? 'global'));
    }
}
