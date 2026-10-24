<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.webhook.secret', '');
        $provided = (string) $request->header('X-Webhook-Token', '');

        if ($secret === '' || hash_equals($secret, $provided) === false) {
            return new JsonResponse(['message' => 'Invalid webhook token.'], 401);
        }

        return $next($request);
    }
}
