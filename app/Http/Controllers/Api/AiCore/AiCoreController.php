<?php

namespace App\Http\Controllers\Api\AiCore;

use App\Http\Controllers\Controller;
use App\Services\AiCore\AiCoreService;
use Illuminate\Http\Request;

class AiCoreController extends Controller
{
    protected $aiCoreService;

    public function __construct(AiCoreService $aiCoreService)
    {
        $this->aiCoreService = $aiCoreService;
    }

    public function chat(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'device_id' => ['required', 'string', 'max:191'],
            'conversation_id' => ['nullable', 'string', 'max:191'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->aiCoreService->process(
            $data['message'],
            $data['device_id'],
            $data['conversation_id'] ?? null,
            $data['title'] ?? null
        );

        return response()->json($result);
    }
}
