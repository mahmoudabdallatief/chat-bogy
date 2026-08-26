<?php

namespace App\Services\AiCore;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

class OllamaChatService
{
    protected string $baseUrl;
    protected string $model;
    protected int $timeout;
    protected string $systemPrompt;
    protected int $maxContextMessages;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) env('OLLAMA_BASE_URL', 'http://localhost:11434'), '/');
        $this->model = (string) env('OLLAMA_MODEL', 'llama3');
        $this->timeout = (int) env('OLLAMA_TIMEOUT', 120);
        $this->systemPrompt = (string) env('OLLAMA_SYSTEM_PROMPT', 'You are a helpful AI assistant named Boogy. Be concise, friendly, and helpful.');
        $this->maxContextMessages = (int) env('OLLAMA_MAX_CONTEXT_MESSAGES', 10);
    }

    public function chat(array $messages): ?string
    {
        $baseUrl = $this->baseUrl;

        if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($baseUrl . '/api/chat', $payload);

            if (!$response->successful()) {
                Log::warning('Ollama chat request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            return $data['message']['content'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Ollama chat request exception', [
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
        if (App::environment('testing')) {
            return false;
        }

        return (bool) env('OLLAMA_ENABLED', false);
    }
}
