<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Memory extends Model
{
    protected $fillable = [
        'uuid',
        'device_id',
        'key',
        'value',
        'type',
        'tags',
        'is_recallable',
        'metadata',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_recallable' => 'boolean',
        'metadata' => 'array',
    ];

    public static function booted()
    {
        static::creating(function (self $memory) {
            $memory->uuid = $memory->uuid ?? (string) Str::uuid();
        });
    }

    public function scopeByDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecallable($query)
    {
        return $query->where('is_recallable', true);
    }

    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }
}
