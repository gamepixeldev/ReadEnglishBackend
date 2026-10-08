@extends('admin.layout')
@section('title', $article ? '编辑文章' : '新建文章')
@section('section', '内容管理 / 精读文章')
@section('description', $article ? '文章 ID #'.$article->id.' · 更新正文、解析与重点词汇' : '先保存文章，再添加解析和重点词汇。')
@section('actions')<a class="btn secondary" href="{{ route('admin.articles.index') }}">← 返回列表</a>@endsection
@section('content')
@php
    $editing = (bool) $article;
    $paragraphRows = old('paragraphs', $editing ? $article->body : [['sentences' => [''], 'translation' => '']]);
    $analysis = $editing ? $article->analysis : null;
    $analysisType = old('type', $analysis->type ?? 'richtext');
    $analysisHtml = app(\App\Services\HtmlContentSanitizer::class)->clean((string) old('html', $analysis->html ?? ''));
    $orderedWords = $words->sortBy(function ($item) use ($article) {
        if (! $article) return 10000;
        $position = array_search((int) $item->id, $article->wordIds, true);
        return $position === false ? 10000 : $position;
    });
@endphp
<div class="split-panel"><div class="form-stack">
    <section class="card"><div class="card-header"><div><h2>基本信息与正文</h2><p>文章标题、内容和版权来源</p></div></div><div class="card-body">
        <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.article.update', $article->id) : route('admin.article.create') }}" id="article-form">@csrf @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <div class="form-field"><label for="title">英文标题 *</label><input class="input" id="title" name="title" value="{{ old('paragraphs') !== null ? old('title', $article->title ?? '') : ($article->title ?? '') }}" required></div>
                <div class="form-field"><label for="translation">中文标题</label><input class="input" id="translation" name="translation" value="{{ old('translation', $article->translation ?? '') }}"></div>
                <div class="form-field"><label for="level">阅读级别</label><input class="input" id="level" name="level" value="{{ old('level', $article->level ?? '') }}" placeholder="如：初级"></div>
                <div class="form-field"><label for="minutes">预计阅读分钟数</label><input class="input" id="minutes" name="minutes" type="number" min="1" value="{{ old('minutes', $article->minutes ?? '') }}"></div>
                <div class="form-field"><label for="slug">内容标识 slug</label><input class="input" id="slug" name="slug" value="{{ old('slug', $article->slug ?? '') }}"></div>
            </div>
            <div class="article-images">
                <div class="form-field"><label for="coverImage">方形封面图</label><div class="cover-upload"><div class="cover-preview square" aria-label="封面预览"><img id="cover-preview-image" src="{{ $coverUrl ?? '' }}" alt="封面预览" @if(!$coverUrl) hidden @endif><span id="cover-preview-empty" @if($coverUrl) hidden @endif>暂无封面</span></div><div class="cover-upload-controls"><input id="coverImage" name="coverImage" type="file" accept="image/jpeg,image/png,image/webp"><p class="form-hint">用于首页和文章列表，建议 1:1 方图。JPG、PNG、WebP，最大 4 MB。</p></div></div></div>
                <div class="form-field"><label for="detailImage">详情横图</label><div class="cover-upload"><div class="cover-preview landscape" aria-label="详情图片预览"><img id="detail-preview-image" src="{{ $detailImageUrl ?? '' }}" alt="详情图片预览" @if(!$detailImageUrl) hidden @endif><span id="detail-preview-empty" @if($detailImageUrl) hidden @endif>暂无详情图</span></div><div class="cover-upload-controls"><input id="detailImage" name="detailImage" type="file" accept="image/jpeg,image/png,image/webp"><p class="form-hint">用于文章详情页，建议 16:9 横图。JPG、PNG、WebP，最大 4 MB。</p></div></div></div>
            </div>
            <div class="card-header" style="padding:22px 0 14px;margin-top:14px"><div><h2>文章正文</h2><p>每段英文按句换行，可填写整段中文翻译。</p></div><button class="btn secondary small" type="button" id="add-paragraph">＋ 添加段落</button></div>
            <div id="paragraphs">@foreach($paragraphRows as $index => $paragraph)
                <div class="paragraph"><div class="paragraph-head"><span>第 {{ $index + 1 }} 段</span><button class="ghost remove-paragraph" type="button">移除</button></div><div class="form-grid"><div class="form-field wide"><label>英文句子（一行一句） *</label><textarea class="textarea" data-field="sentences" name="paragraphs[{{ $index }}][sentences]" required>{{ is_array($paragraph['sentences'] ?? null) ? implode("\n", $paragraph['sentences']) : ($paragraph['sentences'] ?? '') }}</textarea></div><div class="form-field wide"><label>中文翻译 *</label><textarea class="textarea" data-field="translation" name="paragraphs[{{ $index }}][translation]" required>{{ $paragraph['translation'] ?? '' }}</textarea></div></div></div>
            @endforeach</div>
            <div class="form-grid" style="margin-top:18px"><div class="form-field"><label for="rightsType">版权来源类型</label><input class="input" id="rightsType" name="rightsType" value="{{ old('rightsType', $article->rights_type ?? '') }}" placeholder="如：原创、已授权、公版"></div><div class="form-field wide"><label for="rightsNote">版权与来源说明</label><textarea class="textarea" id="rightsNote" name="rightsNote">{{ old('rightsNote', $article->rights_note ?? '') }}</textarea></div></div>
            <div class="section-actions"><button class="btn" type="submit">保存文章</button><span class="helper">保存后可继续添加解析与重点词汇。</span></div>
        </form>
    </div></section>
    @if($editing)
    <section class="card"><div class="card-header"><div><h2>本文语法解析</h2><p>可填写富文本，也可关联公众号文章。</p></div></div><div class="card-body">
        <form method="post" action="{{ route('admin.article.analysis', $article->id) }}" class="rich-form">@csrf @method('PUT')
            <div class="form-field"><label for="analysis-type">内容形式</label><select class="select content-type" id="analysis-type" name="type"><option value="richtext" @selected($analysisType === 'richtext')>富文本内容</option><option value="officialAccount" @selected($analysisType === 'officialAccount')>公众号文章链接</option></select></div>
            <div class="form-field type-rich" style="margin-top:17px"><label>解析正文</label><div class="toolbar"><button type="button" data-command="bold"><b>粗体</b></button><button type="button" data-command="italic"><i>斜体</i></button><button type="button" data-command="insertUnorderedList">列表</button><button type="button" data-command="formatBlock" data-value="h3">小标题</button><button type="button" data-command="removeFormat">清除格式</button></div><div class="rich-editor" contenteditable="true" data-placeholder="在此编写文章语法解析…">{!! $analysisHtml !!}</div><textarea hidden class="rich-value" name="html">{{ $analysisHtml }}</textarea></div>
            <div class="form-grid type-official" style="margin-top:17px"><div class="form-field wide"><label>公众号文章链接</label><input class="input" type="url" name="url" value="{{ old('url', $analysis->url ?? '') }}"></div><div class="form-field"><label>文章标题</label><input class="input" name="title" value="{{ old('type') !== null ? old('title', $analysis->title ?? '') : ($analysis->title ?? '') }}"></div><div class="form-field"><label>摘要</label><input class="input" name="summary" value="{{ old('summary', $analysis->summary ?? '') }}"></div></div>
            <div class="section-actions"><button class="btn" type="submit">保存解析</button></div>
        </form>
    </div></section>
    <section class="card"><div class="card-header"><div><h2>重点词汇</h2><p>选取本篇文章的重点词汇。</p></div><span class="helper">已选 {{ count($article->wordIds) }} 个</span></div><div class="card-body">
        <form method="post" action="{{ route('admin.article.words', $article->id) }}">@csrf @method('PUT')
            <input class="input picker-search" type="search" id="word-filter" placeholder="筛选单词或释义" aria-label="筛选单词">
            <div class="word-picker" id="word-picker">
                @foreach($orderedWords as $availableWord)
                    <label class="word-option" data-search="{{ mb_strtolower($availableWord->headword.' '.$availableWord->meaning) }}"><input type="checkbox" name="wordIds[]" value="{{ $availableWord->id }}" @checked(in_array($availableWord->id, $article->wordIds))><span>{{ $availableWord->headword }} <small>{{ $availableWord->meaning }}</small></span></label>
                @endforeach
            </div>
            <div class="section-actions"><button class="btn" type="submit">保存重点词汇</button><a class="text-link" href="{{ route('admin.words.create') }}">＋ 新建单词</a></div>
        </form>
    </div></section>
    @endif
</div><aside class="form-stack">
    <section class="card"><div class="card-header"><h2>发布状态</h2></div><div class="card-body">@if($editing)<p><span class="status {{ $article->status }}">{{ $article->status === 'published' ? '已发布' : '草稿' }}</span></p><p class="helper">发布后，文章会出现在学习端。</p>
        @if($article->status === 'published')<form method="post" action="{{ route('admin.article.unpublish', $article->id) }}">@csrf<button class="btn danger" type="submit">下架文章</button></form>@else<form method="post" action="{{ route('admin.article.publish', $article->id) }}">@csrf<button class="btn" type="submit">发布文章</button></form>@endif
        @else<p class="helper">新文章保存后默认为草稿。</p>@endif</div></section>
    @if($editing)<section class="card"><div class="card-header"><h2>文章信息</h2></div><div class="card-body"><div class="helper">ID：#{{ $article->id }}</div><div class="helper" style="margin-top:8px">最后更新：{{ $article->updated_at }}</div></div></section>@endif
</aside></div>
@endsection
@push('head')<link rel="stylesheet" href="{{ asset('admin-assets/article-images.css') }}">@endpush
@push('scripts')<script src="{{ asset('admin-assets/editor.js') }}" defer></script>@endpush
