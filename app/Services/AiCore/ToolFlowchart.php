<?php

namespace App\Services\AiCore;

use Illuminate\Support\Str;

class ToolFlowchart
{
    protected array $flowchart;

    public function __construct()
    {
        $this->flowchart = config('ai-core.flowchart', []);
    }

    public function getFlowchart(): array
    {
        return $this->flowchart;
    }

    public function getMapping(string $intentName): ?array
    {
        return $this->flowchart[$intentName] ?? null;
    }

    public function getIntentNames(): array
    {
        return array_keys($this->flowchart);
    }

    public function isResolvable(string $intentName): bool
    {
        return isset($this->flowchart[$intentName]);
    }

    public function resolve(string $intentName, array $entities, string $deviceId): ?array
    {
        $mapping = $this->flowchart[$intentName] ?? null;

        if ($mapping === null) {
            return null;
        }

        $parameters = $this->buildParameters($mapping, $entities, $deviceId);

        if (!$this->hasRequiredParameters($mapping, $parameters)) {
            return null;
        }

        return [
            'id' => (string) Str::uuid(),
            'tool' => $mapping['tool'],
            'group' => $mapping['group'],
            'action' => $mapping['action'],
            'endpoint' => $mapping['endpoint'],
            'parameters' => $parameters,
            'requires_device_permission' => $mapping['requires_device_permission'] ?? false,
            'description' => $mapping['description'] ?? null,
        ];
    }

