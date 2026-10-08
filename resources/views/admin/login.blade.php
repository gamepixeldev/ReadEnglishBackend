<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>管理员登录 · 精读英语</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f2ec;color:#283b34;font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}.login{width:min(420px,calc(100% - 32px));background:#fff;border:1px solid #e8e5dd;border-radius:22px;padding:36px;box-shadow:0 16px 50px #28473712}.brand{font-size:13px;letter-spacing:.16em;color:#54816e;font-weight:700}.mark{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:#e3f2e9;color:#2d8962;font-size:24px;margin:20px 0}.login h1{font-size:25px;margin:0 0 6px}.muted{color:#89928d;margin:0 0 24px}.field{display:grid;gap:7px;margin:16px 0}.field label{font-size:13px;font-weight:650}.field input{width:100%;height:46px;border:1px solid #dedfd9;border-radius:11px;padding:0 13px;font:inherit;outline:none}.field input:focus{border-color:#56a47e;box-shadow:0 0 0 3px #56a47e20}.button{height:46px;width:100%;border:0;border-radius:11px;background:#388b68;color:#fff;font:inherit;font-weight:700;cursor:pointer;margin-top:8px}.error{padding:10px 12px;border-radius:10px;background:#fff0ef;color:#ae5148;font-size:13px}.foot{margin-top:22px;text-align:center;color:#a0a7a2;font-size:12px}
    </style>
</head>
<body>
<main class="login">
    <div class="brand">READING ENGLISH · ADMIN</div><div class="mark">阅</div>
    <h1>欢迎回来</h1><p class="muted">登录内容管理后台</p>
    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('admin.login.submit') }}">@csrf
        <div class="field"><label for="email">管理员账号</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
        <div class="field"><label for="password">密码</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
        <button class="button" type="submit">登录后台</button>
    </form>
    <div class="foot">此页面仅供内容管理员使用</div>
</main>
</body>
</html>
