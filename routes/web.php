<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'create')->name('dashboard');

    Route::get('create', [GenerationController::class, 'create'])->name('generations.create');
    Route::get('library', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('library/{generation}', [GenerationController::class, 'show'])->can('view', 'generation')->name('generations.show');
    Route::post('library/{generation}/retry', [GenerationController::class, 'retry'])->can('retry', 'generation')->name('generations.retry');
    Route::post('generations', [GenerationController::class, 'store'])->name('generations.store');
    Route::post('generations/estimate', [GenerationController::class, 'estimate'])->name('generations.estimate');

    Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('credits', [CreditController::class, 'store'])->name('credits.store');

    Route::get('admin/report', ReportController::class)->middleware('can:viewAdminReport')->name('admin.report');
});

require __DIR__.'/settings.php';
