<?php

namespace App\Http\Middleware;

use App\Models\BoothToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine-to-machine authenticatie voor de booth-API.
 * Leest "Authorization: Bearer <token>", zoekt het niet-ingetrokken booth_token op de
 * sha256-hash, werkt last_used_at bij en zet het model op de request. Geen login-redirect.
 */
class AuthenticateBooth
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if (! $plain) {
            return $this->unauthorized();
        }

        $hash  = BoothToken::hashToken($plain);
        $token = BoothToken::whereNull('revoked_at')
            ->where('token', $hash)
            ->first();

        // Timing-veilige vergelijking als extra check bovenop de hash-lookup.
        if (! $token || ! hash_equals($token->token, $hash)) {
            return $this->unauthorized();
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('booth', $token);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['message' => 'Ongeldig of ontbrekend booth-token.'], 401);
    }
}
