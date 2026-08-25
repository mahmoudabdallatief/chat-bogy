<?php

namespace App\Services\AiCore;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConversationManager
{
    public function start(string $deviceId, ?string $title = null, array $metadata = []): Conversation
    {
        $active = Conversation::byDevice($deviceId)->active()->latest()->first();
        if ($active !== null) {
            return $active;
        }

        return Conversation::create([
            'device_id' => $deviceId,
            'title' => $title ?? 'New Conversation',
            'metadata' => $metadata,
            'is_active' => true,
        ]);
    }

    public function get(string $deviceId, string $id): ?Conversation
    {
        return Conversation::where('uuid', $id)
            ->where('device_id', $deviceId)
            ->with('messages')
            ->first();
    }

    public function getOrCreate(string $deviceId, ?string $conversationId = null, ?string $title = null): Conversation
    {
        if ($conversationId !== null) {
            $existing = $this->get($deviceId, $conversationId);
            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->start($deviceId, $title);
    }

    public function addMessage(Conversation $conversation, string $role, string $content, array $metadata = []): ConversationMessage
    {
        return $conversation->messages()->create([
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    public function getMessages(Conversation $conversation, ?int $limit = null): Collection
    {
        $query = $conversation->messages()->orderBy('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getLastMessages(Conversation $conversation, int $limit = 10): Collection
    {
        return $conversation->messages()->orderBy('created_at', 'desc')->limit($limit)->get()->reverse();
    }

    public function archive(Conversation $conversation): void
    {
        $conversation->update(['is_active' => false]);
    }

    public function destroy(Conversation $conversation): void
    {
        $conversation->messages()->delete();
        $conversation->delete();
    }

    public function clear(Conversation $conversation): void
    {
        $conversation->messages()->delete();
    }

    public function toArray(Conversation $conversation): array
    {
        return [
            'id' => $conversation->uuid,
            'device_id' => $conversation->device_id,
            'title' => $conversation->title,
            'is_active' => $conversation->is_active,
            'metadata' => $conversation->metadata,
            'created_at' => $conversation->created_at ? $conversation->created_at->toIso8601String() : null,
            'updated_at' => $conversation->updated_at ? $conversation->updated_at->toIso8601String() : null,
            'message_count' => $conversation->messages->count(),
        ];
    }
}
