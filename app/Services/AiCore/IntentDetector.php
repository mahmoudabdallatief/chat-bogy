<?php

namespace App\Services\AiCore;

class IntentDetector
{
    protected array $intents;

    protected array $entityRegexes = [
        'phone_number' => '/(\+?\d{1,3}[\s\-\.]?)?\(?\d{3,4}\)?[\s\-\.]?\d{3}[\s\-\.]?\d{4}/',
        'time' => '/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b/i',
        'level' => '/\b(\d{1,3})\s*%?\b/',
        'call_id' => '/\b([a-f0-9\-]{8,})\b/i',
    ];

    public function __construct()
    {
        $this->intents = config('ai-core.intents', []);
    }

    public function detect(string $text): array
    {
        $normalized = $this->normalize($text);
        $bestIntent = null;
        $bestScore = 0;
        $bestEntities = [];

        foreach ($this->intents as $intentName => $config) {
            $score = 0;
            $entities = [];
            $weight = $config['weight'] ?? 1;

            foreach ($config['keywords'] ?? [] as $keyword) {
                if (mb_stripos($normalized, $keyword) !== false) {
                    $score += $weight;
                }
            }

            foreach ($config['patterns'] ?? [] as $pattern) {
                $keywordPart = $this->extractKeywordPart($pattern);
                if ($keywordPart !== '' && mb_stripos($normalized, $keywordPart) !== false) {
                    $score += $weight * 2;
                }

                $extracted = $this->tryExtractFromPattern($pattern, $text);
                if (!empty($extracted)) {
                    $score += $weight * 3;
                    $entities = array_merge($extracted, $entities);
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIntent = $intentName;
                $bestEntities = $entities;
            }
        }

        $confidence = $bestScore > 0 ? min(1.0, $bestScore / 40.0) : 0.0;

        if ($bestIntent !== null) {
            $regexEntities = $this->extractEntitiesByRegex($bestIntent, $text);
            $bestEntities = array_merge($regexEntities, $bestEntities);
        }

        return [
            'intent' => $bestIntent,
            'confidence' => round($confidence, 2),
            'entities' => $bestEntities,
            'original_text' => $text,
            'normalized_text' => $normalized,
        ];
    }

    public function getAllIntents(): array
    {
        return array_keys($this->intents);
    }

    public function getIntentConfig(string $intentName): ?array
    {
        return $this->intents[$intentName] ?? null;
    }

    protected function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text);
        return $text;
    }

    protected function extractKeywordPart(string $pattern): string
    {
        $pattern = preg_replace('/\s*\{\w+\}\s*/', ' ', $pattern);
        $pattern = trim($pattern);
        $pattern = preg_replace('/\s+/', ' ', $pattern);
        return $pattern;
    }

    protected function tryExtractFromPattern(string $pattern, string $text): array
    {
        if (strpos($pattern, '{') === false) {
            return [];
        }

        $placeholders = $this->getPlaceholders($pattern);
        $regex = $this->patternToRegex($pattern);

        if (preg_match($regex, $text, $matches)) {
            return $this->extractFromMatches($placeholders, $matches, $pattern);
        }

        $regex = $this->patternToRegex($pattern, false);
        if (preg_match($regex, $text, $matches)) {
            return $this->extractFromMatches($placeholders, $matches, $pattern);
        }

        return [];
    }

    protected function getPlaceholders(string $pattern): array
    {
        preg_match_all('/\{(\w+)\}/', $pattern, $matches);
        return $matches[1] ?? [];
    }

    protected function patternToRegex(string $pattern, bool $anchored = true): string
    {
        $escaped = preg_quote($pattern, '/');

        $quantifier = $anchored ? '(.+?)' : '(.+)';

        foreach (['title', 'time', 'phone_number', 'contact', 'app', 'level', 'fact', 'query'] as $placeholder) {
            $placeholderEscaped = preg_quote('{' . $placeholder . '}', '/');
            switch ($placeholder) {
                case 'time':
                    $replacement = '([0-9]{1,2}(?::[0-9]{2})?\s*(?:am|pm)?)';
                    break;
                case 'phone_number':
                    $replacement = '(\+?[0-9][0-9\s\-\(\)]+)';
                    break;
                case 'level':
                    $replacement = '([0-9]{1,3})';
                    break;
                case 'title':
                case 'fact':
                case 'contact':
                case 'app':
                case 'query':
                    $replacement = $quantifier;
                    break;
                default:
                    $replacement = $quantifier;
            }
            $escaped = str_replace($placeholderEscaped, $replacement, $escaped);
        }

        $escaped = str_replace('\ ', '\s+', $escaped);

        if ($anchored) {
            return '/^' . $escaped . '$/iu';
        }
        return '/' . $escaped . '/iu';
    }

    protected function extractFromMatches(array $placeholders, array $matches, string $pattern): array
    {
        $entities = [];
        foreach ($placeholders as $index => $name) {
            if (isset($matches[$index + 1]) && $matches[$index + 1] !== '') {
                $entities[$name] = $this->postProcessEntity($name, trim($matches[$index + 1]));
            }
        }
        return $entities;
    }

    protected function extractEntitiesByRegex(string $intentName, string $text): array
    {
        $extractorMap = [
            'call.make' => ['phone_number'],
            'call.end' => ['call_id'],
            'contact.search' => ['query'],
            'device.volume' => ['level'],
            'device.brightness' => ['level'],
            'battery.status' => ['level'],
            'reminder.create' => ['time'],
            'alarm.create' => ['time'],
        ];

        $entities = [];
        $names = $extractorMap[$intentName] ?? [];

        foreach ($names as $name) {
            if (isset($this->entityRegexes[$name])) {
                if (preg_match($this->entityRegexes[$name], $text, $m)) {
                    $rawValue = $name === 'time' ? trim($m[0]) : trim($m[1] ?? $m[0]);
                    $entities[$name] = $this->postProcessEntity($name, $rawValue);
                }
            }
        }

        if (in_array($intentName, ['device.flashlight', 'device.wifi', 'device.bluetooth'], true)) {
            $lower = mb_strtolower($text);
            $negative = preg_match('/\b(off|disable|turn off|turn down)\b/', $lower);
            $positive = preg_match('/\b(on|enable|turn on|turn up)\b/', $lower);
            $entities['enabled'] = $negative ? false : ($positive ? true : true);
        }

        return $entities;
    }

    protected function postProcessEntity(string $name, string $value)
    {
        switch ($name) {
            case 'time': return $this->normalizeTime($value);
            case 'phone_number': return $this->normalizePhoneNumber($value);
            case 'level': return is_numeric($value) ? (int) $value : $value;
            case 'title': return $this->cleanTitle($value);
            case 'enabled': return true;
            case 'while_speaking': return true;
            default: return $value;
        }
    }

    protected function cleanTitle(string $value): string
    {
        $value = preg_replace('/\s+at\s+\d{1,2}(?::\d{2})?\s*(?:am|pm)?/i', '', $value);
        $value = preg_replace('/\s+\d{1,2}(?::\d{2})?\s*(?:am|pm)?/i', '', $value);
        $value = preg_replace('/\s+(?:at|for|on)\s+\d+$/i', '', $value);
        return trim($value);
    }

    protected function normalizeTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)?$/i', $time, $m)) {
            $hour = (int) $m[1];
            $minute = !empty($m[2]) ? $m[2] : '00';
            $meridiem = strtolower($m[3] ?? '');

            if ($meridiem === 'pm' && $hour !== 12) {
                $hour += 12;
            }
            if ($meridiem === 'am' && $hour === 12) {
                $hour = 0;
            }

            return sprintf('%02d:%s', $hour, $minute);
        }
        return $time;
    }

    protected function normalizePhoneNumber(string $number): string
    {
        return preg_replace('/[\s\-\(\)]/', '', $number);
    }
}
