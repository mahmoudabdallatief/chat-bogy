<?php

namespace App\Http\Controllers\Api\AiCore;

use App\Http\Controllers\Controller;
use App\Services\AiCore\ConversationManager;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    protected $conversationManager;

    public function __construct(ConversationManager $conversationManager)
    {
        $this->conversationManager = $conversationManager;
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'title' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $conversation = $this->conversationManager->start(
            $data['device_id'],
            $data['title'] ?? null,
            $data['metadata'] ?? []
        );

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'C1',
            'data' => $this->conversationManager->toArray($conversation),
        ], 201);
    }

    public function show(Request $request, string $id)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
        ]);

        $conversation = $this->conversationManager->get($data['device_id'], $id);

        if ($conversation === null) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'C2',
            'data' => $this->conversationManager->toArray($conversation),
            'messages' => $conversation->messages->map(function ($message) {
                return [
                    'id' => $message->uuid,
                    'role' => $message->role,
                    'content' => $message->content,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at ? $message->created_at->toIso8601String() : null,
                ];
            })->values(),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
        ]);

        $conversation = $this->conversationManager->get($data['device_id'], $id);

        if ($conversation === null) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);
        }

        $this->conversationManager->destroy($conversation);

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'C3',
            'message' => 'Conversation deleted.',
        ]);
    }

    public function list(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'is_active' => ['sometimes', 'boolean'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = \App\Models\Conversation::byDevice($data['device_id'])
            ->orderBy('updated_at', 'desc');

        if (isset($data['is_active'])) {
            $query->where('is_active', $data['is_active']);
        }

        $conversations = $query->limit($data['limit'] ?? 50)->get();

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'C4',
            'data' => $conversations->map(fn ($c) => $this->conversationManager->toArray($c))->values(),
        ]);
    }
}
