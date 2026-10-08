@extends('admin.layout')
@section('title', '单词管理')
@section('section', '内容管理 / 单词管理')
@section('description', '管理单词资料、详解和发布状态。')
@section('actions')<a class="btn" href="{{ route('admin.words.create') }}">＋ 新建单词</a>@endsection
@section('content')
<section class="card">
    <form class="filter-bar" method="get" action="{{ route('admin.words.index') }}"><input class="input search" type="search" name="q" value="{{ $search }}" placeholder="搜索单词或释义"><select class="select" name="status"><option value="">全部状态</option><option value="draft" @selected($status === 'draft')>草稿</option><option value="published" @selected($status === 'published')>已发布</option></select><button class="btn secondary" type="submit">筛选</button>@if($search || $status)<a class="text-link" href="{{ route('admin.words.index') }}">清空</a>@endif</form>
    @if($words->isEmpty())<div class="empty-state"><strong>{{ $search || $status ? '没有匹配的单词' : '还没有单词' }}</strong><span>{{ $search || $status ? '试试其他搜索词或状态' : '从新建单词开始添加内容' }}</span>@if(!$search && !$status)<br><a class="btn" href="{{ route('admin.words.create') }}">新建单词</a>@endif</div>
    @else<div class="table-wrap"><table class="data-table"><thead><tr><th>ID</th><th>单词</th><th>词性</th><th>释义</th><th>状态</th><th>最后更新</th><th>操作</th></tr></thead><tbody>
        @foreach($words as $item)<tr><td><span class="badge-id">#{{ $item->id }}</span></td><td><div class="table-primary">{{ $item->headword }}</div><div class="table-secondary">{{ $item->ipa }}</div></td><td>{{ $item->part_of_speech ?: '—' }}</td><td>{{ $item->meaning }}</td><td><span class="status {{ $item->status }}">{{ $item->status === 'published' ? '已发布' : '草稿' }}</span></td><td>{{ $item->updated_at ?: '—' }}</td><td class="row-actions"><a class="text-link" href="{{ route('admin.words.edit', $item->id) }}">编辑</a></td></tr>@endforeach
    </tbody></table></div><div class="table-footer"><span>共 {{ $words->total() }} 个</span><div class="pager">@if($words->onFirstPage())<span class="disabled">上一页</span>@else<a href="{{ $words->previousPageUrl() }}">上一页</a>@endif<span>{{ $words->currentPage() }} / {{ $words->lastPage() }}</span>@if($words->hasMorePages())<a href="{{ $words->nextPageUrl() }}">下一页</a>@else<span class="disabled">下一页</span>@endif</div></div>@endif
</section>
@endsection
