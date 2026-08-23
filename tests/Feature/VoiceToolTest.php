<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VoiceToolTest extends TestCase
{
    public function test_validation_errors_are_json_without_an_accept_header()
    {
        $this->post('/api/tools/voice/v1/wake-word', [])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => ['transcript' => ['The transcript field is required.']],
            ]);
    }

    public function test_wake_word_is_detected_case_insensitively()
    {
        $this->postJson('/api/tools/voice/v1/wake-word', [
            'transcript' => '  HEY   Boogy, set a reminder ',
        ])->assertOk()
            ->assertJsonPath('data.detected', true)
            ->assertJsonPath('data.wake_word', 'hey boogy');
    }

    public function test_transcription_returns_provider_text()
    {
        config(['services.voice.openai_key' => 'test-key']);
        Http::fake(['*/audio/transcriptions' => Http::response(['text' => 'Hello world'])]);

        $this->post('/api/tools/voice/v2/transcribe', [
            'audio' => UploadedFile::fake()->create('voice.wav', 100, 'audio/wav'),
            'language' => 'en',
        ])->assertOk()->assertJsonPath('data.transcript', 'Hello world');
    }

    public function test_synthesis_returns_audio_from_provider()
    {
        config(['services.voice.openai_key' => 'test-key']);
        Http::fake(['*/audio/speech' => Http::response('audio-content', 200)]);

        $this->postJson('/api/tools/voice/v3/synthesize', ['text' => 'Hello'])
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertSee('audio-content');
    }
}
