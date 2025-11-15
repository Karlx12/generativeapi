<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class GoogleGeminiService
{
    protected string $apiKey = '';
    protected string $baseUrl;
    protected string $model = '';

    public function __construct()
    {
    // Support both GOOGLE_API_KEY and GEMINI_API_KEY environment variable names
    $this->apiKey = config('generative.google_api_key') ?: env('GOOGLE_API_KEY') ?: env('GEMINI_API_KEY') ?: '';
        $this->baseUrl = config('generative.google_base_url', 'https://generativelanguage.googleapis.com/v1beta');
    $this->model = config('generative.google_model') ?: env('GEMINI_MODEL') ?: '';
    }

    public function generateText(string $userPrompt, string $channel = 'generic', array $options = []): array
    {
        $wrapped = $this->wrapPromptForChannel($userPrompt, $channel);

        // Add tone and length into the text itself instead of top-level options
        $tone = $options['tone'] ?? null;
        $length = $options['length'] ?? null;
        if ($tone) {
            $wrapped .= "\nTone: {$tone}.";
        }
        if ($length) {
            $wrapped .= "\nLength: {$length}.";
        }

        // Build payload with only allowed top-level fields to avoid invalid fields being sent
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $wrapped]
                    ]
                ]
            ]
        ];

    $model = $options['model'] ?? $this->model;
        if (empty($model)) {
            return [
                'success' => false,
                'status' => 500,
                'body' => 'Missing GEMINI_MODEL configuration. Set GEMINI_MODEL in .env or pass `model` in request body.'
            ];
        }
        $uri = '/models/' . $model . ':generateContent';
        $response = $this->post($uri, $payload);

        return $this->formatResponse($response);
    }

    public function generateTextFromContents(array $contents, string $channel = 'generic', array $options = []): array
    {
        // Directly use provided contents array (assumed shaped as Gemini's contents format)
        // If user supplied tone or length in options, prepend these as an initial "system" part
        $tone = $options['tone'] ?? null;
        $length = $options['length'] ?? null;
        // Always ensure Spanish instruction is present at the start
        $preParts = ['Responde siempre en español.'];
        if ($tone) $preParts[] = "Tone: {$tone}.";
        if ($length) $preParts[] = "Length: {$length}.";
        array_unshift($contents, [
            'parts' => [
                ['text' => implode(' ', $preParts)]
            ]
        ]);
        $payload = ['contents' => $contents];
        $model = $options['model'] ?? $this->model;
        if (empty($model)) {
            return [
                'success' => false,
                'status' => 500,
                'body' => 'Missing GEMINI_MODEL configuration. Set GEMINI_MODEL in .env or pass `model` in request body.'
            ];
        }
        $uri = '/models/' . $model . ':generateContent';
        $response = $this->post($uri, $payload);
        return $this->formatResponse($response);
    }

    public function generateImage(string $userPrompt, array $options = []): array
    {
        $wrapped = $this->wrapPromptForImage($userPrompt, $options);
        $payload = array_merge(["prompt" => $wrapped], $options);
        $model = $options['model'] ?? $this->model;
        if (empty($model)) {
            return [
                'success' => false,
                'status' => 500,
                'body' => 'Missing GEMINI_MODEL configuration. Set GEMINI_MODEL in .env or pass `model` in request body.'
            ];
        }
        $uri = '/models/' . $model . ':generateImage';
        $response = $this->post($uri, $payload);
        return $this->formatResponse($response);
    }

    public function generateAudio(string $userPrompt, array $options = []): array
    {
        $wrapped = $this->wrapPromptForAudio($userPrompt, $options);
        $payload = [
            'contents' => [[
                'parts' => [[ 'text' => $wrapped ]]
            ]]
        ];
        // Generation config to ensure the model returns audio and optionally uses a voice
        $payload['generationConfig'] = [
            'responseModalities' => ['AUDIO'],
            'speechConfig' => [
                'voiceConfig' => [
                    'prebuiltVoiceConfig' => [
                        'voiceName' => $options['voice'] ?? 'Kore'
                    ]
                ]
            ]
        ];
        if (!empty($model)) {
            $payload['model'] = $model;
        }
        $model = $options['model'] ?? $this->model;
        if (empty($model)) {
            return [
                'success' => false,
                'status' => 500,
                'body' => 'Missing GEMINI_MODEL configuration. Set GEMINI_MODEL in .env or pass `model` in request body.'
            ];
        }
    // Use the generic generateContent endpoint for audio modality
    $uri = '/models/' . $model . ':generateContent';
        $response = $this->post($uri, $payload);
        $formatted = $this->formatResponse($response);

        // If the API returned inline audio data, save it and return metadata
        if ($formatted['success'] && isset($formatted['payload']['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
            $base64 = $formatted['payload']['candidates'][0]['content']['parts'][0]['inlineData']['data'];
            $originalPrompt = $userPrompt;
            $modelUsed = $model;
            $saved = $this->saveGeneratedAudio($base64, $originalPrompt, $modelUsed);
            if ($saved['success']) {
                $formatted['payload']['saved_audio'] = $saved['audio'];
            }
        }

        return $formatted;
    }

    public function generateVideo(string $userPrompt, array $options = []): array
    {
        $wrapped = $this->wrapPromptForVideo($userPrompt, $options);
        $payload = [
            'contents' => [[
                'parts' => [[ 'text' => $wrapped ]]
            ]]
        ];
        // Ensure the video generation returns VIDEO modality (if supported by model)
        $payload['generationConfig'] = [
            'responseModalities' => ['VIDEO']
        ];
        if (!empty($model)) {
            $payload['model'] = $model;
        }
        $model = $options['model'] ?? $this->model;
        if (empty($model)) {
            return [
                'success' => false,
                'status' => 500,
                'body' => 'Missing GEMINI_MODEL configuration. Set GEMINI_MODEL in .env or pass `model` in request body.'
            ];
        }
    // Use the generic generateContent endpoint for video modality
    $uri = '/models/' . $model . ':generateContent';
        $response = $this->post($uri, $payload);
        return $this->formatResponse($response);
    }

    protected function post(string $uri, array $payload)
    {
        $token = $this->apiKey;

        if (empty($token)) {
            return [
                'success' => false,
                'status' => 500,
                'body' => 'Missing Google Gemini API key (set GOOGLE_API_KEY in .env)',
            ];
        }

        $url = rtrim($this->baseUrl, '/') . $uri;

        // Use x-goog-api-key header as in the example curl usage
        $response = Http::withHeaders([
            'x-goog-api-key' => $token,
        ])
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            return [
                'success' => false,
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        }

        return [
            'success' => true,
            'status' => $response->status(),
            'data' => $response->json(),
        ];
    }

    protected function formatResponse(array $resp): array
    {
        if (! $resp['success']) {
            return $resp;
        }

        $data = $resp['data'] ?: [];

        return [
            'success' => true,
            'status' => $resp['status'] ?? 200,
            'payload' => $data,
        ];
    }

    // Save audio bytes and metadata, keep up to 20 files
    public function saveGeneratedAudio(string $base64Data, string $originalPrompt = '', string $model = ''): array
    {
        $bytes = base64_decode($base64Data);
        if ($bytes === false) {
            return ['success' => false, 'status' => 400, 'body' => 'Invalid base64 audio data'];
        }

        $dir = 'audios';
        if (! Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        $id = Str::random(12);
        $filename = $id . '.pcm';
        $path = $dir . '/' . $filename;

    // Use direct file write to ensure storage in testing environment
    $filePath = storage_path('app/' . $path);
    file_put_contents($filePath, $bytes);
    $size = filesize($filePath);

        $meta = $this->loadAudioMetadata();
        $entry = [
            'id' => $id,
            'filename' => $filename,
            'path' => $path,
            'original_prompt' => $originalPrompt,
            'model' => $model,
            'size' => $size,
            'created_at' => now()->toDateTimeString(),
        ];

        $meta[] = $entry;
        // sort by created_at ascending
        usort($meta, function ($a, $b) {
            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        });

        // enforce limit 20
        while (count($meta) > 20) {
            $oldest = array_shift($meta);
            if (!empty($oldest['path']) && Storage::exists($oldest['path'])) {
                Storage::delete($oldest['path']);
            }
        }

        $this->saveAudioMetadata($meta);

        return ['success' => true, 'status' => 201, 'audio' => $entry];
    }

    public function listSavedAudios(): array
    {
        $meta = $this->loadAudioMetadata();
        return ['success' => true, 'status' => 200, 'audios' => $meta];
    }

    public function getSavedAudioById(string $id): ?array
    {
        $meta = $this->loadAudioMetadata();
        foreach ($meta as $e) {
            if ($e['id'] === $id) return $e;
        }
        return null;
    }

    protected function loadAudioMetadata(): array
    {
        $path = storage_path('app/audios/metadata.json');
        if (!file_exists($path)) return [];
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    protected function saveAudioMetadata(array $meta): void
    {
        $dir = storage_path('app/audios');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/metadata.json';
        file_put_contents($path, json_encode($meta, JSON_PRETTY_PRINT));
    }

    protected function wrapPromptForChannel(string $prompt, string $channel): string
    {
        $channel = Str::lower($channel);
        $wrapper = "Responde siempre en español. ";
        $wrapper .= "Act as a professional social media content writer. Create a post for: {$channel}. Use the following instructions and craft a suitable post: \n";
        $wrapper .= "User instructions: {$prompt}";
        return $wrapper;
    }

    protected function wrapPromptForImage(string $prompt, array $options = []): string
    {
        return "Responde siempre en español. Genera una imagen basada en estas instrucciones: " . $prompt;
    }

    protected function wrapPromptForAudio(string $prompt, array $options = []): string
    {
        return "Responde siempre en español. Genera contenido de audio con estas instrucciones: " . $prompt;
    }

    protected function wrapPromptForVideo(string $prompt, array $options = []): string
    {
        return "Responde siempre en español. Genera un guión corto de video y direcciones visuales basadas en: " . $prompt;
    }
}
