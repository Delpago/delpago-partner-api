<?php

namespace App\Http\Middleware;

use App\Models\PartnerApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stateless Bearer-token auth for the Partner API. NOT a session guard.
 * Usage: ->middleware('partner:charges.write')  (scope optional).
 * Sets request attributes: partner, partner_key.
 */
class AuthenticatePartner
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $token = $this->bearer($request);
        if (! $token) {
            return $this->err('unauthorized', 'missing_api_key', 'Provide an API key as a Bearer token.', 401);
        }

        $key = PartnerApiKey::resolve($token);
        if (! $key) {
            return $this->err('unauthorized', 'invalid_api_key', 'The API key is invalid or revoked.', 401);
        }

        $partner = $key->partner;
        if (! $partner || ! $partner->isActive()) {
            return $this->err('unauthorized', 'partner_inactive', 'This partner account is not active.', 403);
        }

        if ($scope && ! $key->hasScope($scope)) {
            return $this->err('forbidden', 'insufficient_scope', "This key lacks the '{$scope}' scope.", 403);
        }

        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('partner', $partner);
        $request->attributes->set('partner_key', $key);

        return $next($request);
    }

    protected function bearer(Request $request): ?string
    {
        $h = $request->header('Authorization', '');
        if (str_starts_with($h, 'Bearer ')) return trim(substr($h, 7));
        return null;
    }

    protected function err(string $type, string $code, string $message, int $status): Response
    {
        return response()->json(['error' => compact('type', 'code', 'message')], $status);
    }
}
