<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTextRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // Accept either a raw prompt string or a full contents array (shape used by the Gemini API)
            'prompt' => 'required_without:contents|string|max:2000',
            'contents' => 'sometimes|array',
            'link_url' => 'sometimes|nullable|url|max:2000',
            'tone' => 'sometimes|string',
            'length' => 'sometimes|in:short,medium,long',
            'model' => 'sometimes|string',
        ];
    }
}
