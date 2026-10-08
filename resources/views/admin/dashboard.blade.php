@extends('admin.layout')
@section('title', '概览')
@section('section', '工作台')
@section('description', '查看内容状态和最近更新。')
@section('content')
<div class="metrics">
    <div class="card metric"><span class="metric-icon">▤</span><div class="metric-label">精读文章</div><div class="metric-value">{{ $articleCount }}</div><div class="metric-note">已发布 {{ $publishedArticles }} 篇</div></div>
    <div class="card metric"><span class="metric-icon">Aa</span><div class="metric-label">单词</div><div class="metric-value">{{ $wordCount }}</div><div class="metric-note">已发布 {{ $publishedWords }} 个</div></div>
    <div class="card metric"><span class="metric-icon">◉</span><div class="metric-label">普通用户</div><div class="metric-value">{{ $userCount }}</div><div class="metric-note">不含管理员</div></div>
    <div class="card metric"><span class="metric-icon">✎</span><div class="metric-label">草稿内容</div><div class="metric-value">{{ ($articleCount - $publishedArticles) + ($wordCount - $publishedWords) }}</div><div class="metric-note">文章和单词待发布</div></div>
</div>
<div class="dashboard-grid">
    <section class="card"><div class="card-header"><h2>最近编辑的文章</h2><a class="text-link" href="{{ route('admin.articles.index') }}">查看全部 →</a></div>
        @if($latestArticles->isEmpty())<div class="empty-state">暂无文章</div>@else<div class="table-wrap"><table class="data-table"><thead><tr><th>文章</th><th>状态</th><th></th></tr></thead><tbody>@foreach($latestArticles as $item)<tr><td><div class="table-primary">{{ $item->translation ?: $item->title }}</div><div class="table-secondary">{{ $item->title }}</div></td><td><span class="status {{ $item->status }}">{{ $item->status === 'published' ? '已发布' : '草稿' }}</span></td><td><a class="text-link" href="{{ route('admin.articles.edit', $item->id) }}">编辑</a></td></tr>@endforeach</tbody></table></div>@endif
    </section>
    <section class="card"><div class="card-header"><h2>最近编辑的单词</h2><a class="text-link" href="{{ route('admin.words.index') }}">查看全部 →</a></div>
        @if($latestWords->isEmpty())<div class="empty-state">暂无单词</div>@else<div class="table-wrap"><table class="data-table"><thead><tr><th>单词</th><th>状态</th><th></th></tr></thead><tbody>@foreach($latestWords as $item)<tr><td><div class="table-primary">{{ $item->headword }}</div><div class="table-secondary">{{ $item->meaning }}</div></td><td><span class="status {{ $item->status }}">{{ $item->status === 'published' ? '已发布' : '草稿' }}</span></td><td><a class="text-link" href="{{ route('admin.words.edit', $item->id) }}">编辑</a></td></tr>@endforeach</tbody></table></div>@endif
    </section>
</div>
@endsection
