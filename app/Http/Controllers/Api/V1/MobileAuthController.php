<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MobileAuthController extends Controller
{
    public function wechat(Request $request)
    {
        $input = $request->validate(['code' => ['required', 'string', 'max:256']]);
        $appId = (string) config('services.wechat_mini.app_id');
        $secret = (string) config('services.wechat_mini.app_secret');

        if ($appId === '' || $secret === '') {
            return $this->error('LOGIN_NOT_CONFIGURED', '微信登录尚未配置', 503);
        }

        try {
            $response = Http::connectTimeout(3)->timeout(8)->get('https://api.weixin.qq.com/sns/jscode2session', [
                'appid' => $appId,
                'secret' => $secret,
                'js_code' => $input['code'],
                'grant_type' => 'authorization_code',
            ]);
        } catch (ConnectionException $exception) {
            return $this->error('WECHAT_UNAVAILABLE', '微信服务暂时不可用，请稍后重试', 502);
        }

        $result = $response->json();
        $openId = is_array($result) ? ($result['openid'] ?? null) : null;
        if (! $response->successful() || ! is_string($openId) || $openId === '' || strlen($openId) > 128) {
            return $this->error('WECHAT_LOGIN_FAILED', '微信登录失败，请重试', 401);
        }

        $user = DB::transaction(function () use ($appId, $openId): User {
            $identity = UserIdentity::query()->where('provider', 'wechat_mini')
                ->where('provider_app_id', $appId)
                ->where('provider_subject', $openId)->first();
            if ($identity) {
                return $identity->user;
            }

            $user = User::query()->create(['is_admin' => false]);
            UserIdentity::query()->create([
                'user_id' => $user->id,
                'provider' => 'wechat_mini',
                'provider_app_id' => $appId,
                'provider_subject' => $openId,
            ]);

            return $user;
        });

        if ($user->is_admin) {
            return $this->error('ACCOUNT_UNAVAILABLE', '此账号无法用于小程序登录', 403);
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = now()->addDays(30);
        DB::table('mobile_access_tokens')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'token' => $token,
            'expiresAt' => $expiresAt->toIso8601String(),
            'user' => $this->publicUser($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->publicUser($request->user())]);
    }

    public function updateProfile(Request $request)
    {
        $input = $request->validate(['name' => ['required', 'string', 'max:24']]);
        $name = trim($input['name']);
        if ($name === '') {
            return $this->error('INVALID_NICKNAME', '请输入昵称', 422);
        }

        $user = $request->user();
        $user->update(['name' => $name]);

        return response()->json(['user' => $this->publicUser($user)]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $path = $request->file('avatar')->store('avatars', 'public');
        if (! $path) {
            return $this->error('AVATAR_UPLOAD_FAILED', '头像上传失败，请重试', 500);
        }

        $user = $request->user();
        $user->update(['avatar_url' => Storage::disk('public')->url($path)]);

        return response()->json(['user' => $this->publicUser($user)]);
    }

    public function logout(Request $request)
    {
        DB::table('mobile_access_tokens')->where('id', $request->attributes->get('mobile_token_id'))->delete();

        return response()->noContent();
    }

    private function publicUser(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'avatarUrl' => $user->avatar_url];
    }

    private function error(string $code, string $message, int $status)
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
