<?php

namespace App\Services\AiCore;

use App\Models\Memory;
use Illuminate\Support\Collection;

class MemoryManager
{
    public function store(string $deviceId, string $key, string $value, ?string $type = null, array $tags = [], bool $recallable = true, array $metadata = []): Memory
    {
        return Memory::updateOrCreate(
            ['device_id' => $deviceId, 'key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'tags' => $tags,
                'is_recallable' => $recallable,
                'metadata' => $metadata,
            ]
        );
    }

    public function get(string $deviceId, string $key): ?Memory
    {
        return Memory::byDevice($deviceId)->where('key', $key)->first();
    }

    public function retrieve(string $deviceId, ?string $intent = null, array $entities = []): Collection
    {
        $memories = Memory::byDevice($deviceId)->recallable()->orderBy('updated_at', 'desc')->get();

        if ($intent === null && empty($entities)) {
            return $memories;
        }

        return $memories->filter(function ($memory) use ($intent, $entities) {
            $tags = $memory->tags ?? [];
            if ($intent !== null && !in_array($intent, $tags)) {
                return false;
            }
            foreach (array_keys($entities) as $entityName) {
                if (!in_array($entityName, $tags)) {
                    return false;
                }
            }
            return true;
        })->values();
    }

    public function search(string $deviceId, string $query, ?string $type = null, int $limit = 20): Collection
    {
        $builder = Memory::byDevice($deviceId)
            ->where(function ($q) use ($query) {
                $q->where('key', 'like', '%' . $query . '%')
                    ->orWhere('value', 'like', '%' . $query . '%');
            });

        if ($type !== null) {
            $builder->ofType($type);
        }

        return $builder->orderBy('updated_at', 'desc')->limit($limit)->get();
    }

    public function delete(string $deviceId, string $key): int
    {
        return Memory::byDevice($deviceId)->where('key', $key)->delete();
    }

    public function deleteById(string $deviceId, int $id): int
    {
        return Memory::byDevice($deviceId)->where('id', $id)->delete();
    }

    public function list(string $deviceId, ?string $type = null, int $limit = 50): Collection
    {
        $query = Memory::byDevice($deviceId)->recallable();

        if ($type !== null) {
            $query->ofType($type);
        }

        return $query->orderBy('updated_at', 'desc')->limit($limit)->get();
    }

    public function toContext(Collection $memories, int $max = 5): string
    {
        $items = $memories->take($max)->map(function (Memory $memory) {
            return "- {$memory->key}: {$memory->value}";
        })->implode("\n");

        return $items;
    }
}
