<?php

namespace App\Services\AiCore;

class CommandProcessor
{
    protected $intentDetector;
    protected $flowchart;

    public function __construct(IntentDetector $intentDetector, ToolFlowchart $flowchart)
    {
        $this->intentDetector = $intentDetector;
        $this->flowchart = $flowchart;
    }

    public function parse(string $text, string $deviceId, ?string $conversationId = null): array
    {
        $intentResult = $this->intentDetector->detect($text);

        $intentName = $intentResult['intent'];
        $entities = $intentResult['entities'];
        $confidence = $intentResult['confidence'];

        $command = null;
        $resolvable = false;
        $reply = null;

        if ($intentName !== null && $this->flowchart->isResolvable($intentName)) {
            $command = $this->flowchart->resolve($intentName, $entities, $deviceId);
            if ($command !== null) {
                $resolvable = true;
                $reply = $this->flowchart->generateReply($intentName, $command, $intentResult);
            }
        }

        return [
            'success' => true,
            'tool' => 'ai-core',
            'source' => 'ai-core',
            'group' => 'A1',
            'intent' => [
                'name' => $intentName,
                'confidence' => $confidence,
                'entities' => $entities,
            ],
            'command' => $command,
            'resolvable' => $resolvable,
            'message' => $reply ?? $this->fallbackReply($intentName, $intentResult),
        ];
    }

    public function execute(string $text, string $deviceId, ?string $conversationId = null): array
    {
        $parsed = $this->parse($text, $deviceId, $conversationId);

        if (!$parsed['resolvable']) {
            return [
                'success' => false,
                'message' => $parsed['message'],
                'intent' => $parsed['intent'],
                'commands' => [],
            ];
        }

        return [
            'success' => true,
            'message' => $parsed['message'],
            'intent' => $parsed['intent'],
            'commands' => [$parsed['command']],
        ];
    }

    protected function fallbackReply(?string $intentName, array $intentResult): string
    {
        if ($intentName === null) {
            return "I couldn't understand what you want to do. Can you rephrase?";
        }

        $requiredEntities = $this->flowchart->getMapping($intentName)['entities'] ?? [];
        if (empty($requiredEntities)) {
            return "I'm not sure how to fulfill '{$intentName}' yet.";
        }

        $missing = [];
        foreach ($requiredEntities as $entity) {
            if ($entity === 'device_id' || $entity === null) {
                continue;
            }
            if (!isset($intentResult['entities'][$entity])) {
                $missing[] = $entity;
            }
        }

        if (!empty($missing)) {
            return "I detected the intent '{$intentName}', but I need more information: "
                . implode(', ', $missing) . '.';
        }

        return "I detected the intent '{$intentName}' but I'm missing the required parameters.";
    }
}
