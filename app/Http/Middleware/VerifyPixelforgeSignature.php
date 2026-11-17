<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPixelforgeSignature
{
    private const int TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $parts = $this->parse((string) $request->header('Pixelforge-Signature'));
        $timestamp = (int) ($parts['t'] ?? 0);
        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", (string) config('services.pixelforge.webhook_secret'));

        abort_unless(
            hash_equals($expected, $parts['v1'] ?? '') && abs(time() - $timestamp) <= self::TOLERANCE_SECONDS,
            Response::HTTP_BAD_REQUEST,
            'Invalid Pixelforge signature.',
        );

        return $next($request);
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $header): array
    {
        return collect(explode(',', $header))
            ->filter(fn (string $part) => str_contains($part, '='))
            ->mapWithKeys(function (string $part) {
                [$key, $value] = explode('=', trim($part), 2);

                return [$key => $value];
            })
            ->all();
    }
}
