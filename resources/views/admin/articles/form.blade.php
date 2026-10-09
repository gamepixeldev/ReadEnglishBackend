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
    $analysisFormat = old('format', $analysis->format ?? 'html');
    $analysisContent = old('html', $analysis->html ?? '');
    $analysisHtml = $analysisFormat === 'html' ? app(\App\Services\HtmlContentSanitizer::class)->clean((string) $analysisContent) : '';
    $analysisMarkdown = $analysisFormat === 'markdown' ? (string) $analysisContent : '';
@endphp
<div class="split-panel"><div class="form-stack">
    <section class="card"><div class="card-header"><div><h2>基本信息与正文</h2><p>文章标题、内容和版权来源</p></div></div><div class="card-body">
        <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.article.update', $article->id) : route('admin.article.create') }}" id="article-form">@csrf @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <div class="form-field"><label for="title">英文标题 <span class="required-mark">*</span></label><input class="input" id="title" name="title" value="{{ old('paragraphs') !== null ? old('title', $article->title ?? '') : ($article->title ?? '') }}" required></div>
                <div class="form-field"><label for="translation">中文标题</label><input class="input" id="translation" name="translation" value="{{ old('translation', $article->translation ?? '') }}"></div>
                <div class="form-field"><label for="level">阅读级别</label><input class="input" id="level" name="level" value="{{ old('level', $article->level ?? '') }}" placeholder="如：初级"></div>
                <div class="form-field"><label for="minutes">预计阅读分钟数</label><input class="input" id="minutes" name="minutes" type="number" min="1" value="{{ old('minutes', $article->minutes ?? '') }}"></div>
                <div class="form-field"><label for="slug">内容标识 slug</label><input class="input" id="slug" name="slug" value="{{ old('slug', $article->slug ?? '') }}"></div>
            </div>
            <div class="article-images">
                <div class="form-field"><label for="coverImage">方形封面图</label><div class="cover-upload"><div class="cover-preview square" aria-label="封面预览"><img id="cover-preview-image" src="{{ $coverUrl ?? '' }}" alt="封面预览" @if(!$coverUrl) hidden @endif><span id="cover-preview-empty" @if($coverUrl) hidden @endif>暂无封面</span></div><div class="cover-upload-controls"><input id="coverImage" name="coverImage" type="file" accept="image/jpeg,image/png,image/webp"><p class="form-hint">用于首页和文章列表，建议 1:1 方图。JPG、PNG、WebP，最大 4 MB。</p></div></div></div>
                <div class="form-field"><label for="detailImage">详情横图</label><div class="cover-upload"><div class="cover-preview landscape" aria-label="详情图片预览"><img id="detail-preview-image" src="{{ $detailImageUrl ?? '' }}" alt="详情图片预览" @if(!$detailImageUrl) hidden @endif><span id="detail-preview-empty" @if($detailImageUrl) hidden @endif>暂无详情图</span></div><div class="cover-upload-controls"><input id="detailImage" name="detailImage" type="file" accept="image/jpeg,image/png,image/webp"><p class="form-hint">用于文章详情页，建议 16:9 横图。JPG、PNG、WebP，最大 4 MB。</p></div></div></div>
            </div>
            <div class="card-header" style="padding:22px 0 14px;margin-top:14px"><div><h2>文章正文</h2><p>每段英文按句换行，可填写整段中文翻译。</p></div></div>
            <div id="paragraphs">@foreach($paragraphRows as $index => $paragraph)
                <div class="paragraph"><div class="paragraph-head"><span>第 {{ $index + 1 }} 段</span><button class="ghost remove-paragraph" type="button">移除</button></div><div class="form-grid"><div class="form-field wide"><label>英文句子（一行一句） <span class="required-mark">*</span></label><textarea class="textarea" data-field="sentences" name="paragraphs[{{ $index }}][sentences]" required>{{ is_array($paragraph['sentences'] ?? null) ? implode("\n", $paragraph['sentences']) : ($paragraph['sentences'] ?? '') }}</textarea></div><div class="form-field wide"><label>中文翻译 <span class="required-mark">*</span></label><textarea class="textarea" data-field="translation" name="paragraphs[{{ $index }}][translation]" required>{{ $paragraph['translation'] ?? '' }}</textarea></div></div></div>
            @endforeach</div>
            <div class="paragraph-add"><button class="btn secondary" type="button" id="add-paragraph">＋ 添加段落</button></div>
            <div class="form-grid" style="margin-top:18px"><div class="form-field"><label for="rightsType">版权来源类型 <span class="required-mark">*</span></label><select class="select" id="rightsType" name="rightsType"><option value="">请选择</option><option value="original" @selected(old('rightsType', $article->rights_type ?? '') === 'original')>原创</option><option value="authorized" @selected(old('rightsType', $article->rights_type ?? '') === 'authorized')>已获授权</option><option value="public_domain" @selected(old('rightsType', $article->rights_type ?? '') === 'public_domain')>公版作品</option><option value="excerpt" @selected(old('rightsType', $article->rights_type ?? '') === 'excerpt')>摘编 / 节选</option><option value="other" @selected(old('rightsType', $article->rights_type ?? '') === 'other')>其他</option></select><p class="form-hint">发布文章前需填写版权说明。</p></div><div class="form-field wide"><label for="rightsNote">版权与来源说明 <span class="required-mark">*</span></label><textarea class="textarea" id="rightsNote" name="rightsNote">{{ old('rightsNote', $article->rights_note ?? '') }}</textarea><p class="form-hint">填写作品来源、授权信息或公版依据，发布前必填。</p></div></div>
            <div class="section-actions"><button class="btn" type="submit">保存文章</button><span class="helper">保存后可继续添加解析与重点词汇。</span></div>
        </form>
    </div></section>
    @if($editing)
    <section class="card"><div class="card-header"><div><h2>本文语法解析</h2><p>可填写富文本，也可关联公众号文章。</p></div></div><div class="card-body">
        <form method="post" action="{{ route('admin.article.analysis', $article->id) }}" class="rich-form">@csrf @method('PUT')
            <div class="form-field"><label for="analysis-type">内容形式</label><select class="select content-type" id="analysis-type" name="type"><option value="richtext" @selected($analysisType === 'richtext')>富文本内容</option><option value="officialAccount" @selected($analysisType === 'officialAccount')>公众号文章链接</option></select></div>
            <input type="hidden" name="format" value="{{ $analysisFormat }}" class="analysis-format-value">
            <div class="form-field type-rich" style="margin-top:17px"><label>解析正文 <span class="required-mark">*</span></label><div class="format-switch"><button type="button" class="format-option" data-format="html">富文本编辑</button><button type="button" class="format-option" data-format="markdown">Markdown</button></div><div class="toolbar html-editor-tools"><button type="button" data-command="bold"><b>粗体</b></button><button type="button" data-command="italic"><i>斜体</i></button><button type="button" data-command="insertUnorderedList">列表</button><button type="button" data-command="formatBlock" data-value="h3">小标题</button><button type="button" data-command="removeFormat">清除格式</button></div><div class="rich-editor" contenteditable="true" data-placeholder="在此编写文章语法解析…">{!! $analysisHtml !!}</div><textarea class="textarea markdown-editor" data-placeholder="粘贴 Markdown 内容…">{{ $analysisMarkdown }}</textarea><textarea hidden class="rich-value" name="html">{{ $analysisFormat === 'markdown' ? $analysisMarkdown : $analysisHtml }}</textarea><p class="form-hint">Markdown 会转换成前台可显示的富文本；前台阅读页面支持。</p></div>
            <div class="form-grid type-official" style="margin-top:17px"><div class="form-field wide"><label>公众号文章链接</label><input class="input" type="url" name="url" value="{{ old('url', $analysis->url ?? '') }}"></div><div class="form-field"><label>文章标题</label><input class="input" name="title" value="{{ old('type') !== null ? old('title', $analysis->title ?? '') : ($analysis->title ?? '') }}"></div><div class="form-field"><label>摘要</label><input class="input" name="summary" value="{{ old('summary', $analysis->summary ?? '') }}"></div></div>
            <div class="section-actions"><button class="btn" type="submit">保存解析</button></div>
        </form>
    </div></section>
    <section class="card"><div class="card-header"><div><h2>重点词汇</h2><p>搜索单词后添加到本文。</p></div><span class="helper" id="selected-word-count">已选 {{ count($selectedWords) }} 个</span></div><div class="card-body">
        <form method="post" action="{{ route('admin.article.words', $article->id) }}" id="article-words-form">@csrf @method('PUT')
            <div class="selected-words" id="selected-words">@foreach($selectedWords as $selectedWord)<span class="selected-word" data-id="{{ $selectedWord->id }}"><span>{{ $selectedWord->headword }} <small>{{ $selectedWord->meaning }}</small>@if($selectedWord->status !== 'published') <em>草稿</em>@endif</span><button type="button" aria-label="移除 {{ $selectedWord->headword }}" data-remove-word="{{ $selectedWord->id }}">×</button></span>@endforeach</div>
            <div id="selected-word-inputs">@foreach($selectedWords as $selectedWord)<input type="hidden" name="wordIds[]" value="{{ $selectedWord->id }}">@endforeach</div>
            <p class="form-hint draft-word-hint" @if(!$selectedWords->contains(fn ($word) => $word->status !== 'published')) hidden @endif>草稿单词需补全详解并发布后，才会显示在学习端。</p>
            <div class="picker-tools"><input class="input picker-search" type="search" id="word-filter" data-search-url="{{ route('admin.article.word-options', $article->id) }}" placeholder="输入英文单词或中文释义搜索" aria-label="搜索词汇"><button class="btn secondary" type="button" id="open-new-word">＋ 新建单词</button></div>
            <div class="word-picker" id="word-picker"><p class="helper">输入至少一个字后，显示最多 20 个匹配结果。</p></div>
            <div class="section-actions"><button class="btn" type="submit">保存重点词汇</button></div>
        </form>
        <dialog class="word-dialog" id="new-word-dialog"><form id="new-word-form" data-create-url="{{ route('admin.article.word-create', $article->id) }}"><div class="dialog-head"><div><h3>添加单词</h3><p>先保存为草稿，可稍后补充详解并发布。</p></div><button type="button" class="dialog-close" id="close-new-word" aria-label="关闭">×</button></div><div class="form-grid"><div class="form-field"><label for="quick-headword">单词 <span class="required-mark">*</span></label><input class="input" id="quick-headword" name="headword" required></div><div class="form-field"><label for="quick-meaning">释义 <span class="required-mark">*</span></label><input class="input" id="quick-meaning" name="meaning" required></div><div class="form-field"><label for="quick-ipa">音标</label><input class="input" id="quick-ipa" name="ipa"></div><div class="form-field"><label for="quick-pos">词性</label><input class="input" id="quick-pos" name="partOfSpeech"></div></div><p class="dialog-error" id="new-word-error" hidden></p><div class="section-actions"><button class="btn" type="submit">创建并添加到本文</button></div></form></dialog>
    </div></section>
    @endif
</div><aside class="form-stack">
    <section class="card"><div class="card-header"><h2>编辑提示</h2></div><div class="card-body"><p class="helper">文章保存后默认为草稿。发布和下架操作在文章列表中进行。</p></div></section>
    @if($editing)<section class="card"><div class="card-header"><h2>文章信息</h2></div><div class="card-body"><div class="helper">ID：#{{ $article->id }}</div><div class="helper" style="margin-top:8px">最后更新：{{ $article->updated_at }}</div></div></section>@endif
</aside></div>
@endsection
@push('head')<link rel="stylesheet" href="{{ asset('admin-assets/article-images.css') }}">@endpush
@push('scripts')<script src="{{ asset('admin-assets/editor.js') }}" defer></script>@endpush
