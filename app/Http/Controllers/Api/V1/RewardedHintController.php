<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RewardedHintSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardedHintController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        return response()->json(RewardedHintSettings::forUser($this->mobileUser($request)));
    }

    private function mobileUser(Request $request): ?User
    {
        $plainToken = $request->bearerToken();
        if (! is_string($plainToken) || ! preg_match('/^[a-f0-9]{64}$/', $plainToken)) return null;

        $token = DB::table('mobile_access_tokens')->where('token_hash', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())->first();
        $user = $token ? User::query()->find($token->user_id) : null;
        return $user && ! $user->is_admin ? $user : null;
    }
}
