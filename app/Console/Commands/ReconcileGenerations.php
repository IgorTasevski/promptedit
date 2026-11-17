<?php

namespace App\Console\Commands;

use App\Services\GenerationReconciler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reconcile-generations')]
#[Description('Poll Pixelforge for generations whose webhooks have not arrived')]
class ReconcileGenerations extends Command
{
    public function handle(GenerationReconciler $reconciler): int
    {
        $this->info("Reconciled {$reconciler->reconcileStale()} generation(s).");

        return self::SUCCESS;
    }
}
