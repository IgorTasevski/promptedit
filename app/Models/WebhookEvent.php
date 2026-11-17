<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $type
 */
#[Fillable(['id', 'type'])]
class WebhookEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';
}
