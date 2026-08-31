<?php

namespace App\Services\AiCore;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiChatService
{
    protected string $apiKey;
    protected string $model;
    protected array $fallbackModels;
    protected int $timeout;
    protected string $systemPrompt;
    protected int $maxContextMessages;

    public function __construct()
    {
        $this->apiKey = (string) env('GEMINI_API_KEY', '');
        $this->model = (string) env('GEMINI_MODEL', 'gemini-3.6-flash');
        $this->fallbackModels = array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_FALLBACK_MODELS', '')))));
        $this->timeout = (int) env('GEMINI_TIMEOUT', 60);
        $this->systemPrompt = (string) env('GEMINI_SYSTEM_PROMPT', 'You are a helpful AI assistant named Boogy. Be concise, friendly, and helpful.');
        $this->maxContextMessages = (int) env('GEMINI_MAX_CONTEXT_MESSAGES', 10);
    }

    public function chat(array $messages): ?string
    {
        if ($this->apiKey === '' || $this->model === '') {
            return null;
        }

        $contents = [];
        $systemPrompt = '';

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt = $msg['content'];
            } else {
                $contents[] = [
                    'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [
                        ['text' => $msg['content']],
                    ],
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
            ],
        ];

        if ($systemPrompt !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ];
        }

        $modelsToTry = array_merge([$this->model], $this->fallbackModels);

        foreach ($modelsToTry as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

            try {
                $response = Http::timeout($this->timeout)
                    ->acceptJson()
                    ->post($url, $payload);

                if ($response->successful()) {
                    $data = $response->json();

                    return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                }

                if (!in_array($response->status(), [404, 429, 503])) {
                    Log::error('Gemini chat request failed', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                Log::warning('Gemini chat request failed, trying fallback', [
                    'model' => $model,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Gemini chat request exception, trying fallback', [
                    'model' => $model,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    public function buildContextMessages($messages, int $limit = null): array
    {
        $limit = $limit ?? $this->maxContextMessages;

        return collect($messages)
            ->take($limit)
            ->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->values()
            ->all();
    }

    public function isEnabled(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        return $this->apiKey !== '' && $this->model !== '';
    }
}
