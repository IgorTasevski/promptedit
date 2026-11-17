<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-admin {email}')]
#[Description('Give a user access to the operator report')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->firstOrFail();
        $user->forceFill(['is_admin' => true])->save();

        $this->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
