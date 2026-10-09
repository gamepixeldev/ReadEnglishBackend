<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.wechat_mini.app_id', 'wx-test-app');
        config()->set('services.wechat_mini.app_secret', 'test-secret');
    }

    public function test_wechat_login_creates_one_user_and_reuses_it_on_next_login(): void
    {
        Http::fake(['api.weixin.qq.com/sns/jscode2session*' => Http::response(['openid' => 'openid-123', 'session_key' => 'private-key'])]);

        $first = $this->postJson('/api/v1/auth/wechat', ['code' => 'first-code'])->assertOk()->json();
        $second = $this->postJson('/api/v1/auth/wechat', ['code' => 'second-code'])->assertOk()->json();

        $this->assertSame($first['user']['id'], $second['user']['id']);
        $this->assertSame('微信用户', $first['user']['name']);
        $this->assertNotSame($first['token'], $second['token']);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::table('user_identities')->count());
        $this->assertSame('wx-test-app', DB::table('user_identities')->value('provider_app_id'));
        $this->assertSame(2, DB::table('mobile_access_tokens')->count());
        $this->assertSame(hash('sha256', $first['token']), DB::table('mobile_access_tokens')->orderBy('id')->value('token_hash'));
        $this->assertStringNotContainsString('private-key', json_encode($first));
        $this->assertStringNotContainsString('openid-123', json_encode($first));

        Http::assertSentCount(2);
    }

    public function test_me_and_logout_require_valid_token_and_logout_revokes_it(): void
    {
        Http::fake(['api.weixin.qq.com/sns/jscode2session*' => Http::response(['openid' => 'openid-123'])]);
        $session = $this->postJson('/api/v1/auth/wechat', ['code' => 'code'])->assertOk()->json();
        $headers = ['Authorization' => 'Bearer '.$session['token']];

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/auth/me', $headers)->assertOk()->assertJsonPath('user.id', $session['user']['id']);
        $this->postJson('/api/v1/auth/logout', [], $headers)->assertNoContent();
        $this->getJson('/api/v1/auth/me', $headers)->assertUnauthorized();
    }

    public function test_invalid_code_and_missing_configuration_do_not_create_users(): void
    {
        Http::fake(['api.weixin.qq.com/sns/jscode2session*' => Http::response(['errcode' => 40029, 'errmsg' => 'invalid code'])]);
        $this->postJson('/api/v1/auth/wechat', [])->assertUnprocessable();
        $this->postJson('/api/v1/auth/wechat', ['code' => 'bad'])->assertUnauthorized()
            ->assertJsonPath('error.code', 'WECHAT_LOGIN_FAILED');

        config()->set('services.wechat_mini.app_secret', null);
        $this->postJson('/api/v1/auth/wechat', ['code' => 'code'])->assertStatus(503)
            ->assertJsonPath('error.code', 'LOGIN_NOT_CONFIGURED');
        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_expired_token_is_rejected(): void
    {
        Http::fake(['api.weixin.qq.com/sns/jscode2session*' => Http::response(['openid' => 'openid-123'])]);
        $session = $this->postJson('/api/v1/auth/wechat', ['code' => 'code'])->assertOk()->json();
        DB::table('mobile_access_tokens')->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$session['token']])->assertUnauthorized();
    }
}
