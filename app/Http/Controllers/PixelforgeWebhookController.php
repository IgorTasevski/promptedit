<?php

namespace App\Http\Controllers;

use App\Http\Requests\PixelforgeWebhookRequest;
use App\Services\WebhookProcessor;
use Illuminate\Http\Response;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(PixelforgeWebhookRequest $request, WebhookProcessor $processor): Response
    {
        $processor->process($request->string('id')->value(), $request->string('type')->value(), $request->generation());

        return response()->noContent();
    }
}
