<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Setting extends Model
{
    protected $fillable = [
        'uuid',
        'group',
        'key',
        'value',
        'type',
        'is_device_scoped',
        'device_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_device_scoped' => 'boolean',
    ];

    public static function booted()
    {
        static::creating(function (self $setting) {
            $setting->uuid = $setting->uuid ?? (string) Str::uuid();
        });
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    public function scopeByDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeGlobal($query)
    {
        return $query->where('is_device_scoped', false)->whereNull('device_id');
    }

    public function getCastedValue()
    {
        switch ($this->type) {
            case 'boolean':
                return (bool) $this->value;
            case 'integer':
                return (int) $this->value;
            case 'float':
                return (float) $this->value;
            case 'json':
                return json_decode($this->value, true);
            default:
                return $this->value;
        }
    }
}
