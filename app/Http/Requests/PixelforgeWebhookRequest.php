<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PixelforgeWebhookRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'type' => ['required', 'string'],
            'data' => ['present', 'array'],
            'data.object' => ['sometimes', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function generation(): ?array
    {
        $object = $this->validated('data.object');

        return is_array($object) ? $object : null;
    }
}
