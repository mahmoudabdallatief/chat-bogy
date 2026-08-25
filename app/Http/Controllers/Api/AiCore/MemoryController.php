<?php

namespace App\Http\Controllers\Api\AiCore;

use App\Http\Controllers\Controller;
use App\Services\AiCore\MemoryManager;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    protected $memoryManager;

    public function __construct(MemoryManager $memoryManager)
    {
        $this->memoryManager = $memoryManager;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'key' => ['required', 'string', 'max:191'],
            'value' => ['required', 'string', 'max:10000'],
            'type' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'is_recallable' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $memory = $this->memoryManager->store(
            $data['device_id'],
            $data['key'],
            $data['value'],
            $data['type'] ?? null,
            $data['tags'] ?? [],
            $data['is_recallable'] ?? true,
            $data['metadata'] ?? []
        );

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'M1',
            'data' => [
                'id' => $memory->uuid,
                'device_id' => $memory->device_id,
                'key' => $memory->key,
                'value' => $memory->value,
                'type' => $memory->type,
                'tags' => $memory->tags,
                'is_recallable' => $memory->is_recallable,
                'metadata' => $memory->metadata,
                'created_at' => $memory->created_at ? $memory->created_at->toIso8601String() : null,
                'updated_at' => $memory->updated_at ? $memory->updated_at->toIso8601String() : null,
            ],
        ], 201);
    }

    public function retrieve(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'intent' => ['nullable', 'string', 'max:100'],
            'entities' => ['nullable', 'array'],
        ]);

        $memories = $this->memoryManager->retrieve(
            $data['device_id'],
            $data['intent'] ?? null,
            $data['entities'] ?? []
        );

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'M2',
            'data' => $memories->map(function ($memory) {
                return [
                    'id' => $memory->uuid,
                    'key' => $memory->key,
                    'value' => $memory->value,
                    'type' => $memory->type,
                    'tags' => $memory->tags,
                ];
            })->values(),
        ]);
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'query' => ['required', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $memories = $this->memoryManager->search(
            $data['device_id'],
            $data['query'],
            $data['type'] ?? null,
            $data['limit'] ?? 20
        );

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'M3',
            'data' => $memories->map(function ($memory) {
                return [
                    'id' => $memory->uuid,
                    'key' => $memory->key,
                    'value' => $memory->value,
                    'type' => $memory->type,
                    'tags' => $memory->tags,
                ];
            })->values(),
        ]);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'type' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $memories = $this->memoryManager->list(
            $data['device_id'],
            $data['type'] ?? null,
            $data['limit'] ?? 50
        );

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'M4',
            'data' => $memories->map(function ($memory) {
                return [
                    'id' => $memory->uuid,
                    'key' => $memory->key,
                    'value' => $memory->value,
                    'type' => $memory->type,
                    'tags' => $memory->tags,
                    'is_recallable' => $memory->is_recallable,
                    'created_at' => $memory->created_at ? $memory->created_at->toIso8601String() : null,
                    'updated_at' => $memory->updated_at ? $memory->updated_at->toIso8601String() : null,
                ];
            })->values(),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
        ]);

        $memory = \App\Models\Memory::where('uuid', $id)
            ->where('device_id', $data['device_id'])
            ->first();

        if ($memory === null) {
            return response()->json([
                'success' => false,
                'message' => 'Memory not found.',
            ], 404);
        }

        $memory->delete();

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'M5',
            'message' => 'Memory deleted.',
        ]);
    }
}
