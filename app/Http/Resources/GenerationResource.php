<?php

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageExpired = $this->image_url_expires_at?->isPast() ?? false;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'prompt' => $this->prompt,
            'size' => $this->size->name,
            'quality' => $this->quality->name,
            'reference_images' => count($this->reference_images),
            'estimated_min' => $this->estimated_min,
            'estimated_max' => $this->estimated_max,
            'price' => $this->price,
            'tokens' => $this->when($this->status === GenerationStatus::Succeeded, fn () => [
                'input_text' => $this->input_text_tokens,
                'input_image' => $this->input_image_tokens,
                'output' => $this->output_tokens,
                'reasoning' => $this->reasoning_tokens,
            ]),
            'image_url' => $imageExpired ? null : $this->image_url,
            'image_expired' => $imageExpired,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'submission_attempts' => $this->submission_attempts,
            'created_at' => $this->created_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
