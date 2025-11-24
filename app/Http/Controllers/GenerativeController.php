<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateTextRequest;
use App\Http\Requests\GenerateMediaRequest;
use App\Services\GoogleGeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerativeController extends Controller
{
    protected GoogleGeminiService $gemini;

    public function __construct(GoogleGeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function generateFacebook(GenerateTextRequest $request): JsonResponse
    {
        $options = $request->only(['tone', 'length', 'model']);
        // Accept either a raw prompt or content array (already shaped like Gemini's contents check)
        $linkUrl = $request->input('link_url');
        if ($request->has('contents')) {
            $contents = $request->input('contents');
            $options['contents'] = $contents;
            $result = $this->gemini->generateTextFromContents($options['contents'], 'facebook', $options, $linkUrl);
        } else {
            $prompt = $request->input('prompt');
            $result = $this->gemini->generateText($prompt, 'facebook', $options, $linkUrl);
        }
        return response()->json($result, $result['status'] ?? 200);
    }

    public function generateInstagram(GenerateTextRequest $request): JsonResponse
    {
        $options = $request->only(['tone', 'length', 'model']);
        $linkUrl = $request->input('link_url');
        if ($request->has('contents')) {
            $contents = $request->input('contents');
            $options['contents'] = $contents;
            $result = $this->gemini->generateTextFromContents($options['contents'], 'instagram', $options, $linkUrl);
        } else {
            $prompt = $request->input('prompt');
            $result = $this->gemini->generateText($prompt, 'instagram', $options, $linkUrl);
        }
        return response()->json($result, $result['status'] ?? 200);
    }

    public function generatePodcast(GenerateTextRequest $request): JsonResponse
    {
        $options = $request->only(['tone', 'length', 'model']);
        $linkUrl = $request->input('link_url');
        if ($request->has('contents')) {
            $contents = $request->input('contents');
            $options['contents'] = $contents;
            $result = $this->gemini->generateTextFromContents($options['contents'], 'podcast', $options, $linkUrl);
        } else {
            $prompt = $request->input('prompt');
            $result = $this->gemini->generateText($prompt, 'podcast', $options, $linkUrl);
        }
        return response()->json($result, $result['status'] ?? 200);
    }

    public function generateImage(GenerateMediaRequest $request): JsonResponse
    {
        $prompt = $request->input('prompt');
        $options = $request->only(['format', 'size', 'model']);
        $result = $this->gemini->generateImage($prompt, $options);
        return response()->json($result, $result['status'] ?? 200);
    }

    public function generateAudio(GenerateMediaRequest $request): JsonResponse
    {
        $prompt = $request->input('prompt');
        $options = $request->only(['format', 'size', 'model']);
        $result = $this->gemini->generateAudio($prompt, $options);
        return response()->json($result, $result['status'] ?? 200);
    }

    public function listAudios(): JsonResponse
    {
        $result = $this->gemini->listSavedAudios();
        return response()->json($result, $result['status'] ?? 200);
    }

    public function sendAudio(Request $request)
    {
        $id = $request->input('id');
        if (! $id) {
            return response()->json(['success' => false, 'status' => 400, 'body' => 'Missing audio id'], 400);
        }

        $audio = $this->gemini->getSavedAudioById($id);
        if (! $audio) {
            return response()->json(['success' => false, 'status' => 404, 'body' => 'Audio not found'], 404);
        }

        $path = storage_path('app/' . $audio['path']);
        if (! file_exists($path)) {
            return response()->json(['success' => false, 'status' => 404, 'body' => 'Audio file missing'], 404);
        }

        return response()->download($path, $audio['filename']);
    }

    public function downloadAudio($id)
    {
        if (! $id) {
            return response()->json(['success' => false, 'status' => 400, 'body' => 'Missing audio id'], 400);
        }

        $audio = $this->gemini->getSavedAudioById($id);
        if (! $audio) {
            return response()->json(['success' => false, 'status' => 404, 'body' => 'Audio not found'], 404);
        }

        $path = storage_path('app/' . $audio['path']);
        if (! file_exists($path)) {
            return response()->json(['success' => false, 'status' => 404, 'body' => 'Audio file missing'], 404);
        }

        return response()->download($path, $audio['filename']);
    }

    public function listImages(): JsonResponse
    {
        $result = $this->gemini->listSavedImages();
        return response()->json($result, $result['status'] ?? 200);
    }

    public function sendImage(Request $request)
    {
        $id = $request->input('id');
        if (! $id) {
            return response()->json(['success' => false, 'status' => 400, 'body' => 'Missing image id'], 400);
        }

        $image = $this->gemini->getSavedImageById($id);
        if (! $image) {
            return response()->json(['success' => false, 'status' => 404, 'body' => 'Image not found'], 404);
        }

        $path = storage_path('app/' . $image['path']);
        if (! file_exists($path)) {
            return response()->json(['success' => false, 'status' => 404, 'body' => 'Image file missing'], 404);
        }

        return response()->download($path, $image['filename']);
    }

    public function downloadImage($id)
    {
        if (! $id) {
            return response()->json(['success' => false, 'status' => 400, 'body' => 'Missing image id'], 400);
        }

        $image = $this->gemini->getSavedImageById($id);
        if (! $image) {
            return response()->json(['success' => false, 'status' => 404, 'body' => 'Image not found'], 404);
        }

        $path = storage_path('app/' . $image['path']);
        if (! file_exists($path)) {
            return response()->json(['success' => false, 'status' => 404, 'body' => 'Image file missing'], 404);
        }

        return response()->download($path, $image['filename']);
    }

    public function generateVideo(GenerateMediaRequest $request): JsonResponse
    {
        $prompt = $request->input('prompt');
        $options = $request->only(['format', 'size', 'model']);
        $result = $this->gemini->generateVideo($prompt, $options);
        return response()->json($result, $result['status'] ?? 200);
    }
}
