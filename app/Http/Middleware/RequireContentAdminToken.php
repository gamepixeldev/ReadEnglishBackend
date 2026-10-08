<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireContentAdminToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('content-admin.token', '');
        if ($expected === '') {
            return response()->json([
                'error' => ['code' => 'ADMIN_NOT_CONFIGURED', 'message' => '内容管理接口尚未配置管理员令牌'],
            ], 503);
        }

        $provided = (string) $request->bearerToken();
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => '管理员身份验证失败'],
            ], 401);
        }

        return $next($request);
    }
}
