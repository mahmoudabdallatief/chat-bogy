<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'title',
        'scheduled_at',
        'timezone',
        'status',
        'payload',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'payload' => 'array',
    ];
}
