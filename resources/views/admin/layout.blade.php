<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '管理后台') · 精读英语</title>
    <link rel="stylesheet" href="{{ asset('admin-assets/admin.css') }}">
    @stack('head')
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}"><span class="brand-symbol">E</span><span><strong>精读英语</strong><small>管理后台</small></span></a>
        <div class="nav-section">工作台</div>
        <nav class="side-nav">
            <a class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span class="nav-icon">▦</span>概览</a>
        </nav>
        <div class="nav-section">内容管理</div>
        <nav class="side-nav">
            <a class="nav-item {{ request()->routeIs('admin.articles.*') ? 'active' : '' }}" href="{{ route('admin.articles.index') }}"><span class="nav-icon">▤</span>精读文章</a>
            <a class="nav-item {{ request()->routeIs('admin.words.*') ? 'active' : '' }}" href="{{ route('admin.words.index') }}"><span class="nav-icon">Aa</span>单词管理</a>
        </nav>
        <div class="nav-section">账号</div>
        <nav class="side-nav"><a class="nav-item {{ request()->routeIs('admin.password') ? 'active' : '' }}" href="{{ route('admin.password') }}"><span class="nav-icon">⚙</span>修改密码</a></nav>
        <div class="sidebar-bottom"><span class="online-dot"></span> 管理服务运行中</div>
    </aside>
    <div class="admin-content">
        <header class="admin-topbar">
            <button class="menu-toggle" type="button" aria-label="打开导航" onclick="document.body.classList.toggle('sidebar-open')">☰</button>
            <div class="topbar-location">精读英语 <span>/</span> @yield('section', '概览')</div>
            <div class="topbar-user"><span class="user-avatar">{{ mb_substr(auth()->user()->name ?: 'A', 0, 1) }}</span><span class="user-name">{{ auth()->user()->name ?: auth()->user()->email }}</span><form method="post" action="{{ route('admin.logout') }}">@csrf<button type="submit" class="logout-link">退出</button></form></div>
        </header>
        <main class="admin-main">
            <div class="page-heading"><div><p class="eyebrow">@yield('section', '工作台')</p><h1>@yield('title', '概览')</h1><p class="page-description">@yield('description')</p></div><div class="heading-actions">@yield('actions')</div></div>
            @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert error"><strong>请检查以下内容</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
<div class="sidebar-backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>
@stack('scripts')
</body>
</html>
