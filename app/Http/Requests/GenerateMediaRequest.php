<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateMediaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'prompt' => 'required|string|max:4000',
            'format' => 'sometimes|string',
            'size' => 'sometimes|string',
            'model' => 'sometimes|string',
        ];
    }
}
