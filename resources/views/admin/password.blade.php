@extends('admin.layout')
@section('title', '修改密码')
@section('section', '账号 / 修改密码')
@section('description', '更新当前管理员的登录密码。')
@section('content')
<section class="card" style="max-width:650px"><div class="card-header"><div><h2>登录密码</h2><p>新密码至少 16 位。</p></div></div><div class="card-body">
    <form method="post" action="{{ route('admin.password.update') }}">@csrf
        <div class="form-stack">
            <div class="form-field"><label for="current_password">当前密码</label><input class="input" id="current_password" type="password" name="current_password" autocomplete="current-password" required></div>
            <div class="form-field"><label for="password">新密码</label><input class="input" id="password" type="password" name="password" autocomplete="new-password" minlength="16" required></div>
            <div class="form-field"><label for="password_confirmation">确认新密码</label><input class="input" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="16" required></div>
        </div><div class="section-actions"><button class="btn" type="submit">保存新密码</button></div>
    </form>
</div></section>
@endsection
