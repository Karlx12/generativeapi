<?php

use Illuminate\Support\Facades\Http;

it('generates facebook text', function () {
    config(['generative.google_api_key' => 'test-key', 'generative.google_model' => 'gemini-2.5-flash']);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'generated' => 'Fake Facebook post content'
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/marketing/generation/facebook', [
        'prompt' => 'Generate a short fb post about a new coffee shop',
        'tone' => 'friendly',
    ]);

    $response->assertStatus(200);
    $response->assertJsonFragment(['success' => true]);
    $response->assertJsonPath('payload.generated', 'Fake Facebook post content');
});

it('generates instagram text', function () {
    config(['generative.google_api_key' => 'test-key', 'generative.google_model' => 'gemini-2.5-flash']);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'generated' => 'Fake Instagram caption'
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/marketing/generation/instagram', [
        'prompt' => 'Write an instagram caption about the sea',
    ]);

    $response->assertStatus(200);
    $response->assertJsonFragment(['success' => true]);
    $response->assertJsonPath('payload.generated', 'Fake Instagram caption');
});

it('generates an image', function () {
    config(['generative.google_api_key' => 'test-key', 'generative.google_model' => 'gemini-image-1']);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'image_url' => 'https://cdn.example.com/image.png'
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/marketing/generation/image', [
        'prompt' => 'A red bird flying over a mountain',
        'size' => '1024x1024',
    ]);

    $response->assertStatus(200);
    $response->assertJsonFragment(['success' => true]);
    $response->assertJsonPath('payload.image_url', 'https://cdn.example.com/image.png');
});

it('generates audio', function () {
    config(['generative.google_api_key' => 'test-key', 'generative.google_model' => 'gemini-audio-1']);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'audio_url' => 'https://cdn.example.com/audio.mp3'
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/marketing/generation/audio', [
        'prompt' => 'A podcast intro with energetic music',
        'format' => 'mp3',
    ]);

    $response->assertStatus(200);
    $response->assertJsonFragment(['success' => true]);
    $response->assertJsonPath('payload.audio_url', 'https://cdn.example.com/audio.mp3');
});

it('generates a video', function () {
    config(['generative.google_api_key' => 'test-key', 'generative.google_model' => 'gemini-video-1']);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'video_url' => 'https://cdn.example.com/video.mp4'
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/marketing/generation/video', [
        'prompt' => 'A short explainer about climate change',
        'format' => 'mp4',
    ]);

    $response->assertStatus(200);
    $response->assertJsonFragment(['success' => true]);
    $response->assertJsonPath('payload.video_url', 'https://cdn.example.com/video.mp4');
});

it('uses GEMINI_API_KEY env variable as fallback for API key', function () {
    // Unset config to ensure service falls back to env
    config(['generative.google_api_key' => '']);
    putenv('GEMINI_API_KEY=test-key-env');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Used GEMINI_API_KEY env key']]]]
            ]
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/marketing/generation/facebook', [
        'prompt' => 'Test env fallback'
    ]);

    // Ensure fallback to env key succeeded and we got 200
    $response->assertStatus(200);
    $response->assertJsonFragment(['success' => true]);
});

it('accepts contents array like Gemini and handles it unchanged', function () {
    config(['generative.google_api_key' => 'test-key', 'generative.google_model' => 'gemini-2.5-flash']);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Direct contents call']]]]
            ]
        ], 200),
    ]);

    $contents = [
        [
            'parts' => [
                [ 'text' => 'Explain how AI works in a single paragraph.' ]
            ]
        ]
    ];

    $response = $this->postJson('/api/v1/marketing/generation/facebook', [
        'contents' => $contents,
        'model' => 'gemini-2.5-flash'
    ]);

    $response->assertStatus(200);
    $response->assertJsonFragment(['success' => true]);
    // Ensure Spanish instruction is prepended to the contents
    Http::assertSent(function ($request) {
        $data = $request->data();
        $first = $data['contents'][0]['parts'][0]['text'] ?? '';
        return str_contains($first, 'Responde siempre en español');
    });
});

it('does not send tone/length as top-level fields to Gemini API', function () {
    config(['generative.google_api_key' => 'test-key', 'generative.google_model' => 'gemini-2.5-flash']);
    Http::fake();

    $response = $this->postJson('/api/v1/marketing/generation/facebook', [
        'prompt' => 'Announce our new vegan coffee shop this weekend',
        'model' => 'gemini-2.5-flash',
        'tone' => 'friendly',
        'length' => 'short'
    ]);

    $response->assertStatus(200);

    // Ensure the outbound request payload does not contain tone or length as top-level keys
    Http::assertSent(function ($request) {
        $data = $request->data();
        return !array_key_exists('tone', $data) && !array_key_exists('length', $data);
    });
    // Ensure the outbound request payload includes Spanish instruction in the contents
    Http::assertSent(function ($request) {
        $data = $request->data();
        $parts = $data['contents'][0]['parts'] ?? [];
        $text = '';
        if (!empty($parts) && is_array($parts)) {
            foreach ($parts as $p) {
                $text .= $p['text'] ?? '';
            }
        }
        return str_contains($text, 'Responde siempre en español');
    });
});

it('saves TTS audio, lists it, and allows download', function () {
    config(['generative.google_api_key' => 'test-key', 'generative.google_model' => 'gemini-2.5-flash-preview-tts']);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'audio placeholder', 'inlineData' => ['data' => base64_encode('hello world')]]]]]
            ]
        ], 200),
    ]);

    // Clean metadata file and audios directory if existed
    $metaPath = storage_path('app/audios/metadata.json');
    if (file_exists($metaPath)) unlink($metaPath);
    $audDir = storage_path('app/audios');
    if (is_dir($audDir)) {
        array_map('unlink', glob($audDir . '/*'));
    }

    $response = $this->postJson('/api/v1/marketing/generation/audio', [
        'prompt' => 'Say cheerfully: Have a wonderful day!',
        'model' => 'gemini-2.5-flash-preview-tts'
    ]);

    $response->assertStatus(200);
    $payload = $response->json('payload');
    expect($payload['saved_audio'])->not->toBeNull();

    $id = $payload['saved_audio']['id'];
    $listResponse = $this->getJson('/api/v1/marketing/generation/audio/list');
    $listResponse->assertStatus(200);
    $listResponse->assertJsonFragment(['id' => $id]);
    // Ensure the file exists on disk before requesting download
    $pathOnDisk = storage_path('app/' . $payload['saved_audio']['path']);
    $this->assertFileExists($pathOnDisk);

    $downloadResponse = $this->postJson('/api/v1/marketing/generation/audio/send', ['id' => $id]);
    $downloadResponse->assertStatus(200);

    // Download using GET /api/v1/marketing/generation/audio/{id}
    $downloadGetResponse = $this->get('/api/v1/marketing/generation/audio/' . $id);
    $downloadGetResponse->assertStatus(200);
    $downloadGetResponse->assertHeader('content-disposition');
    // Since the response is a streamed download we cannot always capture body from the response in tests.
    // Ensure the download's content-length header matches the file size on disk instead
    $fileSize = filesize($pathOnDisk);
    $this->assertNotFalse($fileSize);
    $this->assertEquals((string) $fileSize, $downloadGetResponse->headers->get('content-length'));

    // Ensure there's no backward-compatible generate route; only marketing routes should be used
});
