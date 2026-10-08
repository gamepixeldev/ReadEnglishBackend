<!doctype html>
<html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>修改密码 · 精读英语</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f5f2ec;color:#283b34;font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{max-width:560px;margin:64px auto;padding:24px}.card{background:#fff;border:1px solid #e8e5dd;border-radius:20px;padding:28px}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px}.top a{color:#398563;text-decoration:none}.field{display:grid;gap:7px;margin:16px 0}.field input{height:46px;border:1px solid #dedfd9;border-radius:10px;padding:0 12px;font:inherit}.btn{border:0;border-radius:10px;background:#388b68;color:white;font:inherit;font-weight:700;padding:12px 18px;cursor:pointer}.notice{padding:10px;background:#e8f6ed;border-radius:10px;color:#29744f}.err{color:#a44}.muted{color:#89928d}</style></head>
<body><main class="wrap"><div class="top"><strong>精读英语 · 内容管理</strong><a href="{{ route('admin.content') }}">返回后台</a></div><section class="card"><h1>修改管理员密码</h1><p class="muted">密码至少 16 位，建议使用密码管理器保存。</p>
@if(session('success'))<p class="notice">{{ session('success') }}</p>@endif
@if($errors->any())<p class="err">{{ $errors->first() }}</p>@endif
<form method="post" action="{{ route('admin.password.update') }}">@csrf
<div class="field"><label>当前密码</label><input type="password" name="current_password" autocomplete="current-password" required></div>
<div class="field"><label>新密码</label><input type="password" name="password" autocomplete="new-password" minlength="16" required></div>
<div class="field"><label>确认新密码</label><input type="password" name="password_confirmation" autocomplete="new-password" minlength="16" required></div>
<button class="btn">更新密码</button></form></section></main></body></html>
