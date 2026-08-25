<?php

namespace App\Http\Controllers\Api\AiCore;

use App\Http\Controllers\Controller;
use App\Services\AiCore\ToolFlowchart;
use Illuminate\Http\Request;

class ToolFlowchartController extends Controller
{
    protected $flowchart;

    public function __construct(ToolFlowchart $flowchart)
    {
        $this->flowchart = $flowchart;
    }

    public function execute(Request $request)
    {
        $data = $request->validate([
            'intent' => ['required', 'string', 'max:100'],
            'entities' => ['nullable', 'array'],
            'device_id' => ['required', 'string', 'max:191'],
        ]);

        $command = $this->flowchart->resolve(
            $data['intent'],
            $data['entities'] ?? [],
            $data['device_id']
        );

        if ($command === null) {
            $mapping = $this->flowchart->getMapping($data['intent']);
            if ($mapping === null) {
                return response()->json([
                    'success' => false,
                    'tool' => 'ai-core',
                    'group' => 'F1',
                    'message' => "Intent '{$data['intent']}' is not registered in the flowchart.",
                ], 422);
            }

            return response()->json([
                'success' => false,
                'tool' => 'ai-core',
                'group' => 'F1',
                'message' => "Could not resolve intent '{$data['intent']}' to a command. Required entity parameters are missing.",
                'required_entities' => $mapping['entities'] ?? [],
                'provided_entities' => array_keys($data['entities'] ?? []),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'F1',
            'data' => $command,
        ]);
    }

    public function flowchart()
    {
        $flowchart = $this->flowchart->getFlowchart();

        $mappings = [];
        foreach ($flowchart as $intentName => $mapping) {
            $mappings[$intentName] = [
                'tool' => $mapping['tool'],
                'group' => $mapping['group'],
                'action' => $mapping['action'],
                'endpoint' => $mapping['endpoint'],
                'entities' => $mapping['entities'] ?? [],
                'requires_device_permission' => $mapping['requires_device_permission'] ?? false,
                'description' => $mapping['description'] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'F2',
            'data' => [
                'flowchart' => $mappings,
                'total_intents' => count($mappings),
            ],
        ]);
    }

    public function resolve(Request $request)
    {
        $data = $request->validate([
            'intents' => ['required', 'array', 'min:1'],
            'intents.*' => ['string', 'max:100'],
            'entities' => ['nullable', 'array'],
            'device_id' => ['required', 'string', 'max:191'],
        ]);

        $intentResults = [];
        foreach ($data['intents'] as $intentName) {
            $intentResults[] = [
                'intent' => $intentName,
                'entities' => $data['entities'] ?? [],
            ];
        }

        $commands = $this->flowchart->resolveAll($intentResults, $data['device_id']);

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'F3',
            'data' => [
                'commands' => $commands,
                'resolved_count' => count($commands),
                'total_intents' => count($data['intents']),
            ],
        ]);
    }
}
