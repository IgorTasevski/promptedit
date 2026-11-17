<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $pixelforge_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property Size $size
 * @property Quality $quality
 * @property list<string> $reference_images
 * @property int $estimated_min
 * @property int $estimated_max
 * @property array<string, string>|null $rates
 * @property int|null $input_text_tokens
 * @property int|null $input_image_tokens
 * @property int|null $output_tokens
 * @property int|null $reasoning_tokens
 * @property int|null $provider_cost
 * @property int|null $price
 * @property string|null $image_url
 * @property Carbon|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $submission_attempts
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'pixelforge_id', 'status', 'prompt', 'size', 'quality', 'reference_images', 'estimated_min', 'estimated_max',
    'rates', 'input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens', 'provider_cost', 'price',
    'image_url', 'image_url_expires_at', 'error_code', 'error_message', 'submission_attempts', 'started_at', 'completed_at',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, int>
     */
    public function tokenUsage(): array
    {
        return [
            'input_text_tokens' => (int) $this->input_text_tokens,
            'input_image_tokens' => (int) $this->input_image_tokens,
            'output_tokens' => (int) $this->output_tokens,
            'reasoning_tokens' => (int) $this->reasoning_tokens,
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inFlight(Builder $query): void
    {
        $query->whereIn('status', GenerationStatus::inFlight());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'size' => Size::class,
            'quality' => Quality::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'image_url_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
