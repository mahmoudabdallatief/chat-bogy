<?php

namespace App\Services\AiCore;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiChatService
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected string $systemPrompt;
    protected int $maxContextMessages;

    public function __construct()
    {
        $this->apiKey = (string) env('GEMINI_API_KEY', '');
        $this->model = (string) env('GEMINI_MODEL', 'gemini-3.6-flash');
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

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

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

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error('Gemini chat request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Gemini chat request exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
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
