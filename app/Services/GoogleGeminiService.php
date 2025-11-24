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
        // Translate prompt to English if necessary, using Gemini
        $englishPrompt = $this->translateToEnglish($userPrompt);
        // Imagen only supports English prompts

        // Support both `numberOfImages` and `sampleCount` as input. Clamp to 1..4
        $requested = $options['numberOfImages'] ?? $options['sampleCount'] ?? null;
        $sampleCount = is_numeric($requested) ? (int)$requested : null;
        if (is_null($sampleCount)) {
            // Imagen default is 4
            $sampleCount = 4;
        }
        // enforce allowed range 1-4
        $sampleCount = max(1, min(4, $sampleCount));

        // Validate aspect ratio: allowed values per Imagen docs
        $allowedAspect = ['1:1', '3:4', '4:3', '9:16', '16:9'];
        $aspectRatio = $options['aspectRatio'] ?? '1:1';
        if (! in_array($aspectRatio, $allowedAspect, true)) {
            $aspectRatio = '1:1';
        }

        // Validate personGeneration
        $pg = $options['personGeneration'] ?? 'allow_adult';
        $allowedPersonGen = ['dont_allow', 'allow_adult', 'allow_all'];
        if (! in_array($pg, $allowedPersonGen, true)) {
            $pg = 'allow_adult';
        }

        // Validate imageSize only if provided: allowed values 1K, 2K
        $imageSize = $options['imageSize'] ?? null;
        if (! is_null($imageSize)) {
            $imageSize = strtoupper($imageSize);
            if (! in_array($imageSize, ['1K', '2K'], true)) {
                // invalid value -> drop it
                $imageSize = null;
            }
        }

        $payload = [
            'instances' => [
                ['prompt' => $englishPrompt]
            ],
            'parameters' => [
                // use `sampleCount` per the example; we also accept `numberOfImages` from client
                'sampleCount' => $sampleCount,
                'aspectRatio' => $aspectRatio,
                'personGeneration' => $pg,
            ]
        ];

        if ($imageSize) {
            $payload['parameters']['imageSize'] = $imageSize; // only 1K / 2K
        }
        // Add imageSize if provided (1K or 2K)
        if (!empty($options['imageSize'])) {
            $payload['parameters']['imageSize'] = $options['imageSize'];
        }
        $model = 'imagen-4.0-generate-001'; // Use Imagen model
        $uri = '/models/' . $model . ':predict';
        $response = $this->post($uri, $payload);
        $formatted = $this->formatResponse($response);

        // If the API returned image data, save it and return metadata
        if ($formatted['success'] && isset($formatted['payload']['predictions'])) {
            $images = [];
            foreach ($formatted['payload']['predictions'] as $prediction) {
                if (isset($prediction['bytesBase64Encoded'])) {
                    $base64 = $prediction['bytesBase64Encoded'];
                    $saved = $this->saveGeneratedImage($base64, $userPrompt, $model);
                    if ($saved['success']) {
                        $images[] = $saved['image'];
                    }
                }
            }
            if (!empty($images)) {
                $formatted['payload']['saved_images'] = $images;
            }
        }

        return $formatted;
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
            // Imagen always includes a SynthID watermark in created images
            'has_synthid_watermark' => true,
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

    // Save generated image bytes and metadata, keep up to 50 files
    public function saveGeneratedImage(string $base64Data, string $originalPrompt = '', string $model = ''): array
    {
        $bytes = base64_decode($base64Data);
        if ($bytes === false) {
            return ['success' => false, 'status' => 400, 'body' => 'Invalid base64 image data'];
        }

        $dir = 'images';
        if (! Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        $id = Str::random(12);
        $filename = $id . '.png';
        $path = $dir . '/' . $filename;

        // Convert whatever we received into PNG bytes to guarantee uniform storage
        $pngBytes = $this->ensurePngBytes($bytes);
        $filePath = storage_path('app/' . $path);
        file_put_contents($filePath, $pngBytes);
        $size = filesize($filePath);

        $meta = $this->loadImageMetadata();
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

        // enforce limit 50
        while (count($meta) > 50) {
            $oldest = array_shift($meta);
            if (!empty($oldest['path']) && Storage::exists($oldest['path'])) {
                Storage::delete($oldest['path']);
            }
        }

        $this->saveImageMetadata($meta);

        return ['success' => true, 'status' => 201, 'image' => $entry];
    }

    /**
     * Ensure the provided bytes represent a PNG image. If we can decode into an image
     * resource we re-encode as PNG; otherwise fall back to the original bytes.
     */
    protected function ensurePngBytes(string $bytes): string
    {
        // quick check for a PNG header
        if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return $bytes; // already PNG
        }

        // Attempt to create an image resource from the bytes
        if (function_exists('imagecreatefromstring')) {
            $im = @imagecreatefromstring($bytes);
            if ($im !== false) {
                ob_start();
                // Re-encode as PNG for consistent storage
                imagepng($im);
                imagedestroy($im);
                $png = ob_get_clean();
                if ($png !== false && strlen($png) > 0) {
                    return $png;
                }
            }
        }

        // Fallback to returning original bytes if conversion not possible
        return $bytes;
    }

    public function listSavedImages(): array
    {
        $meta = $this->loadImageMetadata();
        return ['success' => true, 'status' => 200, 'images' => $meta];
    }

    public function getSavedImageById(string $id): ?array
    {
        $meta = $this->loadImageMetadata();
        foreach ($meta as $e) {
            if ($e['id'] === $id) return $e;
        }
        return null;
    }

    protected function loadImageMetadata(): array
    {
        $path = storage_path('app/images/metadata.json');
        if (!file_exists($path)) return [];
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    protected function saveImageMetadata(array $meta): void
    {
        $dir = storage_path('app/images');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/metadata.json';
        file_put_contents($path, json_encode($meta, JSON_PRETTY_PRINT));
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

    protected function translateToEnglish(string $text): string
    {
        // If the text is already in English, return as-is (simple check)
        if (preg_match('/^[a-zA-Z\s\.,!?\'"-]+$/', $text) && !preg_match('/(el|la|los|las|un|una|es|son|está|están)/i', $text)) {
            return $text;
        }
        // Otherwise, translate using Gemini
        $translationPrompt = "Translate the following text to English. Only return the translated text, nothing else: " . $text;
        $result = $this->generateText($translationPrompt, 'generic', []);
        if ($result['success']) {
            return trim($result['payload']['candidates'][0]['content']['parts'][0]['text'] ?? $text);
        }
        return $text; // Fallback
    }

    protected function wrapPromptForImage(string $prompt, array $options = []): string
    {
        // Imagen requires English prompts, so return as-is
        return $prompt;
    }

    protected function wrapPromptForAudio(string $prompt, array $options = []): string
    {
        return "Responde siempre en español. Genera contenido de audio con estas instrucciones: " . $prompt;
    }

    protected function wrapPromptForVideo(string $prompt, array $options = []): string
    {
        return "Responde siempre en español. Genera un guión corto de video y direcciones visuales basadas en: " . $prompt;
    }

    /**
     * Map a channel name to a text prompt wrapper.
     *
     * The controller sends channel names like `facebook`, `instagram` and `podcast`.
     * This helper ensures generateText has a consistent starting instruction
     * and applies small, channel-specific guidance.
     */
    protected function wrapPromptForChannel(string $prompt, string $channel = 'generic'): string
    {
        $base = "Responde siempre en español.";

        switch (strtolower($channel)) {
            case 'facebook':
                // short conversational posts, engaging tone
                return $base . " Escribe una publicación corta y atractiva para Facebook basada en: " . $prompt;

            case 'instagram':
                // captions + relevant hashtags
                return $base . " Escribe un pie de foto para Instagram (breve, con emojis y algunos hashtags) basado en: " . $prompt;

            case 'podcast':
                // longer form, show notes / segment script
                return $base . " Escribe un guión o descripción para un episodio de podcast (un par de párrafos) basado en: " . $prompt;

            default:
                // generic text generation
                return $base . " " . $prompt;
        }
    }
}
