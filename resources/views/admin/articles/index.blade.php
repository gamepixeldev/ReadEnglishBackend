@extends('admin.layout')
@section('title', '精读文章')
@section('section', '内容管理 / 精读文章')
@section('description', '管理文章正文、解析、重点词汇与发布状态。')
@section('actions')<a class="btn" href="{{ route('admin.articles.create') }}">＋ 新建文章</a>@endsection
@section('content')
<section class="card">
    <form class="filter-bar" method="get" action="{{ route('admin.articles.index') }}"><input class="input search" type="search" name="q" value="{{ $search }}" placeholder="搜索中英文标题"><select class="select" name="status"><option value="">全部状态</option><option value="draft" @selected($status === 'draft')>草稿</option><option value="published" @selected($status === 'published')>已发布</option></select><button class="btn secondary" type="submit">筛选</button>@if($search || $status)<a class="text-link" href="{{ route('admin.articles.index') }}">清空</a>@endif</form>
    @if($articles->isEmpty())<div class="empty-state"><strong>{{ $search || $status ? '没有匹配的文章' : '还没有文章' }}</strong><span>{{ $search || $status ? '试试其他搜索词或状态' : '从新建文章开始添加内容' }}</span>@if(!$search && !$status)<br><a class="btn" href="{{ route('admin.articles.create') }}">新建文章</a>@endif</div>
    @else<div class="table-wrap"><table class="data-table"><thead><tr><th>ID</th><th>文章标题</th><th>级别</th><th>状态</th><th>最后更新</th><th>操作</th></tr></thead><tbody>
        @foreach($articles as $item)<tr><td><span class="badge-id">#{{ $item->id }}</span></td><td><div class="table-primary">{{ $item->translation ?: $item->title }}</div>@if($item->translation)<div class="table-secondary">{{ $item->title }}</div>@endif</td><td>{{ $item->level ?: '—' }}</td><td><span class="status {{ $item->status }}">{{ $item->status === 'published' ? '已发布' : '草稿' }}</span></td><td>{{ $item->updated_at ? \Illuminate\Support\Carbon::parse($item->updated_at, 'UTC')->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s') : '—' }}</td><td class="row-actions"><a class="text-link" href="{{ route('admin.articles.edit', $item->id) }}">编辑</a> <form class="inline-action" method="post" action="{{ $item->status === 'published' ? route('admin.article.unpublish', $item->id) : route('admin.article.publish', $item->id) }}">@csrf<button class="btn small {{ $item->status === 'published' ? 'danger' : 'secondary' }}" type="submit">{{ $item->status === 'published' ? '下架' : '发布' }}</button></form></td></tr>@endforeach
    </tbody></table></div><div class="table-footer"><span>共 {{ $articles->total() }} 篇</span><div class="pager">@if($articles->onFirstPage())<span class="disabled">上一页</span>@else<a href="{{ $articles->previousPageUrl() }}">上一页</a>@endif<span>{{ $articles->currentPage() }} / {{ $articles->lastPage() }}</span>@if($articles->hasMorePages())<a href="{{ $articles->nextPageUrl() }}">下一页</a>@else<span class="disabled">下一页</span>@endif</div></div>@endif
</section>
@endsection
