<?php

namespace App\Http\Controllers\Api\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoiceToolController extends Controller
{
    public function process(Request $request)
    {
        $data = $request->validate([
            'transcript' => ['required', 'string', 'max:5000'],
            'language' => ['nullable', 'string', 'max:10'],
            'device_id' => ['nullable', 'string', 'max:191'],
        ]);

        return response()->json([
            'success' => true,
            'tool' => 'voice',
            'data' => [
                'transcript' => trim($data['transcript']),
                'language' => $data['language'] ?? null,
                'request_id' => (string) Str::uuid(),
            ],
        ]);
    }
}
