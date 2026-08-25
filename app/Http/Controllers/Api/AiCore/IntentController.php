<?php

namespace App\Http\Controllers\Api\AiCore;

use App\Http\Controllers\Controller;
use App\Services\AiCore\IntentDetector;
use Illuminate\Http\Request;

class IntentController extends Controller
{
    protected $intentDetector;

    public function __construct(IntentDetector $intentDetector)
    {
        $this->intentDetector = $intentDetector;
    }

    public function detect(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $result = $this->intentDetector->detect($data['message']);

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'I1',
            'data' => $result,
        ]);
    }

    public function index()
    {
        $intents = $this->intentDetector->getAllIntents();

        return response()->json([
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'I2',
            'data' => [
                'intents' => $intents,
                'total' => count($intents),
            ],
        ]);
    }
}
