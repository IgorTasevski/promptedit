<?php

namespace App\Policies;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;

class GenerationPolicy
{
    public function view(User $user, Generation $generation): bool
    {
        return $generation->user_id === $user->id;
    }

    public function retry(User $user, Generation $generation): bool
    {
        return $this->view($user, $generation) && $generation->status === GenerationStatus::Failed;
    }
}