    public function resolveAll(array $intentResults, string $deviceId): array
    {
        $commands = [];

        foreach ($intentResults as $result) {
            $command = $this->resolve($result['intent'], $result['entities'] ?? [], $deviceId);
            if ($command !== null) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    public function generateReply(string $intentName, array $command, ?array $entityResult = null): string
    {
        $entities = $entityResult['entities'] ?? [];

        switch ($intentName) {
            case 'reminder.create': return $this->replyForReminder($entities);
            case 'alarm.create': return $this->replyForAlarm($entities);
            case 'datetime.read': return 'Here is the current date and time.';
            case 'call.make': return $this->replyForCall($entities);
            case 'call.end': return 'Ending the call.';
            case 'contact.search': return 'Searching your contacts for: ' . ($entities['query'] ?? 'the contact');
            case 'contact.list': return 'Listing your contacts.';
            case 'device.volume': return $this->replyForVolume($entities);
            case 'device.flashlight': return $this->replyForFlashlight($entities);
            case 'device.wifi': return 'Turning Wi-Fi ' . ($entities['enabled'] ? 'on' : 'off') . '.';
            case 'device.bluetooth': return 'Turning Bluetooth ' . ($entities['enabled'] ? 'on' : 'off') . '.';
            case 'device.brightness': return $this->replyForBrightness($entities);
            case 'device.lock': return 'Locking your device.';
            case 'notification.list': return 'Fetching your notifications.';
            case 'app.open': return 'Opening ' . ($entities['app'] ?? 'the app') . ' on your device.';
            case 'robot.idle': return 'Setting the robot to idle mode.';
            case 'robot.talking': return 'Robot is now talking.';
            case 'robot.wake': return 'Waking the robot up.';
            case 'battery.status': return $this->replyForBattery($entities);
            case 'memory.store': return 'I\'ll remember that: ' . ($entities['fact'] ?? '');
            case 'memory.retrieve': return 'Let me recall what I remember for you.';
            case 'conversation.clear': return 'Starting a fresh conversation.';
            default: return 'Executing: ' . ($command['action'] ?? '');
        }
    }

    protected function buildParameters(array $mapping, array $entities, string $deviceId): array
    {
        $entityParamMap = [
            'title' => 'title',
            'scheduled_at' => 'scheduled_at',
            'time' => 'time',
            'phone_number' => 'phone_number',
            'contact' => 'contact',
            'app' => 'app',
            'level' => 'level',
            'enabled' => 'enabled',
            'while_speaking' => 'while_speaking',
            'source' => 'source',
            'fact' => 'fact',
            'query' => 'query',
            'call_id' => 'call_id',
            'limit' => 'limit',
        ];

        $parameters = ['device_id' => $deviceId];

        if (isset($entities['scheduled_at'])) {
            $parameters['scheduled_at'] = $entities['scheduled_at'];
        } elseif (isset($entities['time']) && ($mapping['action'] ?? '') === 'set_reminder') {
            $parameters['scheduled_at'] = $entities['time'];
        }

        foreach ($mapping['entities'] ?? [] as $entityName) {
            if ($entityName === 'device_id') {
                continue;
            }
            $paramKey = $entityParamMap[$entityName] ?? $entityName;
            if (array_key_exists($entityName, $entities) && $entities[$entityName] !== null) {
                $parameters[$paramKey] = $entities[$entityName];
            }
        }

        $this->applyDefaults($mapping, $parameters, $entities);

        return array_filter($parameters, function ($value) {
            return $value !== null;
        });
    }

    protected function applyDefaults(array $mapping, array &$parameters, array $entities): void
    {
        $action = $mapping['action'] ?? '';

        if ($action === 'make_call' && !isset($parameters['phone_number'])) {
            if (isset($entities['contact'])) {
                $parameters['phone_number'] = $entities['contact'];
            }
        }

        if (in_array($action, ['set_volume', 'set_brightness'], true) && !isset($parameters['level'])) {
            $parameters['level'] = 50;
        }

        if (in_array($action, ['contacts_list', 'notifications_list'], true) && !isset($parameters['limit'])) {
            $parameters['limit'] = 100;
        }
    }

    protected function hasRequiredParameters(array $mapping, array $parameters): bool
    {
        if (!isset($parameters['device_id'])) {
            return false;
        }

        $action = $mapping['action'] ?? '';

        $actionRequiredParamKeys = [
            'set_reminder' => ['title', 'scheduled_at'],
            'set_alarm' => ['time'],
            'make_call' => ['phone_number'],
            'open_app' => ['app'],
        ];

        foreach ($actionRequiredParamKeys[$action] ?? [] as $paramKey) {
            if ($action === 'make_call' && $paramKey === 'phone_number' && isset($parameters['contact'])) {
                continue;
            }
            if (!isset($parameters[$paramKey])) {
                return false;
            }
        }

        return true;
    }

    protected function replyForReminder(array $entities): string
    {
        $title = $entities['title'] ?? 'something';
        $time = $entities['scheduled_at'] ?? $entities['time'] ?? null;
        $base = "I'll remind you to {$title}.";
        if ($time !== null) {
            $base .= " at {$time}.";
        }
        return $base;
    }

    protected function replyForAlarm(array $entities): string
    {
        $time = $entities['time'] ?? 'now';
        return "Alarm set for {$time}.";
    }

    protected function replyForCall(array $entities): string
    {
        $number = $entities['phone_number'] ?? $entities['contact'] ?? 'the number';
        return "Calling {$number}.";
    }

    protected function replyForVolume(array $entities): string
    {
        if (isset($entities['level'])) {
            return "Setting volume to {$entities['level']}%.";
        }
        return 'Adjusting the device volume.';
    }

    protected function replyForFlashlight(array $entities): string
    {
        $enabled = $entities['enabled'] ?? true;
        $state = $enabled ? 'on' : 'off';
        return "Turning flashlight {$state}.";
    }

    protected function replyForBrightness(array $entities): string
    {
        if (isset($entities['level'])) {
            return "Setting brightness to {$entities['level']}%.";
        }
        return 'Adjusting screen brightness.';
    }

    protected function replyForBattery(array $entities): string
    {
        if (isset($entities['level'])) {
            return "Battery is at {$entities['level']}%.";
        }
        return 'Checking battery level.';
    }
}
