<?php

namespace App\Http\Controllers\Api\AiCore;

use App\Http\Controllers\Controller;
use App\Services\AiCore\CommandProcessor;
use Illuminate\Http\Request;

class CommandController extends Controller
{
    protected $commandProcessor;

    public function __construct(CommandProcessor $commandProcessor)
    {
        $this->commandProcessor = $commandProcessor;
    }

    public function parse(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'device_id' => ['required', 'string', 'max:191'],
            'conversation_id' => ['nullable', 'string', 'max:191'],
        ]);

        $result = $this->commandProcessor->parse(
            $data['message'],
            $data['device_id'],
            $data['conversation_id'] ?? null
        );

        return response()->json($result);
    }

    public function execute(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'device_id' => ['required', 'string', 'max:191'],
            'conversation_id' => ['nullable', 'string', 'max:191'],
        ]);

        $result = $this->commandProcessor->execute(
            $data['message'],
            $data['device_id'],
            $data['conversation_id'] ?? null
        );

        return response()->json($result);
    }
}
