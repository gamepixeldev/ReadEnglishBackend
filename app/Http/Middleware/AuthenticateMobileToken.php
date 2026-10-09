<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        if (! is_string($plainToken) || ! preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
            return $this->unauthorized();
        }

        $token = DB::table('mobile_access_tokens')
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())->first();
        $user = $token ? User::query()->find($token->user_id) : null;
        if (! $user || $user->is_admin) {
            return $this->unauthorized();
        }

        $request->setUserResolver(fn (): User => $user);
        $request->attributes->set('mobile_token_id', $token->id);
        DB::table('mobile_access_tokens')->where('id', $token->id)->update(['last_used_at' => now()]);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => '请重新登录']], 401);
    }
}
