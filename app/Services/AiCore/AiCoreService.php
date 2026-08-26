<?php

namespace App\Services\AiCore;

use App\Models\Conversation;
use Illuminate\Support\Collection;

class AiCoreService
{
    protected $intentDetector;
    protected $flowchart;
    protected $memoryManager;
    protected $commandProcessor;
    protected $conversationManager;
    protected $ollamaChatService;

    public function __construct(
        IntentDetector $intentDetector,
        ToolFlowchart $flowchart,
        MemoryManager $memoryManager,
        CommandProcessor $commandProcessor,
        ConversationManager $conversationManager,
        OllamaChatService $ollamaChatService
    ) {
        $this->intentDetector = $intentDetector;
        $this->flowchart = $flowchart;
        $this->memoryManager = $memoryManager;
        $this->commandProcessor = $commandProcessor;
        $this->conversationManager = $conversationManager;
        $this->ollamaChatService = $ollamaChatService;
    }

    public function process(string $text, string $deviceId, ?string $conversationId = null, ?string $title = null): array
    {
        $conversation = $this->conversationManager->getOrCreate(
            $deviceId,
            $conversationId,
            $title ?? 'AI Core Chat'
        );

        $this->conversationManager->addMessage($conversation, 'user', $text);

        $intentResult = $this->intentDetector->detect($text);
        $intentName = $intentResult['intent'];
        $entities = $intentResult['entities'];

        $memories = collect();
        if ($intentName !== null && $intentName !== 'memory.store') {
            $memories = $this->memoryManager->retrieve($deviceId, $intentName, $entities);
        }

        $commands = [];
        $reply = null;

        if ($intentName === 'conversation.clear') {
            $this->conversationManager->clear($conversation);
            $reply = 'Starting a fresh conversation.';
            $newConversation = $this->conversationManager->start($deviceId, $title ?? 'Fresh Conversation');
            $conversation = $newConversation;
        } elseif ($intentName === 'memory.store') {
            $reply = $this->handleMemoryStore($deviceId, $entities, $intentResult);
        } elseif ($intentName === 'memory.retrieve') {
            $memories = $this->memoryManager->retrieve($deviceId, null, $entities);
            $reply = $this->handleMemoryRetrieve($memories, $entities);
        } else {
            if ($intentName !== null && $this->flowchart->isResolvable($intentName)) {
                $intentResults = [$intentResult];
                $commands = $this->flowchart->resolveAll($intentResults, $deviceId);
            }

            if (!empty($commands)) {
                foreach ($commands as $cmd) {
                    $intentForCmd = $this->findIntentForCommand($cmd['action']);
                    if ($intentForCmd !== null) {
                        $reply = $this->flowchart->generateReply($intentForCmd, $cmd, $intentResult);
                        break;
                    }
                }
                if ($reply === null) {
                    $reply = 'I\'ve queued a command for your device.';
                }
            } else {
                $reply = $this->generateLlmReply($conversation, $text, $memories);
            }
        }

        $this->conversationManager->addMessage($conversation, 'assistant', $reply ?? '', [
            'intent' => $intentName,
            'entities' => $entities,
            'command_ids' => collect($commands)->pluck('id')->all(),
        ]);

        return [
            'success' => true,
            'tool' => 'ai-core',
            'group' => 'A1',
            'data' => [
                'conversation_id' => $conversation->uuid,
                'intent' => [
                    'name' => $intentName,
                    'confidence' => $intentResult['confidence'],
                    'entities' => $entities,
                ],
                'commands' => $commands,
                'memory_context' => $memories->map(fn ($m) => [
                    'key' => $m->key,
                    'value' => $m->value,
                    'type' => $m->type,
                ])->values()->all(),
                'reply' => $reply ?? 'I\'ve processed your message.',
            ],
        ];
    }

    protected function generateLlmReply(Conversation $conversation, string $userText, Collection $memories): string
    {
        if (!$this->ollamaChatService->isEnabled()) {
            return "I'm not sure I understand. Could you rephrase that?";
        }

        $recentMessages = $this->conversationManager->getLastMessages($conversation, config('ai-core.ollama.max_context_messages', 10));
        $messages = $this->ollamaChatService->buildContextMessages($recentMessages);

        $systemPrompt = config('ai-core.ollama.system_prompt', 'You are a helpful AI assistant named Boogy. Be concise, friendly, and helpful.');

        if ($memories->isNotEmpty()) {
            $memoryContext = $memories->take(5)->map(fn ($m) => "- {$m->key}: {$m->value}")->implode("\n");
            $systemPrompt .= "\n\nRelevant memories:\n" . $memoryContext;
        }

        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemPrompt,
        ]);

        $reply = $this->ollamaChatService->chat($messages);

        if ($reply !== null && $reply !== '') {
            return $reply;
        }

        return "I'm not sure I understand. Could you rephrase that?";
    }

    protected function handleMemoryStore(string $deviceId, array $entities, array $intentResult): string
    {
        $fact = $entities['fact'] ?? $intentResult['normalized_text'] ?? '';

        if ($fact === '') {
            return 'I didn\'t catch what you want me to remember.';
        }

        [$key, $value] = $this->extractKeyValue($fact);

        if ($value === null || $value === '') {
            $key = 'fact_' . md5($fact);
            $value = $fact;
        }

        $tags = ['memory.store', $intentResult['intent'] ?? 'fact'];
        if ($key !== null) {
            $tags[] = $key;
        }

        $this->memoryManager->store($deviceId, $key, $value, 'fact', $tags, true, [
            'source' => 'chat',
            'original_fact' => $fact,
        ]);

        return "I'll remember that: {$value}.";
    }

    protected function handleMemoryRetrieve($memories, array $entities): string
    {
        if ($memories->isEmpty()) {
            return 'I don\'t have anything to recall right now.';
        }

        $summary = $memories->first();
        return "I remember: {$summary->key} is {$summary->value}.";
    }

    protected function extractKeyValue(string $fact): array
    {
        if (preg_match('/^my\s+(.+?)\s+is\s+(.+)$/i', $fact, $m)) {
            $key = str_replace(' ', '_', trim($m[1]));
            $value = trim($m[2]);
            return [$key, $value];
        }

        if (preg_match('/^(.+?)\s+is\s+(.+)$/i', $fact, $m)) {
            $key = str_replace(' ', '_', trim($m[1]));
            $value = trim($m[2]);
            return [$key, $value];
        }

        return [null, $fact];
    }

    protected function findIntentForCommand(string $action): ?string
    {
        $flowchart = $this->flowchart->getFlowchart();
        foreach ($flowchart as $intentName => $mapping) {
            if (($mapping['action'] ?? null) === $action) {
                return $intentName;
            }
        }
        return null;
    }

    protected function fallbackReply(?string $intentName, array $intentResult): string
    {
        if ($intentName === null) {
            return "I'm not sure I understand. Could you rephrase that?";
        }
        return "I detected '{$intentName}' but can't execute it right now. Please provide more details.";
    }
}
