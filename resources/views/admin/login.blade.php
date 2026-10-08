<!doctype html>
<html lang="zh-CN">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>管理员登录 · 精读英语</title><link rel="stylesheet" href="{{ asset('admin-assets/admin.css') }}"><style>body{min-height:100vh;display:grid;place-items:center;background:#f4f6fb}.login-card{width:min(410px,calc(100vw - 30px));padding:35px;background:#fff;border:1px solid #e6eaf1;border-radius:12px;box-shadow:0 18px 55px #1d2f5212}.login-brand{display:flex;align-items:center;gap:10px;font-size:16px;font-weight:750;color:#1c2940}.login-card h1{font-size:23px;margin:35px 0 5px}.login-card p{color:#8793a4;margin:0 0 25px}.login-card .form-field{margin-bottom:17px}.login-card .input{height:44px}.login-card .btn{width:100%;height:44px;margin-top:7px}.login-foot{text-align:center;color:#a1acba;font-size:12px;margin-top:25px}</style></head>
<body><main class="login-card"><div class="login-brand"><span class="brand-symbol">E</span>精读英语管理后台</div><h1>登录账号</h1><p>输入管理员账号和密码</p>
    @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('admin.login.submit') }}">@csrf
        <div class="form-field"><label for="email">管理员账号</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
        <div class="form-field"><label for="password">密码</label><input class="input" id="password" name="password" type="password" autocomplete="current-password" required></div>
        <button class="btn" type="submit">登录</button>
    </form><div class="login-foot">精读英语 · 内容管理</div>
</main></body></html>
