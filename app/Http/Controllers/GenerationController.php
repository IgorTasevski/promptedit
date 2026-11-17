<?php

namespace App\Http\Controllers;

use App\Enums\Quality;
use App\Enums\Size;
use App\Http\Requests\GenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Services\GenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function __construct(private readonly GenerationService $generations) {}

    public function index(Request $request): Response
    {
        return Inertia::render('generations/Index', [
            'generations' => GenerationResource::collection($this->generations->history($request->user()))->resolve(),
        ]);
    }

    public function show(Generation $generation): Response
    {
        return Inertia::render('generations/Show', [
            'generation' => GenerationResource::make($generation)->resolve(),
            'breakdown' => $this->generations->costBreakdown($generation),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('generations/Create', [
            'sizes' => Size::options(),
            'qualities' => Quality::options(),
            'recent' => GenerationResource::collection($this->generations->recent($request->user()))->resolve(),
        ]);
    }

    public function store(GenerationRequest $request): RedirectResponse
    {
        $this->generations->create(
            $request->user(),
            $request->validated('prompt'),
            $request->enum('size', Size::class),
            $request->enum('quality', Quality::class),
            $request->referenceImages(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Image queued. It updates here as it renders.']);

        return to_route('generations.index');
    }

    public function retry(Generation $generation): RedirectResponse
    {
        $retry = $this->generations->retry($generation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Trying again with the same settings.']);

        return to_route('generations.show', $retry);
    }

    public function estimate(GenerationRequest $request): JsonResponse
    {
        return response()->json($this->generations->estimate(
            $request->validated('prompt'),
            $request->enum('size', Size::class),
            $request->enum('quality', Quality::class),
            $request->referenceImages(),
        ));
    }
}
