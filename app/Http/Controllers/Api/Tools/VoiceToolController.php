<?php

namespace App\Http\Controllers\Api\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VoiceToolController extends Controller
{

    /** V1: checks a client-provided transcript for a configured wake word. */
    public function detectWakeWord(Request $request)
    {
        $data = $request->validate([
            'transcript' => ['required', 'string', 'max:5000'],
            'wake_word' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        $wakeWord = trim($data['wake_word'] ?? config('services.voice.default_wake_word'));
        $normalizedTranscript = $this->normalize($data['transcript']);
        $normalizedWakeWord = $this->normalize($wakeWord);
        $position = $normalizedWakeWord === '' ? false : strpos($normalizedTranscript, $normalizedWakeWord);

        return response()->json([
            'success' => true,
            'tool' => 'voice',
            'version' => 'v1',
            'data' => [
                'detected' => $position !== false,
                'wake_word' => $wakeWord,
                'position' => $position === false ? null : $position,
                'language' => $data['language'] ?? null,
                'request_id' => (string) Str::uuid(),
            ],
        ]);
    }

    /** V2: sends an uploaded audio recording to the configured STT provider. */
    public function transcribe(Request $request)
    {
        $data = $request->validate([
            'audio' => ['required', 'file', 'max:25600', 'mimetypes:audio/mpeg,audio/mp4,audio/m4a,audio/wav,audio/webm,audio/ogg,audio/mp3'],
            'language' => ['nullable', 'string', 'max:10'],
            'prompt' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->ensureOpenAiIsConfigured();
        $audio = $data['audio'];
        $response = Http::withToken(config('services.voice.openai_key'))
            ->attach('file', fopen($audio->getRealPath(), 'r'), $audio->getClientOriginalName())
            ->post(rtrim(config('services.voice.openai_base_url'), '/') . '/audio/transcriptions', array_filter([
                'model' => config('services.voice.transcription_model'),
                'language' => $data['language'] ?? null,
                'prompt' => $data['prompt'] ?? null,
                'response_format' => 'json',
            ]));

        if ($response->failed()) {
            return $this->providerError($response);
        }

        return response()->json([
            'success' => true,
            'tool' => 'voice',
            'version' => 'v2',
            'data' => [
                'transcript' => $response->json('text'),
                'language' => $data['language'] ?? null,
                'request_id' => (string) Str::uuid(),
            ],
        ]);
    }

    /** V3: converts text to an audio stream returned directly to the caller. */
    public function synthesize(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:4096'],
            'voice' => ['nullable', 'string', 'max:50'],
            'format' => ['nullable', 'string', 'in:mp3,wav,opus,aac,flac,pcm'],
            'speed' => ['nullable', 'numeric', 'between:0.25,4'],
        ]);

        $this->ensureOpenAiIsConfigured();
        $format = $data['format'] ?? 'mp3';
        $response = Http::withToken(config('services.voice.openai_key'))
            ->post(rtrim(config('services.voice.openai_base_url'), '/') . '/audio/speech', array_filter([
                'model' => config('services.voice.speech_model'),
                'input' => $data['text'],
                'voice' => $data['voice'] ?? 'alloy',
                'response_format' => $format,
                'speed' => $data['speed'] ?? null,
            ]));

        if ($response->failed()) {
            return $this->providerError($response);
        }

        return response($response->body(), 200, [
            'Content-Type' => $this->mimeTypeFor($format),
            'Content-Disposition' => 'inline; filename="speech.' . $format . '"',
            'X-Voice-Tool-Version' => 'v3',
        ]);
    }

    private function ensureOpenAiIsConfigured()
    {
        abort_unless(config('services.voice.provider') === 'openai' && config('services.voice.openai_key'), 503,
            'Voice provider is not configured. Set VOICE_PROVIDER and OPENAI_API_KEY.');
    }

    private function providerError($response)
    {
        return response()->json([
            'success' => false,
            'tool' => 'voice',
            'message' => 'The voice provider could not process the request.',
            'provider_status' => $response->status(),
            'provider_response' => $response->json(),
        ], 502);
    }

    private function normalize($value)
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($value), 'UTF-8'));
    }

    private function mimeTypeFor($format)
    {
        return [
            'wav' => 'audio/wav', 'opus' => 'audio/ogg', 'aac' => 'audio/aac',
            'flac' => 'audio/flac', 'pcm' => 'audio/L16',
        ][$format] ?? 'audio/mpeg';
    }
}
